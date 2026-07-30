<?php

namespace App\Http\Requests;

use App\Models\Rfc;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRfcRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $requiresFollowUpFields = $this->user()?->isPengelolaAplikasi() === true;

        return [
            'aplikasi_id' => ['required', 'exists:aplikasis,id'],
            'tipe_rfc' => ['required', Rule::in(Rfc::TIPE_VALUES)],
            'deskripsi' => ['nullable', 'string', 'max:5000'],
            'formulir_rfc' => [
                'required',
                'file',
                'mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'max:8000',
            ],
            'pelaksana' => [$requiresFollowUpFields ? 'required' : 'nullable', Rule::in(Rfc::PELAKSANA_VALUES)],
            'status_tindaklanjut' => [$requiresFollowUpFields ? 'required' : 'nullable', Rule::in(Rfc::STATUS_VALUES)],
        ];
    }
}
