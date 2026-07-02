<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aplikasi_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aplikasi_id')->constrained('aplikasis')->cascadeOnDelete();
            $table->string('category', 64)->default('studi_kelayakan');
            $table->string('title');
            $table->string('item_status', 32)->default('pending');
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['aplikasi_id', 'category']);
            $table->index('item_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aplikasi_checklists');
    }
};
