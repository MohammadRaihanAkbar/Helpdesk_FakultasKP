{{--
    Dashboard Staff Fakultas (/staff/dashboard) — US-06, PRD 10.3, mockup A. Semua angka dari data asli.

    5 kartu PRD + 1 kartu tambahan (rata-rata rating), daftar tiket Mendekati/Melewati SLA,
    daftar tiket yang sedang saya proses, pintasan ke Antrean & Tiket Saya, dan spesialisasi.
--}}
@php
    $cards = [
        ['label' => 'Antrean', 'value' => $stats['queue'], 'icon' => 'inbox', 'note' => 'menunggu diambil', 'tone' => 'bg-menunggu-bg text-menunggu-fg', 'href' => route('staff.queue')],
        ['label' => 'Sedang Saya Proses', 'value' => $stats['in_progress'], 'icon' => 'refresh', 'note' => 'tiket diproses', 'tone' => 'bg-diproses-bg text-diproses-fg', 'href' => route('staff.tickets.index')],
        ['label' => 'Selesai Hari Ini', 'value' => $stats['resolved_today'], 'icon' => 'circle-check', 'note' => 'oleh Anda', 'tone' => 'bg-selesai-bg text-selesai-fg', 'href' => route('staff.history')],
        ['label' => 'Ditolak Bulan Ini', 'value' => $stats['rejected_month'], 'icon' => 'x', 'note' => now()->translatedFormat('F'), 'tone' => 'bg-ditolak-bg text-ditolak-fg', 'href' => route('staff.history', ['status' => 'ditolak', 'month' => now()->format('Y-m')])],
        ['label' => 'Ditangani Bulan Ini', 'value' => $stats['handled_month'], 'icon' => 'ticket', 'note' => $stats['resolved_month'].' selesai', 'tone' => 'bg-accent-soft text-accent-ink', 'href' => route('staff.history', ['month' => now()->format('Y-m')])],
        ['label' => 'Rata-rata Rating', 'value' => $stats['rating_count'] ? number_format($stats['rating_avg'], 1, ',', '.') : '–', 'icon' => 'star', 'note' => $stats['rating_count'] ? 'dari '.$stats['rating_count'].' rating' : 'belum ada rating', 'tone' => 'bg-accent-soft text-accent-ink', 'href' => null],
    ];
@endphp

<x-layouts.app title="Dashboard Staff">
    <x-dashboard-greeting :user="$user">
        Anda bertugas di {{ $user->faculty?->name ?? 'fakultas yang belum ditentukan' }}.
        @if ($user->specialties->isEmpty())
            Anda belum memiliki spesialisasi layanan, jadi antrean Anda kosong. Hubungi Admin Fakultas.
        @else
            Ada <b>{{ $stats['queue'] }} tiket menunggu</b> di antrean Anda dan <b>{{ $stats['in_progress'] }} sedang Anda proses</b>.
        @endif
        <x-slot:action>
            <div class="flex flex-col gap-2 sm:flex-row md:flex-col">
                <x-ui.button variant="cta" :href="route('staff.queue')" icon="inbox" class="px-6">Buka Antrean</x-ui.button>
                <a href="{{ route('staff.tickets.index') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-control border border-on-primary/30 px-5 text-sm font-bold text-on-primary hover:bg-on-primary/10">
                    <x-icon name="ticket" class="size-4" /> Tiket Saya
                </a>
            </div>
        </x-slot:action>
    </x-dashboard-greeting>

    <section aria-label="Ringkasan tugas" class="mb-4 grid grid-cols-2 gap-3 md:mb-5 md:grid-cols-3 md:gap-4 xl:grid-cols-6">
        @foreach ($cards as $card)
            <{{ $card['href'] ? 'a' : 'div' }} @if ($card['href']) href="{{ $card['href'] }}" @endif
                class="rounded-card border border-line bg-surface/85 p-4 shadow-card {{ $card['href'] ? 'hover:border-primary/40 hover:bg-surface' : '' }}">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-sm font-bold text-muted">{{ $card['label'] }}</p>
                    <span class="grid size-9 shrink-0 place-items-center rounded-control {{ $card['tone'] }}"><x-icon :name="$card['icon']" class="size-4" /></span>
                </div>
                <p class="mt-1 font-heading text-3xl font-extrabold text-ink">{{ $card['value'] }}</p>
                <p class="text-xs text-muted">{{ $card['note'] }}</p>
            </{{ $card['href'] ? 'a' : 'div' }}>
        @endforeach
    </section>

    <div class="grid gap-4 lg:grid-cols-[1.5fr_1fr]">
        <div class="min-w-0 space-y-4">
            <x-ui.card title="Tiket Mendekati / Melewati SLA" description="Dari antrean dan tiket yang sedang Anda proses." class="p-0! md:p-0! [&>header]:px-4 [&>header]:pt-4 md:[&>header]:px-5 md:[&>header]:pt-5">
                @if ($slaAlerts->isEmpty())
                    <div class="px-4 pb-4 md:px-5 md:pb-5">
                        <x-ui.empty-state icon="circle-check" title="Semua tiket masih dalam target SLA">
                            Tiket yang sisa waktunya kurang dari 25% target akan ditandai kuning, yang sudah lewat ditandai merah.
                        </x-ui.empty-state>
                    </div>
                @else
                    <ul class="divide-y divide-line border-t border-line">
                        @foreach ($slaAlerts as $ticket)
                            <li>
                                <x-staff-ticket-row :ticket="$ticket" :href="route('staff.tickets.show', $ticket)"
                                                    :show-reporter="$ticket->handler_id === $user->id || ! $ticket->category->isSensitive()" />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>

            <x-ui.card title="Tiket Saya (Sedang Diproses)" class="p-0! md:p-0! [&>header]:px-4 [&>header]:pt-4 md:[&>header]:px-5 md:[&>header]:pt-5">
                @if ($inProgress->isNotEmpty())
                    <x-slot:action>
                        <a href="{{ route('staff.tickets.index') }}" class="inline-flex min-h-10 items-center gap-1 text-sm font-bold text-primary hover:underline">
                            Semua <x-icon name="chevron-right" class="size-4" />
                        </a>
                    </x-slot:action>
                @endif
                @if ($inProgress->isEmpty())
                    <div class="px-4 pb-4 md:px-5 md:pb-5">
                        <x-ui.empty-state icon="ticket" title="Belum ada tiket yang Anda proses">
                            Ambil tiket dari antrean untuk mulai menangani.
                            <x-slot:action><x-ui.button :href="route('staff.queue')" icon="inbox">Buka Antrean</x-ui.button></x-slot:action>
                        </x-ui.empty-state>
                    </div>
                @else
                    <ul class="divide-y divide-line border-t border-line">
                        @foreach ($inProgress as $ticket)
                            <li><x-staff-ticket-row :ticket="$ticket" :href="route('staff.tickets.show', $ticket)" /></li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>

        <x-ui.card title="Spesialisasi layanan" description="Diatur oleh Admin Fakultas. Antrean Anda berisi tiket dari kelompok ini.">
            @if ($user->specialties->isEmpty())
                <p class="text-sm text-muted">Belum ada spesialisasi. Hubungi Admin Fakultas.</p>
            @else
                <ul class="divide-y divide-line text-sm">
                    @foreach ($user->specialties->sortBy('name') as $category)
                        <li class="flex items-center justify-between gap-2 py-2.5 first:pt-0 last:pb-0">
                            <a href="{{ route('staff.queue', ['group' => $category->id]) }}" class="font-semibold hover:text-primary hover:underline">{{ $category->name }}</a>
                            @if ($category->isSensitive())
                                <x-ui.badge tone="danger">Sensitif</x-ui.badge>
                            @elseif (! $category->is_active)
                                <x-ui.badge>Nonaktif</x-ui.badge>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>
</x-layouts.app>
