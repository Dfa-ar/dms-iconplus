<?php

namespace App\Http\Requests\Admin;

use App\Models\PaOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CorrectPaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('paOrder'));
    }

    public function rules(): array
    {
        return [
            'current_status' => ['required', Rule::in([
                PaOrder::STATUS_UNASSIGNED,
                PaOrder::STATUS_ASSIGNED,
                PaOrder::STATUS_ON_PROGRESS,
                PaOrder::STATUS_DONE,
                PaOrder::STATUS_KENDALA,
            ])],
            // Bukan kolom di tabel pa_orders -- field khusus supaya Admin
            // wajib menulis alasan tiap kali koreksi, lalu di Controller
            // dikirim ke AuditLogger sebagai detail tambahan (lihat
            // routes/web-example.php).
            'correction_reason' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'correction_reason.required' => 'Alasan koreksi wajib diisi (akan tercatat di audit log).',
        ];
    }
}
