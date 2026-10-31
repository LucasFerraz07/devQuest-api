<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exercise extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'stage_id',
        'title',
        'statement',
        'type',
        'difficulty',
        'programming_language',
        'starter_code',
        'explanation',
        'points',
        'position',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'position' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(ExerciseOption::class)->orderBy('position');
    }

    public function testCases(): HasMany
    {
        return $this->hasMany(ExerciseTestCase::class)->orderBy('position');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ExerciseSubmission::class);
    }
}
