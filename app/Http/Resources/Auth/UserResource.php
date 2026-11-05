<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'total_xp' => $this->total_xp,
            'current_level' => $this->current_level,
            'next_level_xp' => $this->current_level * config('devquest.xp_per_level'),
        ];
    }
}
