<?php

namespace App\Http\Resources\Learning;

use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Lesson
 *
 * @property-read string|null $previous_lesson_id
 * @property-read string|null $next_lesson_id
 */
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
