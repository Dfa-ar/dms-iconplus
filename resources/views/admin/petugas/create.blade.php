@extends('layouts.app')

@php
    $active = 'petugas';
    $title = 'Tambah Petugas';
    $subtitle = 'Buat data petugas baru';
@endphp

@section('title', 'Tambah Petugas')

@section('content')
    <div class="mx-auto max-w-3xl rounded-xl border border-slate-200 bg-white p-6">
        <form method="POST" action="{{ route('admin.petugas.store') }}" class="space-y-5">
            @csrf

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Nama Petugas</label>
                    <input type="text" name="name" value="{{ old('name') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">NIP / Kode Pegawai</label>
                    <input type="text" name="employee_code" value="{{ old('employee_code') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Nomor HP</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Target Harian</label>
                    <input type="number" name="daily_target" value="{{ old('daily_target', 20) }}" min="1" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Wilayah</label>
                    <select name="region_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                        <option value="">Pilih wilayah</option>
                        @foreach (App\Models\Region::orderBy('kabupaten_kota')->get() as $region)
                            <option value="{{ $region->id }}" {{ old('region_id') == $region->id ? 'selected' : '' }}>{{ $region->kabupaten_kota }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Status Aktif</label>
                    <select name="is_active" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                        <option value="1" {{ old('is_active', 1) == 1 ? 'selected' : '' }}>Aktif</option>
                        <option value="0" {{ old('is_active') == 0 ? 'selected' : '' }}>Nonaktif</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Email Login <span class="text-red-500">*</span></label>
                    <input type="email" name="user_email" value="{{ old('user_email') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Password Login <span class="text-red-500">*</span></label>
                    <input type="password" name="user_password" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <a href="{{ route('admin.petugas.index') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Batal</a>
                <button type="submit" class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">Simpan</button>
            </div>
        </form>
    </div>
@endsection
