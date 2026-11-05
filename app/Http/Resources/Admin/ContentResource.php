<?php

namespace App\Http\Resources\Admin;

use App\Models\Exercise;
use App\Models\LearningPath;
use App\Models\Lesson;
use App\Models\Stage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $fields = match (true) {
            $this->resource instanceof LearningPath => ['slug', 'description', 'cover_image_url', 'created_by'],
            $this->resource instanceof Stage => ['learning_path_id', 'description', 'position', 'minimum_score'],
            $this->resource instanceof Lesson => ['stage_id', 'content', 'position', 'estimated_minutes'],
            $this->resource instanceof Exercise => ['stage_id', 'statement', 'type', 'difficulty', 'programming_language', 'starter_code', 'explanation', 'points', 'position'],
        };
        $data = $this->resource->only(['id', 'title', 'is_published', ...$fields]);
        $data['created_at'] = $this->created_at->toISOString();
        $data['updated_at'] = $this->updated_at->toISOString();
        if ($this->resource instanceof Exercise) {
            $data['options'] = $this->options->map(fn ($option) => $option->only(['id', 'content', 'is_correct', 'position']));
            $data['test_cases'] = $this->testCases->map(fn ($test) => $test->only(['id', 'input', 'expected_output', 'position']));
        }

        return $data;
    }
}
