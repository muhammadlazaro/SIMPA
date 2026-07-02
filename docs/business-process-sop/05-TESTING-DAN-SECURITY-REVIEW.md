# Tahap 5 - Testing dan Security Review

## Tujuan
Memastikan kualitas fungsional, keamanan, dan kesiapan rilis.

## Trigger Masuk
MVP/ruang lingkup release siap di staging.

## Input Wajib
- Build kandidat rilis
- Test scenario/acceptance criteria
- Checklist security review

## Aktivitas Inti
1. Functional test dan regression test.
2. UAT oleh perwakilan bisnis.
3. Security review (minimum baseline): validasi input, auth, akses per role, audit log.

## Output Wajib
- Laporan hasil test dan defect list
- UAT sign-off (diterima / catatan perbaikan)
- Security review result (pass/fail + temuan)

## Gate ke Tahap Berikut
- Defect severity tinggi = 0
- UAT sign-off disetujui
- Security review status = pass atau mitigasi disetujui manajemen risiko

## SLA
- Siklus test + security review: maksimal 5 hari kerja per kandidat rilis.

## RACI Ringkas
- R: Tim Implementasi, Tim Uji Keamanan, Unit Kerja (UAT)
- A: Pengelola Aplikasi
- C: Analis Desain
- I: Operasional/Support

## Risiko dan Mitigasi
- Risiko: UAT formal tidak terjadi.
- Mitigasi: Jadikan UAT sign-off sebagai gate wajib rilis.

## Bukti Audit
- Test report
- UAT sign-off
- Security review report
- Status gate Testing -> Go-Live