<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Epic A: status operasional + normalisasi legacy, indeks pemilik pengajuan, tabel dokumen.
     */
    public function up(): void
    {
        // Normalisasi nilai status lama (string bebas di DB)
        DB::table('aplikasis')->where('status', 'Production')->update(['status' => 'Aktif']);
        DB::table('aplikasis')->whereIn('status', ['Dev-staging', 'Dev-Staging'])->update(['status' => 'Dev-Feat-Staging']);

        Schema::table('aplikasis', function (Blueprint $table) {
            $table->index('created_by', 'aplikasis_created_by_index');
        });

        Schema::create('aplikasi_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aplikasi_id')->constrained('aplikasis')->cascadeOnDelete();
            $table->string('document_type', 64);
            $table->string('storage_path');
            $table->string('original_filename')->nullable();
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('status', 32)->default('active');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['aplikasi_id', 'document_type']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aplikasi_documents');

        Schema::table('aplikasis', function (Blueprint $table) {
            $table->dropIndex('aplikasis_created_by_index');
        });

        // Tidak mengembalikan normalisasi status (data bisa sudah berubah di produksi).
    }
};
