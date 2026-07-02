<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Indexes for aplikasis table
        Schema::table('aplikasis', function (Blueprint $table) {
            $table->index('nama_aplikasi');
            $table->index('jenis_layanan_aplikasi');
            $table->index('status');
            $table->index('created_at');
        });

        // Indexes for analisa_desains table
        Schema::table('analisa_desains', function (Blueprint $table) {
            $table->index('aplikasi_id');
            $table->index('ui_platform');
            $table->index('storage_type');
        });

        // Indexes for users table
        Schema::table('users', function (Blueprint $table) {
            $table->index('email');
            $table->index('role');
        });

        // Indexes for proyeks table
        Schema::table('proyeks', function (Blueprint $table) {
            $table->index('aplikasi_id');
        });

        // Indexes for database_configs table
        Schema::table('database_configs', function (Blueprint $table) {
            $table->index('aplikasi_id');
        });

        // Indexes for object_storage_configs table
        Schema::table('object_storage_configs', function (Blueprint $table) {
            $table->index('aplikasi_id');
        });

        // Indexes for api_gateway_configs table
        Schema::table('api_gateway_configs', function (Blueprint $table) {
            $table->index('aplikasi_id');
        });

        // Indexes for environment_configs table
        Schema::table('environment_configs', function (Blueprint $table) {
            $table->index('aplikasi_id');
        });

        // Indexes for devops_configs table
        Schema::table('devops_configs', function (Blueprint $table) {
            $table->index('aplikasi_id');
        });

        // Indexes for frontend_configs table
        Schema::table('frontend_configs', function (Blueprint $table) {
            $table->index('aplikasi_id');
        });

        // Indexes for backend_configs table
        Schema::table('backend_configs', function (Blueprint $table) {
            $table->index('aplikasi_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('aplikasis', function (Blueprint $table) {
            $table->dropIndex(['nama_aplikasi']);
            $table->dropIndex(['jenis_layanan_aplikasi']);
            $table->dropIndex(['status']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('analisa_desains', function (Blueprint $table) {
            $table->dropIndex(['aplikasi_id']);
            $table->dropIndex(['ui_platform']);
            $table->dropIndex(['storage_type']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->dropIndex(['role']);
        });

        Schema::table('proyeks', function (Blueprint $table) {
            $table->dropIndex(['aplikasi_id']);
        });

        Schema::table('database_configs', function (Blueprint $table) {
            $table->dropIndex(['aplikasi_id']);
        });

        Schema::table('object_storage_configs', function (Blueprint $table) {
            $table->dropIndex(['aplikasi_id']);
        });

        Schema::table('api_gateway_configs', function (Blueprint $table) {
            $table->dropIndex(['aplikasi_id']);
        });

        Schema::table('environment_configs', function (Blueprint $table) {
            $table->dropIndex(['aplikasi_id']);
        });

        Schema::table('devops_configs', function (Blueprint $table) {
            $table->dropIndex(['aplikasi_id']);
        });

        Schema::table('frontend_configs', function (Blueprint $table) {
            $table->dropIndex(['aplikasi_id']);
        });

        Schema::table('backend_configs', function (Blueprint $table) {
            $table->dropIndex(['aplikasi_id']);
        });
    }
};
