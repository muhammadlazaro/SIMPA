<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('aplikasis')
            ->where('status', 'deployed')
            ->update(['status' => 'deployed_production']);

        DB::table('aplikasi_status_histories')
            ->where('status_sebelumnya', 'deployed')
            ->update(['status_sebelumnya' => 'deployed_production']);

        DB::table('aplikasi_status_histories')
            ->where('status_baru', 'deployed')
            ->update(['status_baru' => 'deployed_production']);
    }

    public function down(): void
    {
        DB::table('aplikasis')
            ->where('status', 'deployed_production')
            ->update(['status' => 'deployed']);

        DB::table('aplikasi_status_histories')
            ->where('status_sebelumnya', 'deployed_production')
            ->update(['status_sebelumnya' => 'deployed']);

        DB::table('aplikasi_status_histories')
            ->where('status_baru', 'deployed_production')
            ->update(['status_baru' => 'deployed']);
    }
};

