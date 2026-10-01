<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SlaSetting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit()
    {
        $this->authorize('manageSettings');

        return view('admin.settings.index', ['settings' => SlaSetting::current()]);
    }

    public function update(Request $request)
    {
        $this->authorize('manageSettings');

        $validated = $request->validate([
            'sla_days' => ['required', 'integer', 'min:1', 'max:365'],
            'aging_green_max' => ['required', 'integer', 'min:0', 'lt:aging_yellow_max'],
            'aging_yellow_max' => ['required', 'integer', 'gt:aging_green_max', 'lt:aging_orange_max'],
            'aging_orange_max' => ['required', 'integer', 'gt:aging_yellow_max', 'max:365'],
            'bast_number_format' => [
                'required',
                'string',
                'max:100',
                'regex:/^[A-Za-z0-9_{}\/-]+$/',
                function (string $attribute, string $value, \Closure $fail): void {
                    foreach (['{sequence}', '{kp_code}', '{year}'] as $token) {
                        if (! str_contains($value, $token)) {
                            $fail('Pola nomor harus memuat token {sequence}, {kp_code}, dan {year}.');
                            return;
                        }
                    }
                },
            ],
        ]);

        SlaSetting::current()->update($validated);

        return back()->with('status', 'Pengaturan SLA dan nomor BAST berhasil disimpan.');
    }
}