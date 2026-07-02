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
        Schema::create('aplikasis', function (Blueprint $table) {
            $table->id();
            $table->string('nama_layanan');
            $table->string('nama_singkat');
            $table->string('nama_aplikasi');
            $table->enum('jenis_layanan_aplikasi', ['publik', 'internal']);
            $table->string('kode_unitOrganisasi');
            $table->string('tipe_akuisisi');
            $table->string('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aplikasis');
    }
};
