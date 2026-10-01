<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FatPoint;
use App\Models\Region;
use Illuminate\Http\Request;

class FatPointController extends Controller
{
    public function index()
    {
        $this->authorize('manageNetworkAssets');

        $fatPoints = FatPoint::query()
            ->with('region')
            ->orderBy('nama_fat')
            ->paginate(20);

        $regions = Region::query()
            ->orderBy('kabupaten_kota')
            ->get();

        return view('admin.fat-point.index', compact('fatPoints', 'regions'));
    }

    public function store(Request $request)
    {
        $this->authorize('manageNetworkAssets');

        $data = $request->validate([
            'kode_fat' => ['required', 'string', 'max:80', 'unique:fat_points,kode_fat'],
            'nama_fat' => ['required', 'string', 'max:150'],
            'region_id' => ['nullable', 'exists:regions,id'],
            'kantor_perwakilan_id' => ['nullable', 'exists:kantor_perwakilan,id'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        FatPoint::create([
            'kode_fat' => $data['kode_fat'],
            'nama_fat' => $data['nama_fat'],
            'region_id' => $data['region_id'] ?? null,
            'kantor_perwakilan_id' => $data['kantor_perwakilan_id'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('status', 'Master FAT berhasil ditambahkan.');
    }

    public function update(Request $request, FatPoint $fatPoint)
    {
        $this->authorize('manageNetworkAssets');

        $data = $request->validate([
            'kode_fat' => ['required', 'string', 'max:80', 'unique:fat_points,kode_fat,' . $fatPoint->id],
            'nama_fat' => ['required', 'string', 'max:150'],
            'region_id' => ['nullable', 'exists:regions,id'],
            'kantor_perwakilan_id' => ['nullable', 'exists:kantor_perwakilan,id'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $fatPoint->update([
            'kode_fat' => $data['kode_fat'],
            'nama_fat' => $data['nama_fat'],
            'region_id' => $data['region_id'] ?? null,
            'kantor_perwakilan_id' => $data['kantor_perwakilan_id'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('status', 'Master FAT berhasil diperbarui.');
    }

    public function destroy(FatPoint $fatPoint)
    {
        $this->authorize('manageNetworkAssets');

        if ($fatPoint->splitters()->exists() || $fatPoint->paOrders()->exists()) {
            return back()->withErrors([
                'fatPoint' => 'FAT masih digunakan oleh splitter atau PA, tidak bisa dihapus.',
            ]);
        }

        $fatPoint->delete();

        return back()->with('status', 'Master FAT berhasil dihapus.');
    }
}
