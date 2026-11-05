<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_incremental_role_migration_preserves_existing_accounts_and_progress_fields(): void
    {
        $admin = User::factory()->create(['total_xp' => 150, 'current_level' => 2]);
        $student = User::factory()->create();
        DB::table('users')->where('id', $admin->id)->update(['role' => 'system_admin']);
        DB::table('users')->where('id', $student->id)->update(['role' => 'tenant_admin']);
        $migration = require database_path('migrations/2026_11_04_000002_normalize_user_roles.php');
        $migration->up();
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin', 'total_xp' => 150, 'current_level' => 2]);
        $this->assertDatabaseHas('users', ['id' => $student->id, 'role' => 'student']);
        $this->assertDatabaseCount('users', 2);
    }
}
