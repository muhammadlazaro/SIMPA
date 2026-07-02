<?php

namespace App\Enums;

/**
 * Jenis dokumen generik terikat aplikasi (Epic A — fondasi).
 * Diperluas per fitur (UAT, BA, dll.) tanpa mengubah kolom aplikasis.
 */
enum AplikasiJenisDokumen: string
{
    case FormulirPengajuan = 'formulir_pengajuan';
    case LampiranUmum = 'lampiran_umum';
    case LaporanAnalisaDesain = 'laporan_analisa_desain';
    case TemplateUat = 'template_uat';
    case PetunjukAplikasi = 'petunjuk_aplikasi';
    case Uat = 'uat';
    case BeritaAcara = 'berita_acara';
    case Rilis = 'rilis';
    case LaporanUjiKeamanan = 'laporan_uji_keamanan';
    case Lainnya = 'lainnya';
}
