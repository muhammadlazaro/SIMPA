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
        Schema::create('api_gateway_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aplikasi_id')->constrained('aplikasis')->onDelete('cascade');
            $table->enum('environment', ['spl-dev', 'spl']);
            $table->string('service_name'); // otomatis dari nama_aplikasi
            $table->string('host')->nullable();
            $table->string('path'); // otomatis /api
            $table->string('route_name'); // otomatis dari nama_aplikasi
            $table->string('route_path'); // otomatis /nama_aplikasi
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_gateway_configs');
    }
};
