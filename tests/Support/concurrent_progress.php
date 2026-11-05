<?php

use App\Models\Exercise;
use App\Models\User;
use App\Services\Learning\ProgressService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

// Separate process: real SQLite file and independent connection, no test transaction.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config([
    'database.default' => 'sqlite',
    'database.connections.sqlite.database' => $argv[1],
    'cache.default' => 'array',
]);
DB::purge('sqlite');
$user = User::findOrFail($argv[2]);
$exercise = Exercise::findOrFail($argv[3]);
$barrier = $argv[4];
file_put_contents($barrier.'.'.$argv[5], 'ready');
$deadline = microtime(true) + 10;
while (! file_exists($barrier)) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Concurrency barrier timed out.');
    }
    usleep(10000);
}
$progress = app(ProgressService::class);
$progress->start($exercise->stage, $user);
$progress->completeLesson($exercise->stage->lessons()->firstOrFail(), $user);
$result = $progress->submit($exercise, $user, $exercise->options()->where('is_correct', true)->firstOrFail()->id);
echo json_encode(['score_awarded' => $result['submission']->score_awarded]);
