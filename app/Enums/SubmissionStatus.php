<?php

namespace App\Enums;

enum SubmissionStatus: string
{
    case PENDING = 'pending';
    case CORRECT = 'correct';
    case INCORRECT = 'incorrect';
    case ERROR = 'error';
}
