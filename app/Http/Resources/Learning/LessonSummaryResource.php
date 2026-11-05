<?php

namespace App\Http\Resources\Learning;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'stage_id' => $this->stage_id, 'title' => $this->title,
            'position' => $this->position, 'estimated_minutes' => $this->estimated_minutes,
            'is_completed' => $this->userProgress->isNotEmpty(),
            'completed_at' => $this->userProgress->first()?->completed_at?->toISOString(),
        ];
    }
}
