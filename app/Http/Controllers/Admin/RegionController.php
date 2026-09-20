<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Region;
use Illuminate\Http\Request;

class RegionController extends Controller
{
    public function index()
    {
        $this->authorize('manageRegions');

        $regions = Region::orderBy('parent_group')
            ->orderBy('kabupaten_kota')
            ->paginate(30);

        return view('admin.region.index', compact('regions'));
    }

    public function store(Request $request)
    {
        $this->authorize('manageRegions');

        $data = $request->validate([
            'kabupaten_kota' => ['required', 'string', 'max:150'],
            'kecamatan' => ['nullable', 'string', 'max:150'],
            'kelurahan' => ['nullable', 'string', 'max:150'],
            'parent_group' => ['nullable', 'string', 'max:100'],
        ]);

        Region::create($data);

        return back()->with('status', 'Wilayah berhasil ditambahkan.');
    }

    public function update(Request $request, Region $region)
    {
        $this->authorize('manageRegions');

        $data = $request->validate([
            'kabupaten_kota' => ['required', 'string', 'max:150'],
            'kecamatan' => ['nullable', 'string', 'max:150'],
            'kelurahan' => ['nullable', 'string', 'max:150'],
            'parent_group' => ['nullable', 'string', 'max:100'],
        ]);

        $region->update($data);

        return back()->with('status', 'Wilayah berhasil diperbarui.');
    }

    public function destroy(Region $region)
    {
        $this->authorize('manageRegions');

        if ($region->officers()->exists() || $region->paOrders()->exists()) {
            return back()->withErrors(['region' => 'Wilayah ini masih dipakai petugas/PA, tidak bisa dihapus.']);
        }

        $region->delete();

        return back()->with('status', 'Wilayah berhasil dihapus.');
    }
}
