{{--
    Dashboard Admin Fakultas (/admin/dashboard) — US-10, F-DSB-03, PRD 10.4, mockup A. Semua angka dari data asli.

    1. Kartu operasional US-10: Masuk Bulan Ini, Menunggu, Diproses, Selesai Hari Ini, Ditolak Bulan Ini.
    2. KPI bulan ini (PRD 1.4) dari tiket yang masuk bulan ini: SLA Compliance, CSAT, Time-to-Claim,
       Claim Rate, Completion Rate. Garis bawah hijau/merah = memenuhi/belum memenuhi target PRD.
    3. Tiket Menganggur (Menunggu + lewat SLA) dengan tombol Ping.
    4. Tren 7 hari, distribusi per Kelompok Layanan, performa Staff bulan ini, "Kelompok tanpa Staff".
--}}
@php
    use App\Support\ChartPalette;
    use App\Support\Format;

    $monthName = now()->translatedFormat('F Y');
@endphp

<x-layouts.app title="Dashboard Admin Fakultas">
    <x-dashboard-greeting :user="$user">
        @if ($ready)
            Admin {{ $user->faculty->name }}.
            Ada <b>{{ $cards['menunggu'] }} tiket menunggu</b>
            @if ($idleTotal)
                , <b>{{ $idleTotal }} di antaranya menganggur melewati batas waktu</b>.
            @else
                dan tidak ada yang melewati batas waktu.
            @endif
        @endif
        <x-slot:action>
            <div class="flex flex-col gap-2 sm:flex-row md:flex-col">
                <x-ui.button variant="cta" :href="route('admin.queue')" icon="inbox" class="px-6">Monitor Antrean</x-ui.button>
                <a href="{{ route('admin.reports.index') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-control border border-on-primary/30 px-5 text-sm font-bold text-on-primary hover:bg-on-primary/10">
                    <x-icon name="chart" class="size-4" /> Laporan & Analitik
                </a>
            </div>
        </x-slot:action>
    </x-dashboard-greeting>

    @if (! $ready)
        <x-ui.card title="Fakultas">
            <p class="text-sm text-muted">Akun Anda belum ditugaskan ke fakultas. Hubungi Super Admin.</p>
        </x-ui.card>
    @else
        {{-- [M6] Pengingat pendaftar alumni (menu Verifikasi Alumni tidak muat di bottom nav HP). --}}
        @if ($pendingAlumni ?? 0)
            <x-ui.alert type="warning" class="mb-4">
                <b>{{ $pendingAlumni }} pendaftaran alumni</b> menunggu verifikasi Anda.
                <a href="{{ route('admin.alumni.index') }}" class="font-bold text-primary underline underline-offset-4">Buka Verifikasi Alumni</a>
            </x-ui.alert>
        @endif

        {{-- 1. Kartu operasional (US-10) --}}
        <section aria-label="Ringkasan operasional" class="mb-3 grid grid-cols-2 gap-3 md:grid-cols-3 md:gap-4 xl:grid-cols-5">
            <x-kpi-card label="Tiket Masuk" :value="$cards['masuk_bulan_ini']" :note="'bulan '.$monthName" icon="ticket" tone="bg-accent-soft text-accent-ink"
                        :href="route('admin.reports.index', ['period' => 'custom', 'from' => now()->startOfMonth()->format('Y-m-d'), 'to' => now()->format('Y-m-d')])" />
            <x-kpi-card label="Menunggu di Antrean" :value="$cards['menunggu']" note="belum diambil staff" icon="hourglass" tone="bg-menunggu-bg text-menunggu-fg"
                        :href="route('admin.queue', ['status' => 'menunggu'])" />
            <x-kpi-card label="Sedang Diproses" :value="$cards['diproses']" note="ditangani staff" icon="refresh" tone="bg-diproses-bg text-diproses-fg"
                        :href="route('admin.queue', ['status' => 'diproses'])" />
            <x-kpi-card label="Selesai Hari Ini" :value="$cards['selesai_hari_ini']" :note="now()->translatedFormat('j F')" icon="circle-check" tone="bg-selesai-bg text-selesai-fg" />
            <x-kpi-card label="Ditolak Bulan Ini" :value="$cards['ditolak_bulan_ini']" :note="$monthName" icon="x" tone="bg-ditolak-bg text-ditolak-fg" />
        </section>

        {{-- 2. KPI bulan ini (PRD 1.4) --}}
        <section aria-label="KPI bulan ini" class="mb-4 grid grid-cols-2 gap-3 md:mb-5 md:grid-cols-3 md:gap-4 xl:grid-cols-5">
            <x-kpi-card label="Ketepatan Waktu" :value="$kpi['sla_rate']" suffix="%" icon="shield-check" tone="bg-selesai-bg text-selesai-fg"
                        :note="$kpi['selesai'] ? $kpi['sla_met'].' dari '.$kpi['selesai'].' selesai · target ≥ 85%' : 'belum ada tiket selesai · target ≥ 85%'"
                        :status="$kpi['sla_rate'] === null ? null : ($kpi['sla_rate'] >= 85 ? 'good' : 'bad')" />
            <x-kpi-card label="Rata-rata CSAT" :value="$kpi['csat'] === null ? null : number_format($kpi['csat'], 1, ',', '.').' / 5'" icon="star" tone="bg-accent-soft text-accent-ink"
                        :note="$kpi['rated'] ? 'dari '.$kpi['rated'].' rating · target ≥ 4,0' : 'belum ada rating · target ≥ 4,0'"
                        :status="$kpi['csat'] === null ? null : ($kpi['csat'] >= 4 ? 'good' : 'bad')" />
            <x-kpi-card label="Rata-rata Time-to-Claim" :value="$kpi['avg_claim_seconds'] === null ? null : Format::seconds($kpi['avg_claim_seconds'])" icon="clock" tone="bg-diproses-bg text-diproses-fg"
                        note="dibuat → diambil staff (dipantau)" />
            <x-kpi-card label="Ticket Claim Rate" :value="$kpi['claim_rate']" suffix="%" icon="inbox" tone="bg-primary-soft text-primary"
                        :note="$kpi['total'] ? $kpi['claimed'].' dari '.$kpi['total'].' tiket diambil · target ≥ 95%' : 'belum ada tiket · target ≥ 95%'"
                        :status="$kpi['claim_rate'] === null ? null : ($kpi['claim_rate'] >= 95 ? 'good' : 'bad')" />
            <x-kpi-card label="Ticket Completion Rate" :value="$kpi['completion_rate']" suffix="%" icon="circle-check" tone="bg-selesai-bg text-selesai-fg"
                        :note="($kpi['selesai'] + $kpi['ditolak']) ? $kpi['selesai'].' selesai, '.$kpi['ditolak'].' ditolak · target ≥ 90%' : 'belum ada tiket ditutup · target ≥ 90%'"
                        :status="$kpi['completion_rate'] === null ? null : ($kpi['completion_rate'] >= 90 ? 'good' : 'bad')" />
        </section>
        <p class="-mt-2 mb-4 text-xs text-muted md:mb-5">
            KPI dihitung dari tiket yang masuk sejak 1 {{ $monthName }}. Garis hijau = memenuhi target PRD, merah = belum. Rincian lain di
            <a href="{{ route('admin.reports.index') }}" class="font-semibold text-primary hover:underline">Laporan & Analitik</a>.
        </p>

        <div class="grid gap-4 lg:grid-cols-[1.5fr_1fr]">
            <div class="min-w-0 space-y-4">
                {{-- 3. Tiket Menganggur --}}
                <x-ui.card title="Tiket Menganggur (Lewat Batas Waktu)" description="Masih Menunggu dan sudah melewati batas waktu penanganan. Kirim Ping ke Staff terkait."
                           class="p-0! md:p-0! [&>header]:px-4 [&>header]:pt-4 md:[&>header]:px-5 md:[&>header]:pt-5">
                    @if ($idleTotal > $idle->count())
                        <x-slot:action>
                            <a href="{{ route('admin.queue', ['status' => 'menunggu', 'sla' => 'overdue']) }}" class="inline-flex min-h-10 items-center gap-1 text-sm font-bold text-primary hover:underline">
                                Semua ({{ $idleTotal }}) <x-icon name="chevron-right" class="size-4" />
                            </a>
                        </x-slot:action>
                    @endif
                    @if ($idle->isEmpty())
                        <div class="px-4 pb-4 md:px-5 md:pb-5">
                            <x-ui.empty-state icon="circle-check" title="Tidak ada tiket menganggur">
                                Semua tiket yang menunggu masih dalam batas waktu target.
                            </x-ui.empty-state>
                        </div>
                    @else
                        <ul class="divide-y divide-line border-t border-line">
                            @foreach ($idle as $ticket)
                                <li><x-admin-ticket-row :ticket="$ticket" :recipients="$recipientCounts[$ticket->id] ?? 0" /></li>
                            @endforeach
                        </ul>
                    @endif
                </x-ui.card>

                {{-- 4a. Tren 7 hari --}}
                <x-ui.card title="Tren tiket 7 hari terakhir" description="Tiket masuk dan tiket selesai per hari.">
                    <x-charts.bars caption="Tiket masuk dan selesai per hari, 7 hari terakhir"
                                   :labels="array_column($trend['masuk'], 'label')"
                                   :series="[
                                       ['name' => 'Masuk', 'values' => array_column($trend['masuk'], 'value'), 'class' => 'bg-primary'],
                                       ['name' => 'Selesai', 'values' => array_column($trend['selesai'], 'value'), 'class' => 'bg-accent'],
                                   ]" />
                </x-ui.card>

                {{-- 4c. Performa staff --}}
                <x-ui.card :title="'Performa Staff: '.$monthName" description="Dari tiket yang masuk bulan ini." class="p-0! md:p-0! [&>header]:px-4 [&>header]:pt-4 md:[&>header]:px-5 md:[&>header]:pt-5">
                    @include('admin._staff-performance', ['rows' => $staffPerformance])
                </x-ui.card>
            </div>

            <div class="min-w-0 space-y-4">
                {{-- 4b. Distribusi per kelompok --}}
                <x-ui.card title="Tiket per Kelompok Layanan" :description="'Tiket masuk '.$monthName.'.'">
                    @if (empty($groups))
                        <p class="text-sm text-muted">Belum ada tiket bulan ini.</p>
                    @else
                        <x-charts.donut caption="Proporsi tiket per Kelompok Layanan bulan ini"
                                        :slices="collect($groups)->map(fn ($g) => ['label' => $g['group']->name, 'value' => $g['total']])->all()" />
                    @endif
                </x-ui.card>

                <x-coverage-summary :coverage="$coverage" />

                <x-ui.card title="Akses cepat">
                    <div class="grid gap-2">
                        @foreach ([
                            ['admin.queue', 'inbox', 'Monitor Antrean'],
                            ['admin.staff.index', 'users', 'Kelola Staff'],
                            ['admin.services.index', 'sliders', 'Pengaturan Layanan'],
                            ['admin.reports.index', 'chart', 'Laporan & Analitik'],
                        ] as [$route, $icon, $label])
                            <a href="{{ route($route) }}" class="flex min-h-12 items-center gap-3 rounded-control border border-line bg-surface px-3 text-sm font-bold shadow-sm hover:border-primary hover:bg-primary-soft">
                                <span class="grid size-8 place-items-center rounded-control bg-primary-soft text-primary"><x-icon :name="$icon" class="size-4" /></span>
                                {{ $label }}
                                <x-icon name="chevron-right" class="ml-auto size-4 text-muted" />
                            </a>
                        @endforeach
                    </div>
                </x-ui.card>
            </div>
        </div>
    @endif
</x-layouts.app>
