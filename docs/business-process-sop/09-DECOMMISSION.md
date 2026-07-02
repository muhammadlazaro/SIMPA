# Tahap 9 - Decommission (Pensiun Aplikasi)

## Tujuan
Menonaktifkan layanan secara aman, patuh regulasi, dan tanpa mengganggu proses bisnis.

## Trigger Masuk
Aplikasi diganti, tidak lagi relevan, atau biaya/risiko operasional tidak layak.

## Input Wajib
- Keputusan resmi decommission
- Rencana migrasi data/layanan
- Persyaratan retensi data dan kepatuhan

## Aktivitas Inti
1. Identifikasi dampak ke proses bisnis dan sistem tergantung.
2. Migrasi/arsip data sesuai kebijakan retensi.
3. Lakukan cut-off layanan dan komunikasi penghentian.

## Output Wajib
- Layanan non-aktif dengan dampak terkelola
- Arsip/migrasi data tervalidasi
- Dokumentasi penutupan layanan

## Gate Keluar
- Semua dependensi kritis sudah dimigrasi/diterminasi aman
- Kepatuhan retensi data terpenuhi
- Stakeholder menyetujui penutupan

## SLA
- Menyesuaikan tingkat kompleksitas; wajib ada target tanggal cut-off dan checklist progres mingguan.

## RACI Ringkas
- R: Pengelola Aplikasi, Operasional/Support
- A: Pengelola Aplikasi
- C: Unit Kerja, Tim Implementasi, Tim Keamanan/Kepatuhan
- I: Stakeholder terkait

## Risiko dan Mitigasi
- Risiko: Data penting hilang saat decommission.
- Mitigasi: Backup terverifikasi, uji restore, dan approval tertulis sebelum cut-off.

## Bukti Audit
- Persetujuan decommission
- Bukti migrasi/arsip data
- Berita acara penutupan layanan