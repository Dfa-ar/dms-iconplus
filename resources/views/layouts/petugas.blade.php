<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Tugas Saya') — PLN ICON PLUS</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-800 antialiased">
    <div class="mx-auto flex min-h-screen max-w-md flex-col bg-white shadow-sm">
        <header class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <div class="inline-flex h-10 w-[135px] items-center justify-center rounded-lg bg-white px-2">
                <img src="{{ asset('image/pln_iconplus.png') }}" alt="PLN ICON PLUS" class="max-h-10 max-w-full object-contain">
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-50 hover:text-red-500" title="Keluar">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                </button>
            </form>
        </header>

        <main class="flex-1 overflow-y-auto pb-20">
            @if (session('status'))
                <div class="m-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="m-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <ul class="list-inside list-disc space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>

        <x-mobile.bottom-nav :active="$active ?? null" />
    </div>
</body>
</html>
