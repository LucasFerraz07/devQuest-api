<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseSubmission extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'exercise_id',
        'selected_option_id',
        'submitted_code',
        'execution_output',
        'execution_error',
        'status',
        'score_awarded',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'score_awarded' => 'integer',
            'submitted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(ExerciseOption::class, 'selected_option_id');
    }
}
