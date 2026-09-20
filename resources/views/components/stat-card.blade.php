@props(['label', 'value', 'icon' => 'list', 'tone' => 'slate', 'suffix' => null])

@php
    $tones = [
        'slate' => 'bg-slate-50 text-slate-600',
        'emerald' => 'bg-emerald-50 text-emerald-600',
        'sky' => 'bg-sky-50 text-sky-600',
        'amber' => 'bg-amber-50 text-amber-600',
        'red' => 'bg-red-50 text-red-600',
    ];
    $toneClass = $tones[$tone] ?? $tones['slate'];
@endphp

<div class="app-card p-4" data-reveal>
    <div class="flex items-center justify-between">
        <span class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ $label }}</span>
        <span class="flex h-10 w-10 items-center justify-center rounded-xl {{ $toneClass }}">
            <x-dashboard-icon :name="$icon" class="h-4 w-4" />
        </span>
    </div>
    <p class="mt-4 text-2xl font-extrabold tracking-tight text-slate-900">
        {{ $value }}@if ($suffix)<span class="text-sm font-medium text-slate-400"> {{ $suffix }}</span>@endif
    </p>
</div>
