<?php

namespace App\Http\Resources\Learning;

use App\Http\Resources\Auth\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgressResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $result = [
            'user' => new UserResource($this->resource['user']),
            'stage' => new StageResource($this->resource['stage']),
            'new_achievements' => AchievementResource::collection($this->resource['new_achievements']),
            'next_stage_id' => $this->resource['next_stage_id'],
        ];
        if (isset($this->resource['submission'])) {
            $result['submission'] = new SubmissionResource($this->resource['submission']);
            if ($this->resource['explanation'] !== null) {
                $result['explanation'] = $this->resource['explanation'];
            }
        }
        if (isset($this->resource['lesson'])) {
            $result['lesson'] = new LessonResource($this->resource['lesson']);
        }

        return $result;
    }
}
