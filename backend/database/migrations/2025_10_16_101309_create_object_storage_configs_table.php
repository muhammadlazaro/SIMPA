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
        Schema::create('object_storage_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aplikasi_id')->constrained('aplikasis')->onDelete('cascade');
            $table->enum('environment', ['minio-dev', 'minio']);
            $table->string('minio_bucket'); // otomatis dari nama_aplikasi
            $table->string('minio_default_region')->default('us-east-1');
            $table->string('minio_endpoint');
            $table->string('minio_url');
            $table->boolean('minio_use_path_style_endpoint')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('object_storage_configs');
    }
};
