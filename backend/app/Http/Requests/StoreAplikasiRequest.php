<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAplikasiRequest extends FormRequest
{
    /**
     * Authorization dikelola oleh route middleware (auth:sanctum + role).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nama_layanan'           => ['required', 'string', 'max:255'],
            'nama_singkat'           => ['required', 'string', 'max:10'],
            'nama_aplikasi'          => ['required', 'string', 'max:255'],
            'jenis_layanan_aplikasi' => ['required', 'in:publik,internal,pendukung'],
            'kode_unitOrganisasi'    => ['required', 'string', 'max:255'],
            'tipe_akuisisi'          => ['required', 'in:Custom-Made,Off-The-Shelf'],
            // Status ditetapkan otomatis sebagai Pengajuan saat create
            'status'                 => 'prohibited',
            // Dokumen opsional; bisa diupload dari halaman detail setelah pengajuan dibuat.
            'surat_pengajuan'        => ['nullable', 'file', 'mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'max:5120'],
        ];
    }

    /**
     * Pesan validasi dalam Bahasa Indonesia.
     */
    public function messages(): array
    {
        return [
            'nama_layanan.required'           => 'Nama Layanan wajib diisi.',
            'nama_singkat.required'           => 'Nama Singkat wajib diisi.',
            'nama_singkat.max'                => 'Nama Singkat maksimal 10 karakter.',
            'nama_aplikasi.required'          => 'Nama Aplikasi wajib diisi.',
            'jenis_layanan_aplikasi.required' => 'Jenis Layanan wajib dipilih.',
            'jenis_layanan_aplikasi.in'       => 'Jenis Layanan harus publik, internal, atau pendukung.',
            'kode_unitOrganisasi.required'    => 'Kode Unit Organisasi wajib diisi.',
            'tipe_akuisisi.required'          => 'Tipe Akuisisi wajib dipilih.',
            'tipe_akuisisi.in'                => 'Tipe Akuisisi harus Custom-Made atau Off-The-Shelf.',
            'status.prohibited'               => 'Status tidak boleh diisi secara manual.',
            'surat_pengajuan.file'            => 'Surat pengajuan harus berupa file.',
            'surat_pengajuan.mimetypes'       => 'Surat pengajuan harus berformat PDF, DOC, atau DOCX.',
            'surat_pengajuan.max'             => 'Ukuran file maksimal 5 MB.',
        ];
    }
}
