// Mapping status aplikasi ke CSS badge class.
export const STATUS_BADGE_CLASS = {
  'diajukan': 'badge-warning',
  'perlu_perbaikan_pengajuan': 'badge-warning',
  'ditolak': 'badge-danger',
  'terverifikasi': 'badge-info',
  'layak': 'badge-info',
  'tidak_layak': 'badge-danger',
  'analisa_desain': 'badge-info',
  'pengembangan': 'badge-warning',
  'uat': 'badge-info',
  'perbaikan_uat': 'badge-warning',
  'uji_keamanan': 'badge-info',
  'perbaikan_keamanan': 'badge-warning',
  'siap_deploy': 'badge-success',
  'deployed_staging': 'badge-warning',
  'deployed_production': 'badge-success',
  'nonaktif': 'badge-danger',
};

// Mapping status RFC ke CSS badge class.
export const RFC_STATUS_BADGE_CLASS = {
  'Diajukan': 'badge-warning',
  'Analisa Desain': 'badge-info',
  'Dev-Staging': 'badge-warning',
  'Production': 'badge-success',
  'UAT': 'badge-info',
};

// Mapping HTTP method ke CSS class untuk transaction badges.
export const HTTP_METHOD_CLASS = {
  get: 'get',
  post: 'post',
  put: 'put',
  delete: 'delete',
};

export function getStatusBadgeClass(status, fallback = 'badge-warning') {
  return STATUS_BADGE_CLASS[status] ?? fallback;
}

export function getRfcStatusBadgeClass(status) {
  return RFC_STATUS_BADGE_CLASS[status] ?? 'badge-warning';
}

export function getHttpMethodClass(method) {
  return HTTP_METHOD_CLASS[method?.toLowerCase()] ?? 'default';
}

export const STATUS_LABELS = {
  'diajukan': 'Diajukan - Menunggu Verifikasi',
  'perlu_perbaikan_pengajuan': 'Perlu Perbaikan - Silakan revisi',
  'ditolak': 'Ditolak - Tidak Lolos',
  'terverifikasi': 'Terverifikasi - Dilanjutkan',
  'layak': 'Lolos Kelayakan - Siap Dianalisis',
  'tidak_layak': 'Tidak Layak - Dihentikan',
  'analisa_desain': 'Analisis & Desain - Sedang Diproses',
  'pengembangan': 'Pengembangan - Dalam Pengerjaan',
  'uat': 'UAT - Pengujian',
  'perbaikan_uat': 'Perbaikan UAT - Perlu Revisi',
  'uji_keamanan': 'Uji Keamanan - Sedang Dicek',
  'perbaikan_keamanan': 'Perbaikan Keamanan - Revisi',
  'siap_deploy': 'Siap Deploy - Menunggu Release',
  'deployed_staging': 'Deployed Staging - Testing',
  'deployed_production': 'Deployed Production - Live',
  'nonaktif': 'Nonaktif - Tidak Digunakan',
};

export function getStatusLabel(status) {
  return STATUS_LABELS[status] ?? status;
}

export const SHORT_STATUS_LABELS = {
  'diajukan': 'Diajukan',
  'perlu_perbaikan_pengajuan': 'Perlu Perbaikan',
  'ditolak': 'Ditolak',
  'terverifikasi': 'Terverifikasi',
  'layak': 'Layak',
  'tidak_layak': 'Tidak Layak',
  'analisa_desain': 'Analisis Desain',
  'pengembangan': 'Pengembangan',
  'uat': 'UAT',
  'perbaikan_uat': 'Perbaikan UAT',
  'uji_keamanan': 'Uji Keamanan',
  'perbaikan_keamanan': 'Perbaikan Keamanan',
  'siap_deploy': 'Siap Deploy',
  'deployed_staging': 'Deployed Staging',
  'deployed_production': 'Deployed Production',
  'nonaktif': 'Nonaktif',
};

export function getShortStatusLabel(status) {
  return SHORT_STATUS_LABELS[status] ?? status ?? '-';
}

export function getStatusTooltip(status) {
  const map = {
    'diajukan': 'Pengajuan Anda sedang menunggu verifikasi oleh tim pengelola.',
    'layak': 'Aplikasi telah lolos kelayakan, tim analis akan mengerjakan desain selanjutnya.',
    'analisa_desain': 'Tim analis & desain sedang memproses detail teknis.',
    'pengembangan': 'Tim implementasi sedang mengembangkan aplikasi.',
    'uat': 'Aplikasi berada dalam fase User Acceptance Testing.',
    'uji_keamanan': 'Tim keamanan sedang melakukan audit.',
    'siap_deploy': 'Aplikasi siap dipublikasikan ke produksi.',
    'deployed_staging': 'Aplikasi sudah di-deploy ke lingkungan staging untuk testing.',
    'deployed_production': 'Aplikasi sudah di-deploy ke produksi dan aktif untuk pengguna.',
    'nonaktif': 'Aplikasi sudah ditandai tidak aktif dan tidak lagi digunakan.',
  };
  return map[status] ?? '';
}
