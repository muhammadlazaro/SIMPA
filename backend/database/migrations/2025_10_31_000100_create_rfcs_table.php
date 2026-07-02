<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfcs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aplikasi_id')->constrained('aplikasis')->onDelete('cascade');
            $table->enum('tipe_rfc', ['Medium','Standar','Minor','Major','Darurat']);
            $table->text('deskripsi')->nullable();
            $table->string('status_tindaklanjut')->default('Staging');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['aplikasi_id','tipe_rfc']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfcs');
    }
};


