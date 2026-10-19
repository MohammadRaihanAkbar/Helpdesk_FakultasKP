{{--
    Ringkasan cakupan spesialisasi (US-13, F-ADM-09): Kelompok Layanan aktif + jumlah Staff aktif yang memegangnya.
    Kelompok dengan 0 staff ditandai "Kelompok tanpa Staff" beserta jumlah tiket yang sedang menunggu.

    Pemakaian: <x-coverage-summary :coverage="$coverage" />   ($coverage dari StaffSpecialtyService::coverage())
--}}
@props(['coverage'])

@php
    $uncovered = $coverage->where('staff_count', 0);
@endphp

<x-ui.card title="Cakupan spesialisasi" description="Hanya Staff aktif yang dihitung." {{ $attributes }}>
    @if ($uncovered->isEmpty())
        <x-ui.alert type="success">Semua Kelompok Layanan aktif sudah punya minimal satu Staff aktif.</x-ui.alert>
    @else
        <x-ui.alert type="warning" title="Kelompok tanpa Staff ({{ $uncovered->count() }})">
            Tiket di kelompok ini tetap diterima, tetapi belum ada Staff yang melihatnya di antrean.
        </x-ui.alert>
    @endif

    <ul class="mt-4 divide-y divide-line text-sm">
        @foreach ($coverage as $row)
            <li class="flex items-center justify-between gap-3 py-2.5">
                <span class="min-w-0">
                    <span class="font-semibold text-ink">{{ $row['group']->name }}</span>
                    @if ($row['group']->isSensitive())
                        <x-ui.badge tone="danger" class="ml-1">Sensitif</x-ui.badge>
                    @endif
                    @if ($row['waiting_count'] > 0)
                        <span class="block text-xs text-muted">{{ $row['waiting_count'] }} tiket menunggu</span>
                    @endif
                </span>
                @if ($row['staff_count'] === 0)
                    <x-ui.badge tone="danger">Tanpa Staff</x-ui.badge>
                @else
                    <x-ui.badge tone="success">{{ $row['staff_count'] }} staff</x-ui.badge>
                @endif
            </li>
        @endforeach
    </ul>
</x-ui.card>
