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
        Schema::create('analisa_desains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aplikasi_id')->constrained('aplikasis')->onDelete('cascade');
            
            // UI Section
            $table->string('ui_platform')->nullable(); // layanan, dws
            
            // Interop Section
            $table->string('interop_type')->nullable(); // master-data, authentication-int
            
            // Storage Section
            $table->string('storage_type')->nullable(); // db, object-storage
            
            // Aktor Section
            $table->string('nama_aktor')->nullable(); // user, pegawai, pengelola
            
            // Transaksi Section
            $table->string('method')->nullable(); // GET, POST, PUT, DELETE
            $table->string('url')->nullable();
            $table->enum('tipe_resource', ['tertutup', 'terbuka'])->nullable();
            $table->string('aktor_transaksi')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analisa_desains');
    }
};
