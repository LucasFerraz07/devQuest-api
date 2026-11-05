<?php

namespace App\Http\Resources\Learning;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            ...(new LessonSummaryResource($this->resource))->toArray($request),
            'content' => $this->content,
            'previous_lesson_id' => $this->previous_lesson_id,
            'next_lesson_id' => $this->next_lesson_id,
        ];
    }
}
