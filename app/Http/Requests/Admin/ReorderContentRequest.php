<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReorderContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-content');
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:1000'],
            'ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
