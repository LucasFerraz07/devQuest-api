<?php

namespace App\Http\Resources\Admin;

use App\Enums\Difficulty;
use App\Enums\ExerciseType;
use App\Models\Exercise;
use App\Models\LearningPath;
use App\Models\Lesson;
use App\Models\Stage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContentResource extends JsonResource
{
    /**
     * @return array{
     *     id: string,
     *     title: string,
     *     is_published: bool,
     *     created_at: string,
     *     updated_at: string,
     *     slug?: string,
     *     description?: string|null,
     *     cover_image_url?: string|null,
     *     created_by?: string,
     *     learning_path_id?: string,
     *     stage_id?: string,
     *     position?: int,
     *     minimum_score?: int,
     *     content?: string,
     *     estimated_minutes?: int|null,
     *     statement?: string,
     *     type?: ExerciseType,
     *     difficulty?: Difficulty,
     *     programming_language?: string|null,
     *     starter_code?: string|null,
     *     explanation?: string|null,
     *     points?: int,
     *     options?: list<array{id: string, content: string, is_correct: bool, position: int}>,
     *     test_cases?: list<array{id: string, input: string, expected_output: string, position: int}>,
     * }
     */
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
