<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfcs', function (Blueprint $table) {
            $table->string('formulir_path')->nullable()->after('deskripsi');
            $table->string('formulir_original_filename')->nullable()->after('formulir_path');
            $table->string('formulir_mime_type', 128)->nullable()->after('formulir_original_filename');
            $table->unsignedBigInteger('formulir_file_size')->nullable()->after('formulir_mime_type');
        });
    }

    public function down(): void
    {
        Schema::table('rfcs', function (Blueprint $table) {
            $table->dropColumn([
                'formulir_path',
                'formulir_original_filename',
                'formulir_mime_type',
                'formulir_file_size',
            ]);
        });
    }
};
