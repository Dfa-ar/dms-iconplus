@props(['level' => null, 'status' => null, 'label' => null])

@php
    // Dua mode pemakaian:
    // 1. <x-badge-status :level="$priority['level']" :label="$priority['label']" />
    //    -- untuk badge aging dari AgingCalculator (green/yellow/orange/red)
    // 2. <x-badge-status :status="$paOrder->current_status" />
    //    -- untuk badge current_status PA (UNASSIGNED/ASSIGNED/dst)
    $statusMap = [
        'UNASSIGNED' => ['label' => 'Belum Ditugaskan', 'class' => 'bg-slate-100 text-slate-600'],
        'ASSIGNED' => ['label' => 'Ditugaskan', 'class' => 'bg-sky-100 text-sky-700'],
        'ON_PROGRESS' => ['label' => 'Diproses', 'class' => 'bg-amber-100 text-amber-700'],
        'DONE' => ['label' => 'Selesai', 'class' => 'bg-emerald-100 text-emerald-700'],
        'KENDALA' => ['label' => 'Kendala', 'class' => 'bg-red-100 text-red-700'],
    ];

    $levelClassMap = [
        'green' => 'bg-emerald-100 text-emerald-700',
        'yellow' => 'bg-amber-100 text-amber-700',
        'orange' => 'bg-orange-100 text-orange-700',
        'red' => 'bg-red-100 text-red-700',
    ];

    if ($status) {
        $label = $statusMap[$status]['label'] ?? $status;
        $class = $statusMap[$status]['class'] ?? 'bg-slate-100 text-slate-600';
    } else {
        $label = $label ?? '-';
        $class = $levelClassMap[$level] ?? 'bg-slate-100 text-slate-600';
    }
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-medium {$class}"]) }}>
    {{ $label }}
</span>
