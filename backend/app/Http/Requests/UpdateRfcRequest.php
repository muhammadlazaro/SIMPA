<?php

namespace App\Http\Requests;

use App\Models\Rfc;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRfcRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'aplikasi_id' => ['sometimes', 'required', 'exists:aplikasis,id'],
            'tipe_rfc' => ['sometimes', 'required', Rule::in(Rfc::TIPE_VALUES)],
            'deskripsi' => ['nullable', 'string', 'max:5000'],
            'formulir_rfc' => [
                'nullable',
                'file',
                'mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'max:8000',
            ],
            'pelaksana' => ['sometimes', 'required', Rule::in(Rfc::PELAKSANA_VALUES)],
            'status_tindaklanjut' => ['sometimes', 'required', Rule::in(Rfc::STATUS_VALUES)],
        ];
    }
}
