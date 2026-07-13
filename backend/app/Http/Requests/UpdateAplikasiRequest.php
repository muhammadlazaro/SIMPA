<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @property string|null $nama_layanan
 * @property string|null $nama_singkat
 * @property string|null $nama_aplikasi
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
            'nama_layanan' => 'sometimes|required|string|max:255',
            'nama_singkat' => 'sometimes|required|string|max:10',
            'nama_aplikasi' => 'sometimes|required|string|max:255',
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
            'nama_layanan' => 'nama layanan',
            'nama_singkat' => 'nama singkat',
            'nama_aplikasi' => 'nama aplikasi',
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
            'status.prohibited' => 'Status tidak dapat diubah langsung. Silakan gunakan tombol aksi (workflow).',
        ];
    }
}
