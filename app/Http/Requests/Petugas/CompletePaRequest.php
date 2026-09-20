<?php

namespace App\Http\Requests\Petugas;

use Illuminate\Foundation\Http\FormRequest;

class CompletePaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // $this->route('paOrder') mengasumsikan route model binding
        // Route::post('/my/tasks/{paOrder}/complete', ...)
        return $this->user()->can('complete', $this->route('paOrder'));
    }

    public function rules(): array
    {
        return [
            // Evidence aktif bersifat opsional di Phase 1; bila dikirim,
            // setiap file tetap harus berupa gambar dan <= 4 MB.
            'foto_perangkat' => ['nullable', 'image', 'max:4096'],
            'foto_modem_ont' => ['nullable', 'image', 'max:4096'],
            'foto_serah_terima' => ['nullable', 'image', 'max:4096'],
            'receiver_name' => ['required', 'string', 'max:150'],
            // default ke waktu submit kalau tidak diisi manual
            'pickup_time' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'receiver_name.required' => 'Nama penerima wajib diisi.',
            '*.image' => 'File yang diunggah harus berupa gambar.',
            '*.max' => 'Ukuran file maksimal 4 MB.',
        ];
    }
}
