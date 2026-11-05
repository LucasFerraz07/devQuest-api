<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\LearningPath;
use App\Models\Lesson;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LearningFixtures;
use Tests\TestCase;

class AdminContentTest extends TestCase
{
    use LearningFixtures, RefreshDatabase;

    public function test_students_cannot_use_any_admin_operation(): void
    {
        $path = $this->course();
        $this->tokenFor(User::factory()->create());
        foreach (['learning-paths' => $path->id, 'stages' => $path->stages[0]->id, 'lessons' => $path->stages[0]->lessons[0]->id, 'exercises' => $path->stages[0]->exercises[0]->id] as $type => $id) {
            $this->getJson('/api/admin/'.$type)->assertForbidden();
            $this->getJson('/api/admin/'.$type.'/'.$id)->assertForbidden();
            $this->postJson('/api/admin/'.$type, [])->assertForbidden();
            $this->patchJson('/api/admin/'.$type.'/'.$id, [])->assertForbidden();
            $this->deleteJson('/api/admin/'.$type.'/'.$id)->assertForbidden();
        }
    }

    public function test_admin_can_build_publish_and_inspect_a_complete_path(): void
    {
        $admin = User::factory()->admin()->create();
        $this->tokenFor($admin);
        $path = $this->postJson('/api/admin/learning-paths', ['title' => 'JavaScript', 'slug' => 'javascript'])
            ->assertCreated()->assertJsonPath('data.created_by', $admin->id)->json('data.id');
        $stage = $this->postJson('/api/admin/stages', ['learning_path_id' => $path, 'title' => 'Variáveis', 'position' => 1, 'minimum_score' => 80])
            ->assertCreated()->json('data.id');
        $lesson = $this->postJson('/api/admin/lessons', ['stage_id' => $stage, 'title' => 'Aula', 'content' => '# Conteúdo', 'position' => 1, 'is_published' => true])
            ->assertCreated()->json('data.id');
        $exercise = $this->postJson('/api/admin/exercises', [
            'stage_id' => $stage, 'title' => 'Desafio', 'statement' => 'Escolha uma opção.', 'type' => 'multiple_choice', 'position' => 1, 'is_published' => true,
            'options' => $this->sampleOptions(),
        ])->assertCreated()->assertJsonPath('data.options.0.is_correct', true)->json('data.id');
        $this->patchJson('/api/admin/stages/'.$stage, ['is_published' => true])->assertOk();
        $this->patchJson('/api/admin/learning-paths/'.$path, ['is_published' => true])->assertOk();
        $this->getJson('/api/admin/exercises/'.$exercise)->assertOk()->assertJsonPath('data.options.0.is_correct', true);
        $this->getJson('/api/admin/stages?learning_path_id='.$path)->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/admin/lessons?stage_id='.$stage)->assertOk()->assertJsonPath('data.0.id', $lesson);
        $this->getJson('/api/learning-paths/'.$path)->assertOk()->assertJsonPath('data.stages.0.status', 'available');
    }

    public function test_invalid_publication_is_rolled_back(): void
    {
        $this->tokenFor(User::factory()->admin()->create());
        $path = LearningPath::factory()->create();
        $this->patchJson('/api/admin/learning-paths/'.$path->id, ['is_published' => true])->assertUnprocessable();
        $this->assertFalse($path->fresh()->is_published);
        $stage = Stage::factory()->create(['learning_path_id' => $path->id]);
        $this->patchJson('/api/admin/stages/'.$stage->id, ['is_published' => true])->assertUnprocessable();
        $this->assertFalse($stage->fresh()->is_published);
    }

    public function test_invalid_correct_options_and_foreign_option_ids_are_rejected(): void
    {
        $stage = Stage::factory()->create();
        $this->tokenFor(User::factory()->admin()->create());
        $payload = ['stage_id' => $stage->id, 'title' => 'Desafio', 'statement' => 'Enunciado', 'type' => 'multiple_choice', 'position' => 1];
        $this->postJson('/api/admin/exercises', $payload)->assertUnprocessable()->assertJsonValidationErrors('options');
        $wrong = $this->sampleOptions();
        $wrong[0]['is_correct'] = false;
        $this->postJson('/api/admin/exercises', $payload + ['options' => $wrong])->assertUnprocessable();
        $wrong[0]['is_correct'] = true;
        $wrong[1]['is_correct'] = true;
        $this->postJson('/api/admin/exercises', $payload + ['options' => $wrong])->assertUnprocessable();
        $wrong = $this->sampleOptions();
        $wrong[0]['id'] = '01900000-0000-7000-8000-000000000000';
        $this->postJson('/api/admin/exercises', $payload + ['options' => $wrong])->assertUnprocessable();
        $this->assertDatabaseCount('exercises', 0);
    }

    public function test_code_exercises_can_only_be_drafts_and_cannot_be_submitted(): void
    {
        $stage = $this->course(1)->stages[0];
        $this->tokenFor(User::factory()->admin()->create());
        $payload = ['stage_id' => $stage->id, 'title' => 'Código', 'statement' => 'Implemente.', 'type' => 'code', 'position' => 2];
        $id = $this->postJson('/api/admin/exercises', $payload)->assertCreated()->json('data.id');
        $this->patchJson('/api/admin/exercises/'.$id, ['is_published' => true])->assertUnprocessable();
        $this->tokenFor(User::factory()->create());
        $this->getJson('/api/exercises/'.$id)->assertForbidden();
        $this->postJson('/api/exercises/'.$id.'/submissions', ['submitted_code' => 'print(1)'])->assertForbidden();
    }

    public function test_unique_positions_parent_immutability_and_pagination_are_validated(): void
    {
        $path = $this->course(1);
        $this->tokenFor(User::factory()->admin()->create());
        $this->postJson('/api/admin/stages', ['learning_path_id' => $path->id, 'title' => 'Duplicada', 'position' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('position');
        $other = LearningPath::factory()->create();
        $this->patchJson('/api/admin/stages/'.$path->stages[0]->id, ['learning_path_id' => $other->id])->assertUnprocessable();
        $this->getJson('/api/admin/exercises?per_page=101')->assertUnprocessable();
        $this->getJson('/api/admin/stages/01900000-0000-7000-8000-000000000000')->assertNotFound()->assertJsonPath('error', true);
    }

    public function test_structural_changes_are_frozen_but_editorial_changes_and_path_unpublishing_are_allowed(): void
    {
        $path = $this->course();
        $stage = $path->stages[0];
        $exercise = $stage->exercises[0];
        $this->tokenFor(User::factory()->create());
        $this->postJson('/api/stages/'.$stage->id.'/start')->assertOk();
        $this->tokenFor(User::factory()->admin()->create());
        $this->patchJson('/api/admin/stages/'.$stage->id, ['minimum_score' => 50])->assertStatus(409)->assertJsonPath('error', true);
        $this->patchJson('/api/admin/stages/'.$stage->id, ['is_published' => false])->assertStatus(409);
        $this->patchJson('/api/admin/exercises/'.$exercise->id, ['points' => 200])->assertStatus(409);
        $this->patchJson('/api/admin/exercises/'.$exercise->id, ['options' => $this->sampleOptions()])->assertStatus(409);
        $this->deleteJson('/api/admin/learning-paths/'.$path->id)->assertStatus(409);
        $this->postJson('/api/admin/stages', ['learning_path_id' => $path->id, 'title' => 'Outra', 'position' => 3])->assertStatus(409);
        $this->patchJson('/api/admin/lessons/'.$stage->lessons[0]->id, ['content' => '# Texto corrigido'])->assertOk();
        $this->patchJson('/api/admin/exercises/'.$exercise->id, ['statement' => 'Enunciado corrigido'])->assertOk();
        $this->patchJson('/api/admin/learning-paths/'.$path->id, ['is_published' => false])->assertOk();
        $this->assertDatabaseCount('user_stage_progress', 1);
    }

    public function test_draft_crud_and_nested_option_replacement_work(): void
    {
        $path = LearningPath::factory()->create();
        $stage = Stage::factory()->create(['learning_path_id' => $path->id]);
        $lesson = Lesson::factory()->create(['stage_id' => $stage->id]);
        $exercise = Exercise::factory()->create(['stage_id' => $stage->id]);
        $this->tokenFor(User::factory()->admin()->create());
        $this->patchJson('/api/admin/exercises/'.$exercise->id, ['options' => $this->sampleOptions(), 'position' => 2])
            ->assertOk()->assertJsonCount(2, 'data.options')->assertJsonPath('data.position', 2);
        $this->assertSame(2, $exercise->options()->count());
        foreach (['exercises' => $exercise->id, 'lessons' => $lesson->id, 'stages' => $stage->id, 'learning-paths' => $path->id] as $type => $id) {
            $this->deleteJson('/api/admin/'.$type.'/'.$id)->assertOk()->assertJsonPath('data', null);
        }
        $this->assertDatabaseCount('learning_paths', 0);
    }

    public function test_reordering_swaps_positions_atomically_and_rejects_incomplete_or_foreign_sets(): void
    {
        $path = $this->course(2, 2);
        $stage = $path->stages[0];
        $lesson = Lesson::factory()->create(['stage_id' => $stage->id, 'position' => 2]);
        $this->tokenFor(User::factory()->admin()->create());
        $this->putJson('/api/admin/learning-paths/'.$path->id.'/stages/order', ['ids' => [$path->stages[1]->id, $stage->id]])->assertOk();
        $this->assertSame(2, $stage->fresh()->position);
        $this->putJson('/api/admin/stages/'.$stage->id.'/exercises/order', ['ids' => [$stage->exercises[1]->id, $stage->exercises[0]->id]])->assertOk();
        $this->assertSame(2, $stage->exercises[0]->fresh()->position);
        $this->putJson('/api/admin/stages/'.$stage->id.'/lessons/order', ['ids' => [$lesson->id, $stage->lessons[0]->id]])->assertOk();
        $this->assertSame(1, $lesson->fresh()->position);
        $this->putJson('/api/admin/learning-paths/'.$path->id.'/stages/order', ['ids' => [$stage->id]])->assertUnprocessable();
        $this->putJson('/api/admin/stages/'.$stage->id.'/exercises/order', ['ids' => [$stage->exercises[0]->id, $path->stages[1]->exercises[0]->id]])->assertUnprocessable();
        $this->assertSame(2, $stage->exercises[0]->fresh()->position);
        $this->tokenFor(User::factory()->create());
        $this->postJson('/api/stages/'.$path->stages[1]->id.'/start')->assertOk();
        $this->tokenFor(User::factory()->admin()->create());
        $this->putJson('/api/admin/learning-paths/'.$path->id.'/stages/order', ['ids' => [$stage->id, $path->stages[1]->id]])->assertStatus(409);
    }

    private function sampleOptions(): array
    {
        return [
            ['content' => 'Correta', 'is_correct' => true, 'position' => 1],
            ['content' => 'Incorreta', 'is_correct' => false, 'position' => 2],
        ];
    }
}
