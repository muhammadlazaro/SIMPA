<?php

namespace App\Support;

use App\Enums\AplikasiJenisDokumen;
use App\Models\Aplikasi;
use App\Models\User;

/**
 * Aturan akses dokumen generik per aplikasi (Epic A4).
 */
final class AplikasiDocumentAccess
{
    public static function canView(User $user, Aplikasi $aplikasi): bool
    {
        $isImplementationRole = $user->isTimImplementasiAplikasi() || $user->isDevops();
        $isUnitOwner = $user->isUnitKerja()
            && (int) $aplikasi->getAttribute('created_by') === (int) $user->getKey();

        return $user->isPengelolaAplikasi()
            || $isImplementationRole
            || $user->isAnalisDesain()
            || $user->isTimUjiKeamanan()
            || $isUnitOwner;
    }

    public static function canUploadType(User $user, AplikasiJenisDokumen $type): bool
    {
        $allowedForAnalis = $type === AplikasiJenisDokumen::LaporanAnalisaDesain;
        $allowedForImplementation = in_array($type, [
            AplikasiJenisDokumen::TemplateUat,
            AplikasiJenisDokumen::PetunjukAplikasi,
        ], true);
        $allowedForUnit = in_array($type, [
            AplikasiJenisDokumen::FormulirPengajuan,
            AplikasiJenisDokumen::LampiranUmum,
            AplikasiJenisDokumen::Uat,
            AplikasiJenisDokumen::BeritaAcara,
            AplikasiJenisDokumen::Rilis,
            AplikasiJenisDokumen::Lainnya,
        ], true);

        $allowedForSecurity = $type === AplikasiJenisDokumen::LaporanUjiKeamanan;

        return $user->isPengelolaAplikasi()
            || ($user->isAnalisDesain() && $allowedForAnalis)
            || ($user->isUnitKerja() && $allowedForUnit)
            || ($user->isTimImplementasiAplikasi() && $allowedForImplementation)
            || ($user->isTimUjiKeamanan() && $allowedForSecurity);
    }

    public static function canUploadTypeForStatus(AplikasiJenisDokumen $type, string $status): bool
    {
        $allowedStatuses = match ($type) {
            AplikasiJenisDokumen::FormulirPengajuan,
            AplikasiJenisDokumen::LampiranUmum => [
                Aplikasi::STATUS_DIAJUKAN,
                Aplikasi::STATUS_PERLU_PERBAIKAN,
            ],
            AplikasiJenisDokumen::LaporanAnalisaDesain => [
                Aplikasi::STATUS_ANALISA_DESAIN,
            ],
            AplikasiJenisDokumen::TemplateUat,
            AplikasiJenisDokumen::PetunjukAplikasi => [
                Aplikasi::STATUS_PENGEMBANGAN,
                Aplikasi::STATUS_PERBAIKAN_UAT,
            ],
            AplikasiJenisDokumen::Uat => [
                Aplikasi::STATUS_UAT,
                Aplikasi::STATUS_PERBAIKAN_UAT,
            ],
            AplikasiJenisDokumen::LaporanUjiKeamanan => [
                Aplikasi::STATUS_UJI_KEAMANAN,
                Aplikasi::STATUS_PERBAIKAN_KEAMANAN,
            ],
            AplikasiJenisDokumen::Rilis,
            AplikasiJenisDokumen::BeritaAcara => [
                Aplikasi::STATUS_DEPLOYED_PRODUCTION,
            ],
            AplikasiJenisDokumen::Lainnya => [],
        };

        return in_array($status, $allowedStatuses, true);
    }
}
