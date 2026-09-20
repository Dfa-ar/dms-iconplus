<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index()
    {
        $this->authorize('manageUsers'); // satu Gate yang sama dengan kelola user

        $roles = Role::withCount('users')->orderBy('name')->get();

        return view('system.roles.index', compact('roles'));
    }

    public function store(Request $request)
    {
        $this->authorize('manageUsers');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:roles,name'],
        ]);

        Role::create($data);

        return back()->with('status', 'Role berhasil ditambahkan.');
    }

    public function destroy(Role $role)
    {
        $this->authorize('manageUsers');

        if ($role->users()->exists()) {
            return back()->withErrors(['role' => 'Role ini masih dipakai oleh user, tidak bisa dihapus.']);
        }

        $role->delete();

        return back()->with('status', 'Role berhasil dihapus.');
    }
}
