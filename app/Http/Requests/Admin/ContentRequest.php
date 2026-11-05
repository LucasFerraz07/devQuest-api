<?php

namespace App\Http\Requests\Admin;

use App\Enums\ContentType;
use App\Enums\Difficulty;
use App\Enums\ExerciseType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-content');
    }

    public function rules(): array
    {
        $type = ContentType::from($this->route('content_type'));
        $record = $this->route('record') ? $type->model()::findOrFail($this->route('record')) : null;
        $required = $record ? 'sometimes' : 'required';
        $rules = [
            'title' => [$required, 'string', 'max:255'],
            'is_published' => ['sometimes', 'boolean'],
            'created_by' => ['prohibited'],
        ];
        if ($type === ContentType::PATH) {
            return $rules + [
                'slug' => [$required, 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('learning_paths', 'slug')->ignore($record?->id)],
                'description' => ['sometimes', 'nullable', 'string'],
                'cover_image_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            ];
        }
        $parentField = $type === ContentType::STAGE ? 'learning_path_id' : 'stage_id';
        $parentTable = $type === ContentType::STAGE ? 'learning_paths' : 'stages';
        $parentId = $record?->{$parentField} ?? $this->input($parentField);
        $rules[$parentField] = [$required, 'uuid', Rule::exists($parentTable, 'id')];
        if ($record) {
            $rules[$parentField][] = Rule::in([$record->{$parentField}]);
        }
        $rules['position'] = [$required, 'integer', 'between:1,1000000', Rule::unique((new ($type->model()))->getTable(), 'position')->where($parentField, $parentId)->ignore($record?->id)];
        if ($type === ContentType::STAGE) {
            return $rules + [
                'description' => ['sometimes', 'nullable', 'string'],
                'minimum_score' => ['sometimes', 'integer', 'between:0,100'],
            ];
        }
        if ($type === ContentType::LESSON) {
            return $rules + [
                'content' => [$required, 'string'],
                'estimated_minutes' => ['sometimes', 'nullable', 'integer', 'between:1,65535'],
            ];
        }

        return $rules + [
            'statement' => [$required, 'string'],
            'type' => [$required, Rule::enum(ExerciseType::class)],
            'difficulty' => ['sometimes', Rule::enum(Difficulty::class)],
            'points' => ['sometimes', 'integer', 'between:1,100000'],
            'explanation' => ['sometimes', 'nullable', 'string'],
            'programming_language' => ['sometimes', 'nullable', 'string', 'max:255'],
            'starter_code' => ['sometimes', 'nullable', 'string'],
            'options' => ['sometimes', 'array', 'min:2', 'max:20'],
            'options.*' => ['array:content,is_correct,position'],
            'options.*.content' => ['required', 'string'],
            'options.*.is_correct' => ['required', 'boolean'],
            'options.*.position' => ['required', 'integer', 'between:1,1000000', 'distinct'],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            if ($this->has('options') && is_array($this->input('options')) && ! $validator->errors()->has('options')) {
                $count = collect($this->input('options'))->filter(fn ($option) => is_array($option) && in_array($option['is_correct'] ?? null, [true, 1, '1'], true))->count();
                if ($count !== 1) {
                    $validator->errors()->add('options', 'Informe exatamente uma alternativa correta.');
                }
            }
        }];
    }
}
