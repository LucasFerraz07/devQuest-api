<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stage extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'learning_path_id',
        'title',
        'description',
        'position',
        'minimum_score',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'minimum_score' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public function learningPath(): BelongsTo
    {
        return $this->belongsTo(LearningPath::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('position');
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class)->orderBy('position');
    }

    public function userProgress(): HasMany
    {
        return $this->hasMany(UserStageProgress::class);
    }
}
