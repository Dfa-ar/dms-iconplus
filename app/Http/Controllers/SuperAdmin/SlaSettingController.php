<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SlaSetting;
use Illuminate\Http\Request;

class SlaSettingController extends Controller
{
    /**
     * Single-row settings table -- pakai firstOrCreate supaya selalu ada
     * baris untuk diedit meski belum pernah di-seed.
     */
    public function edit()
    {
        $this->authorize('manageSlaSettings');

        $sla = SlaSetting::firstOrCreate([], [
            'sla_days' => 14,
            'aging_green_max' => 2,
            'aging_yellow_max' => 6,
            'aging_orange_max' => 13,
        ]);

        return view('system.sla-settings.edit', compact('sla'));
    }

    public function update(Request $request)
    {
        $this->authorize('manageSlaSettings');

        $data = $request->validate([
            'sla_days' => ['required', 'integer', 'min:1', 'max:365'],
            'aging_green_max' => ['required', 'integer', 'min:0'],
            'aging_yellow_max' => ['required', 'integer', 'gt:aging_green_max'],
            'aging_orange_max' => ['required', 'integer', 'gt:aging_yellow_max'],
        ]);

        $sla = SlaSetting::firstOrCreate([]);
        $sla->update($data);

        return back()->with('status', 'Pengaturan SLA berhasil disimpan.');
    }
}
