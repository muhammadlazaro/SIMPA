<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aplikasi_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aplikasi_id')->constrained('aplikasis')->cascadeOnDelete();
            $table->string('note_type', 64)->default('perbaikan');
            $table->text('body');
            $table->boolean('is_checked')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->index(['aplikasi_id', 'note_type']);
            $table->index('is_checked');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aplikasi_notes');
    }
};
