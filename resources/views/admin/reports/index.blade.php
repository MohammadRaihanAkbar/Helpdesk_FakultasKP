{{--
    Laporan & Analitik Fakultas (/admin/reports) — US-15, F-ADM-07, F-ADM-08.

    Periode menyaring tiket yang MASUK (created_at) pada rentang itu. Kartu KPI, grafik, tabel, DAN file ekspor
    memakai filter yang sama (App\Services\Reports\ReportFilters), jadi angka di layar bisa dicocokkan dengan file.
--}}
@php
    use App\Enums\TicketStatus;
    use App\Services\Reports\ReportFilters;
    use App\Support\ChartPalette;
    use App\Support\Format;

    $exportQuery = $filters->toQuery();
    $unitLabels = ['day' => 'per hari', 'week' => 'per minggu', 'month' => 'per bulan'];
    $reporterTotal = max(1, collect($reporterTypes)->sum('total'));
@endphp

<x-layouts.app title="Laporan & Analitik">
    <x-page-header title="Laporan & Analitik" :description="'Kinerja layanan '.$faculty->code.' untuk tiket yang masuk '.$filters->label().'.'">
        <x-slot:action>
            <x-ui.button variant="secondary" icon="download" :href="route('admin.reports.export', $exportQuery + ['format' => 'csv'])">Unduh CSV</x-ui.button>
            <x-ui.button icon="download" :href="route('admin.reports.export', $exportQuery + ['format' => 'xlsx'])">Unduh Excel</x-ui.button>
        </x-slot:action>
    </x-page-header>

    {{-- Filter --}}
    <x-ui.card class="mb-4">
        <form method="GET" action="{{ route('admin.reports.index') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
              x-data="{ period: @js($filters->period) }">
            <x-ui.select name="period" label="Periode" :options="ReportFilters::PERIODS" :selected="$filters->period" x-model="period" />
            <div x-show="period === 'custom'" x-cloak>
                <x-ui.input name="from" type="date" label="Dari tanggal" :value="$filters->from->format('Y-m-d')" />
            </div>
            <div x-show="period === 'custom'" x-cloak>
                <x-ui.input name="to" type="date" label="Sampai tanggal" :value="$filters->to->format('Y-m-d')" />
            </div>
            <x-ui.select name="category" label="Kelompok / Jenis Masalah" placeholder="Semua kategori" :options="$categoryOptions" :selected="$filters->category?->id" />
            <x-ui.select name="staff" label="Staff penangan" placeholder="Semua staff"
                         :options="$staffOptions->mapWithKeys(fn ($s) => [$s->id => $s->name.($s->is_active ? '' : ' (nonaktif)')])->all()"
                         :selected="$filters->staff?->id" />
            <x-ui.select name="status" label="Status" placeholder="Semua status"
                         :options="collect(TicketStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()"
                         :selected="$filters->status?->value" />
            <x-ui.select name="unit" label="Grafik volume" :options="['day' => 'Per hari', 'week' => 'Per minggu', 'month' => 'Per bulan']" :selected="$unit" />
            <div class="flex items-end gap-2">
                <x-ui.button type="submit" icon="search">Terapkan</x-ui.button>
                @if ($filters->narrowed() || $filters->period !== '30')
                    <x-ui.button variant="secondary" :href="route('admin.reports.index')">Reset</x-ui.button>
                @endif
            </div>
        </form>
    </x-ui.card>

    {{-- Kartu KPI (US-15 + PRD 1.4) --}}
    <section aria-label="Ringkasan KPI" class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4 md:gap-4">
        <x-kpi-card label="Total Tiket" :value="$kpi['total']" icon="ticket" tone="bg-accent-soft text-accent-ink"
                    :note="$kpi['menunggu'].' menunggu · '.$kpi['diproses'].' diproses · '.$kpi['selesai'].' selesai · '.$kpi['ditolak'].' ditolak'" />
        <x-kpi-card label="Rata-rata Resolution Time" :value="$kpi['avg_resolution_seconds'] === null ? null : Format::seconds($kpi['avg_resolution_seconds'])" icon="hourglass"
                    tone="bg-diproses-bg text-diproses-fg" note="dibuat → selesai (tiket Selesai)" />
        <x-kpi-card label="Rata-rata Time-to-Claim" :value="$kpi['avg_claim_seconds'] === null ? null : Format::seconds($kpi['avg_claim_seconds'])" icon="clock"
                    tone="bg-diproses-bg text-diproses-fg" note="dibuat → diambil staff" />
        <x-kpi-card label="SLA Compliance" :value="$kpi['sla_rate']" suffix="%" icon="shield-check" tone="bg-selesai-bg text-selesai-fg"
                    :note="$kpi['sla_met'].' dari '.$kpi['selesai'].' selesai · target ≥ 85%'"
                    :status="$kpi['sla_rate'] === null ? null : ($kpi['sla_rate'] >= 85 ? 'good' : 'bad')" />
        <x-kpi-card label="CSAT Score" :value="$kpi['csat'] === null ? null : number_format($kpi['csat'], 1, ',', '.').' / 5'" icon="star" tone="bg-accent-soft text-accent-ink"
                    :note="$kpi['rated'].' rating · target ≥ 4,0'"
                    :status="$kpi['csat'] === null ? null : ($kpi['csat'] >= 4 ? 'good' : 'bad')" />
        <x-kpi-card label="Ticket Claim Rate" :value="$kpi['claim_rate']" suffix="%" icon="inbox" tone="bg-primary-soft text-primary"
                    :note="$kpi['claimed'].' dari '.$kpi['total'].' diambil · target ≥ 95%'"
                    :status="$kpi['claim_rate'] === null ? null : ($kpi['claim_rate'] >= 95 ? 'good' : 'bad')" />
        <x-kpi-card label="Ticket Completion Rate" :value="$kpi['completion_rate']" suffix="%" icon="circle-check" tone="bg-selesai-bg text-selesai-fg"
                    :note="'selesai / ditutup · target ≥ 90%'"
                    :status="$kpi['completion_rate'] === null ? null : ($kpi['completion_rate'] >= 90 ? 'good' : 'bad')" />
        <div class="rounded-card border border-dashed border-line p-4 text-xs leading-relaxed text-muted">
            Semua angka dari tiket yang <b>masuk</b> pada periode & filter di atas. SLA Compliance dan Resolution Time hanya
            menghitung tiket <b>Selesai</b> (tiket Ditolak tidak dinilai SLA).
        </div>
    </section>

    <div class="mb-4 grid gap-4 lg:grid-cols-[1.4fr_1fr]">
        <x-ui.card :title="'Volume tiket masuk '.$unitLabels[$unit]" :description="$filters->label()">
            <x-charts.bars :caption="'Jumlah tiket masuk '.$unitLabels[$unit]"
                           :labels="array_column($volume, 'label')"
                           :series="[['name' => 'Tiket masuk', 'values' => array_column($volume, 'value'), 'class' => 'bg-primary']]" />
        </x-ui.card>

        <x-ui.card title="Distribusi per Jenis Pelapor" description="Dari snapshot jenis pelapor saat tiket dibuat.">
            <ul class="space-y-3">
                @foreach ($reporterTypes as $i => $row)
                    <li>
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-semibold text-ink">{{ $row['type']->label() }}</span>
                            <span class="font-mono text-xs text-muted">{{ $row['total'] }} · {{ number_format($row['total'] / $reporterTotal * 100, 0) }}%</span>
                        </div>
                        <div class="mt-1 h-2.5 overflow-hidden rounded-full bg-sunken" aria-hidden="true">
                            <div class="h-full rounded-full {{ ChartPalette::at($i)['bg'] }}" style="width: {{ round($row['total'] / $reporterTotal * 100, 1) }}%"></div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    </div>

    <x-ui.card title="Proporsi per Kelompok Layanan" description="Klik nama kelompok untuk melihat rincian per Jenis Masalah." class="mb-4">
        @if (empty($groups))
            <p class="text-sm text-muted">Tidak ada tiket pada periode ini.</p>
        @else
            <x-charts.donut caption="Proporsi tiket per Kelompok Layanan"
                            :slices="collect($groups)->map(fn ($g) => ['label' => $g['group']->name, 'value' => $g['total']])->all()">
                <ul class="divide-y divide-line">
                    @foreach ($groups as $i => $g)
                        <li>
                            <details class="group py-1.5" @if (empty($g['children'])) open @endif>
                                <summary class="flex min-h-10 cursor-pointer list-none items-center gap-2">
                                    <span class="size-2.5 shrink-0 rounded-sm {{ ChartPalette::at($i)['bg'] }}" aria-hidden="true"></span>
                                    <span class="min-w-0 flex-1 truncate font-semibold text-ink">{{ $g['group']->name }}</span>
                                    <span class="font-mono text-xs font-semibold text-ink">{{ $g['total'] }}</span>
                                    <span class="w-11 text-right font-mono text-xs text-muted">{{ number_format($g['total'] / max(1, $kpi['total']) * 100, 0) }}%</span>
                                    @if (! empty($g['children']))
                                        <x-icon name="chevron-down" class="size-4 text-muted transition-transform group-open:rotate-180" />
                                    @else
                                        <span class="size-4"></span>
                                    @endif
                                </summary>
                                @if (! empty($g['children']))
                                    <ul class="mt-1 mb-1 ml-5 space-y-1 border-l-2 border-line pl-3 text-sm">
                                        @foreach ($g['children'] as $child)
                                            <li class="flex items-center justify-between gap-2">
                                                <span class="truncate text-muted">{{ $child['category']->name }}</span>
                                                <span class="font-mono text-xs text-ink">{{ $child['total'] }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </details>
                        </li>
                    @endforeach
                </ul>
            </x-charts.donut>
        @endif
    </x-ui.card>

    <x-ui.card title="Performa per Staff" description="Tiket yang ditangani pada periode ini." class="mb-4 p-0! md:p-0! [&>header]:px-4 [&>header]:pt-4 md:[&>header]:px-5 md:[&>header]:pt-5">
        @include('admin._staff-performance', ['rows' => $staffPerformance])
    </x-ui.card>

    {{-- Tabel tiket (isi yang sama dengan file ekspor) --}}
    <x-ui.card title="Daftar tiket" :description="$tickets->total().' tiket. Kolom sama dengan file ekspor CSV/Excel.'" class="p-0! md:p-0! [&>header]:px-4 [&>header]:pt-4 md:[&>header]:px-5 md:[&>header]:pt-5">
        @if ($tickets->isEmpty())
            <div class="px-4 pb-4 md:px-5 md:pb-5">
                <x-ui.empty-state icon="chart" title="Tidak ada tiket">Ubah periode atau filter untuk melihat data.</x-ui.empty-state>
            </div>
        @else
            <div class="overflow-x-auto border-t border-line">
                <table class="w-full min-w-[76rem] text-sm">
                    <thead class="bg-sunken/70 text-left text-xs font-bold text-muted uppercase">
                        <tr>
                            <th scope="col" class="px-4 py-2.5 md:px-5">No Tiket</th>
                            <th scope="col" class="px-3 py-2.5">Pelapor</th>
                            <th scope="col" class="px-3 py-2.5">Kategori</th>
                            <th scope="col" class="px-3 py-2.5">Status</th>
                            <th scope="col" class="px-3 py-2.5">Dibuat</th>
                            <th scope="col" class="px-3 py-2.5">Diambil</th>
                            <th scope="col" class="px-3 py-2.5 text-right">SLA</th>
                            <th scope="col" class="px-3 py-2.5">Selesai/Ditolak</th>
                            <th scope="col" class="px-3 py-2.5 text-right">Rating</th>
                            <th scope="col" class="px-3 py-2.5">Handler</th>
                            <th scope="col" class="px-4 py-2.5 text-right md:px-5">Bukti</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($tickets as $ticket)
                            <tr class="align-top">
                                <td class="px-4 py-2.5 whitespace-nowrap md:px-5"><a href="{{ route('admin.tickets.show', $ticket) }}" class="font-mono font-semibold text-accent-ink hover:underline">{{ $ticket->ticket_number }}</a></td>
                                <td class="min-w-36 px-3 py-2.5">{{ $ticket->reporter?->name }}<span class="block text-xs text-muted">{{ $ticket->reporter_type->label() }}</span></td>
                                <td class="max-w-56 px-3 py-2.5">{{ $ticket->category->pathLabel() }}</td>
                                <td class="px-3 py-2.5"><x-ui.badge :status="$ticket->status" /></td>
                                <td class="px-3 py-2.5 whitespace-nowrap">{{ $ticket->created_at->format('d/m/Y H:i') }}</td>
                                <td class="px-3 py-2.5 whitespace-nowrap">{{ $ticket->assigned_at?->format('d/m/Y H:i') ?? '–' }}</td>
                                <td class="px-3 py-2.5 text-right font-mono whitespace-nowrap">{{ $ticket->sla_target_hours }} j</td>
                                <td class="px-3 py-2.5 whitespace-nowrap">{{ $ticket->resolved_at?->format('d/m/Y H:i') ?? '–' }}</td>
                                <td class="px-3 py-2.5 text-right font-mono">{{ $ticket->rating?->score ?? '–' }}</td>
                                <td class="min-w-36 px-3 py-2.5">{{ $ticket->handler?->name ?? '–' }}</td>
                                <td class="px-4 py-2.5 text-right font-mono md:px-5">{{ $ticket->resolution_proofs_count ?: '–' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>

    <div class="mt-4">{{ $tickets->links() }}</div>
</x-layouts.app>
