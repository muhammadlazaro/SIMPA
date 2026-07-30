<?php

namespace App\Http\Controllers\Traits;

use App\Enums\AplikasiJenisDokumen;
use App\Http\Helpers\ApiResponse;
use App\Models\Aplikasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

trait HandlesAplikasiTransitions
{
    private const TRANSITION_ACCESS_DENIED_MESSAGE = 'Akses ditolak.';

    private function recordStatusHistory(Aplikasi $aplikasi, string $aksi, string $statusBaru, ?string $catatan, $user): void
    {
        $statusSebelumnya = $aplikasi->status;
        $aplikasi->status = $statusBaru;
        $aplikasi->save();
        \Illuminate\Support\Facades\Cache::forget('aplikasi:stats:v1');

        $aplikasi->statusHistories()->create([
            'status_sebelumnya' => $statusSebelumnya,
            'status_baru' => $statusBaru,
            'aksi' => $aksi,
            'catatan' => $catatan,
            'changed_by' => $user ? $user->getKey() : null,
        ]);
    }

    public function verifikasiPengajuan(Request $request, Aplikasi $aplikasi): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'pengelola_aplikasi') {
            return ApiResponse::forbidden(self::TRANSITION_ACCESS_DENIED_MESSAGE);
        }
        if ($aplikasi->status !== Aplikasi::STATUS_DIAJUKAN) {
            return ApiResponse::error('Aplikasi tidak dalam status diajukan.');
        }

        $request->validate([
            'status_target' => 'required|string|in:'.Aplikasi::STATUS_TERVERIFIKASI.','.Aplikasi::STATUS_PERLU_PERBAIKAN.','.Aplikasi::STATUS_DITOLAK,
            'catatan' => 'required|string',
        ]);

        $statusBaru = $request->input('status_target');
        if ($statusBaru === Aplikasi::STATUS_TERVERIFIKASI && ! $this->hasActiveDocument($aplikasi, AplikasiJenisDokumen::FormulirPengajuan)) {
            return ApiResponse::error('Formulir pengajuan wajib diunggah sebelum pengajuan disetujui.', null, 422);
        }

        $this->recordStatusHistory($aplikasi, 'Verifikasi Pengajuan', $statusBaru, $request->input('catatan'), $user);

        return ApiResponse::success(['status' => $statusBaru], 'Status pengajuan diperbarui.');
    }

    public function perbaikanPengajuan(Request $request, Aplikasi $aplikasi): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'unit_kerja') {
            return ApiResponse::forbidden(self::TRANSITION_ACCESS_DENIED_MESSAGE);
        }
        if ((int) $aplikasi->getAttribute('created_by') !== (int) $user->getKey()) {
            return ApiResponse::forbidden('Anda hanya dapat memperbaiki pengajuan milik sendiri.');
        }
        if ($aplikasi->status !== Aplikasi::STATUS_PERLU_PERBAIKAN) {
            return ApiResponse::error('Aplikasi tidak dalam status perlu perbaikan pengajuan.');
        }

        $request->validate(['catatan' => 'nullable|string']);

        $this->recordStatusHistory($aplikasi, 'Kirim Ulang Pengajuan', Aplikasi::STATUS_DIAJUKAN, $request->input('catatan', 'Pengajuan telah diperbaiki'), $user);

        return ApiResponse::success(['status' => Aplikasi::STATUS_DIAJUKAN], 'Pengajuan dikirim ulang.');
    }

    public function studiKelayakan(Request $request, Aplikasi $aplikasi): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'analis_desain') {
            return ApiResponse::forbidden(self::TRANSITION_ACCESS_DENIED_MESSAGE);
        }
        if ($aplikasi->status !== Aplikasi::STATUS_ANALISA_DESAIN) {
            return ApiResponse::error('Analisis desain belum dimulai.');
        }

        $request->validate(['is_layak' => 'required|boolean', 'catatan' => 'required|string']);

        $isLayak = $request->boolean('is_layak');
        if ($isLayak && ! $this->hasActiveDocument($aplikasi, AplikasiJenisDokumen::LaporanAnalisaDesain)) {
            return ApiResponse::error('Laporan analisis desain wajib diunggah sebelum aplikasi dinyatakan layak.', null, 422);
        }

        $statusBaru = $isLayak ? Aplikasi::STATUS_LAYAK : Aplikasi::STATUS_TIDAK_LAYAK;
        $this->recordStatusHistory($aplikasi, 'Studi Kelayakan', $statusBaru, $request->input('catatan'), $user);

        return ApiResponse::success(['status' => $statusBaru], 'Hasil studi kelayakan disimpan.');
    }

    public function mulaiAnalisaDesain(Request $request, Aplikasi $aplikasi): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'analis_desain') {
            return ApiResponse::forbidden(self::TRANSITION_ACCESS_DENIED_MESSAGE);
        }
        if ($aplikasi->status !== Aplikasi::STATUS_TERVERIFIKASI && $aplikasi->status !== Aplikasi::STATUS_ANALISA_DESAIN) {
            return ApiResponse::error('Status tidak sesuai.');
        }

        if ($aplikasi->status === Aplikasi::STATUS_TERVERIFIKASI) {
            $this->recordStatusHistory($aplikasi, 'Mulai Analisa Desain', Aplikasi::STATUS_ANALISA_DESAIN, 'Analis mulai bekerja', $user);
        }

        return ApiResponse::success(['status' => Aplikasi::STATUS_ANALISA_DESAIN]);
    }

    public function mulaiPengembangan(Request $request, Aplikasi $aplikasi): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'tim_implementasi_aplikasi') {
            return ApiResponse::forbidden(self::TRANSITION_ACCESS_DENIED_MESSAGE);
        }
        if ($aplikasi->status !== Aplikasi::STATUS_LAYAK) {
            return ApiResponse::error('Status tidak sesuai.');
        }
        if (! $this->hasActiveDocument($aplikasi, AplikasiJenisDokumen::LaporanAnalisaDesain)) {
            return ApiResponse::error('Laporan analisa desain wajib diunggah sebelum pengembangan dimulai.', null, 422);
        }

        $request->validate(['catatan' => 'nullable|string']);
        $this->recordStatusHistory($aplikasi, 'Mulai Pengembangan', Aplikasi::STATUS_PENGEMBANGAN, $request->input('catatan', 'Mulai proses coding'), $user);

        return ApiResponse::success(['status' => Aplikasi::STATUS_PENGEMBANGAN], 'Pengembangan dimulai.');
    }

    public function siapUat(Request $request, Aplikasi $aplikasi): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'tim_implementasi_aplikasi') {
            return ApiResponse::forbidden(self::TRANSITION_ACCESS_DENIED_MESSAGE);
        }
        if ($aplikasi->status !== Aplikasi::STATUS_PENGEMBANGAN) {
            return ApiResponse::error('Aplikasi belum dalam pengembangan.');
        }
        if (! $this->implementationChecklistComplete($aplikasi)) {
            return ApiResponse::error('Checklist implementasi wajib diselesaikan sebelum aplikasi ditandai siap UAT.', null, 422);
        }
        if (! $this->hasActiveDocument($aplikasi, AplikasiJenisDokumen::TemplateUat)) {
            return ApiResponse::error('Template UAT wajib diunggah sebelum aplikasi ditandai siap UAT.', null, 422);
        }
        if (! $this->hasActiveDocument($aplikasi, AplikasiJenisDokumen::PetunjukAplikasi)) {
            return ApiResponse::error('Petunjuk aplikasi wajib diunggah sebelum aplikasi ditandai siap UAT.', null, 422);
        }

        $request->validate(['catatan' => 'nullable|string']);
        $this->recordStatusHistory($aplikasi, 'Aplikasi Siap UAT', Aplikasi::STATUS_UAT, $request->input('catatan', 'Template dan aplikasi siap diuji (UAT)'), $user);

        return ApiResponse::success(['status' => Aplikasi::STATUS_UAT], 'Aplikasi siap untuk UAT.');
    }

    public function verifikasiUat(Request $request, Aplikasi $aplikasi): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'pengelola_aplikasi') {
            return ApiResponse::forbidden(self::TRANSITION_ACCESS_DENIED_MESSAGE);
        }
        if ($aplikasi->status !== Aplikasi::STATUS_UAT) {
            return ApiResponse::error('Status tidak sesuai untuk verifikasi UAT.');
        }

        $request->validate(['is_sesuai' => 'required|boolean', 'catatan' => 'required|string']);
        if ($request->boolean('is_sesuai') && ! $this->hasActiveDocument($aplikasi, AplikasiJenisDokumen::Uat)) {
            return ApiResponse::error('Dokumen UAT wajib diunggah sebelum UAT dinyatakan sesuai.', null, 422);
        }

        $statusBaru = $request->boolean('is_sesuai') ? Aplikasi::STATUS_UJI_KEAMANAN : Aplikasi::STATUS_PERBAIKAN_UAT;
        $this->recordStatusHistory($aplikasi, 'Verifikasi UAT', $statusBaru, $request->input('catatan'), $user);

        return ApiResponse::success(['status' => $statusBaru], 'Verifikasi UAT disimpan.');
    }

    public function selesaiPerbaikanUat(Request $request, Aplikasi $aplikasi): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'tim_implementasi_aplikasi') {
            return ApiResponse::forbidden(self::TRANSITION_ACCESS_DENIED_MESSAGE);
        }
        if ($aplikasi->status !== Aplikasi::STATUS_PERBAIKAN_UAT) {
            return ApiResponse::error('Status bukan perbaikan UAT.');
        }

        $request->validate(['catatan' => 'required|string']);
        $this->recordStatusHistory($aplikasi, 'Perbaikan UAT Selesai', Aplikasi::STATUS_UAT, $request->input('catatan'), $user);

        return ApiResponse::success(['status' => Aplikasi::STATUS_UAT], 'Perbaikan UAT selesai disubmit.');
    }

    public function hasilUjiKeamanan(Request $request, Aplikasi $aplikasi): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'tim_uji_keamanan') {
            return ApiResponse::forbidden(self::TRANSITION_ACCESS_DENIED_MESSAGE);
        }

        $payload = $request->validate([
            'is_lolos' => ['required', 'boolean'],
            'catatan' => ['required', 'string', 'max:4000'],
            'security_test_notes' => ['nullable', 'string', 'max:4000'],
            'note' => ['nullable', 'string', 'max:4000'],
        ]);

        return DB::transaction(function () use ($aplikasi, $payload, $user): JsonResponse {
            $lockedAplikasi = Aplikasi::query()
                ->whereKey($aplikasi->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $lockedAplikasi->status !== Aplikasi::STATUS_UJI_KEAMANAN
                || $lockedAplikasi->security_test_passed !== null
            ) {
                return ApiResponse::error(
                    'Keputusan uji keamanan sudah disimpan dan tidak dapat dikirim ulang.',
                    null,
                    422
                );
            }

            if (! $this->hasActiveDocument($lockedAplikasi, AplikasiJenisDokumen::LaporanUjiKeamanan)) {
                return ApiResponse::error(
                    'Laporan uji keamanan wajib diunggah sebelum hasil uji keamanan disimpan.',
                    null,
                    422
                );
            }

            $isLolos = (bool) $payload['is_lolos'];
            $summary = trim((string) ($payload['security_test_notes'] ?? $payload['catatan']));
            $improvementNote = trim((string) ($payload['note'] ?? ''));
            $statusBaru = $isLolos
                ? Aplikasi::STATUS_SIAP_DEPLOY
                : Aplikasi::STATUS_PERBAIKAN_KEAMANAN;

            $lockedAplikasi->security_test_passed = $isLolos;
            $lockedAplikasi->security_test_notes = $summary;
            $lockedAplikasi->security_tested_by = $user->getKey();
            $lockedAplikasi->security_tested_at = now();

            $historyText = "Ringkasan Hasil Uji:\n{$summary}";
            if ($improvementNote !== '') {
                $historyText .= "\n\nCatatan Perbaikan:\n{$improvementNote}";
            }

            $lockedAplikasi->notes()->create([
                'note_type' => 'uji_keamanan',
                'body' => $historyText,
                'created_by' => $user->getKey(),
            ]);

            $this->recordStatusHistory(
                $lockedAplikasi,
                'Hasil Uji Keamanan',
                $statusBaru,
                $payload['catatan'],
                $user
            );

            return ApiResponse::success(
                ['status' => $statusBaru],
                'Hasil Uji Keamanan disimpan.'
            );
        });
    }

    public function selesaiPerbaikanKeamanan(Request $request, Aplikasi $aplikasi): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'tim_implementasi_aplikasi') {
            return ApiResponse::forbidden(self::TRANSITION_ACCESS_DENIED_MESSAGE);
        }
        if ($aplikasi->status !== Aplikasi::STATUS_PERBAIKAN_KEAMANAN) {
            return ApiResponse::error('Status bukan perbaikan keamanan.');
        }

        $request->validate(['catatan' => 'required|string']);
        $aplikasi->security_test_passed = null;
        $aplikasi->security_test_notes = null;
        $aplikasi->security_tested_by = null;
        $aplikasi->security_tested_at = null;
        $this->recordStatusHistory($aplikasi, 'Perbaikan Keamanan Selesai', Aplikasi::STATUS_UJI_KEAMANAN, $request->input('catatan'), $user);

        return ApiResponse::success(['status' => Aplikasi::STATUS_UJI_KEAMANAN], 'Perbaikan Keamanan selesai disubmit.');
    }

    public function deployAplikasi(Request $request, Aplikasi $aplikasi): JsonResponse
    {
        \Illuminate\Support\Facades\Log::notice('Legacy deployment endpoint called', [
            'aplikasi_id' => $aplikasi->getKey(),
            'user_id' => $request->user()?->getKey(),
        ]);

        return ApiResponse::error(
            'Endpoint deploy lama dinonaktifkan. Gunakan deployment-status dengan environment staging atau production.',
            null,
            410
        );
    }

    public function statusHistories(Request $request, Aplikasi $aplikasi): JsonResponse
    {
        if (! $this->canAccessWorkflowAplikasi($aplikasi, $request->user())) {
            return ApiResponse::forbidden(self::TRANSITION_ACCESS_DENIED_MESSAGE);
        }

        $histories = $aplikasi->statusHistories()->with('changer:id,name')->orderBy('id', 'desc')->get();

        return ApiResponse::success(['histories' => $histories]);
    }

    private function hasActiveDocument(Aplikasi $aplikasi, AplikasiJenisDokumen $type): bool
    {
        return $aplikasi->documents()
            ->where('document_type', $type->value)
            ->where('status', 'active')
            ->exists();
    }

    private function implementationChecklistComplete(Aplikasi $aplikasi): bool
    {
        $query = $aplikasi->checklists()->where('category', 'implementation_progress');

        return (clone $query)->exists()
            && ! (clone $query)->where('item_status', '!=', 'done')->exists();
    }
}
