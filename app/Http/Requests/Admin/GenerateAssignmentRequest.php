<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class GenerateAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('generateAssignment');
    }

    public function rules(): array
    {
        return [
            'region_id' => ['required', 'exists:regions,id'],
            // FR-05: "target per petugas dapat diatur, default 20"
            'target_per_officer' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Helper supaya controller tidak perlu tulis '?? 20' berulang-ulang.
     */
    public function targetPerOfficer(): int
    {
        return (int) ($this->validated('target_per_officer') ?? 20);
    }
}
