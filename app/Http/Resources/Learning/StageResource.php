<?php

namespace App\Http\Resources\Learning;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $progress = $this->userProgress->first();

        return [
            'id' => $this->id, 'learning_path_id' => $this->learning_path_id,
            'title' => $this->title, 'description' => $this->description, 'position' => $this->position,
            'minimum_score' => $this->minimum_score,
            'status' => $this->access_status->value, 'best_score' => $progress?->best_score ?? 0,
            'started_at' => $progress?->started_at?->toISOString(), 'completed_at' => $progress?->completed_at?->toISOString(),
            'lessons_count' => (int) $this->lessons_count,
            'completed_lessons_count' => (int) $this->completed_lessons_count,
            'exercises_count' => (int) $this->exercises_count,
            'attempted_exercises_count' => (int) $this->attempted_exercises_count,
            'solved_exercises_count' => (int) $this->solved_exercises_count,
            'next_stage_id' => $this->when($this->resource->getAttribute('next_stage_id') !== null, $this->next_stage_id),
            'lessons' => LessonSummaryResource::collection($this->whenLoaded('lessons')),
            'exercises' => ExerciseSummaryResource::collection($this->whenLoaded('exercises')),
        ];
    }
}
