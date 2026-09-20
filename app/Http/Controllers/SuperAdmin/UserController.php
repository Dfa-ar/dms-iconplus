<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreUserRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $this->authorize('manageUsers');

        $users = User::with('role')->orderBy('name')->paginate(20);

        return view('system.users.index', compact('users'));
    }

    public function create()
    {
        $this->authorize('manageUsers');

        $roles = Role::orderBy('name')->get();

        return view('system.users.create', compact('roles'));
    }

    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role_id' => $data['role_id'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        return redirect()
            ->route('system.users.index')
            ->with('status', "Akun {$user->name} berhasil dibuat.");
    }

    public function edit(User $user)
    {
        $this->authorize('manageUsers');

        $roles = Role::orderBy('name')->get();

        return view('system.users.edit', compact('user', 'roles'));
    }

    public function update(StoreUserRequest $request, User $user)
    {
        $data = $request->validated();

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'role_id' => $data['role_id'],
            'is_active' => $data['is_active'] ?? $user->is_active,
            'password' => ! empty($data['password']) ? Hash::make($data['password']) : $user->password,
        ]);

        return back()->with('status', "Akun {$user->name} berhasil diperbarui.");
    }

    /**
     * Nonaktifkan, bukan hapus -- akun bisa jadi masih direferensikan di
     * assignments.assigned_by, status_logs.changed_by, audit_logs.user_id, dst.
     */
    public function destroy(User $user)
    {
        $this->authorize('manageUsers');

        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => 'Tidak bisa menonaktifkan akun sendiri.']);
        }

        $user->update(['is_active' => false]);

        return back()->with('status', "Akun {$user->name} dinonaktifkan.");
    }
}
