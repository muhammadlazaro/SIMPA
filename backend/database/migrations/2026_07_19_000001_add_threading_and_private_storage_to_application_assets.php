<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aplikasi_notes', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('aplikasi_id')
                ->constrained('aplikasi_notes')
                ->cascadeOnDelete();
            $table->timestamp('edited_at')->nullable()->after('checked_at');
            $table->index(['aplikasi_id', 'parent_id']);
        });

        Schema::table('aplikasi_documents', function (Blueprint $table) {
            // Existing documents remain readable from public storage; new uploads use local/private.
            $table->string('storage_disk', 32)->default('public')->after('storage_path');
        });
    }

    public function down(): void
    {
        Schema::table('aplikasi_documents', function (Blueprint $table) {
            $table->dropColumn('storage_disk');
        });

        Schema::table('aplikasi_notes', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['aplikasi_id', 'parent_id']);
            $table->dropColumn(['parent_id', 'edited_at']);
        });
    }
};
