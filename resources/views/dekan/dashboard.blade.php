{{--
    [M6] Dashboard Dekan (/dekan/dashboard) — pemantauan statistik hasil kerja layanan helpdesk SATU fakultas.
    Hanya baca: tidak ada tombol aksi tiket, Ping, atau pengaturan. Data dari Dekan\DashboardController.

    1. Banner sambutan + pilihan periode (7 / 30 hari) + tombol ke Laporan & Ekspor.
    2. Kartu jumlah tiket per status (menunggu & diproses = keadaan SAAT INI, sisanya tiket yang masuk pada periode).
    3. KPI PRD 1.4 (garis hijau/merah dibanding target), sama rumusnya dengan dashboard Admin.
    4. Tren masuk vs selesai per hari, proporsi per Kelompok Layanan.
    5. Tabel hasil kerja per Staff.
--}}
@php
    use App\Http\Controllers\Dekan\DashboardController;
    use App\Support\Format;

    $periodLabel = DashboardController::PERIODS[$filters->period].' terakhir';
@endphp

<x-layouts.app title="Dashboard Dekan">
    <x-dashboard-greeting :user="$user">
        Dekan {{ $faculty->name }}. Ringkasan hasil kerja layanan helpdesk <b>{{ $periodLabel }}</b> ({{ $filters->label() }}).
        <x-slot:action>
            <div class="flex flex-col gap-2 sm:flex-row md:flex-col">
                <x-ui.button variant="cta" :href="route('dekan.laporan', ['period' => $filters->period])" icon="download" class="px-6">Laporan & Ekspor</x-ui.button>
            </div>
        </x-slot:action>
    </x-dashboard-greeting>

    {{-- Pilihan periode: tautan biasa (tanpa JavaScript), tombol aktif ditandai aria-current. --}}
    <nav aria-label="Periode" class="mb-4 flex flex-wrap items-center gap-2">
        <span class="text-sm font-semibold text-muted">Periode:</span>
        @foreach (DashboardController::PERIODS as $value => $label)
            <a href="{{ route('dekan.dashboard', ['period' => $value]) }}" @if ($filters->period === (string) $value) aria-current="page" @endif
               @class([
                   'inline-flex min-h-10 items-center rounded-full border px-4 text-sm font-bold',
                   'border-primary bg-primary text-on-primary' => $filters->period === (string) $value,
                   'border-line bg-surface text-ink hover:border-primary' => $filters->period !== (string) $value,
               ])>{{ $label }}</a>
        @endforeach
        <span class="ml-auto hidden items-center gap-1.5 rounded-full bg-sunken px-3 py-1 text-xs font-semibold text-muted sm:inline-flex">
            <x-icon name="eye" class="size-3.5" /> Mode pemantauan (hanya baca)
        </span>
    </nav>

    {{-- 2. Jumlah tiket per status --}}
    <section aria-label="Jumlah tiket per status" class="mb-3 grid grid-cols-2 gap-3 md:grid-cols-3 md:gap-4 xl:grid-cols-5">
        <x-kpi-card label="Tiket Masuk" :value="$kpi['total']" :note="$periodLabel" icon="ticket" tone="bg-accent-soft text-accent-ink" />
        <x-kpi-card label="Menunggu di Antrean" :value="$now['menunggu']" note="saat ini, belum diambil Staff" icon="hourglass" tone="bg-menunggu-bg text-menunggu-fg" />
        <x-kpi-card label="Sedang Diproses" :value="$now['diproses']" note="saat ini, ditangani Staff" icon="refresh" tone="bg-diproses-bg text-diproses-fg" />
        <x-kpi-card label="Selesai" :value="$kpi['selesai']" :note="'dari tiket masuk '.$periodLabel" icon="circle-check" tone="bg-selesai-bg text-selesai-fg" />
        <x-kpi-card label="Ditolak" :value="$kpi['ditolak']" :note="'dari tiket masuk '.$periodLabel" icon="x" tone="bg-ditolak-bg text-ditolak-fg" />
    </section>

    {{-- 3. KPI (PRD 1.4) --}}
    <section aria-label="KPI layanan" class="mb-4 grid grid-cols-2 gap-3 md:mb-5 md:grid-cols-3 md:gap-4 xl:grid-cols-5">
        <x-kpi-card label="Ketepatan Waktu" :value="$kpi['sla_rate']" suffix="%" icon="shield-check" tone="bg-selesai-bg text-selesai-fg"
                    :note="$kpi['selesai'] ? $kpi['sla_met'].' dari '.$kpi['selesai'].' selesai · target ≥ 85%' : 'belum ada tiket selesai · target ≥ 85%'"
                    :status="$kpi['sla_rate'] === null ? null : ($kpi['sla_rate'] >= 85 ? 'good' : 'bad')" />
        <x-kpi-card label="Rata-rata Resolution Time" :value="$kpi['avg_resolution_seconds'] === null ? null : Format::seconds($kpi['avg_resolution_seconds'])" icon="hourglass"
                    tone="bg-diproses-bg text-diproses-fg" note="dibuat → selesai" />
        <x-kpi-card label="Rata-rata Time-to-Claim" :value="$kpi['avg_claim_seconds'] === null ? null : Format::seconds($kpi['avg_claim_seconds'])" icon="clock"
                    tone="bg-diproses-bg text-diproses-fg" note="dibuat → diambil Staff" />
        <x-kpi-card label="Rata-rata CSAT" :value="$kpi['csat'] === null ? null : number_format($kpi['csat'], 1, ',', '.').' / 5'" icon="star" tone="bg-accent-soft text-accent-ink"
                    :note="$kpi['rated'] ? 'dari '.$kpi['rated'].' rating · target ≥ 4,0' : 'belum ada rating · target ≥ 4,0'"
                    :status="$kpi['csat'] === null ? null : ($kpi['csat'] >= 4 ? 'good' : 'bad')" />
        <x-kpi-card label="Ticket Completion Rate" :value="$kpi['completion_rate']" suffix="%" icon="circle-check" tone="bg-selesai-bg text-selesai-fg"
                    :note="($kpi['selesai'] + $kpi['ditolak']) ? $kpi['selesai'].' selesai, '.$kpi['ditolak'].' ditolak · target ≥ 90%' : 'belum ada tiket ditutup · target ≥ 90%'"
                    :status="$kpi['completion_rate'] === null ? null : ($kpi['completion_rate'] >= 90 ? 'good' : 'bad')" />
    </section>

    <div class="mb-4 grid gap-4 lg:grid-cols-[1.5fr_1fr]">
        <x-ui.card :title="'Tren tiket '.$periodLabel" description="Tiket masuk dan tiket selesai per hari." class="min-w-0">
            <x-charts.bars :caption="'Tiket masuk dan selesai per hari, '.$periodLabel"
                           :labels="array_column($trend['masuk'], 'label')"
                           :series="[
                               ['name' => 'Masuk', 'values' => array_column($trend['masuk'], 'value'), 'class' => 'bg-primary'],
                               ['name' => 'Selesai', 'values' => array_column($trend['selesai'], 'value'), 'class' => 'bg-accent'],
                           ]" />
        </x-ui.card>

        <x-ui.card title="Tiket per Kelompok Layanan" :description="'Tiket masuk '.$periodLabel.'.'" class="min-w-0">
            @if (empty($groups))
                <x-ui.empty-state icon="chart" title="Belum ada tiket">Belum ada tiket masuk pada periode ini.</x-ui.empty-state>
            @else
                <x-charts.donut caption="Proporsi tiket per Kelompok Layanan"
                                :slices="collect($groups)->map(fn ($g) => ['label' => $g['group']->name, 'value' => $g['total']])->all()" />
            @endif
        </x-ui.card>
    </div>

    {{-- 5. Hasil kerja per Staff --}}
    <x-ui.card :title="'Hasil Kerja Staff: '.$periodLabel" description="Tiket yang masuk pada periode ini dan diambil masing-masing Staff. Ketepatan waktu & penyelesaian hanya dari tiket Selesai."
               class="p-0! md:p-0! [&>header]:px-4 [&>header]:pt-4 md:[&>header]:px-5 md:[&>header]:pt-5">
        @include('admin._staff-performance', ['rows' => $staffPerformance, 'detailed' => true])
    </x-ui.card>

    <p class="mt-4 flex items-start gap-1.5 text-xs text-muted">
        <x-icon name="info" class="mt-0.5 size-4 shrink-0" />
        Angka dihitung dengan rumus KPI yang sama dengan dashboard Admin Fakultas. Rentang tanggal lain, filter per kategori/Staff, dan unduhan CSV/Excel ada di
        <a href="{{ route('dekan.laporan') }}" class="font-semibold text-primary hover:underline">Laporan & Ekspor</a>.
    </p>
</x-layouts.app>
