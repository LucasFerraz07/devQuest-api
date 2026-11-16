<?php

namespace App\Http\Resources\Learning;

use App\Models\ExerciseSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ExerciseSubmission */
class SubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'exercise_id' => $this->exercise_id,
            'selected_option_id' => $this->selected_option_id,
            'status' => $this->status->value, 'score_awarded' => $this->score_awarded,
            'submitted_at' => $this->submitted_at->toISOString(),
        ];
    }
}
