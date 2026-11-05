<?php

namespace App\Http\Resources\Learning;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AchievementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'slug' => $this->slug,
            'description' => $this->description, 'icon' => $this->icon,
            'earned_at' => ($this->resource->getAttribute('earned_at') ?? $this->resource->getRelationValue('pivot')?->earned_at)?->toISOString(),
        ];
    }
}
