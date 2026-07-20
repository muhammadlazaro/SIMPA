<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAplikasiNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'note_type' => ['nullable', 'in:perbaikan,uji_keamanan,info'],
            'body' => ['required', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
