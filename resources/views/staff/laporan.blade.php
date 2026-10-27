{{--
    [M6] Laporan Kerja Staff (/staff/laporan) — hasil kerja Staff yang login, per periode.

    - Filter: tombol periode 7 / 14 / 30 / 90 hari (tautan biasa) + rentang tanggal sendiri (form dari–sampai).
    - Kartu angka dari App\Services\Reports\StaffWorkReport::summary().
    - Daftar tiket (diambil ATAU ditutup pada periode) + unduh CSV/Excel berkolom sama dengan ekspor Admin (US-15).
--}}
@php
    use App\Services\Reports\StaffWorkReport;
    use App\Support\Format;

    $exportQuery = $report->toQuery();
    $presets = array_filter(StaffWorkReport::PERIODS, fn ($label, $key) => $key !== 'custom', ARRAY_FILTER_USE_BOTH);
@endphp

<x-layouts.app title="Laporan Kerja">
    <x-page-header title="Laporan Kerja Saya" :description="'Tiket yang Anda ambil atau tutup pada '.$report->label().'.'">
        <x-slot:action>
            <x-ui.button variant="secondary" icon="download" :href="route('staff.laporan.export', $exportQuery + ['format' => 'csv'])">Unduh CSV</x-ui.button>
            <x-ui.button icon="download" :href="route('staff.laporan.export', $exportQuery + ['format' => 'xlsx'])">Unduh Excel</x-ui.button>
        </x-slot:action>
    </x-page-header>

    {{-- Filter periode --}}
    <x-ui.card class="mb-4">
        <nav aria-label="Periode cepat" class="flex flex-wrap items-center gap-2">
            <span class="text-sm font-semibold text-muted">Periode:</span>
            @foreach ($presets as $value => $label)
                <a href="{{ route('staff.laporan', ['period' => $value]) }}" @if ($report->period === (string) $value) aria-current="page" @endif
                   @class([
                       'inline-flex min-h-10 items-center rounded-full border px-4 text-sm font-bold',
                       'border-primary bg-primary text-on-primary' => $report->period === (string) $value,
                       'border-line bg-surface text-ink hover:border-primary' => $report->period !== (string) $value,
                   ])>{{ str_replace(' terakhir', '', $label) }}</a>
            @endforeach
        </nav>

        <form method="GET" action="{{ route('staff.laporan') }}" class="mt-4 grid gap-3 border-t border-dashed border-line pt-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
            <input type="hidden" name="period" value="custom">
            <x-ui.input name="from" type="date" label="Dari tanggal" :value="$report->from->format('Y-m-d')" />
            <x-ui.input name="to" type="date" label="Sampai tanggal" :value="$report->to->format('Y-m-d')" />
            <div class="flex gap-2">
                <x-ui.button type="submit" icon="calendar">Terapkan</x-ui.button>
                @if ($report->period !== '30')
                    <x-ui.button variant="secondary" :href="route('staff.laporan')">Reset</x-ui.button>
                @endif
            </div>
        </form>
    </x-ui.card>

    {{-- Angka hasil kerja --}}
    <section aria-label="Ringkasan hasil kerja" class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4 md:gap-4">
        <x-kpi-card label="Tiket Diambil" :value="$summary['diambil']" icon="inbox" tone="bg-primary-soft text-primary" note="diambil pada periode ini" />
        <x-kpi-card label="Selesai" :value="$summary['selesai']" icon="circle-check" tone="bg-selesai-bg text-selesai-fg" note="ditutup Selesai pada periode ini" />
        <x-kpi-card label="Ditolak" :value="$summary['ditolak']" icon="x" tone="bg-ditolak-bg text-ditolak-fg" note="ditutup Ditolak pada periode ini" />
        <x-kpi-card label="Masih Diproses" :value="$summary['diproses']" icon="refresh" tone="bg-diproses-bg text-diproses-fg" note="belum ditutup" />
        <x-kpi-card label="Rata-rata Penyelesaian" :value="$summary['avg_resolution_seconds'] === null ? null : Format::seconds($summary['avg_resolution_seconds'])" icon="hourglass"
                    tone="bg-diproses-bg text-diproses-fg" note="dibuat → selesai" />
        <x-kpi-card label="Rata-rata Respons Pertama" :value="$summary['avg_first_response_seconds'] === null ? null : Format::seconds($summary['avg_first_response_seconds'])" icon="message"
                    tone="bg-diproses-bg text-diproses-fg" note="dibuat → balasan pertama Anda" />
        <x-kpi-card label="Ketepatan Waktu" :value="$summary['sla_rate']" suffix="%" icon="shield-check" tone="bg-selesai-bg text-selesai-fg"
                    :note="$summary['selesai'] ? $summary['sla_met'].' dari '.$summary['selesai'].' selesai tepat waktu · target ≥ 85%' : 'belum ada tiket selesai · target ≥ 85%'"
                    :status="$summary['sla_rate'] === null ? null : ($summary['sla_rate'] >= 85 ? 'good' : 'bad')" />
        <x-kpi-card label="Rata-rata CSAT" :value="$summary['csat'] === null ? null : number_format($summary['csat'], 1, ',', '.').' / 5'" icon="star" tone="bg-accent-soft text-accent-ink"
                    :note="$summary['rated'] ? 'dari '.$summary['rated'].' rating · target ≥ 4,0' : 'belum ada rating · target ≥ 4,0'"
                    :status="$summary['csat'] === null ? null : ($summary['csat'] >= 4 ? 'good' : 'bad')" />
    </section>

    {{-- Daftar tiket --}}
    <x-ui.card title="Tiket pada periode ini" :description="$tickets->total().' tiket. File unduhan berisi tiket yang sama.'"
               class="p-0! md:p-0! [&>header]:px-4 [&>header]:pt-4 md:[&>header]:px-5 md:[&>header]:pt-5">
        @if ($tickets->isEmpty())
            <div class="px-4 pb-4 md:px-5 md:pb-5">
                <x-ui.empty-state icon="chart" title="Belum ada tiket pada periode ini">
                    Pilih periode yang lebih panjang, atau ambil tiket dari Antrean.
                </x-ui.empty-state>
            </div>
        @else
            <div class="overflow-x-auto border-t border-line">
                <table class="w-full min-w-[60rem] text-sm">
                    <thead class="bg-sunken/70 text-left text-xs font-bold text-muted uppercase">
                        <tr>
                            <th scope="col" class="px-4 py-2.5 md:px-5">No Tiket</th>
                            <th scope="col" class="px-3 py-2.5">Kategori</th>
                            <th scope="col" class="px-3 py-2.5">Status</th>
                            <th scope="col" class="px-3 py-2.5">Diambil</th>
                            <th scope="col" class="px-3 py-2.5">Selesai/Ditolak</th>
                            <th scope="col" class="px-3 py-2.5">Waktu penyelesaian</th>
                            <th scope="col" class="px-3 py-2.5">Ketepatan</th>
                            <th scope="col" class="px-4 py-2.5 text-right md:px-5">Rating</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($tickets as $ticket)
                            @php($met = $ticket->slaMet())
                            <tr class="align-top">
                                <td class="px-4 py-2.5 whitespace-nowrap md:px-5">
                                    <a href="{{ route('staff.tickets.show', $ticket) }}" class="font-mono font-semibold text-accent-ink hover:underline">{{ $ticket->ticket_number }}</a>
                                    <span class="block max-w-56 truncate text-xs text-muted" title="{{ $ticket->title }}">{{ $ticket->title }}</span>
                                </td>
                                <td class="min-w-48 max-w-64 px-3 py-2.5">{{ $ticket->category->pathLabel() }}</td>
                                <td class="px-3 py-2.5"><x-ui.badge :status="$ticket->status" /></td>
                                <td class="px-3 py-2.5 whitespace-nowrap">{{ $ticket->assigned_at?->format('d/m/Y H:i') ?? '–' }}</td>
                                <td class="px-3 py-2.5 whitespace-nowrap">{{ $ticket->resolved_at?->format('d/m/Y H:i') ?? '–' }}</td>
                                <td class="px-3 py-2.5 whitespace-nowrap">{{ $ticket->resolved_at ? Format::duration($ticket->created_at, $ticket->resolved_at) : '–' }}</td>
                                <td class="px-3 py-2.5 whitespace-nowrap">
                                    @if ($met === true)
                                        <x-ui.badge tone="success">Tepat waktu</x-ui.badge>
                                    @elseif ($met === false)
                                        <x-ui.badge tone="danger">Lewat target</x-ui.badge>
                                    @else
                                        <span class="text-muted">–</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right font-mono md:px-5">{{ $ticket->rating?->score ?? '–' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>

    <div class="mt-4">{{ $tickets->links() }}</div>
</x-layouts.app>
