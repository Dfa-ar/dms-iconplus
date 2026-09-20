<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KendalaReason;
use Illuminate\Http\Request;

class KendalaReasonController extends Controller
{
    public function index()
    {
        $this->authorize('manageKendalaReasons');

        $reasons = KendalaReason::orderBy('label')->get();

        return view('admin.kendala-reason.index', compact('reasons'));
    }

    public function store(Request $request)
    {
        $this->authorize('manageKendalaReasons');

        $data = $request->validate([
            'label' => ['required', 'string', 'max:150', 'unique:kendala_reasons,label'],
        ]);

        KendalaReason::create($data + ['is_active' => true]);

        return back()->with('status', 'Alasan kendala berhasil ditambahkan.');
    }

    public function update(Request $request, KendalaReason $kendalaReason)
    {
        $this->authorize('manageKendalaReasons');

        $data = $request->validate([
            'label' => ['required', 'string', 'max:150', 'unique:kendala_reasons,label,' . $kendalaReason->id],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $kendalaReason->update($data);

        return back()->with('status', 'Alasan kendala berhasil diperbarui.');
    }

    /**
     * Nonaktifkan saja, bukan hapus -- supaya PA lama yang sudah pakai
     * alasan ini (foreign key kendala_reason_id) tidak kehilangan riwayat.
     */
    public function destroy(KendalaReason $kendalaReason)
    {
        $this->authorize('manageKendalaReasons');

        $kendalaReason->update(['is_active' => false]);

        return back()->with('status', 'Alasan kendala dinonaktifkan.');
    }
}
