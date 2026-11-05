<?php

namespace App\Enums;

use App\Models\Exercise;
use App\Models\LearningPath;
use App\Models\Lesson;
use App\Models\Stage;

/** The four administrative resources share the same CRUD lifecycle. */
enum ContentType: string
{
    case PATH = 'learning-paths';
    case STAGE = 'stages';
    case LESSON = 'lessons';
    case EXERCISE = 'exercises';

    public function model(): string
    {
        return match ($this) {
            self::PATH => LearningPath::class,
            self::STAGE => Stage::class,
            self::LESSON => Lesson::class,
            self::EXERCISE => Exercise::class,
        };
    }
}
