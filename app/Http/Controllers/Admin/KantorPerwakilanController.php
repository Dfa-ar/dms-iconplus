<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KantorPerwakilan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class KantorPerwakilanController extends Controller
{
    public function index()
    {
        $this->authorize('manageRegions');

        $kantorPerwakilan = KantorPerwakilan::query()
            ->withCount(['regions', 'officers'])
            ->orderBy('nama')
            ->paginate(20);

        return view('admin.kantor-perwakilan.index', compact('kantorPerwakilan'));
    }

    public function store(Request $request)
    {
        $this->authorize('manageRegions');
        $request->merge(['kode' => Str::upper(trim((string) $request->input('kode')))]);

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:150'],
            'kode' => ['required', 'string', 'alpha_dash', 'max:30', 'unique:kantor_perwakilan,kode'],
            'alamat' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'pic_nama' => ['nullable', 'string', 'max:150'],
            'pic_unit' => ['nullable', 'string', 'max:150'],
        ]);

        KantorPerwakilan::create($data);

        return back()->with('status', 'Kantor Perwakilan berhasil ditambahkan.');
    }

    public function update(Request $request, KantorPerwakilan $kantorPerwakilan)
    {
        $this->authorize('manageRegions');
        $request->merge(['kode' => Str::upper(trim((string) $request->input('kode')))]);

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:150'],
            'kode' => ['required', 'string', 'alpha_dash', 'max:30', 'unique:kantor_perwakilan,kode,' . $kantorPerwakilan->id],
            'alamat' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'pic_nama' => ['nullable', 'string', 'max:150'],
            'pic_unit' => ['nullable', 'string', 'max:150'],
        ]);

        $kantorPerwakilan->update($data);

        return back()->with('status', 'Kantor Perwakilan berhasil diperbarui.');
    }

    public function destroy(KantorPerwakilan $kantorPerwakilan)
    {
        $this->authorize('manageRegions');

        if ($kantorPerwakilan->regions()->exists() || $kantorPerwakilan->officers()->exists()) {
            return back()->withErrors([
                'kantorPerwakilan' => 'Kantor Perwakilan masih dipakai data wilayah atau petugas, tidak bisa dihapus.',
            ]);
        }

        $kantorPerwakilan->delete();

        return back()->with('status', 'Kantor Perwakilan berhasil dihapus.');
    }
}
