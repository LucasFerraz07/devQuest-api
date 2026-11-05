<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\LearningPath;
use App\Models\User;
use Database\Seeders\AchievementSeeder;
use Database\Seeders\DemoContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\LearningFixtures;
use Tests\TestCase;

class JourneyTest extends TestCase
{
    use LearningFixtures, RefreshDatabase;

    public function test_catalogue_and_stage_map_do_not_create_progress_or_expose_contents(): void
    {
        $path = $this->course();
        LearningPath::factory()->create();
        $this->tokenFor(User::factory()->create());
        $this->getJson('/api/learning-paths?per_page=1')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.stages_count', 2)->assertJsonMissingPath('data.0.stages');
        $this->getJson('/api/learning-paths/'.$path->id)->assertOk()
            ->assertJsonPath('data.stages.0.status', 'available')->assertJsonPath('data.stages.1.status', 'locked')
            ->assertJsonMissingPath('data.stages.0.lessons')->assertJsonMissingPath('data.stages.0.exercises');
        $this->assertDatabaseCount('user_stage_progress', 0);
        $this->getJson('/api/learning-paths?per_page=101')->assertUnprocessable();
    }

    public function test_locked_and_unpublished_content_cannot_be_accessed_by_id(): void
    {
        $path = $this->course();
        $this->tokenFor(User::factory()->create());
        $locked = $path->stages[1];
        $this->getJson('/api/stages/'.$locked->id)->assertForbidden();
        $this->postJson('/api/stages/'.$locked->id.'/start')->assertForbidden();
        $this->getJson('/api/lessons/'.$locked->lessons[0]->id)->assertForbidden();
        $this->putJson('/api/lessons/'.$locked->lessons[0]->id.'/completion')->assertForbidden();
        $this->getJson('/api/exercises/'.$locked->exercises[0]->id)->assertForbidden();
        $this->postJson('/api/exercises/'.$locked->exercises[0]->id.'/submissions', ['selected_option_id' => $locked->exercises[0]->options[0]->id])->assertForbidden();
        $path->update(['is_published' => false]);
        $this->getJson('/api/learning-paths/'.$path->id)->assertForbidden();
        $this->getJson('/api/stages/'.$path->stages[0]->id)->assertForbidden();
        $this->getJson('/api/exercises/'.$path->stages[0]->exercises[0]->id)->assertForbidden();
        $this->getJson('/api/stages/not-a-uuid')->assertNotFound()->assertJsonPath('error', true);
    }

    public function test_start_and_lesson_completion_are_idempotent(): void
    {
        $stage = $this->course()->stages[0];
        $this->tokenFor(User::factory()->create());
        $first = $this->postJson('/api/stages/'.$stage->id.'/start')->assertOk()->json('data.started_at');
        $this->postJson('/api/stages/'.$stage->id.'/start')->assertOk()->assertJsonPath('data.started_at', $first);
        $url = '/api/lessons/'.$stage->lessons[0]->id.'/completion';
        $first = $this->putJson($url)->assertOk()->assertJsonPath('data.stage.status', 'in_progress')->json('data.lesson.completed_at');
        $this->putJson($url)->assertOk()->assertJsonPath('data.lesson.completed_at', $first);
        $this->assertDatabaseCount('user_lesson_progress', 1);
        $this->assertDatabaseCount('user_stage_progress', 1);
    }

    public function test_answer_contract_hides_the_solution_until_a_correct_attempt(): void
    {
        $exercise = $this->course()->stages[0]->exercises[0];
        $this->tokenFor(User::factory()->create());
        $this->getJson('/api/exercises/'.$exercise->id)->assertOk()
            ->assertJsonMissingPath('data.options.0.is_correct')->assertJsonMissingPath('data.explanation');
        $wrong = $exercise->options->firstWhere('is_correct', false);
        $this->postJson('/api/exercises/'.$exercise->id.'/submissions', ['selected_option_id' => $wrong->id])
            ->assertCreated()->assertJsonPath('data.submission.status', 'incorrect')->assertJsonPath('data.submission.score_awarded', 0)->assertJsonMissingPath('data.explanation');
        $correct = $exercise->options->firstWhere('is_correct', true);
        $this->postJson('/api/exercises/'.$exercise->id.'/submissions', ['selected_option_id' => $correct->id])
            ->assertCreated()->assertJsonPath('data.explanation', $exercise->explanation)->assertJsonPath('data.stage.status', 'in_progress');
        $this->getJson('/api/exercises/'.$exercise->id)->assertOk()->assertJsonPath('data.is_solved', true)->assertJsonPath('data.explanation', $exercise->explanation);
    }

    public function test_full_student_flow_unlocks_next_stage_and_awards_xp_only_once(): void
    {
        $this->seed(AchievementSeeder::class);
        $path = $this->course();
        $user = User::factory()->create();
        $this->tokenFor($user);
        foreach ($path->stages as $index => $stage) {
            $exercise = $stage->exercises[0];
            $correct = $exercise->options->firstWhere('is_correct', true);
            $this->getJson('/api/stages/'.$stage->id)->assertOk();
            $this->putJson('/api/lessons/'.$stage->lessons[0]->id.'/completion')->assertOk();
            $this->postJson('/api/exercises/'.$exercise->id.'/submissions', ['selected_option_id' => $correct->id])
                ->assertCreated()->assertJsonPath('data.stage.status', 'completed')->assertJsonPath('data.stage.best_score', 100)
                ->assertJsonPath('data.user.total_xp', 75 * ($index + 1))
                ->assertJsonPath('data.next_stage_id', $path->stages->get($index + 1)?->id);
            $this->postJson('/api/exercises/'.$exercise->id.'/submissions', ['selected_option_id' => $correct->id])
                ->assertCreated()->assertJsonPath('data.submission.score_awarded', 0)->assertJsonCount(0, 'data.new_achievements');
            $this->postJson('/api/exercises/'.$exercise->id.'/submissions', ['selected_option_id' => $exercise->options->firstWhere('is_correct', false)->id])
                ->assertCreated()->assertJsonPath('data.stage.best_score', 100)->assertJsonPath('data.stage.status', 'completed');
        }
        $this->assertSame(150, $user->fresh()->total_xp);
        $this->assertSame(2, $user->fresh()->current_level);
        $this->getJson('/api/me/achievements')->assertOk()->assertJsonCount(3, 'data')->assertJsonStructure(['data' => [['earned_at']]]);
        $this->getJson('/api/me/dashboard')->assertOk()->assertJsonPath('data.completed_stages_count', 2)
            ->assertJsonPath('data.learning_paths.0.progress_percent', 100)->assertJsonPath('data.next_activity', null);
    }

    public function test_weighted_score_requires_every_exercise_attempt_and_every_lesson(): void
    {
        $stage = $this->course(1, 2, 75)->stages[0];
        $this->tokenFor(User::factory()->create());
        $first = $stage->exercises[0];
        $second = $stage->exercises[1];
        $this->postJson('/api/exercises/'.$first->id.'/submissions', ['selected_option_id' => $first->options->firstWhere('is_correct', true)->id])
            ->assertCreated()->assertJsonPath('data.stage.best_score', 75)->assertJsonPath('data.stage.status', 'in_progress');
        $this->postJson('/api/exercises/'.$second->id.'/submissions', ['selected_option_id' => $second->options->firstWhere('is_correct', false)->id])
            ->assertCreated()->assertJsonPath('data.stage.status', 'in_progress');
        $this->putJson('/api/lessons/'.$stage->lessons[0]->id.'/completion')->assertOk()->assertJsonPath('data.stage.status', 'completed');
    }

    public function test_below_minimum_score_does_not_complete_stage(): void
    {
        $stage = $this->course(1, 2, 80)->stages[0];
        $this->tokenFor(User::factory()->create());
        $this->putJson('/api/lessons/'.$stage->lessons[0]->id.'/completion')->assertOk();
        foreach ($stage->exercises as $index => $exercise) {
            $this->postJson('/api/exercises/'.$exercise->id.'/submissions', ['selected_option_id' => $exercise->options->firstWhere('is_correct', $index === 0)->id])->assertCreated();
        }
        $this->getJson('/api/stages/'.$stage->id)->assertOk()->assertJsonPath('data.best_score', 75)->assertJsonPath('data.status', 'in_progress');
    }

    public function test_foreign_options_and_forged_submission_fields_are_rejected(): void
    {
        $stage = $this->course(1, 2)->stages[0];
        $this->tokenFor(User::factory()->create());
        $this->postJson('/api/exercises/'.$stage->exercises[0]->id.'/submissions', ['selected_option_id' => $stage->exercises[1]->options[0]->id])
            ->assertUnprocessable()->assertJsonValidationErrors('selected_option_id');
        $this->postJson('/api/exercises/'.$stage->exercises[0]->id.'/submissions', ['selected_option_id' => $stage->exercises[0]->options[0]->id, 'score_awarded' => 1000])
            ->assertUnprocessable()->assertJsonValidationErrors('score_awarded');
        $this->assertDatabaseCount('exercise_submissions', 0);
    }

    public function test_history_and_dashboard_are_scoped_to_the_current_student(): void
    {
        $exercise = $this->course()->stages[0]->exercises[0];
        $this->tokenFor(User::factory()->create());
        $this->postJson('/api/exercises/'.$exercise->id.'/submissions', ['selected_option_id' => $exercise->options[0]->id])->assertCreated();
        $this->getJson('/api/me/submissions')->assertOk()->assertJsonPath('meta.total', 1);
        $this->tokenFor(User::factory()->create());
        $this->getJson('/api/me/submissions')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/me/dashboard')->assertOk()->assertJsonPath('data.started_paths_count', 0)->assertJsonPath('data.user.total_xp', 0);
        $this->getJson('/api/exercises/'.$exercise->id)->assertOk()->assertJsonPath('data.is_solved', false)->assertJsonMissingPath('data.explanation');
    }

    public function test_catalogue_query_count_does_not_grow_with_the_number_of_paths(): void
    {
        $this->course(1);
        $this->tokenFor(User::factory()->create());
        DB::enableQueryLog();
        $this->getJson('/api/learning-paths')->assertOk();
        $baseline = count(DB::getQueryLog());
        DB::disableQueryLog();
        for ($i = 0; $i < 4; $i++) {
            $this->course(1);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson('/api/learning-paths')->assertOk()->assertJsonCount(5, 'data');
        $this->assertLessThanOrEqual($baseline, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }

    public function test_demo_content_is_repeatable_and_can_be_studied(): void
    {
        $this->seed(DemoContentSeeder::class);
        $this->seed(DemoContentSeeder::class);
        $this->assertDatabaseCount('learning_paths', 1);
        $this->assertDatabaseCount('stages', 2);
        $this->tokenFor(User::factory()->create());
        $path = LearningPath::first();
        $this->getJson('/api/learning-paths/'.$path->id)->assertOk()->assertJsonPath('data.stages.0.status', 'available');
        $exercise = Exercise::first();
        $this->postJson('/api/exercises/'.$exercise->id.'/submissions', ['selected_option_id' => $exercise->options()->where('is_correct', true)->first()->id])
            ->assertCreated()->assertJsonPath('data.user.total_xp', 50);
    }
}
