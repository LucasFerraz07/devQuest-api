<?php

namespace App\Http\Resources\Learning;

use App\Models\Exercise;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Exercise */
class ExerciseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            ...(new ExerciseSummaryResource($this->resource))->toArray($request),
            'statement' => $this->statement,
            'options' => $this->options->map(fn ($option) => ['id' => $option->id, 'content' => $option->content, 'position' => $option->position]),
            'explanation' => $this->when((bool) $this->is_solved, $this->explanation),
        ];
    }
}
