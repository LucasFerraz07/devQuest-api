<?php

namespace Database\Seeders;

use App\Models\Achievement;
use Illuminate\Database\Seeder;

class AchievementSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['first-correct-answer', 'Primeiro acerto', 'Acerte seu primeiro exercício.', 'check'],
            ['first-completed-stage', 'Primeira etapa', 'Conclua sua primeira etapa.', 'flag'],
            ['first-completed-path', 'Primeira trilha', 'Conclua sua primeira trilha.', 'trophy'],
        ] as [$slug, $name, $description, $icon]) {
            Achievement::updateOrCreate(['slug' => $slug], compact('name', 'description', 'icon'));
        }
    }
}
