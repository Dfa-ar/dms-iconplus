<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreOfficerRequest;
use App\Models\Officer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OfficerController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Officer::class);

        $officers = Officer::with('region')
            ->withCount(['currentPaOrders as active_task_count' => function ($q) {
                $q->whereIn('current_status', ['ASSIGNED', 'ON_PROGRESS']);
            }])
            ->orderBy('name')
            ->paginate(20);

        return view('admin.petugas.index', compact('officers'));
    }

    public function create()
    {
        $this->authorize('create', Officer::class);

        return view('admin.petugas.create');
    }

    public function store(StoreOfficerRequest $request)
    {
        $data = $request->validated();

        $officer = DB::transaction(function () use ($data) {
            $userId = null;

            // Kalau diisi email+password, sekalian buat akun login role 'petugas'
            if (! empty($data['user_email'])) {
                $petugasRole = Role::firstOrCreate(['name' => 'petugas']);

                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['user_email'],
                    'password' => Hash::make($data['user_password']),
                    'role_id' => $petugasRole->id,
                    'is_active' => $data['is_active'] ?? true,
                ]);

                $userId = $user->id;
            }

            return Officer::create([
                'user_id' => $userId,
                'employee_code' => $data['employee_code'],
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'region_id' => $data['region_id'],
                'daily_target' => $data['daily_target'] ?? 20,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });

        return redirect()
            ->route('admin.petugas.index')
            ->with('status', "Petugas {$officer->name} berhasil ditambahkan.");
    }

    public function edit(Officer $officer)
    {
        $this->authorize('update', $officer);

        return view('admin.petugas.edit', compact('officer'));
    }

    public function update(StoreOfficerRequest $request, Officer $officer)
    {
        $data = $request->validated();

        $officer->update([
            'name' => $data['name'],
            'employee_code' => $data['employee_code'],
            'phone' => $data['phone'] ?? null,
            'region_id' => $data['region_id'],
            'daily_target' => $data['daily_target'] ?? $officer->daily_target,
            'is_active' => $data['is_active'] ?? $officer->is_active,
        ]);

        return back()->with('status', "Data petugas {$officer->name} diperbarui.");
    }

    /**
     * Nonaktifkan, bukan hapus permanen -- supaya riwayat assignment &
     * PA yang pernah ditangani tetap utuh (FK nullOnDelete tetap
     * mempertahankan data PA, tapi lebih aman nonaktifkan saja).
     */
    public function destroy(Officer $officer)
    {
        $this->authorize('delete', $officer);

        $officer->update(['is_active' => false]);

        return back()->with('status', "Petugas {$officer->name} dinonaktifkan.");
    }
}
