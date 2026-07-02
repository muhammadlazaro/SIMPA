<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAplikasiChecklistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['nullable', 'in:studi_kelayakan,uji_keamanan,rilis'],
            'title' => ['required', 'string', 'max:255'],
            'item_status' => ['nullable', 'in:pending,in_progress,done'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
