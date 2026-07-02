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
        Schema::table('aplikasis', function (Blueprint $table) {
            $table->string('doc_pengajuan_path')->nullable()->after('status');
            $table->string('doc_permohonan_path')->nullable()->after('doc_pengajuan_path');
            $table->string('doc_studi_kelayakan_path')->nullable()->after('doc_permohonan_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('aplikasis', function (Blueprint $table) {
            $table->dropColumn([
                'doc_pengajuan_path',
                'doc_permohonan_path',
                'doc_studi_kelayakan_path',
            ]);
        });
    }
};


