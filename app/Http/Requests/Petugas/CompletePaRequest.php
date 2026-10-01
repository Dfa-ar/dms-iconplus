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
        if ($this->route('paOrder')?->close_icrm_step !== 'STEP_5') {
            return [];
        }

        return [
            'ba_pengambilan_perangkat' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:8192'],
            'receiver_name' => ['required', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'ba_pengambilan_perangkat.required' => 'BA Pengambilan yang telah ditandatangani pelanggan wajib diunggah.',
            'ba_pengambilan_perangkat.mimes' => 'BA Pengambilan harus berupa PDF atau gambar.',
            'ba_pengambilan_perangkat.max' => 'Ukuran BA Pengambilan maksimal 8 MB.',
            'receiver_name.required' => 'Nama penerima wajib diisi.',
        ];
    }
}
