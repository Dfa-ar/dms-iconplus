@props(['level' => null, 'status' => null, 'label' => null])

@php
    $statusMap = [
        'UNASSIGNED' => ['label' => 'Belum Ditugaskan', 'class' => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200'],
        'ASSIGNED' => ['label' => 'Ditugaskan', 'class' => 'bg-sky-100 text-sky-700 ring-1 ring-sky-200'],
        'ON_PROGRESS' => ['label' => 'Diproses', 'class' => 'bg-amber-100 text-amber-700 ring-1 ring-amber-200'],
        'DONE' => ['label' => 'Selesai', 'class' => 'bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200'],
        'KENDALA' => ['label' => 'Kendala', 'class' => 'bg-red-100 text-red-700 ring-1 ring-red-200'],
    ];

    $levelClassMap = [
        'green' => 'bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200',
        'yellow' => 'bg-amber-100 text-amber-700 ring-1 ring-amber-200',
        'orange' => 'bg-orange-100 text-orange-700 ring-1 ring-orange-200',
        'red' => 'bg-red-100 text-red-700 ring-1 ring-red-200',
    ];

    if ($status) {
        $label = $statusMap[$status]['label'] ?? $status;
        $class = $statusMap[$status]['class'] ?? 'bg-slate-100 text-slate-700 ring-1 ring-slate-200';
    } else {
        $label = $label ?? '-';
        $class = $levelClassMap[$level] ?? 'bg-slate-100 text-slate-700 ring-1 ring-slate-200';
    }
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold {$class}"]) }}>
    {{ $label }}
</span>
