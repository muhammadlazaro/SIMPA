<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use App\Models\AppNotification;

/**
 * @property int $id
 * @property string $nama_layanan
 * @property string $nama_singkat
 * @property string $nama_aplikasi
 * @property string $jenis_layanan_aplikasi
 * @property string $kode_unitOrganisasi
 * @property string $tipe_akuisisi
 * @property string $status
 * @property string|null $doc_pengajuan_path
 * @property string|null $doc_permohonan_path
 * @property string|null $doc_studi_kelayakan_path
 * @property bool|null $security_test_passed
 * @property int|null $security_tested_by
 * @property string|null $security_test_notes
 * @property \Illuminate\Support\Carbon|null $deployed_staging_at
 * @property int|null $deployed_staging_by
 * @property \Illuminate\Support\Carbon|null $deployed_production_at
 * @property int|null $deployed_production_by
 * @property string|null $deployment_notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $security_tested_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Aplikasi extends Model
{
    use HasFactory, SoftDeletes;

    // Status Constants
    public const STATUS_DIAJUKAN = 'diajukan';
    public const STATUS_PERLU_PERBAIKAN = 'perlu_perbaikan_pengajuan';
    public const STATUS_DITOLAK = 'ditolak';
    public const STATUS_TERVERIFIKASI = 'terverifikasi';
    public const STATUS_LAYAK = 'layak';
    public const STATUS_TIDAK_LAYAK = 'tidak_layak';
    public const STATUS_ANALISA_DESAIN = 'analisa_desain';
    public const STATUS_PENGEMBANGAN = 'pengembangan';
    public const STATUS_UAT = 'uat';
    public const STATUS_PERBAIKAN_UAT = 'perbaikan_uat';
    public const STATUS_UJI_KEAMANAN = 'uji_keamanan';
    public const STATUS_PERBAIKAN_KEAMANAN = 'perbaikan_keamanan';
    public const STATUS_SIAP_DEPLOY = 'siap_deploy';
    public const STATUS_DEPLOYED_STAGING = 'deployed_staging';
    public const STATUS_DEPLOYED_PRODUCTION = 'deployed_production';
    public const STATUS_NONAKTIF = 'nonaktif';

    protected $fillable = [
        'nama_layanan',
        'nama_singkat',
        'nama_aplikasi',
        'jenis_layanan_aplikasi',
        'kode_unitOrganisasi',
        'tipe_akuisisi',
        'status',
        'doc_pengajuan_path',
        'doc_permohonan_path',
        'doc_studi_kelayakan_path',
        'security_test_passed',
        'security_tested_by',
        'security_tested_at',
        'security_test_notes',
        'deployed_staging_at',
        'deployed_staging_by',
        'deployed_production_at',
        'deployed_production_by',
        'deployment_notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'jenis_layanan_aplikasi' => 'string',
        'security_test_passed' => 'boolean',
        'security_tested_at' => 'datetime',
        'deployed_staging_at' => 'datetime',
        'deployed_production_at' => 'datetime',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        // Automatically set created_by when creating
        static::creating(function ($model) {
            if (Auth::check() && !$model->created_by) {
                $model->created_by = Auth::id();
            }
        });

        // Automatically set updated_by when updating
        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });

        /**
         * Kirim notifikasi in-app ke semua pihak yang berkepentingan saat status workflow berubah.
         *
         * Setiap entri dalam $recipientRoles mendefinisikan:
         *   - role       : role yang menerima notifikasi
         *   - type       : 'action_required' (harus bertindak) | 'info' (hanya informasi)
         *   - title      : judul notifikasi
         *   - body       : isi pesan (%s diganti nama aplikasi)
         *
         * Selain itu, unit_kerja (pemilik/created_by) selalu mendapat notifikasi
         * untuk status yang berdampak langsung pada mereka.
         */
        static::updated(function ($model) {
            if (! $model->wasChanged('status')) {
                return;
            }

            $newStatus = $model->getAttribute('status');
            $appName   = $model->getAttribute('nama_aplikasi');

            // ─── 1. Daftar notifikasi per-role berdasarkan status baru ────────────────
            $recipientRoles = match ($newStatus) {
                // Pengajuan baru → pengelola harus memverifikasi
                self::STATUS_DIAJUKAN => [
                    [
                        'role'  => 'pengelola_aplikasi',
                        'type'  => 'action_required',
                        'title' => 'Pengajuan Baru Masuk',
                        'body'  => 'Aplikasi "%s" baru diajukan dan menunggu verifikasi Anda.',
                    ],
                ],

                // Layak → analis harus mulai analisa
                self::STATUS_LAYAK => [
                    [
                        'role'  => 'analis_desain',
                        'type'  => 'action_required',
                        'title' => 'Analisa Desain Diperlukan',
                        'body'  => 'Aplikasi "%s" dinyatakan layak. Silakan mulai proses analisa desain.',
                    ],
                ],

                // Analisa desain → tim implementasi harus siap
                self::STATUS_ANALISA_DESAIN => [
                    [
                        'role'  => 'tim_implementasi_aplikasi',
                        'type'  => 'action_required',
                        'title' => 'Aplikasi Siap Dikembangkan',
                        'body'  => 'Analisa desain untuk aplikasi "%s" telah selesai. Silakan mulai pengembangan.',
                    ],
                ],

                // Pengembangan dimulai → pengelola perlu tahu progress
                self::STATUS_PENGEMBANGAN => [
                    [
                        'role'  => 'pengelola_aplikasi',
                        'type'  => 'info',
                        'title' => 'Pengembangan Dimulai',
                        'body'  => 'Aplikasi "%s" kini memasuki tahap pengembangan oleh tim implementasi.',
                    ],
                ],

                // Siap UAT → pengelola harus verifikasi UAT
                self::STATUS_UAT => [
                    [
                        'role'  => 'pengelola_aplikasi',
                        'type'  => 'action_required',
                        'title' => 'UAT Siap Diverifikasi',
                        'body'  => 'Aplikasi "%s" telah selesai dikembangkan dan siap untuk verifikasi UAT.',
                    ],
                ],

                // Perbaikan UAT → tim implementasi harus perbaiki
                self::STATUS_PERBAIKAN_UAT => [
                    [
                        'role'  => 'tim_implementasi_aplikasi',
                        'type'  => 'action_required',
                        'title' => 'Perbaikan UAT Diperlukan',
                        'body'  => 'Aplikasi "%s" belum memenuhi UAT dan memerlukan perbaikan sebelum dapat dilanjutkan.',
                    ],
                ],

                // Uji keamanan → tim uji harus menguji
                self::STATUS_UJI_KEAMANAN => [
                    [
                        'role'  => 'tim_uji_keamanan',
                        'type'  => 'action_required',
                        'title' => 'Uji Keamanan Diperlukan',
                        'body'  => 'Aplikasi "%s" telah lulus UAT dan siap untuk diuji keamanannya.',
                    ],
                    [
                        'role'  => 'pengelola_aplikasi',
                        'type'  => 'info',
                        'title' => 'Aplikasi Masuk Uji Keamanan',
                        'body'  => 'Aplikasi "%s" telah melewati UAT dan kini masuk tahap uji keamanan.',
                    ],
                ],

                // Perbaikan keamanan → tim implementasi harus perbaiki, pengelola perlu tahu
                self::STATUS_PERBAIKAN_KEAMANAN => [
                    [
                        'role'  => 'tim_implementasi_aplikasi',
                        'type'  => 'action_required',
                        'title' => 'Perbaikan Keamanan Diperlukan',
                        'body'  => 'Aplikasi "%s" belum lolos uji keamanan. Silakan lakukan perbaikan yang diperlukan.',
                    ],
                    [
                        'role'  => 'pengelola_aplikasi',
                        'type'  => 'info',
                        'title' => 'Hasil Uji Keamanan: Belum Lolos',
                        'body'  => 'Aplikasi "%s" belum lolos uji keamanan. Tim implementasi sedang melakukan perbaikan.',
                    ],
                ],

                // Siap deploy → devops harus deploy, pengelola perlu tahu kabar baik ini
                self::STATUS_SIAP_DEPLOY => [
                    [
                        'role'  => 'devops_developer',
                        'type'  => 'action_required',
                        'title' => 'Siap untuk Deployment',
                        'body'  => 'Aplikasi "%s" telah lolos uji keamanan dan siap untuk dideploy ke production.',
                    ],
                    [
                        'role'  => 'pengelola_aplikasi',
                        'type'  => 'info',
                        'title' => 'Aplikasi Lolos Uji Keamanan',
                        'body'  => 'Aplikasi "%s" telah lolos uji keamanan dan menunggu proses deployment oleh DevOps.',
                    ],
                ],

                // Deployed Production → pengelola dan unit kerja perlu tahu aplikasi sudah live
                self::STATUS_DEPLOYED_PRODUCTION => [
                    [
                        'role'  => 'pengelola_aplikasi',
                        'type'  => 'info',
                        'title' => 'Aplikasi Berhasil Dideploy',
                        'body'  => 'Aplikasi "%s" telah berhasil dideploy ke production dan kini aktif.',
                    ],
                ],

                self::STATUS_NONAKTIF => [
                    [
                        'role'  => 'pengelola_aplikasi',
                        'type'  => 'info',
                        'title' => 'Aplikasi Dinonaktifkan',
                        'body'  => 'Aplikasi "%s" telah ditandai nonaktif dan tidak lagi digunakan.',
                    ],
                ],

                default => [],
            };

            // ─── 2. Kirim notifikasi ke setiap role yang relevan ──────────────────────
            foreach ($recipientRoles as $entry) {
                $usersToNotify = \App\Models\User::where('role', $entry['role'])->get();
                foreach ($usersToNotify as $user) {
                    AppNotification::create([
                        'user_id'     => $user->getKey(),
                        'aplikasi_id' => $model->getKey(),
                        'type'        => $entry['type'],
                        'title'       => $entry['title'],
                        'body'        => sprintf($entry['body'], $appName),
                    ]);
                }
            }

            // ─── 3. Notifikasi khusus untuk Unit Kerja (pemilik aplikasi) ─────────────
            // Hanya untuk status yang berdampak langsung: keputusan penting dan final.
            if ($model->created_by) {
                $unitKerjaEntry = match ($newStatus) {
                    self::STATUS_PERLU_PERBAIKAN => [
                        'type'  => 'action_required',
                        'title' => 'Pengajuan Perlu Perbaikan',
                        'body'  => 'Pengajuan aplikasi "%s" memerlukan perbaikan sebelum dapat diproses lebih lanjut.',
                    ],
                    self::STATUS_DITOLAK => [
                        'type'  => 'info',
                        'title' => 'Pengajuan Ditolak',
                        'body'  => 'Pengajuan aplikasi "%s" telah ditolak. Silakan hubungi pengelola untuk informasi lebih lanjut.',
                    ],
                    self::STATUS_TERVERIFIKASI => [
                        'type'  => 'info',
                        'title' => 'Pengajuan Terverifikasi',
                        'body'  => 'Pengajuan aplikasi "%s" telah diverifikasi dan akan segera dievaluasi kelayakannya.',
                    ],
                    self::STATUS_LAYAK => [
                        'type'  => 'info',
                        'title' => 'Aplikasi Dinyatakan Layak',
                        'body'  => 'Aplikasi "%s" dinyatakan layak dan akan memasuki tahap analisa desain.',
                    ],
                    self::STATUS_TIDAK_LAYAK => [
                        'type'  => 'info',
                        'title' => 'Aplikasi Tidak Layak',
                        'body'  => 'Aplikasi "%s" dinyatakan tidak layak setelah evaluasi studi kelayakan.',
                    ],
                    self::STATUS_DEPLOYED_PRODUCTION => [
                        'type'  => 'info',
                        'title' => 'Aplikasi Anda Sudah Live!',
                        'body'  => 'Selamat! Aplikasi "%s" telah berhasil dideploy dan kini dapat diakses pengguna.',
                    ],
                    self::STATUS_NONAKTIF => [
                        'type'  => 'info',
                        'title' => 'Aplikasi Dinonaktifkan',
                        'body'  => 'Aplikasi "%s" telah ditandai nonaktif dan tidak lagi digunakan.',
                    ],
                    default => null,
                };

                if ($unitKerjaEntry !== null) {
                    AppNotification::create([
                        'user_id'     => $model->created_by,
                        'aplikasi_id' => $model->getKey(),
                        'type'        => $unitKerjaEntry['type'],
                        'title'       => $unitKerjaEntry['title'],
                        'body'        => sprintf($unitKerjaEntry['body'], $appName),
                    ]);
                }
            }
        });
    }

    /**
     * Get the analisa desains for the aplikasi.
     */
    public function analisaDesains(): HasMany
    {
        return $this->hasMany(AnalisaDesain::class);
    }

    /**
     * Get the proyeks for the aplikasi.
     */
    public function proyeks(): HasMany
    {
        return $this->hasMany(Proyek::class);
    }

    /**
     * Get the database configs for the aplikasi.
     */
    public function databaseConfigs(): HasMany
    {
        return $this->hasMany(DatabaseConfig::class);
    }

    /**
     * Get the object storage configs for the aplikasi.
     */
    public function objectStorageConfigs(): HasMany
    {
        return $this->hasMany(ObjectStorageConfig::class);
    }

    /**
     * Get the api gateway configs for the aplikasi.
     */
    public function apiGatewayConfigs(): HasMany
    {
        return $this->hasMany(ApiGatewayConfig::class);
    }

    /**
     * Get the environment configs for the aplikasi.
     */
    public function environmentConfigs(): HasMany
    {
        return $this->hasMany(EnvironmentConfig::class);
    }

    /**
     * Get the devops configs for the aplikasi.
     */
    public function devopsConfigs(): HasMany
    {
        return $this->hasMany(DevopsConfig::class);
    }

    /**
     * Get the frontend configs for the aplikasi.
     */
    public function frontendConfigs(): HasMany
    {
        return $this->hasMany(FrontendConfig::class);
    }

    /**
     * Get the backend configs for the aplikasi.
     */
    public function backendConfigs(): HasMany
    {
        return $this->hasMany(BackendConfig::class);
    }

    /**
     * Get the user who created this aplikasi.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this aplikasi.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the user who last performed security testing.
     */
    public function securityTester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'security_tested_by');
    }

    /**
     * Get the user who deployed to staging.
     */
    public function stagingDeployer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deployed_staging_by');
    }

    /**
     * Get the user who deployed to production.
     */
    public function productionDeployer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deployed_production_by');
    }

    /**
     * Dokumen generik (formulir, UAT, laporan, BA, rilis, …).
     */
    public function documents(): HasMany
    {
        return $this->hasMany(AplikasiDocument::class, 'aplikasi_id');
    }

    /**
     * Checklist pengelola aplikasi (mis. studi kelayakan).
     */
    public function checklists(): HasMany
    {
        return $this->hasMany(AplikasiChecklist::class, 'aplikasi_id');
    }

    /**
     * Status history.
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(AplikasiStatusHistory::class, 'aplikasi_id');
    }

    /**
     * Catatan perbaikan / riwayat komunikasi.
     */
    public function notes(): HasMany
    {
        return $this->hasMany(AplikasiNote::class, 'aplikasi_id');
    }

    /**
     * Request for Changes (RFC).
     */
    public function rfcs(): HasMany
    {
        return $this->hasMany(Rfc::class, 'aplikasi_id');
    }

    /**
     * Notifikasi yang terkait dengan aplikasi ini.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(AppNotification::class, 'aplikasi_id');
    }
}
