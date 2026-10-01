<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FatPoint;
use App\Models\Splitter;
use Illuminate\Http\Request;

class SplitterController extends Controller
{
    public function index()
    {
        $this->authorize('manageNetworkAssets');

        $splitters = Splitter::query()
            ->with('fatPoint')
            ->orderBy('nama_splitter')
            ->paginate(20);

        $fatPoints = FatPoint::query()
            ->orderBy('nama_fat')
            ->get();

        return view('admin.splitter.index', compact('splitters', 'fatPoints'));
    }

    public function store(Request $request)
    {
        $this->authorize('manageNetworkAssets');

        $data = $request->validate([
            'kode_splitter' => ['required', 'string', 'max:80', 'unique:splitters,kode_splitter'],
            'nama_splitter' => ['required', 'string', 'max:150'],
            'fat_point_id' => ['nullable', 'exists:fat_points,id'],
            'capacity_port' => ['nullable', 'integer', 'min:1', 'max:512'],
            'total_port' => ['nullable', 'integer', 'min:1', 'max:512'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        Splitter::create([
            'kode_splitter' => $data['kode_splitter'],
            'nama_splitter' => $data['nama_splitter'],
            'fat_point_id' => $data['fat_point_id'] ?? null,
            'capacity_port' => $data['capacity_port'] ?? $data['total_port'] ?? 24,
            'total_port' => $data['total_port'] ?? $data['capacity_port'] ?? 24,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('status', 'Master splitter berhasil ditambahkan.');
    }

    public function update(Request $request, Splitter $splitter)
    {
        $this->authorize('manageNetworkAssets');

        $data = $request->validate([
            'kode_splitter' => ['required', 'string', 'max:80', 'unique:splitters,kode_splitter,' . $splitter->id],
            'nama_splitter' => ['required', 'string', 'max:150'],
            'fat_point_id' => ['nullable', 'exists:fat_points,id'],
            'capacity_port' => ['nullable', 'integer', 'min:1', 'max:512'],
            'total_port' => ['nullable', 'integer', 'min:1', 'max:512'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $splitter->update([
            'kode_splitter' => $data['kode_splitter'],
            'nama_splitter' => $data['nama_splitter'],
            'fat_point_id' => $data['fat_point_id'] ?? null,
            'capacity_port' => $data['capacity_port'] ?? $data['total_port'] ?? 24,
            'total_port' => $data['total_port'] ?? $data['capacity_port'] ?? 24,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('status', 'Master splitter berhasil diperbarui.');
    }

    public function destroy(Splitter $splitter)
    {
        $this->authorize('manageNetworkAssets');

        if ($splitter->paOrders()->exists()) {
            return back()->withErrors([
                'splitter' => 'Splitter masih dipakai PA, tidak bisa dihapus.',
            ]);
        }

        $splitter->delete();

        return back()->with('status', 'Master splitter berhasil dihapus.');
    }
}
