<?php

namespace Database\Seeders;

use App\Models\LearningPath;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);
        DB::transaction(function () {
            $admin = User::where('email', env('ADMIN_EMAIL', 'admin@teste.com'))->firstOrFail();
            $path = LearningPath::firstOrCreate(['slug' => 'javascript-fundamentos'], [
                'title' => 'Fundamentos de JavaScript',
                'description' => 'Aprenda variáveis, tipos e funções com aulas e desafios.',
                'created_by' => $admin->id,
            ]);
            // Reruns never edit content that learners may already be studying.
            if (! $path->wasRecentlyCreated) {
                return;
            }
            foreach ([
                ['Variáveis e tipos', 'Qual palavra declara uma constante?', 'const', 'var', 'Use `const` para uma referência que não será reatribuída.'],
                ['Funções', 'Qual expressão declara uma função?', 'function soma() {}', 'const soma = 1', 'A palavra `function` declara uma função.'],
            ] as $index => [$title, $statement, $correct, $incorrect, $explanation]) {
                $stage = $path->stages()->create(['title' => $title, 'position' => $index + 1, 'minimum_score' => 100, 'is_published' => true]);
                $stage->lessons()->create(['title' => $title, 'content' => '# '.$title."\n\n".$explanation, 'position' => 1, 'estimated_minutes' => 5, 'is_published' => true]);
                $exercise = $stage->exercises()->create([
                    'title' => 'Pratique: '.$title, 'statement' => $statement, 'type' => 'multiple_choice',
                    'explanation' => $explanation, 'points' => 50, 'position' => 1, 'is_published' => true,
                ]);
                $exercise->options()->createMany([
                    ['content' => $correct, 'is_correct' => true, 'position' => 1],
                    ['content' => $incorrect, 'is_correct' => false, 'position' => 2],
                ]);
            }
            $path->update(['is_published' => true]);
        });
    }
}
