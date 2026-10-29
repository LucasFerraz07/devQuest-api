<?php

namespace App\Enums;

enum StageStatus: string
{
    case LOCKED = 'locked';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
}
