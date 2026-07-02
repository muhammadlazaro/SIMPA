<?php

namespace App\Enums;

/**
 * Status siklus hidup aplikasi.
 *
 * Semua status harus direferensikan melalui enum ini, bukan string literal,
 * agar perubahan status terpusat di satu tempat.
 */
enum AplikasiStatus: string
{
    case DIAJUKAN = 'diajukan';
    case PERLU_PERBAIKAN_PENGAJUAN = 'perlu_perbaikan_pengajuan';
    case DITOLAK = 'ditolak';
    case TERVERIFIKASI = 'terverifikasi';
    case LAYAK = 'layak';
    case TIDAK_LAYAK = 'tidak_layak';
    case ANALISA_DESAIN = 'analisa_desain';
    case PENGEMBANGAN = 'pengembangan';
    case UAT = 'uat';
    case PERBAIKAN_UAT = 'perbaikan_uat';
    case UJI_KEAMANAN = 'uji_keamanan';
    case PERBAIKAN_KEAMANAN = 'perbaikan_keamanan';
    case SIAP_DEPLOY = 'siap_deploy';
    case DEPLOYED_STAGING = 'deployed_staging';
    case DEPLOYED_PRODUCTION = 'deployed_production';
    case NONAKTIF = 'nonaktif';

    /**
     * Semua status value sebagai array string (berguna untuk validation rules).
     *
     * @return list<string>
     */
    public static function allValues(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }

    /**
     * Status yang termasuk fase "development" (belum operasional).
     *
     * @return list<string>
     */
    public static function developmentValues(): array
    {
        return [
            self::DIAJUKAN->value,
            self::PERLU_PERBAIKAN_PENGAJUAN->value,
            self::TERVERIFIKASI->value,
            self::LAYAK->value,
            self::ANALISA_DESAIN->value,
            self::PENGEMBANGAN->value,
            self::UAT->value,
            self::PERBAIKAN_UAT->value,
            self::UJI_KEAMANAN->value,
            self::PERBAIKAN_KEAMANAN->value,
            self::SIAP_DEPLOY->value,
            self::DEPLOYED_STAGING->value,
        ];
    }

    /**
     * Status yang termasuk fase "operational" (sudah aktif / deployed).
     *
     * @return list<string>
     */
    public static function operationalValues(): array
    {
        return [
            self::DEPLOYED_PRODUCTION->value,
        ];
    }

    /**
     * Status aplikasi operasional yang sudah tidak digunakan.
     *
     * @return list<string>
     */
    public static function inactiveValues(): array
    {
        return [
            self::NONAKTIF->value,
        ];
    }

    /**
     * Status pengajuan/proses yang dihentikan sebelum production.
     *
     * @return list<string>
     */
    public static function stoppedValues(): array
    {
        return [
            self::DITOLAK->value,
            self::TIDAK_LAYAK->value,
        ];
    }

    /**
     * String validasi yang bisa dipakai di rule 'in:...'
     */
    public static function validationRule(): string
    {
        return 'in:' . implode(',', self::allValues());
    }
}
