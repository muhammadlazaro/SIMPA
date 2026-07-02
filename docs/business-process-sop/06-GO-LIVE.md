# Tahap 6 - Go-Live

## Tujuan
Merilis aplikasi ke production secara terkendali dan aman.

## Trigger Masuk
Testing dan security review dinyatakan lulus gate.

## Input Wajib
- Persetujuan rilis
- Runbook cutover
- Rollback plan tervalidasi

## Aktivitas Inti
1. Freeze perubahan release scope.
2. Eksekusi deploy sesuai runbook.
3. Verifikasi smoke test production dan komunikasi hasil rilis.

## Output Wajib
- Release berhasil di production
- Bukti smoke test production
- Komunikasi go-live ke stakeholder

## Gate ke Tahap Berikut
- Smoke test pass
- Tidak ada insiden blocker pada jam pertama
- Monitoring dasar berjalan

## SLA
- Window go-live ditetapkan sebelum hari H; verifikasi awal maksimal 2 jam setelah deploy.

## RACI Ringkas
- R: Tim Implementasi, DevOps
- A: Pengelola Aplikasi
- C: Tim Uji Keamanan, Unit Kerja, Operasional/Support
- I: Stakeholder terkait

## Risiko dan Mitigasi
- Risiko: Downtime tak terencana.
- Mitigasi: Rollback criteria jelas dan on-call PIC standby.

## Bukti Audit
- Runbook eksekusi
- Log deploy
- Hasil smoke test
- Status gate Go-Live -> Hypercare