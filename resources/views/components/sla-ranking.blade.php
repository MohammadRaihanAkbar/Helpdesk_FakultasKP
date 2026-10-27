{{--
    Peringkat Performa SLA Fakultas (US-02, F-TKU-08, PRD 10.2) di dashboard pelapor.

    Pemakaian: <x-sla-ranking :ranking="$slaRanking" />   ($slaRanking dari TicketMetrics::facultyRanking())

    - Urutan: rata-rata waktu penyelesaian dibanding target SLA, tercepat di atas.
    - Warna (dengan teks, tidak hanya warna): hijau "Cepat" (≤ 75% target), kuning "Sesuai target" (≤ 100%),
      merah "Lambat" (rata-rata melewati target). Batang = rata-rata waktu terhadap target (penuh = 150% target).
    - Hanya tiket Selesai, hanya agregat per fakultas aktif — tidak ada rincian kategori (aman untuk kategori sensitif).
--}}
@props(['ranking'])

@php
    use App\Support\Format;

    $tones = [
        'good' => ['bar' => 'bg-selesai-dot', 'badge' => 'success', 'label' => 'Cepat'],
        'warning' => ['bar' => 'bg-menunggu-dot', 'badge' => 'warning', 'label' => 'Sesuai target'],
        'bad' => ['bar' => 'bg-ditolak-dot', 'badge' => 'danger', 'label' => 'Lambat'],
        'neutral' => ['bar' => 'bg-line-strong', 'badge' => 'neutral', 'label' => 'Belum ada data'],
    ];
@endphp

<x-ui.card title="Ketepatan Waktu Fakultas" description="Rata-rata waktu penyelesaian tiket selesai dibanding target waktu penanganan." {{ $attributes }}>
    @if (collect($ranking)->whereNotNull('rank')->isEmpty())
        <x-ui.empty-state icon="chart" title="Belum ada tiket selesai">
            Peringkat muncul setelah ada tiket yang diselesaikan Staff.
        </x-ui.empty-state>
    @else
        <ol class="space-y-4 text-sm">
            @foreach ($ranking as $row)
                @php($tone = $tones[$row['tone']])
                <li>
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <span class="flex min-w-0 items-center gap-2">
                            <span class="w-5 shrink-0 text-right font-mono text-xs text-muted">{{ $row['rank'] ?? '–' }}</span>
                            <span class="font-bold text-ink" title="{{ $row['name'] }}">{{ $row['code'] }}</span>
                            <x-ui.badge :tone="$tone['badge']">{{ $tone['label'] }}</x-ui.badge>
                        </span>
                        <span class="ml-7 text-xs text-muted sm:ml-auto sm:text-right">
                            @if ($row['rank'])
                                rata-rata <span class="font-mono font-semibold text-ink">{{ Format::seconds($row['avg_resolution_seconds']) }}</span>
                                · <span class="font-mono font-semibold text-ink">{{ Format::percent($row['sla_rate']) }}</span> tepat waktu
                            @else
                                belum ada tiket selesai
                            @endif
                        </span>
                    </div>
                    <div class="mt-1.5 ml-7 h-2 overflow-hidden rounded-full bg-sunken" aria-hidden="true">
                        <div class="h-full rounded-full {{ $tone['bar'] }}" style="width: {{ $row['avg_ratio'] === null ? 0 : min(100, round($row['avg_ratio'] / 1.5 * 100)) }}%"></div>
                    </div>
                </li>
            @endforeach
        </ol>
        <p class="mt-4 flex items-start gap-1.5 text-xs text-muted">
            <x-icon name="info" class="mt-0.5 size-4 shrink-0" />
            Dari {{ collect($ranking)->sum('resolved') }} tiket Selesai. Tiket ditolak tidak dihitung. Diperbarui berkala.
        </p>
    @endif
</x-ui.card>
