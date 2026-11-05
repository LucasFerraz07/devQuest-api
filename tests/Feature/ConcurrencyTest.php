<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AchievementSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Tests\Support\LearningFixtures;
use Tests\TestCase;

class ConcurrencyTest extends TestCase
{
    use LearningFixtures;

    public function test_independent_connections_do_not_duplicate_xp_progress_or_achievements(): void
    {
        $database = tempnam(sys_get_temp_dir(), 'devquest-db-');
        $barrier = $database.'-barrier';
        $processes = [];
        try {
            config(['database.connections.sqlite.database' => $database]);
            DB::purge('sqlite');
            Artisan::call('migrate', ['--force' => true]);
            $this->seed(AchievementSeeder::class);
            $exercise = $this->course(1)->stages[0]->exercises[0];
            $user = User::factory()->create();
            for ($i = 0; $i < 2; $i++) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/concurrent_progress.php'), $database, $user->id, $exercise->id, $barrier, (string) $i], base_path(), ['APP_ENV' => 'testing', 'CACHE_STORE' => 'array']);
                $process->setTimeout(20)->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 10;
            while (! file_exists($barrier.'.0') || ! file_exists($barrier.'.1')) {
                if (microtime(true) > $deadline) {
                    $this->fail('Workers did not reach the barrier: '.implode('\n', array_map(fn ($p) => $p->getErrorOutput().$p->getOutput(), $processes)));
                }
                usleep(10000);
            }
            file_put_contents($barrier, 'start');
            $awarded = 0;
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
                $awarded += json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR)['score_awarded'];
            }
            $this->assertSame(75, $awarded);
            $this->assertSame(75, $user->fresh()->total_xp);
            $this->assertDatabaseCount('exercise_submissions', 2);
            $this->assertDatabaseCount('user_stage_progress', 1);
            $this->assertDatabaseCount('user_lesson_progress', 1);
            $this->assertDatabaseCount('user_achievements', 3);
            Artisan::call('migrate:rollback', ['--force' => true]);
            $this->assertFalse(Schema::hasTable('user_lesson_progress'));
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            DB::purge('sqlite');
            foreach ([$database, $database.'-journal', $database.'-wal', $database.'-shm', $barrier, $barrier.'.0', $barrier.'.1'] as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
    }
}
