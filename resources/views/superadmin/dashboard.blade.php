{{--
    Dashboard Super Admin (/super-admin/dashboard) — F-DSB-04, F-SPA-03, PRD 10.9. Semua angka dari data asli.

    1. Kartu metrik global: Fakultas Aktif, Total Tiket, CSAT, SLA Compliance (rumus TicketMetrics tanpa filter fakultas).
    2. Ringkasan per fakultas (hanya angka agregat, tanpa isi tiket).
    3. Fakultas dengan celah coverage Staff (Kelompok Layanan tanpa Staff aktif) — informasi read-only.
--}}
@php
    use App\Support\Format;
@endphp

<x-layouts.app title="Dashboard Super Admin">
    <x-dashboard-greeting :user="$user">
        Gambaran se-universitas: <b>{{ $cards['faculties_active'] }} fakultas aktif</b>, <b>{{ $kpi['total'] }} tiket</b> sejak awal
        @if ($gaps->isNotEmpty())
            — <b>{{ $gaps->count() }} fakultas</b> punya Kelompok Layanan tanpa Staff.
        @else
            dan semua fakultas aktif sudah punya Staff di setiap Kelompok Layanan.
        @endif
        <x-slot:action>
            <div class="flex flex-col gap-2 sm:flex-row md:flex-col">
                <x-ui.button variant="cta" :href="route('superadmin.faculties.index')" icon="landmark" class="px-6">Kelola Fakultas</x-ui.button>
                <a href="{{ route('superadmin.users.index') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-control border border-on-primary/30 px-5 text-sm font-bold text-on-primary hover:bg-on-primary/10">
                    <x-icon name="users" class="size-4" /> Tunjuk Admin Fakultas
                </a>
            </div>
        </x-slot:action>
    </x-dashboard-greeting>

    {{-- 1. Metrik global (PRD 10.9) --}}
    <section aria-label="Metrik global" class="mb-4 grid grid-cols-2 gap-3 md:mb-5 md:gap-4 xl:grid-cols-4">
        <x-kpi-card label="Fakultas Aktif" :value="$cards['faculties_active']" :note="'dari '.$cards['faculties_total'].' fakultas · '.$cards['admins'].' Admin Fakultas'"
                    icon="landmark" tone="bg-primary-soft text-primary" :href="route('superadmin.faculties.index')" />
        <x-kpi-card label="Total Tiket" :value="$kpi['total']" :note="$cards['masuk_bulan_ini'].' masuk bulan ini · '.$kpi['selesai'].' selesai'"
                    icon="ticket" tone="bg-accent-soft text-accent-ink" />
        <x-kpi-card label="Rata-rata CSAT" :value="$kpi['csat'] === null ? null : number_format($kpi['csat'], 1, ',', '.').' / 5'" icon="star" tone="bg-accent-soft text-accent-ink"
                    :note="$kpi['rated'] ? 'dari '.$kpi['rated'].' rating · target ≥ 4,0' : 'belum ada rating · target ≥ 4,0'"
                    :status="$kpi['csat'] === null ? null : ($kpi['csat'] >= 4 ? 'good' : 'bad')" />
        <x-kpi-card label="SLA Compliance" :value="$kpi['sla_rate']" suffix="%" icon="shield-check" tone="bg-selesai-bg text-selesai-fg"
                    :note="$kpi['selesai'] ? $kpi['sla_met'].' dari '.$kpi['selesai'].' selesai · target ≥ 85%' : 'belum ada tiket selesai · target ≥ 85%'"
                    :status="$kpi['sla_rate'] === null ? null : ($kpi['sla_rate'] >= 85 ? 'good' : 'bad')" />
    </section>

    <div class="grid gap-4 lg:grid-cols-[1.5fr_1fr]">
        {{-- 2. Ringkasan per fakultas --}}
        <x-ui.card title="Ringkasan per Fakultas" description="Seluruh tiket sejak awal. SLA Compliance hanya menghitung tiket Selesai."
                   class="min-w-0 p-0! md:p-0! [&>header]:px-4 [&>header]:pt-4 md:[&>header]:px-5 md:[&>header]:pt-5">
            <div class="overflow-x-auto border-t border-line">
                <table class="w-full min-w-[34rem] text-left text-sm">
                    <thead class="border-b border-line bg-sunken/60 text-xs font-extrabold tracking-wide text-muted uppercase">
                        <tr>
                            <th scope="col" class="px-5 py-3">Fakultas</th>
                            <th scope="col" class="px-3 py-3 text-center">Tiket</th>
                            <th scope="col" class="px-3 py-3 text-center">Selesai</th>
                            <th scope="col" class="px-3 py-3 text-center">SLA</th>
                            <th scope="col" class="px-5 py-3 text-center">CSAT</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($facultyStats as $row)
                            <tr @class(['opacity-70' => ! $row['faculty']->is_active])>
                                <td class="px-5 py-3">
                                    <p class="font-bold text-ink">{{ $row['faculty']->code }}
                                        @unless ($row['faculty']->is_active)
                                            <x-ui.badge class="ml-1">Nonaktif</x-ui.badge>
                                        @endunless
                                    </p>
                                    <p class="text-xs text-muted">{{ $row['faculty']->name }}</p>
                                </td>
                                <td class="px-3 py-3 text-center font-mono font-semibold">{{ $row['kpi']['total'] }}</td>
                                <td class="px-3 py-3 text-center font-mono">{{ $row['kpi']['selesai'] }}</td>
                                <td @class(['px-3 py-3 text-center font-mono font-semibold',
                                            'text-selesai-fg' => $row['kpi']['sla_rate'] !== null && $row['kpi']['sla_rate'] >= 85,
                                            'text-ditolak-fg' => $row['kpi']['sla_rate'] !== null && $row['kpi']['sla_rate'] < 85])>
                                    {{ Format::percent($row['kpi']['sla_rate']) }}
                                </td>
                                <td class="px-5 py-3 text-center font-mono">{{ $row['kpi']['csat'] === null ? '–' : number_format($row['kpi']['csat'], 1, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.card>

        {{-- 3. Celah coverage Staff (US-17) --}}
        <x-ui.card title="Celah Coverage Staff" description="Kelompok Layanan aktif yang belum punya Staff aktif.">
            @if ($gaps->isEmpty())
                <x-ui.alert type="success">Semua fakultas aktif sudah punya minimal satu Staff aktif di setiap Kelompok Layanan.</x-ui.alert>
            @else
                <ul class="divide-y divide-line text-sm">
                    @foreach ($gaps as $gap)
                        <li class="py-3 first:pt-0">
                            <div class="flex items-center justify-between gap-3">
                                <span class="font-bold text-ink">{{ $gap['faculty']->code }}</span>
                                <span class="flex flex-wrap justify-end gap-1.5">
                                    <x-ui.badge tone="danger">{{ $gap['groups']->count() }} kelompok tanpa Staff</x-ui.badge>
                                    <x-ui.badge :tone="$gap['waiting_count'] ? 'warning' : 'neutral'">{{ $gap['waiting_count'] }} tiket menunggu</x-ui.badge>
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-muted">{{ $gap['groups']->pluck('name')->implode(', ') }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
            <p class="mt-4 flex items-start gap-1.5 text-xs text-muted">
                <x-icon name="info" class="mt-0.5 size-4 shrink-0" />
                Hanya ringkasan. Penugasan Staff & spesialisasi tetap wewenang Admin Fakultas masing-masing.
            </p>
        </x-ui.card>
    </div>
</x-layouts.app>
