@extends('layouts.admin')

@php
    $active = 'accounts';
    $title = 'Manajemen Akun';
    $subtitle = 'Akun login petugas operasional';
@endphp

@section('title', 'Manajemen Akun')

@section('content')
    <div class="space-y-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <span class="page-kicker">Administrasi</span>
                <h1 class="mt-2 text-2xl font-extrabold text-slate-900">Manajemen Akun</h1>
            </div>
            <dl class="grid grid-cols-3 gap-5 border-y border-slate-200 py-3 text-sm lg:min-w-[440px] lg:border-y-0 lg:border-l lg:pl-6">
                <div><dt class="text-xs text-slate-500">Petugas</dt><dd class="mt-1 text-lg font-bold text-slate-900">{{ number_format($totalOfficers) }}</dd></div>
                <div><dt class="text-xs text-slate-500">Terhubung</dt><dd class="mt-1 text-lg font-bold text-emerald-700">{{ number_format($linkedAccounts) }}</dd></div>
                <div><dt class="text-xs text-slate-500">Belum ada akun</dt><dd class="mt-1 text-lg font-bold text-amber-700">{{ number_format($totalOfficers - $linkedAccounts) }}</dd></div>
            </dl>
        </div>

        <form method="GET" action="{{ route('admin.accounts.index') }}" class="flex max-w-xl gap-2">
            <label class="sr-only" for="account-search">Cari petugas atau akun</label>
            <input id="account-search" type="search" name="search" value="{{ $search }}" placeholder="Cari nama, NIP, atau email" class="min-h-11 min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-3 text-sm focus:border-sky-600 focus:outline-none focus:ring-2 focus:ring-sky-100">
            <button type="submit" class="inline-flex min-h-11 items-center rounded-lg bg-sky-700 px-4 text-sm font-semibold text-white hover:bg-sky-800">Cari</button>
        </form>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1120px] text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Petugas</th>
                            <th class="px-4 py-3 font-semibold">Wilayah</th>
                            <th class="px-4 py-3 font-semibold">Email login</th>
                            <th class="px-4 py-3 font-semibold">Status akun</th>
                            <th class="px-4 py-3 font-semibold">Password baru</th>
                            <th class="sticky right-0 z-20 w-24 bg-slate-50 px-4 py-3 text-right font-semibold shadow-[-8px_0_12px_rgba(15,23,42,0.04)]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($officers as $officer)
                            <tr class="align-top">
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-slate-800">{{ $officer->name }}</p>
                                    <p class="mt-1 text-xs text-slate-500">NIP {{ $officer->employee_code }}</p>
                                    @if ($officer->user)
                                        <form id="account-{{ $officer->id }}" method="POST" action="{{ route('admin.accounts.update', $officer) }}" class="hidden">
                                            @csrf
                                            @method('PATCH')
                                        </form>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-slate-600">{{ $officer->region?->kabupaten_kota ?? '-' }}</td>
                                @if ($officer->user)
                                    <td class="px-4 py-4">
                                        <label class="sr-only" for="email-{{ $officer->id }}">Email {{ $officer->name }}</label>
                                        <input form="account-{{ $officer->id }}" id="email-{{ $officer->id }}" type="email" name="email" value="{{ old('email', $officer->user->email) }}" required class="min-h-10 w-64 rounded-lg border border-slate-300 px-2.5 text-sm">
                                    </td>
                                    <td class="px-4 py-4">
                                        <label class="sr-only" for="active-{{ $officer->id }}">Status akun {{ $officer->name }}</label>
                                        <select form="account-{{ $officer->id }}" id="active-{{ $officer->id }}" name="is_active" required class="min-h-10 rounded-lg border border-slate-300 bg-white px-2.5 text-sm">
                                            <option value="1" @selected(old('is_active', (int) $officer->user->is_active) === '1' || old('is_active', (int) $officer->user->is_active) === 1)>Aktif</option>
                                            <option value="0" @selected((string) old('is_active', (int) $officer->user->is_active) === '0')>Nonaktif</option>
                                        </select>
                                    </td>
                                    <td class="px-4 py-4">
                                        <label class="sr-only" for="password-{{ $officer->id }}">Password baru</label>
                                        <input form="account-{{ $officer->id }}" id="password-{{ $officer->id }}" type="password" name="password" minlength="8" autocomplete="new-password" placeholder="Kosongkan jika tetap" class="min-h-10 w-52 rounded-lg border border-slate-300 px-2.5 text-sm">
                                        <label class="sr-only" for="password-confirmation-{{ $officer->id }}">Konfirmasi password baru</label>
                                        <input form="account-{{ $officer->id }}" id="password-confirmation-{{ $officer->id }}" type="password" name="password_confirmation" minlength="8" autocomplete="new-password" placeholder="Konfirmasi" class="mt-2 min-h-10 w-52 rounded-lg border border-slate-300 px-2.5 text-sm">
                                    </td>
                                    <td class="sticky right-0 z-10 w-24 bg-white px-4 py-4 text-right shadow-[-8px_0_12px_rgba(15,23,42,0.06)]">
                                        <button form="account-{{ $officer->id }}" type="submit" class="min-h-10 rounded-lg bg-sky-700 px-3 text-xs font-semibold text-white hover:bg-sky-800">Simpan</button>
                                    </td>
                                @else
                                    <td colspan="4" class="px-4 py-4">
                                        <form method="POST" action="{{ route('admin.accounts.store', $officer) }}" class="grid gap-2 lg:grid-cols-[minmax(180px,1fr)_minmax(150px,0.8fr)_minmax(150px,0.8fr)_auto]">
                                            @csrf
                                            <label class="sr-only" for="new-email-{{ $officer->id }}">Email login {{ $officer->name }}</label>
                                            <input id="new-email-{{ $officer->id }}" type="email" name="email" value="{{ old('email') }}" required placeholder="Email login" class="min-h-10 min-w-0 rounded-lg border border-slate-300 px-2.5 text-sm">
                                            <label class="sr-only" for="new-password-{{ $officer->id }}">Password</label>
                                            <input id="new-password-{{ $officer->id }}" type="password" name="password" minlength="8" required autocomplete="new-password" placeholder="Password baru" class="min-h-10 min-w-0 rounded-lg border border-slate-300 px-2.5 text-sm">
                                            <label class="sr-only" for="new-password-confirmation-{{ $officer->id }}">Konfirmasi password</label>
                                            <input id="new-password-confirmation-{{ $officer->id }}" type="password" name="password_confirmation" minlength="8" required autocomplete="new-password" placeholder="Konfirmasi" class="min-h-10 min-w-0 rounded-lg border border-slate-300 px-2.5 text-sm">
                                            <button type="submit" class="min-h-10 rounded-lg bg-emerald-700 px-3 text-xs font-semibold text-white hover:bg-emerald-800">Buat akun</button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-12 text-center text-sm text-slate-500">Tidak ada petugas yang cocok.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($officers->hasPages())
                <div class="border-t border-slate-100 px-5 py-3">{{ $officers->links() }}</div>
            @endif
        </div>
    </div>
@endsection
