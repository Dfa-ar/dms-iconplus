<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UploadPaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('uploadPa');
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimes' => 'File harus berformat Excel (.xlsx/.xls) atau CSV.',
            'file.max' => 'Ukuran file maksimal 10 MB.',
        ];
    }
}
