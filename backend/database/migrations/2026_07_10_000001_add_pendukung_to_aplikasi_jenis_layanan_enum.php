<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("ALTER TABLE aplikasis MODIFY jenis_layanan_aplikasi ENUM('publik', 'internal', 'pendukung') NOT NULL");
    }

    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::table('aplikasis')
            ->where('jenis_layanan_aplikasi', 'pendukung')
            ->update(['jenis_layanan_aplikasi' => 'internal']);

        DB::statement("ALTER TABLE aplikasis MODIFY jenis_layanan_aplikasi ENUM('publik', 'internal') NOT NULL");
    }
};
