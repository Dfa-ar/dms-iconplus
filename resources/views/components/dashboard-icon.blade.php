@props(['name'])

@php
    $paths = [
        'tower' => 'M12 21v-6m0 0a4 4 0 100-8 4 4 0 000 8zm-7 6h14M8 21l1-6M16 21l-1-6',
        'shuffle' => 'M4 7h4l6 10h6M4 17h4l2.5-4.2M14 7h6m0 0l-3-3m3 3l-3 3m3 8l-3 3m3-3l-3-3',
        'list' => 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01',
        'users' => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m5-3.13a4 4 0 100-8 4 4 0 000 8zm7 3a4 4 0 00-3-3.87',
        'key' => 'M7 14a5 5 0 117.07-7.07L21 13.86V17h-3v3h-3v-3h-3.14A5 5 0 017 14zm0-2h.01',
        'clock' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        'bell' => 'M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0a3 3 0 11-6 0m6 0H9',
        'search' => 'M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z',
        'export' => 'M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3',
        'sync' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
        'alert' => 'M12 9v3.75m0 3.75h.008v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        'check' => 'M5 13l4 4L19 7',
        'location' => 'M12 21s7-4.5 7-10a7 7 0 10-14 0c0 5.5 7 10 7 10zm0-8a2 2 0 100-4 2 2 0 000 4z',
        'logout' => 'M10 17l5-5-5-5m5 5H3m10 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1',
    ];

    $d = $paths[$name] ?? $paths['list'];
@endphp

<svg {{ $attributes->merge(['class' => 'h-4 w-4']) }} fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $d }}" />
</svg>
