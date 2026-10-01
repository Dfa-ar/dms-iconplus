<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QcRejectReason;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class QcRejectReasonController extends Controller
{
    public function index()
    {
        $this->authorize('manageQcRejectReasons');

        $reasons = QcRejectReason::query()
            ->orderByDesc('is_active')
            ->orderBy('label')
            ->paginate(30);

        return view('admin.qc-reject-reasons.index', compact('reasons'));
    }

    public function store(Request $request)
    {
        $this->authorize('manageQcRejectReasons');
        $request->merge(['code' => Str::lower(trim((string) $request->input('code')))]);

        $data = $request->validate([
            'code' => ['required', 'string', 'alpha_dash', 'max:80', 'unique:qc_reject_reasons,code'],
            'label' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        QcRejectReason::create($data + ['is_active' => true]);

        return back()->with('status', 'Alasan penolakan QC berhasil ditambahkan.');
    }

    public function update(Request $request, QcRejectReason $qcRejectReason)
    {
        $this->authorize('manageQcRejectReasons');
        $request->merge(['code' => Str::lower(trim((string) $request->input('code')))]);

        $data = $request->validate([
            'code' => ['required', 'string', 'alpha_dash', 'max:80', 'unique:qc_reject_reasons,code,' . $qcRejectReason->id],
            'label' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ]);

        $qcRejectReason->update($data);

        return back()->with('status', 'Alasan penolakan QC berhasil diperbarui.');
    }

    public function destroy(QcRejectReason $qcRejectReason)
    {
        $this->authorize('manageQcRejectReasons');
        $qcRejectReason->update(['is_active' => false]);

        return back()->with('status', 'Alasan QC dinonaktifkan dan riwayat lama tetap tersimpan.');
    }
}