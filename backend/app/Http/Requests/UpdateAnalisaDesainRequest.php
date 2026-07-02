<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAnalisaDesainRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $u = $this->user();

        return $u && ($u->isPengelolaAplikasi() || $u->isAnalisDesain());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'aplikasi_id' => 'sometimes|required|exists:aplikasis,id',
            'ui_platform' => 'nullable|string|max:255',
            'interop_type' => 'nullable|string|max:255',
            'storage_type' => 'nullable|in:db,object-storage',
            'nama_aktor' => 'nullable|string|max:255',
            'method' => 'nullable|in:GET,POST,PUT,DELETE,PATCH',
            'url' => 'nullable|string|max:500',
            'tipe_resource' => 'nullable|in:terbuka,tertutup',
            'aktor_transaksi' => 'nullable|string|max:255',
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     */
    public function attributes(): array
    {
        return [
            'aplikasi_id' => 'aplikasi',
            'ui_platform' => 'UI platform',
            'interop_type' => 'tipe interop',
            'storage_type' => 'tipe storage',
            'nama_aktor' => 'nama aktor',
            'method' => 'HTTP method',
            'url' => 'URL endpoint',
            'tipe_resource' => 'tipe resource',
            'aktor_transaksi' => 'aktor transaksi',
        ];
    }
}
