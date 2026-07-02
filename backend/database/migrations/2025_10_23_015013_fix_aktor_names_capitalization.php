<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Fix nama_aktor capitalization from lowercase to proper case
        DB::table('analisa_desains')
            ->where('nama_aktor', 'user')
            ->update(['nama_aktor' => 'User']);
            
        DB::table('analisa_desains')
            ->where('nama_aktor', 'pegawai')
            ->update(['nama_aktor' => 'Pegawai']);
            
        DB::table('analisa_desains')
            ->where('nama_aktor', 'pengelola')
            ->update(['nama_aktor' => 'Pengelola']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to lowercase if needed
        DB::table('analisa_desains')
            ->where('nama_aktor', 'User')
            ->update(['nama_aktor' => 'user']);
            
        DB::table('analisa_desains')
            ->where('nama_aktor', 'Pegawai')
            ->update(['nama_aktor' => 'pegawai']);
            
        DB::table('analisa_desains')
            ->where('nama_aktor', 'Pengelola')
            ->update(['nama_aktor' => 'pengelola']);
    }
};
