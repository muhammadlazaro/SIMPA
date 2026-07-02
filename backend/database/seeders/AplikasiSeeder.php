<?php

namespace Database\Seeders;

use App\Models\Aplikasi;
use App\Models\User;
use App\Services\AutoGenerationService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AplikasiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get pengelola user for created_by/updated_by
        $pengelolaUser = User::where('role', 'pengelola_aplikasi')->first();
        $unitKerjaUser = User::where('role', 'unit_kerja')->first();
        
        if (!$pengelolaUser || !$unitKerjaUser) {
            $this->command->error('Required users not found. Please run UserSeeder first.');
            return;
        }

        // Sample aplikasi data with user tracking
        $aplikasiData = [
            [
                'nama_layanan' => 'SIMPEG',
                'nama_singkat' => 'SIMPEG',
                'nama_aplikasi' => 'E-SIMPEG 2024',
                'jenis_layanan_aplikasi' => 'publik',
                'kode_unitOrganisasi' => 'UNIT001',
                'tipe_akuisisi' => 'Custom-Made',
                'status' => 'deployed_production',
                'created_by' => $pengelolaUser->id,
                'updated_by' => $pengelolaUser->id,
            ],
            [
                'nama_layanan' => 'Surat Menyurat',
                'nama_singkat' => 'PERSURATAN',
                'nama_aplikasi' => 'E-Office v2',
                'jenis_layanan_aplikasi' => 'internal',
                'kode_unitOrganisasi' => 'UNIT002',
                'tipe_akuisisi' => 'Off-The-Shelf',
                'status' => 'uat',
                'created_by' => $pengelolaUser->id,
                'updated_by' => $pengelolaUser->id,
            ],
            [
                'nama_layanan' => 'Pelayanan Unit Kerja',
                'nama_singkat' => 'UNITAPP',
                'nama_aplikasi' => 'Aplikasi Saya Unit Kerja',
                'jenis_layanan_aplikasi' => 'internal',
                'kode_unitOrganisasi' => 'UNIT009',
                'tipe_akuisisi' => 'Custom-Made',
                'status' => 'diajukan',
                'created_by' => $unitKerjaUser->id,
                'updated_by' => $unitKerjaUser->id,
            ],
        ];

        foreach ($aplikasiData as $data) {
            // Check if aplikasi already exists
            $exists = Aplikasi::where('nama_aplikasi', $data['nama_aplikasi'])->exists();
            
            if (!$exists) {
                DB::transaction(function () use ($data) {
                    $aplikasi = Aplikasi::create($data);
                    
                    // Auto-generate related configurations
                    $autoGenService = new AutoGenerationService();
                    $autoGenService->generateAllConfigurations($aplikasi->id);
                    
                    $this->command->info("Created aplikasi: {$aplikasi->nama_aplikasi}");
                });
            } else {
                $this->command->warn("Aplikasi '{$data['nama_aplikasi']}' already exists. Skipping.");
            }
        }
    }
}
