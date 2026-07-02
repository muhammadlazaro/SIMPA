<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('users')->whereIn('role', ['pengelola', 'pengelola_aplikasi_lama'])->update(['role' => 'pengelola_aplikasi']);
        DB::table('users')->where('role', 'analis')->update(['role' => 'analis_desain']);
        DB::table('users')->where('role', 'frontend')->update(['role' => 'tim_implementasi_aplikasi']);
        DB::table('users')->where('role', 'backend')->update(['role' => 'tim_implementasi_aplikasi']);
        DB::table('users')->where('role', 'devops')->update(['role' => 'devops_developer']);
        DB::table('users')->where('role', 'user')->update(['role' => 'tim_implementasi_aplikasi']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')->where('role', 'pengelola_aplikasi')->update(['role' => 'pengelola']);
        DB::table('users')->where('role', 'analis_desain')->update(['role' => 'analis']);
        DB::table('users')->where('role', 'tim_implementasi_aplikasi')->update(['role' => 'frontend']);
        DB::table('users')->where('role', 'devops_developer')->update(['role' => 'devops']);
    }
};
