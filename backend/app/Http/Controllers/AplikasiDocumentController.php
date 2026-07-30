<?php

namespace App\Http\Controllers;

use App\Enums\AplikasiJenisDokumen;
use App\Enums\UserRole;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\StoreAplikasiDocumentRequest;
use App\Models\Aplikasi;
use App\Models\AplikasiDocument;
use App\Models\AppNotification;
use App\Models\User;
use App\Support\AplikasiDocumentAccess;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class AplikasiDocumentController extends Controller
{
    private const ACCESS_DENIED_MESSAGE = 'Akses ditolak.';

    private const PRIVATE_DISK = 'local';

    public function index(Request $request, Aplikasi $aplikasi): JsonResponse
    {
        $user = $request->user();
        if (! AplikasiDocumentAccess::canView($user, $aplikasi)) {
            return ApiResponse::forbidden(self::ACCESS_DENIED_MESSAGE);
        }

        $documents = $aplikasi->documents()
            ->with('uploader:id,name')
            ->orderByDesc('id')
            ->get()
            ->map(fn (AplikasiDocument $doc) => $this->documentPayload($aplikasi, $doc));

        return ApiResponse::success(['documents' => $documents]);
    }

    public function store(StoreAplikasiDocumentRequest $request, Aplikasi $aplikasi): JsonResponse
    {
        $user = $request->user();
        if (! AplikasiDocumentAccess::canView($user, $aplikasi)) {
            return ApiResponse::forbidden(self::ACCESS_DENIED_MESSAGE);
        }

        $aplikasiId = (int) $aplikasi->getKey();

        /** @var AplikasiJenisDokumen $jenis */
        $jenis = $request->enum('document_type', AplikasiJenisDokumen::class);
        if (! AplikasiDocumentAccess::canUploadType($user, $jenis)) {
            return ApiResponse::forbidden('Anda tidak dapat mengunggah jenis dokumen ini.');
        }
        if (! AplikasiDocumentAccess::canUploadTypeForStatus($jenis, (string) $aplikasi->getAttribute('status'))) {
            return ApiResponse::error(
                'Jenis dokumen ini belum dapat diunggah pada tahap aplikasi saat ini.',
                null,
                422
            );
        }

        $file = $request->file('file');
        $path = $file->store('aplikasi_documents', self::PRIVATE_DISK);

        try {
            $doc = DB::transaction(function () use ($aplikasiId, $jenis, $path, $file, $user, $request) {
                // OWASP: Use lockForUpdate to prevent race condition on version increment
                $maxVersion = (int) AplikasiDocument::query()
                    ->where('aplikasi_id', $aplikasiId)
                    ->where('document_type', $jenis->value)
                    ->lockForUpdate()
                    ->max('version');

                AplikasiDocument::query()
                    ->where('aplikasi_id', $aplikasiId)
                    ->where('document_type', $jenis->value)
                    ->where('status', 'active')
                    ->update(['status' => 'superseded']);

                return AplikasiDocument::create([
                    'aplikasi_id' => $aplikasiId,
                    'document_type' => $jenis,
                    'storage_path' => $path,
                    'storage_disk' => self::PRIVATE_DISK,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                    'version' => max(1, $maxVersion + 1),
                    'status' => 'active',
                    'uploaded_by' => $user?->getKey(),
                    'notes' => $request->input('notes'),
                ]);
            });
        } catch (\Throwable $e) {
            // Hapus file yang sudah diupload jika transaksi gagal
            Storage::disk(self::PRIVATE_DISK)->delete($path);

            Log::error('Failed to store document', [
                'aplikasi_id' => $aplikasiId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Gagal mengunggah dokumen', null, 500);
        }

        $doc->load('uploader:id,name');
        if ($jenis === AplikasiJenisDokumen::Uat) {
            $this->notifyPengelolaForUatDocument($aplikasi, $user);
        }

        return ApiResponse::created([
            'document' => $this->documentPayload($aplikasi, $doc),
        ], 'Dokumen berhasil diunggah');
    }

    public function preview(Request $request, Aplikasi $aplikasi, AplikasiDocument $document): BinaryFileResponse|JsonResponse
    {
        if ((int) $document->getAttribute('aplikasi_id') !== (int) $aplikasi->getKey()) {
            $response = ApiResponse::notFound('Dokumen tidak ditemukan.');
        } elseif (! AplikasiDocumentAccess::canView($request->user(), $aplikasi)) {
            $response = ApiResponse::forbidden(self::ACCESS_DENIED_MESSAGE);
        } else {
            $response = $this->buildPreviewResponse($document);
        }

        return $response;
    }

    private function buildPreviewResponse(AplikasiDocument $document): BinaryFileResponse|JsonResponse
    {
        $diskName = $this->resolveDisk($document);
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($diskName);
        $path = (string) $document->getAttribute('storage_path');

        if ($path === '' || ! $disk->exists($path)) {
            return ApiResponse::notFound('File dokumen tidak ditemukan.');
        }

        $fileName = trim((string) $document->getAttribute('original_filename')) ?: 'dokumen';
        $fallbackName = preg_replace('/[^A-Za-z0-9._-]/', '_', $fileName) ?: 'dokumen';
        $disposition = (new ResponseHeaderBag)->makeDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $fileName,
            $fallbackName
        );

        return response()->file($disk->path($path), [
            'Content-Type' => (string) ($document->getAttribute('mime_type') ?: 'application/octet-stream'),
            'Content-Disposition' => $disposition,
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function documentPayload(Aplikasi $aplikasi, AplikasiDocument $document): array
    {
        $documentType = $document->getAttribute('document_type');
        $mimeType = (string) $document->getAttribute('mime_type');

        return [
            'id' => $document->getKey(),
            'document_type' => $documentType instanceof AplikasiJenisDokumen
                ? $documentType->value
                : $documentType,
            'original_filename' => $document->getAttribute('original_filename'),
            'mime_type' => $mimeType,
            'file_size' => $document->getAttribute('file_size'),
            'preview_url' => route('aplikasi.documents.preview', [
                'aplikasi' => $aplikasi,
                'document' => $document,
            ], false),
            'preview_supported' => $mimeType === 'application/pdf',
            'version' => $document->getAttribute('version'),
            'status' => $document->getAttribute('status'),
            'notes' => $document->getAttribute('notes'),
            'uploaded_by' => $document->getRelationValue('uploader'),
            'created_at' => $document->getAttribute('created_at'),
        ];
    }

    private function resolveDisk(AplikasiDocument $document): string
    {
        $disk = (string) ($document->getAttribute('storage_disk') ?: 'public');

        return in_array($disk, ['local', 'public'], true) ? $disk : 'public';
    }

    private function notifyPengelolaForUatDocument(Aplikasi $aplikasi, ?User $uploader): void
    {
        if (! $uploader?->isUnitKerja()) {
            return;
        }

        $appName = (string) (
            $aplikasi->getAttribute('nama_aplikasi')
            ?: $aplikasi->getAttribute('nama_layanan')
            ?: 'Aplikasi'
        );
        $uploaderName = (string) ($uploader->getAttribute('name') ?: 'Unit Kerja');

        User::query()
            ->where('role', UserRole::PENGELOLA_APLIKASI->value)
            ->get(['id'])
            ->each(function (User $pengelola) use ($aplikasi, $appName, $uploaderName): void {
                AppNotification::create([
                    'user_id' => $pengelola->getKey(),
                    'aplikasi_id' => $aplikasi->getKey(),
                    'type' => 'action_required',
                    'title' => 'Dokumen UAT Diunggah',
                    'body' => sprintf(
                        'Dokumen UAT untuk aplikasi "%s" telah diunggah oleh %s. Silakan verifikasi hasil UAT.',
                        $appName,
                        $uploaderName
                    ),
                ]);
            });
    }
}
