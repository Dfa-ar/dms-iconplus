<?php

namespace App\Http\Requests\Petugas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KendalaPaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reportKendala', $this->route('paOrder'));
    }

    public function rules(): array
    {
        return [
            // dropdown wajib, hanya alasan yang aktif di master kendala_reasons
            'kendala_reason_id' => ['required', Rule::exists('kendala_reasons', 'id')->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'foto_kendala' => ['nullable', 'image', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        return [
            'kendala_reason_id.required' => 'Alasan kendala wajib dipilih.',
            'kendala_reason_id.exists' => 'Alasan kendala tidak valid atau tidak aktif.',
            'foto_kendala.required' => 'Foto lokasi/kendala wajib diunggah.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $reason = $this->input('kendala_reason_id')
            ? \App\Models\KendalaReason::find($this->input('kendala_reason_id'))
            : null;

        if ($reason?->label === 'Lainnya' && blank($this->input('notes'))) {
            $this->merge(['notes' => null]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $reason = $this->input('kendala_reason_id')
                ? \App\Models\KendalaReason::find($this->input('kendala_reason_id'))
                : null;

            if ($reason?->label === 'Lainnya' && blank($this->input('notes'))) {
                $validator->errors()->add('notes', 'Keterangan wajib diisi untuk alasan Lainnya.');
            }
        });
    }
}
