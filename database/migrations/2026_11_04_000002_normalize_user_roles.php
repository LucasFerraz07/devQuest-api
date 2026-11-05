<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'system_admin')->update(['role' => 'admin']);
        DB::table('users')->whereIn('role', ['user', 'tenant_admin'])->update(['role' => 'student']);
    }

    public function down(): void
    {
        // The old tenant roles cannot be reconstructed without losing information.
    }
};
