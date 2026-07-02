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
        Schema::create('frontend_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aplikasi_id')->constrained('aplikasis')->onDelete('cascade');
            $table->string('nama_modul'); // dari proyek
            $table->string('local_url'); // otomatis /spl-dev.bssn.go.id/nama_aplikasi
            $table->string('feat_staging_production_url'); // otomatis /api/nama_aplikasi
            $table->boolean('check')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('frontend_configs');
    }
};
