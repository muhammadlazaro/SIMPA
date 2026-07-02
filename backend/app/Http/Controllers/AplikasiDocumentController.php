<?php

namespace App\Http\Controllers;

use App\Enums\AplikasiJenisDokumen;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\StoreAplikasiDocumentRequest;
use App\Models\Aplikasi;
use App\Models\AplikasiDocument;
use App\Support\AplikasiDocumentAccess;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AplikasiDocumentController extends Controller
{
    public function index(Request $request, Aplikasi $aplikasi): JsonResponse
    {
        $user = $request->user();
        if (! AplikasiDocumentAccess::canView($user, $aplikasi)) {
            return ApiResponse::forbidden('Akses ditolak.');
        }

        $documents = $aplikasi->documents()
            ->with('uploader:id,name')
            ->orderByDesc('id')
            ->get()
            ->pipe(function ($collection) {
                /** @var FilesystemAdapter $publicDisk */
                $publicDisk = Storage::disk('public');

                return $collection->map(function (AplikasiDocument $doc) use ($publicDisk) {
                    $documentType = $doc->getAttribute('document_type');

                    return [
                        'id' => $doc->getKey(),
                        'document_type' => $documentType instanceof AplikasiJenisDokumen
                            ? $documentType->value
                            : $documentType,
                        'original_filename' => $doc->getAttribute('original_filename'),
                        'mime_type' => $doc->getAttribute('mime_type'),
                        'file_size' => $doc->getAttribute('file_size'),
                        'file_url' => $publicDisk->url((string) $doc->getAttribute('storage_path')),
                        'version' => $doc->getAttribute('version'),
                        'status' => $doc->getAttribute('status'),
                        'notes' => $doc->getAttribute('notes'),
                        'uploaded_by' => $doc->getRelationValue('uploader'),
                        'created_at' => $doc->getAttribute('created_at'),
                    ];
                });
            });

        return ApiResponse::success(['documents' => $documents]);
    }

    public function store(StoreAplikasiDocumentRequest $request, Aplikasi $aplikasi): JsonResponse
    {
        $user = $request->user();
        if (! AplikasiDocumentAccess::canView($user, $aplikasi)) {
            return ApiResponse::forbidden('Akses ditolak.');
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
        $path = $file->store('aplikasi_documents', 'public');

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
            Storage::disk('public')->delete($path);

            Log::error('Failed to store document', [
                'aplikasi_id' => $aplikasiId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Gagal mengunggah dokumen', null, 500);
        }

        $doc->load('uploader:id,name');

        /** @var FilesystemAdapter $publicDisk */
        $publicDisk = Storage::disk('public');

        return ApiResponse::created([
            'document' => [
                'id' => $doc->getKey(),
                'document_type' => $jenis->value,
                'original_filename' => $doc->getAttribute('original_filename'),
                'file_url' => $publicDisk->url((string) $doc->getAttribute('storage_path')),
                'version' => $doc->getAttribute('version'),
                'status' => $doc->getAttribute('status'),
                'uploaded_by' => $doc->getRelationValue('uploader'),
                'created_at' => $doc->getAttribute('created_at'),
            ],
        ], 'Dokumen berhasil diunggah');
    }
}
