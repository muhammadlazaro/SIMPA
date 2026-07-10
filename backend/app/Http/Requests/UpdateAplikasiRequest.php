<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @property string|null $jenis_layanan_aplikasi
 * @property string|null $kode_unitOrganisasi
 * @property string|null $tipe_akuisisi
 * @property string|null $status
 */
class UpdateAplikasiRequest extends FormRequest
{
    /**
     * Authorization dikelola oleh route middleware (auth:sanctum + role).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // These fields are locked after creation (cannot be updated)
            'nama_layanan' => 'prohibited',
            'nama_singkat' => 'prohibited',
            'nama_aplikasi' => 'prohibited',
            
            // These fields can be updated
            'jenis_layanan_aplikasi' => 'sometimes|required|in:publik,internal,pendukung',
            'kode_unitOrganisasi' => 'sometimes|required|string|max:50',
            'tipe_akuisisi' => 'sometimes|required|in:Custom-Made,Off-The-Shelf',
            // Status wajib melalui alur workflow API
            'status' => 'prohibited',
            'surat_dokumen' => 'sometimes|file|mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document|max:5120',
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     */
    public function attributes(): array
    {
        return [
            'jenis_layanan_aplikasi' => 'jenis layanan aplikasi',
            'kode_unitOrganisasi' => 'kode unit organisasi',
            'tipe_akuisisi' => 'tipe akuisisi',
            'status' => 'status',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'nama_layanan.prohibited' => 'Nama layanan tidak dapat diubah setelah aplikasi dibuat',
            'nama_singkat.prohibited' => 'Nama singkat tidak dapat diubah setelah aplikasi dibuat',
            'nama_aplikasi.prohibited' => 'Nama aplikasi tidak dapat diubah setelah aplikasi dibuat',
            'status.prohibited' => 'Status tidak dapat diubah langsung. Silakan gunakan tombol aksi (workflow).',
        ];
    }
}
