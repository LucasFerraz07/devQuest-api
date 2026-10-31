<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lesson extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'stage_id',
        'title',
        'content',
        'position',
        'estimated_minutes',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'estimated_minutes' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }
}
