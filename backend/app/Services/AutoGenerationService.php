<?php

namespace App\Services;

use App\Models\Aplikasi;
use App\Models\AnalisaDesain;
use App\Models\Proyek;
use App\Models\DatabaseConfig;
use App\Models\ObjectStorageConfig;
use App\Models\ApiGatewayConfig;
use App\Models\FrontendConfig;
use App\Models\BackendConfig;
use App\Models\DevopsConfig;
use App\Models\EnvironmentConfig;
use Illuminate\Support\Facades\Log;

class AutoGenerationService
{
    /**
     * Trigger auto-generation for all related configurations
     */
    public function generateAllConfigurations(int $aplikasiId, bool $resetAnalisaDesain = false): array
    {
        $aplikasi = Aplikasi::findOrFail($aplikasiId);
        $results = [];

        try {
            // 1. Generate Analisa Desain
            $results['analisa_desain'] = $this->generateAnalisaDesain($aplikasi, $resetAnalisaDesain);

            // 2. Generate Proyek (berdasarkan analisa desain)
            $results['proyek'] = $this->generateProyek($aplikasi);

            // 3. Generate Database Config
            $results['database'] = $this->generateDatabaseConfig($aplikasi);

            // 4. Generate Object Storage Config
            $results['object_storage'] = $this->generateObjectStorageConfig($aplikasi);

            // 5. Generate API Gateway Config
            $results['api_gateway'] = $this->generateApiGatewayConfig($aplikasi);

            // 6. Generate Frontend Config
            $results['frontend'] = $this->generateFrontendConfig($aplikasi);

            // 7. Generate Backend Config
            $results['backend'] = $this->generateBackendConfig($aplikasi);

            // 8. Generate DevOps Config
            $results['devops'] = $this->generateDevopsConfig($aplikasi);

            Log::info("Auto-generation completed for aplikasi: {$aplikasi->nama_aplikasi}");
            
            return [
                'success' => true,
                'message' => 'Semua konfigurasi berhasil di-generate otomatis',
                'data' => $results
            ];

        } catch (\Exception $e) {
            Log::error("Auto-generation failed for aplikasi ID {$aplikasiId}: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Gagal melakukan auto-generation: ' . $e->getMessage(),
                'data' => $results
            ];
        }
    }

    /**
     * Generate Analisa Desain
     */
    private function generateAnalisaDesain(Aplikasi $aplikasi, bool $resetAnalisaDesain = false): array
    {
        if ($resetAnalisaDesain) {
            AnalisaDesain::where('aplikasi_id', $aplikasi->id)->delete();
        }

        $configs = $this->generateUiPlatformAnalisa($aplikasi);
        $configs = array_merge(
            $configs,
            $this->generateAnalisaDimension($aplikasi, 'interop_type', ['master-data', 'authentication-int']),
            $this->generateAnalisaDimension($aplikasi, 'storage_type', ['db', 'object-storage']),
            $this->generateAnalisaDimension($aplikasi, 'nama_aktor', ['User', 'Pegawai', 'Pengelola']),
        );

        $configs[] = AnalisaDesain::updateOrCreate(
            [
                'aplikasi_id' => $aplikasi->id,
                'method' => 'GET',
                'url' => '/api/documents',
            ],
            [
                'ui_platform' => null,
                'interop_type' => null,
                'storage_type' => null,
                'nama_aktor' => null,
                'tipe_resource' => 'terbuka',
                'aktor_transaksi' => 'user, pengelola',
            ],
        );

        return $configs;
    }

    private function generateUiPlatformAnalisa(Aplikasi $aplikasi): array
    {
        $platforms = ['dws'];
        if ($aplikasi->jenis_layanan_aplikasi === 'publik') {
            array_unshift($platforms, 'layanan');
        } else {
            AnalisaDesain::where('aplikasi_id', $aplikasi->id)
                ->where('ui_platform', 'layanan')
                ->delete();
        }

        return $this->generateAnalisaDimension($aplikasi, 'ui_platform', $platforms);
    }

    private function generateAnalisaDimension(Aplikasi $aplikasi, string $dimension, array $values): array
    {
        $nullableAttributes = [
            'ui_platform',
            'interop_type',
            'storage_type',
            'nama_aktor',
            'method',
            'url',
            'tipe_resource',
            'aktor_transaksi',
        ];
        $attributes = array_fill_keys(array_diff($nullableAttributes, [$dimension]), null);

        return array_map(
            fn (string $value): AnalisaDesain => AnalisaDesain::updateOrCreate(
                ['aplikasi_id' => $aplikasi->id, $dimension => $value],
                $attributes,
            ),
            $values,
        );
    }

    /**
     * Update only UI Platform and Proyek based on jenis_layanan_aplikasi
     * This method preserves existing interop, storage, aktor, and transaksi data
     */
    public function updateUIAndProyekOnly(int $aplikasiId): array
    {
        $aplikasi = Aplikasi::findOrFail($aplikasiId);
        $results = [];

        try {
            // Update UI Platform based on jenis_layanan_aplikasi
            $this->updateUIPlatform($aplikasi);
            
            // Update Proyek based on jenis_layanan_aplikasi
            $results['proyek'] = $this->generateProyek($aplikasi);

            Log::info("UI Platform and Proyek updated for aplikasi: {$aplikasi->nama_aplikasi}");
            
            return [
                'success' => true,
                'message' => 'UI Platform dan Proyek berhasil diupdate',
                'data' => $results
            ];

        } catch (\Exception $e) {
            Log::error("Update UI and Proyek failed for aplikasi ID {$aplikasiId}: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Gagal mengupdate UI Platform dan Proyek: ' . $e->getMessage(),
                'data' => $results
            ];
        }
    }

    /**
     * Update UI Platform based on jenis_layanan_aplikasi
     */
    private function updateUIPlatform(Aplikasi $aplikasi): void
    {
        if ($aplikasi->jenis_layanan_aplikasi === 'publik') {
            // Untuk publik: pastikan ada layanan dan dws
            AnalisaDesain::updateOrCreate(
                [
                    'aplikasi_id' => $aplikasi->id,
                    'ui_platform' => 'layanan'
                ],
                [
                    'interop_type' => null,
                    'storage_type' => null,
                    'nama_aktor' => null,
                    'method' => null,
                    'url' => null,
                    'tipe_resource' => null,
                    'aktor_transaksi' => null
                ]
            );

            AnalisaDesain::updateOrCreate(
                [
                    'aplikasi_id' => $aplikasi->id,
                    'ui_platform' => 'dws'
                ],
                [
                    'interop_type' => null,
                    'storage_type' => null,
                    'nama_aktor' => null,
                    'method' => null,
                    'url' => null,
                    'tipe_resource' => null,
                    'aktor_transaksi' => null
                ]
            );
        } else {
            // Untuk internal: hapus layanan jika ada, pastikan ada dws
            AnalisaDesain::where('aplikasi_id', $aplikasi->id)
                ->where('ui_platform', 'layanan')
                ->delete();

            AnalisaDesain::updateOrCreate(
                [
                    'aplikasi_id' => $aplikasi->id,
                    'ui_platform' => 'dws'
                ],
                [
                    'interop_type' => null,
                    'storage_type' => null,
                    'nama_aktor' => null,
                    'method' => null,
                    'url' => null,
                    'tipe_resource' => null,
                    'aktor_transaksi' => null
                ]
            );
        }
    }

    /**
     * Generate Proyek berdasarkan analisa desain
     */
    private function generateProyek(Aplikasi $aplikasi): array
    {
        $proyeks = [];
        $namaAplikasi = $aplikasi->nama_aplikasi;

        // 1. Backend proyek
        $proyeks[] = Proyek::updateOrCreate(
            [
                'aplikasi_id' => $aplikasi->id,
                'modul' => $namaAplikasi . '-backend'
            ],
            [
                'jenis' => 'backend'
            ]
        );

        // 2. Frontend proyek - berdasarkan jenis layanan
        if ($aplikasi->jenis_layanan_aplikasi === 'publik') {
            // Untuk publik: layanan-frontend dan dws-frontend
            $proyeks[] = Proyek::updateOrCreate(
                [
                    'aplikasi_id' => $aplikasi->id,
                    'modul' => $namaAplikasi . '-layanan-frontend'
                ],
                [
                    'jenis' => 'frontend'
                ]
            );
        } else {
            // Untuk internal: hapus proyek layanan-frontend jika ada
            Proyek::where('aplikasi_id', $aplikasi->id)
                ->where('modul', 'like', '%-layanan-frontend')
                ->delete();
        }

        // 3. Frontend DWS proyek (selalu ada)
        $proyeks[] = Proyek::updateOrCreate(
            [
                'aplikasi_id' => $aplikasi->id,
                'modul' => $namaAplikasi . '-dws-frontend'
            ],
            [
                'jenis' => 'frontend'
            ]
        );

        return $proyeks;
    }

    /**
     * Generate Database Config
     */
    private function generateDatabaseConfig(Aplikasi $aplikasi): array
    {
        $configs = [];
        $namaAplikasi = $aplikasi->nama_aplikasi;
        $envConfig = config('environment.database');

        // Staging database
        $configs[] = DatabaseConfig::updateOrCreate(
            [
                'aplikasi_id' => $aplikasi->id,
                'deployment' => 'staging'
            ],
            [
                'db_connection' => $envConfig['connection'],
                'db_host' => $envConfig['staging']['host'],
                'db_port' => $envConfig['staging']['port'],
                'db_database' => $namaAplikasi,
                'db_username' => $namaAplikasi
            ]
        );

        // Production database
        $configs[] = DatabaseConfig::updateOrCreate(
            [
                'aplikasi_id' => $aplikasi->id,
                'deployment' => 'production'
            ],
            [
                'db_connection' => $envConfig['connection'],
                'db_host' => $envConfig['production']['host'],
                'db_port' => $envConfig['production']['port'],
                'db_database' => $namaAplikasi,
                'db_username' => $namaAplikasi
            ]
        );

        return $configs;
    }

    /**
     * Generate Object Storage Config
     */
    private function generateObjectStorageConfig(Aplikasi $aplikasi): array
    {
        $configs = [];
        $namaAplikasi = $aplikasi->nama_aplikasi;
        $envConfig = config('environment.object_storage');

        // MinIO Dev
        $configs[] = ObjectStorageConfig::updateOrCreate(
            [
                'aplikasi_id' => $aplikasi->id,
                'environment' => 'minio-dev'
            ],
            [
                'minio_bucket' => $namaAplikasi,
                'minio_default_region' => $envConfig['default_region'],
                'minio_endpoint' => $envConfig['dev']['endpoint'],
                'minio_url' => $envConfig['dev']['url'],
                'minio_use_path_style_endpoint' => true
            ]
        );

        // MinIO Production
        $configs[] = ObjectStorageConfig::updateOrCreate(
            [
                'aplikasi_id' => $aplikasi->id,
                'environment' => 'minio'
            ],
            [
                'minio_bucket' => $namaAplikasi,
                'minio_default_region' => $envConfig['default_region'],
                'minio_endpoint' => $envConfig['production']['endpoint'],
                'minio_url' => $envConfig['production']['url'],
                'minio_use_path_style_endpoint' => true
            ]
        );

        return $configs;
    }

    /**
     * Generate API Gateway Config
     */
    private function generateApiGatewayConfig(Aplikasi $aplikasi): array
    {
        $configs = [];
        $namaAplikasi = $aplikasi->nama_aplikasi;

        // SPL Dev
        $configs[] = ApiGatewayConfig::updateOrCreate(
            [
                'aplikasi_id' => $aplikasi->id,
                'environment' => 'spl-dev'
            ],
            [
                'service_name' => $namaAplikasi,
                'path' => '/api',
                'route_name' => $namaAplikasi,
                'route_path' => '/' . $namaAplikasi
            ]
        );

        // SPL Production
        $configs[] = ApiGatewayConfig::updateOrCreate(
            [
                'aplikasi_id' => $aplikasi->id,
                'environment' => 'spl'
            ],
            [
                'service_name' => $namaAplikasi,
                'path' => '/api',
                'route_name' => $namaAplikasi,
                'route_path' => '/' . $namaAplikasi
            ]
        );

        return $configs;
    }

    /**
     * Generate Frontend Config
     */
    private function generateFrontendConfig(Aplikasi $aplikasi): array
    {
        $configs = [];
        $namaAplikasi = $aplikasi->nama_aplikasi;

        // Get proyek frontend
        $proyeks = Proyek::where('aplikasi_id', $aplikasi->id)
                        ->where('jenis', 'frontend')
                        ->get();

        // CLEANUP: Delete FrontendConfigs that don't have matching Proyek
        $validModulNames = $proyeks->pluck('modul')->toArray();
        FrontendConfig::where('aplikasi_id', $aplikasi->id)
            ->whereNotIn('nama_modul', $validModulNames)
            ->delete();

        foreach ($proyeks as $proyek) {
            $configs[] = FrontendConfig::updateOrCreate(
                [
                    'aplikasi_id' => $aplikasi->id,
                    'nama_modul' => $proyek->modul
                ],
                [
                    'local_url' => '/spl-dev.bssn.go.id/' . $namaAplikasi,
                    'feat_staging_production_url' => '/api/' . $namaAplikasi,
                    'check' => false
                ]
            );
        }

        return $configs;
    }

    /**
     * Generate Backend Config
     */
    private function generateBackendConfig(Aplikasi $aplikasi): array
    {
        $configs = [];
        $namaAplikasi = $aplikasi->nama_aplikasi;
        $envConfig = config('environment.database');

        // Local backend config
        $configs[] = BackendConfig::updateOrCreate(
            [
                'aplikasi_id' => $aplikasi->id,
                'deployment' => 'local'
            ],
            [
                'db_connection' => 'mysql',
                'db_host' => 'localhost',
                'db_port' => $envConfig['local']['port'],
                'db_database' => $namaAplikasi,
                'db_username' => $namaAplikasi,
                'method' => 'GET',
                'url_endpoint' => '/api',
                'check' => false
            ]
        );

        return $configs;
    }

    /**
     * Generate DevOps Config
     */
    private function generateDevopsConfig(Aplikasi $aplikasi): array
    {
        $configs = [];
        $namaAplikasi = $aplikasi->nama_aplikasi;

        // Database Staging Config
        $configs[] = DevopsConfig::updateOrCreate(
            [
                'aplikasi_id' => $aplikasi->id,
                'env_staging' => 'staging'
            ],
            [
                'project' => null,
                'dbt_dev' => null,
                'dbt' => null,
                'spl_dev' => null,
                'spl' => null,
                'auth' => null,
                'db_connection' => 'mysql',
                'db_host' => 'dbt-dev.bssn.go.id',
                'db_port' => '3306',
                'db_database' => $namaAplikasi,
                'db_username' => $namaAplikasi,
                'env_production' => null
            ]
        );

        // Database Production Config
        $configs[] = DevopsConfig::updateOrCreate(
            [
                'aplikasi_id' => $aplikasi->id,
                'env_production' => 'production'
            ],
            [
                'project' => null,
                'dbt_dev' => null,
                'dbt' => null,
                'spl_dev' => null,
                'spl' => null,
                'auth' => null,
                'db_connection' => 'mysql',
                'db_host' => 'dbt.bssn.go.id',
                'db_port' => '3306',
                'db_database' => $namaAplikasi,
                'db_username' => $namaAplikasi,
                'env_staging' => null
            ]
        );

        return $configs;
    }
}
