<?php

namespace App\Models;

use App\Enums\StageStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserStageProgress extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'user_stage_progress';

    protected $fillable = [
        'user_id',
        'stage_id',
        'status',
        'best_score',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => StageStatus::class,
            'best_score' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }
}
