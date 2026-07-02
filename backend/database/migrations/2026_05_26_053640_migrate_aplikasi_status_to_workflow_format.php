<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Mapping dari status lama ke status baru.
     */
    private array $statusMap = [
        'Pengajuan'             => 'diajukan',
        'Studi Kelayakan'       => 'terverifikasi',
        'Analisa dan Desain'    => 'analisa_desain',
        'Dev-Feat-Staging'      => 'pengembangan',
        'Testing'               => 'uat',
        'Aktif'                 => 'deployed_production',
        'Aktif dalam perbaikan' => 'deployed_production',
        'Non-aktif'             => 'deployed_production',
        'Maintenance'           => 'deployed_production',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->statusMap as $old => $new) {
            DB::table('aplikasis')
                ->where('status', $old)
                ->update(['status' => $new]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $reverseMap = [
            'diajukan'       => 'Pengajuan',
            'terverifikasi'  => 'Studi Kelayakan',
            'analisa_desain' => 'Analisa dan Desain',
            'pengembangan'   => 'Dev-Feat-Staging',
            'uat'            => 'Testing',
            'deployed_production' => 'Aktif',
        ];

        foreach ($reverseMap as $new => $old) {
            DB::table('aplikasis')
                ->where('status', $new)
                ->update(['status' => $old]);
        }
    }
};
