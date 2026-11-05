<?php

namespace Tests\Feature;

use App\Enums\UserType;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Support\LearningFixtures;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use LearningFixtures, RefreshDatabase;

    public function test_seeders_are_repeatable_and_admin_can_login(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('achievements', 3);
        $this->postJson('/api/auth/login', ['email' => 'admin@teste.com', 'password' => 'password'])
            ->assertOk()->assertJsonPath('data.user.role', 'admin')->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure(['data' => ['token', 'expires_in', 'refresh_expires_in']]);
    }

    public function test_registration_returns_a_student_without_sensitive_fields(): void
    {
        $this->postJson('/api/auth/register', ['name' => 'Aluno', 'email' => 'aluno@example.com', 'password' => 'password', 'password_confirmation' => 'password'])
            ->assertCreated()->assertJsonPath('data.user.role', 'student')
            ->assertJsonPath('data.user.total_xp', 0)->assertJsonPath('data.user.current_level', 1)
            ->assertJsonMissingPath('data.user.password');
        $this->assertSame(UserType::STUDENT, User::first()->role);
    }

    public function test_registration_rejects_privileged_fields(): void
    {
        $this->postJson('/api/auth/register', ['name' => 'Aluno', 'email' => 'aluno@example.com', 'password' => 'password', 'password_confirmation' => 'password', 'role' => 'admin', 'total_xp' => 500, 'current_level' => 20])
            ->assertUnprocessable()->assertJsonPath('error', true)->assertJsonPath('data', null)
            ->assertJsonValidationErrors(['role', 'total_xp', 'current_level']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_invalid_password_and_missing_or_invalid_tokens_return_401(): void
    {
        $user = User::factory()->create();
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'incorrect'])->assertUnauthorized()->assertJsonPath('error', true);
        Auth::forgetGuards();
        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->withToken('invalid-token')->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_expired_token_can_be_refreshed_and_old_token_is_revoked(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $this->travel(61)->minutes();
        $this->getJson('/api/auth/me')->assertUnauthorized();
        Auth::forgetGuards();
        $renewal = $this->postJson('/api/auth/refresh')->assertOk();
        $newToken = $renewal->json('data.token');
        Auth::forgetGuards();
        $this->withToken($newToken)->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.id', $user->id);
        Auth::forgetGuards();
        $this->withToken($token)->postJson('/api/auth/refresh')->assertUnauthorized();
    }

    public function test_refresh_window_and_deleted_subject_are_enforced(): void
    {
        $user = User::factory()->create();
        $this->tokenFor($user);
        $this->travel(15)->days();
        $this->postJson('/api/auth/refresh')->assertUnauthorized();
        $this->travelBack();
        $this->tokenFor($user);
        $user->delete();
        $this->postJson('/api/auth/refresh')->assertUnauthorized();
    }

    public function test_logout_invalidates_the_access_token(): void
    {
        $token = $this->tokenFor(User::factory()->create());
        $this->postJson('/api/auth/logout')->assertOk();
        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_login_rate_limit_returns_the_common_envelope(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'missing@example.com', 'password' => 'wrong-password'])->assertUnauthorized();
        }
        $this->postJson('/api/auth/login', ['email' => 'missing@example.com', 'password' => 'wrong-password'])
            ->assertStatus(429)->assertJsonPath('error', true)->assertHeader('Retry-After');
    }

    public function test_cors_preflight_allows_the_react_origin(): void
    {
        $this->withHeaders(['Origin' => 'http://localhost:5173', 'Access-Control-Request-Method' => 'POST', 'Access-Control-Request-Headers' => 'authorization,content-type'])
            ->options('/api/auth/login')->assertNoContent()->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }
}
