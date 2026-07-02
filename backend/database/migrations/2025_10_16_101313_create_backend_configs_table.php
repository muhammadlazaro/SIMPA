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
        Schema::create('backend_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aplikasi_id')->constrained('aplikasis')->onDelete('cascade');
            $table->enum('deployment', ['local', 'staging', 'production']);
            $table->string('db_connection')->default('mysql');
            $table->string('db_host');
            $table->string('db_port')->default('3306');
            $table->string('db_database'); // otomatis dari nama_aplikasi
            $table->string('db_username'); // otomatis dari nama_aplikasi
            $table->string('method')->nullable(); // GET, POST, PUT, DELETE
            $table->string('url_endpoint')->nullable(); // /api
            $table->boolean('check')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backend_configs');
    }
};
