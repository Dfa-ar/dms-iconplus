<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Officer;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AccountManagementController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $officers = Officer::query()
            ->with(['region:id,kabupaten_kota', 'user:id,name,email,is_active,role_id'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->where('email', 'like', "%{$search}%"));
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $totalOfficers = Officer::count();
        $linkedAccounts = Officer::whereNotNull('user_id')->count();

        return view('admin.accounts.index', compact('officers', 'search', 'totalOfficers', 'linkedAccounts'));
    }

    public function store(Request $request, Officer $officer)
    {
        abort_if($officer->user_id, 409, 'Petugas ini sudah memiliki akun.');

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = DB::transaction(function () use ($data, $officer) {
            $user = User::create([
                'name' => $officer->name,
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role_id' => Role::firstOrCreate(['name' => 'petugas'])->id,
                'is_active' => $officer->is_active,
            ]);

            $officer->user()->associate($user);
            $officer->save();

            return $user;
        });

        AuditLogger::log('officer_account_created', 'Officer', $officer->id, "Akun petugas {$officer->employee_code} dibuat.");

        return redirect()->route('admin.accounts.index')->with('status', "Akun {$user->email} berhasil dibuat.");
    }

    public function update(Request $request, Officer $officer)
    {
        $user = $officer->user;
        abort_unless($user, 404, 'Petugas ini belum memiliki akun.');

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'is_active' => ['required', 'boolean'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        DB::transaction(function () use ($data, $officer, $user) {
            $user->email = $data['email'];
            $user->name = $officer->name;
            $user->is_active = (bool) $data['is_active'];

            if (! empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }

            $user->save();
            $officer->update(['is_active' => (bool) $data['is_active']]);
        });

        AuditLogger::log('officer_account_updated', 'Officer', $officer->id, "Akun petugas {$officer->employee_code} diperbarui.");

        return redirect()->route('admin.accounts.index')->with('status', "Akun petugas {$officer->name} berhasil diperbarui.");
    }
}
