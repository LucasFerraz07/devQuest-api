<?php

namespace App\Http\Requests\Learning;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitExerciseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('exercise'));
    }

    public function rules(): array
    {
        return [
            'selected_option_id' => ['required', 'uuid', Rule::exists('exercise_options', 'id')->where('exercise_id', $this->route('exercise')->id)],
            /** @ignoreParam */
            'user_id' => ['prohibited'],
            /** @ignoreParam */
            'status' => ['prohibited'],
            /** @ignoreParam */
            'score_awarded' => ['prohibited'],
            /** @ignoreParam */
            'submitted_code' => ['prohibited'],
        ];
    }
}
