<?php

namespace Tests\Feature;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\Auth\AuthService;
use App\Services\Learning\ContentService;
use App\Services\Learning\JourneyService;
use App\Services\Learning\ProfileService;
use App\Services\Learning\ProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\LearningFixtures;
use Tests\TestCase;

class ApiExceptionHandlingTest extends TestCase
{
    use LearningFixtures, RefreshDatabase;

    #[DataProvider('controllerActions')]
    public function test_controller_catches_service_exception_without_global_handler(string $service, string $action, string $method, string $uri, array $input = []): void
    {
        $path = $this->course(1);
        $stage = $path->stages[0];
        $lesson = $stage->lessons[0];
        $exercise = $stage->exercises[0];
        $uri = strtr($uri, ['{path}' => $path->id, '{stage}' => $stage->id, '{lesson}' => $lesson->id, '{exercise}' => $exercise->id]);
        if ($action === 'submit') {
            $input['selected_option_id'] = $exercise->options[0]->id;
        }
        if ($action === 'reorder') {
            $input['ids'] = [$stage->id];
        }
        $this->tokenFor(User::factory()->admin()->create());
        $this->withoutExceptionHandling();
        $this->mock($service, function (MockInterface $mock) use ($action) {
            $mock->shouldReceive($action)->once()->andThrow(new ApiException('Operação bloqueada.', 409, ['reason' => 'business_rule']));
        });

        $this->json($method, '/api/'.$uri, $input)->assertStatus(409)->assertExactJson([
            'error' => true,
            'message' => 'Operação bloqueada.',
            'data' => ['reason' => 'business_rule'],
        ]);
    }

    public static function controllerActions(): array
    {
        return [
            'register' => [AuthService::class, 'register', 'POST', 'auth/register', ['name' => 'Aluno', 'email' => 'student@example.com', 'password' => 'password', 'password_confirmation' => 'password']],
            'login' => [AuthService::class, 'login', 'POST', 'auth/login', ['email' => 'student@example.com', 'password' => 'password']],
            'me' => [AuthService::class, 'me', 'GET', 'auth/me'],
            'refresh' => [AuthService::class, 'refresh', 'POST', 'auth/refresh'],
            'logout' => [AuthService::class, 'logout', 'POST', 'auth/logout'],
            'catalogue' => [JourneyService::class, 'catalogue', 'GET', 'learning-paths'],
            'path' => [JourneyService::class, 'path', 'GET', 'learning-paths/{path}'],
            'stage' => [JourneyService::class, 'stage', 'GET', 'stages/{stage}'],
            'start' => [ProgressService::class, 'start', 'POST', 'stages/{stage}/start'],
            'lesson' => [JourneyService::class, 'lesson', 'GET', 'lessons/{lesson}'],
            'completeLesson' => [ProgressService::class, 'completeLesson', 'PUT', 'lessons/{lesson}/completion'],
            'exercise' => [JourneyService::class, 'exercise', 'GET', 'exercises/{exercise}'],
            'submit' => [ProgressService::class, 'submit', 'POST', 'exercises/{exercise}/submissions'],
            'dashboard' => [ProfileService::class, 'dashboard', 'GET', 'me/dashboard'],
            'submissions' => [ProfileService::class, 'submissions', 'GET', 'me/submissions'],
            'achievements' => [ProfileService::class, 'achievements', 'GET', 'me/achievements'],
            'indexContent' => [ContentService::class, 'index', 'GET', 'admin/learning-paths'],
            'showContent' => [ContentService::class, 'find', 'GET', 'admin/learning-paths/{path}'],
            'storeContent' => [ContentService::class, 'save', 'POST', 'admin/learning-paths', ['title' => 'Nova trilha', 'slug' => 'nova-trilha']],
            'updateContent' => [ContentService::class, 'save', 'PATCH', 'admin/learning-paths/{path}', ['title' => 'Novo título']],
            'destroyContent' => [ContentService::class, 'delete', 'DELETE', 'admin/learning-paths/{path}'],
            'reorderContent' => [ContentService::class, 'reorder', 'PUT', 'admin/learning-paths/{path}/stages/order'],
        ];
    }

    public function test_invalid_refresh_token_is_converted_to_a_caught_api_exception(): void
    {
        $this->withoutExceptionHandling();
        $this->withToken('invalid-token')->postJson('/api/auth/refresh')
            ->assertUnauthorized()->assertExactJson([
                'error' => true, 'message' => 'Autenticação inválida ou expirada.', 'data' => null,
            ]);
    }

    public function test_unexpected_service_exception_uses_generic_error_without_exposing_details(): void
    {
        $this->tokenFor(User::factory()->create());
        $this->mock(ProfileService::class, function (MockInterface $mock) {
            $mock->shouldReceive('dashboard')->once()->andThrow(new RuntimeException('Sensitive database details'));
        });

        $this->getJson('/api/me/dashboard')->assertInternalServerError()->assertExactJson([
            'error' => true, 'message' => 'Não foi possível processar a solicitação.', 'data' => null,
        ]);
    }
}
