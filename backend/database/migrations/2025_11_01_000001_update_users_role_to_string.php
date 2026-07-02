<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // MySQL supports direct enum -> varchar alteration.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role VARCHAR(30) NOT NULL DEFAULT 'tim_implementasi_aplikasi'");
        } elseif (DB::getDriverName() === 'sqlite') {
            // SQLite stores enum() as VARCHAR plus a CHECK constraint; new role strings fail inserts.
            // Rebuild the column as a plain string without the legacy enum check.
            $roles = DB::table('users')->pluck('role', 'id')->all();
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['role']);
            });
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
            Schema::table('users', function (Blueprint $table) {
                $table->string('role', 30)->default('tim_implementasi_aplikasi');
                $table->index('role');
            });
            foreach ($roles as $id => $role) {
                DB::table('users')->where('id', $id)->update(['role' => $role]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert only on MySQL where enum alteration is supported.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin_sistem', 'pengelola_aplikasi', 'analis_desain', 'unit_kerja', 'tim_implementasi_aplikasi', 'devops_developer', 'tim_uji_keamanan') NOT NULL DEFAULT 'tim_implementasi_aplikasi'");
        }
    }
};
