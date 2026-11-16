<?php

namespace App\Http\Resources\Learning;

use App\Models\Achievement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Achievement */
class AchievementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'slug' => $this->slug,
            'description' => $this->description, 'icon' => $this->icon,
            /** @var string|null */
            'earned_at' => ($this->resource->getAttribute('earned_at') ?? $this->resource->getRelationValue('pivot')?->earned_at)?->toISOString(),
        ];
    }
}
