<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOfficerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Officer::class);
    }

    public function rules(): array
    {
        $officerId = $this->route('officer')?->id;
        $isUpdate = (bool) $officerId;

        return [
            'name' => ['required', 'string', 'max:150'],
            'employee_code' => ['required', 'string', 'max:50', Rule::unique('officers', 'employee_code')->ignore($officerId)],
            'phone' => ['nullable', 'string', 'max:30'],
            'region_id' => ['required', 'exists:regions,id'],
            'daily_target' => ['nullable', 'integer', 'min:1', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'user_email' => [$isUpdate ? 'nullable' : 'required', 'email', Rule::unique('users', 'email')],
            'user_password' => [$isUpdate ? 'nullable' : 'required', 'string', 'min:8', 'required_with:user_email'],
        ];
    }
}
