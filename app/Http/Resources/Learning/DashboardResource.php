<?php

namespace App\Http\Resources\Learning;

use App\Http\Resources\Auth\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user' => new UserResource($this->resource['user']),
            'started_paths_count' => $this->resource['started_paths_count'],
            'completed_stages_count' => $this->resource['completed_stages_count'],
            'achievements_count' => $this->resource['achievements_count'],
            'learning_paths' => LearningPathResource::collection($this->resource['learning_paths']),
            'next_activity' => $this->resource['next_activity'],
        ];
    }
}
