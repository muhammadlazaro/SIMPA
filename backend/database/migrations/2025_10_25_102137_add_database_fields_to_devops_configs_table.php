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
        Schema::table('devops_configs', function (Blueprint $table) {
            $table->string('db_connection')->nullable()->after('auth');
            $table->string('db_host')->nullable()->after('db_connection');
            $table->string('db_port')->nullable()->after('db_host');
            $table->string('db_database')->nullable()->after('db_port');
            $table->string('db_username')->nullable()->after('db_database');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devops_configs', function (Blueprint $table) {
            $table->dropColumn(['db_connection', 'db_host', 'db_port', 'db_database', 'db_username']);
        });
    }
};
