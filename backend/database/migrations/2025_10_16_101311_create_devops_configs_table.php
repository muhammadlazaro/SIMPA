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
        Schema::create('devops_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aplikasi_id')->constrained('aplikasis')->onDelete('cascade');
            $table->string('project')->nullable();
            $table->string('dbt_dev')->nullable();
            $table->string('dbt')->nullable();
            $table->string('spl_dev')->nullable();
            $table->string('spl')->nullable();
            $table->string('auth')->nullable();
            $table->string('env_staging')->nullable();
            $table->string('env_production')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devops_configs');
    }
};
