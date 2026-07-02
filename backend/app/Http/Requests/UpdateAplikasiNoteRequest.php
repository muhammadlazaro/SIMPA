<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAplikasiNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'note_type' => ['sometimes', 'required', 'in:perbaikan,uji_keamanan,info'],
            'body' => ['sometimes', 'required', 'string', 'max:5000'],
            'is_checked' => ['sometimes', 'boolean'],
        ];
    }
}
