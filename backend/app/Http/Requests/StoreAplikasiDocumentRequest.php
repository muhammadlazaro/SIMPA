<?php

namespace App\Http\Requests;

use App\Enums\AplikasiJenisDokumen;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAplikasiDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'document_type' => ['required', Rule::enum(AplikasiJenisDokumen::class)],
            'file' => ['required', 'file', 'mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'max:10240'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
