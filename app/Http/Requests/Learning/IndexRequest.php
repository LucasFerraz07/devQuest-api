<?php

namespace App\Http\Requests\Learning;

use App\Enums\SubmissionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'search' => ['sometimes', 'string', 'max:100'],
            'exercise_id' => ['sometimes', 'uuid'],
            'status' => ['sometimes', Rule::enum(SubmissionStatus::class)],
            'is_published' => ['sometimes', 'boolean'],
            'learning_path_id' => ['sometimes', 'uuid'],
            'stage_id' => ['sometimes', 'uuid'],
        ];
    }
}
