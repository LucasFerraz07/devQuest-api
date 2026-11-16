<?php

use App\Documentation\TypedResourceSchema;
use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;

return [
    'api_path' => 'api',
    'export_path' => 'docs/openapi.json',
    'info' => [
        'version' => '1.0.0',
        'description' => 'DevQuest API — trilhas de aprendizado, progresso, XP e conquistas',
    ],
    'ui' => ['title' => 'DevQuest API'],
    'middleware' => ['web', RestrictedDocsAccess::class],
    'extensions' => [TypedResourceSchema::class],
];
