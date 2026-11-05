<?php

namespace App\Http\Resources\Learning;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LearningPathResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'title' => $this->title, 'slug' => $this->slug,
            'description' => $this->description, 'cover_image_url' => $this->cover_image_url,
            'stages_count' => (int) $this->stages_count,
            'completed_stages_count' => (int) $this->completed_stages_count,
            'started_stages_count' => (int) $this->started_stages_count,
            'progress_percent' => $this->stages_count > 0 ? (int) floor(100 * $this->completed_stages_count / $this->stages_count) : 0,
            'stages' => StageResource::collection($this->whenLoaded('stages')),
        ];
    }
}
