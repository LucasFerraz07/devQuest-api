<?php

namespace App\Http\Resources\Learning;

use App\Models\Exercise;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Exercise */
class ExerciseSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'stage_id' => $this->stage_id, 'title' => $this->title,
            'type' => $this->type->value, 'difficulty' => $this->difficulty->value,
            'points' => $this->points, 'position' => $this->position,
            'is_attempted' => (bool) $this->is_attempted, 'is_solved' => (bool) $this->is_solved,
        ];
    }
}
