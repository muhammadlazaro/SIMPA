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
        Schema::create('environment_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aplikasi_id')->constrained('aplikasis')->onDelete('cascade');
            $table->string('env_name');
            $table->text('env_value');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('environment_configs');
    }
};
