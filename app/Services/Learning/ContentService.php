<?php

namespace App\Services\Learning;

use App\Enums\ContentType;
use App\Enums\ExerciseType;
use App\Exceptions\ApiException;
use App\Models\Exercise;
use App\Models\LearningPath;
use App\Models\Lesson;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContentService
{
    public function __construct(private LearningTransaction $transaction) {}

    public function index(ContentType $type, array $filters): LengthAwarePaginator
    {
        $query = $type->model()::query();
        $query->when($filters['search'] ?? null, fn ($q, $search) => $q->where('title', 'like', '%'.$search.'%'));
        if (array_key_exists('is_published', $filters)) {
            $query->where('is_published', $filters['is_published']);
        }
        if ($type === ContentType::STAGE && isset($filters['learning_path_id'])) {
            $query->where('learning_path_id', $filters['learning_path_id']);
        }
        if (in_array($type, [ContentType::LESSON, ContentType::EXERCISE], true) && isset($filters['stage_id'])) {
            $query->where('stage_id', $filters['stage_id']);
        }
        if ($type === ContentType::EXERCISE) {
            $query->with(['options', 'testCases']);
        }

        return $query->orderBy($type === ContentType::PATH ? 'title' : 'position')->orderBy('id')->paginate($filters['per_page'] ?? 20);
    }

    public function find(ContentType $type, string $id): Model
    {
        $model = $type->model()::findOrFail($id);

        return $model instanceof Exercise ? $model->load(['options', 'testCases']) : $model;
    }

    public function save(ContentType $type, ?string $id, array $data, User $admin): Model
    {
        $model = $id ? $this->find($type, $id) : new ($type->model());
        if (! $id && $type === ContentType::PATH) {
            return DB::transaction(function () use ($model, $data, $admin) {
                $model->fill([...$data, 'created_by' => $admin->id])->save();
                $this->validatePath($model);

                return $model->refresh();
            });
        }
        $pathId = $this->pathId($model, $data);

        return $this->transaction->run($pathId, null, function (LearningPath $path) use ($model, $data, $id) {
            if ($id) {
                $model->refresh()->unsetRelations();
            }
            $options = $data['options'] ?? null;
            unset($data['options']);
            $model->fill($data);
            if ($model instanceof Lesson || $model instanceof Exercise) {
                Stage::findOrFail($model->stage_id);
            }
            if ($path->stageProgress()->exists()) {
                $editorial = ['title', 'description', 'cover_image_url', 'slug', 'statement', 'explanation', 'content', 'estimated_minutes'];
                if ($model instanceof LearningPath) {
                    $editorial[] = 'is_published';
                }
                if (! $id || $options !== null || array_diff(array_keys($model->getDirty()), $editorial)) {
                    throw new ApiException('A trilha já foi iniciada. Alterações estruturais estão bloqueadas.', 409);
                }
            }
            $model->save();
            if ($model instanceof Exercise) {
                if ($options !== null) {
                    $model->options()->delete();
                    $model->options()->createMany($options);
                }
                $this->validateExercise($model);
            }
            $path->refresh();
            if ($model instanceof Stage && $model->is_published) {
                $this->validateStage($model);
            }
            if ($model instanceof Lesson || $model instanceof Exercise) {
                $stage = $model->stage()->firstOrFail();
                if ($stage->is_published) {
                    $this->validateStage($stage);
                }
            }
            $this->validatePath($path);

            return $model instanceof Exercise ? $model->refresh()->load(['options', 'testCases']) : $model->refresh();
        });
    }

    public function delete(ContentType $type, string $id): void
    {
        $model = $this->find($type, $id);
        $this->transaction->run($this->pathId($model, []), null, function (LearningPath $path) use ($model) {
            if ($path->stageProgress()->exists()) {
                throw new ApiException('Conteúdo de uma trilha iniciada não pode ser excluído.', 409);
            }
            $model->refresh();
            $stage = $model instanceof Lesson || $model instanceof Exercise ? $model->stage : null;
            $model->delete();
            if ($stage?->is_published) {
                $this->validateStage($stage);
            }
            if (! $model instanceof LearningPath) {
                $this->validatePath($path);
            }
        });
    }

    public function reorder(ContentType $type, string $parentId, array $ids): void
    {
        $parent = $type === ContentType::STAGE ? LearningPath::findOrFail($parentId) : Stage::findOrFail($parentId);
        $pathId = $parent instanceof LearningPath ? $parent->id : $parent->learning_path_id;
        $this->transaction->run($pathId, null, function (LearningPath $path) use ($type, $parent, $ids) {
            $parent->refresh();
            if ($path->stageProgress()->exists()) {
                throw new ApiException('A trilha já foi iniciada. A ordem está bloqueada.', 409);
            }
            $relation = match ($type) {
                ContentType::STAGE => $parent->stages(),
                ContentType::LESSON => $parent->lessons(),
                ContentType::EXERCISE => $parent->exercises(),
            };
            $records = $relation->get();
            if ($records->count() !== count($ids) || $records->pluck('id')->diff($ids)->isNotEmpty()) {
                throw ValidationException::withMessages(['ids' => ['Informe todos os itens deste agrupamento, sem itens de outro agrupamento.']]);
            }
            // Move out of the occupied range before assigning the final positions.
            $offset = $records->max('position') + count($ids) + 1;
            foreach ($ids as $index => $id) {
                $records->firstWhere('id', $id)->update(['position' => $offset + $index]);
            }
            foreach ($ids as $index => $id) {
                $records->firstWhere('id', $id)->update(['position' => $index + 1]);
            }
        });
    }

    private function pathId(Model $model, array $data): string
    {
        return match (true) {
            $model instanceof LearningPath => $model->id,
            $model instanceof Stage => $model->learning_path_id ?? $data['learning_path_id'],
            default => Stage::findOrFail($model->stage_id ?? $data['stage_id'])->learning_path_id,
        };
    }

    private function validatePath(LearningPath $path): void
    {
        if (! $path->is_published) {
            return;
        }
        $stages = $path->stages()->where('is_published', true)->get();
        if ($stages->isEmpty()) {
            throw ValidationException::withMessages(['is_published' => ['Publique ao menos uma etapa antes da trilha.']]);
        }
        foreach ($stages as $stage) {
            $this->validateStage($stage);
        }
    }

    private function validateStage(Stage $stage): void
    {
        if (! $stage->lessons()->where('is_published', true)->exists()) {
            throw ValidationException::withMessages(['is_published' => ['A etapa precisa de uma aula publicada.']]);
        }
        $exercises = $stage->exercises()->where('is_published', true)->get();
        if ($exercises->isEmpty()) {
            throw ValidationException::withMessages(['is_published' => ['A etapa precisa de um exercício publicado.']]);
        }
        foreach ($exercises as $exercise) {
            $this->validateExercise($exercise);
        }
    }

    private function validateExercise(Exercise $exercise): void
    {
        if ($exercise->type === ExerciseType::CODE) {
            if ($exercise->is_published) {
                throw ValidationException::withMessages(['is_published' => ['Exercícios de código ainda não podem ser publicados.']]);
            }

            return;
        }
        if ($exercise->options()->count() < 2 || $exercise->options()->where('is_correct', true)->count() !== 1) {
            throw ValidationException::withMessages(['options' => ['Informe ao menos duas alternativas e exatamente uma correta.']]);
        }
    }
}
