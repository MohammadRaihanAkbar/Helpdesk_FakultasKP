{{--
    Dashboard Pelapor (/dashboard) — PRD 10.2, US-02, mockup A.
    Semua angka berasal dari tiket milik pelapor yang login (tanpa angka contoh).
--}}
@php
    $cards = [
        ['label' => 'Total Tiket', 'value' => $stats['total'], 'icon' => 'ticket', 'note' => 'semua tiket Anda', 'tone' => 'bg-accent-soft text-accent-ink'],
        ['label' => 'Menunggu', 'value' => $stats['menunggu'], 'icon' => 'hourglass', 'note' => 'belum diambil staff', 'tone' => 'bg-menunggu-bg text-menunggu-fg'],
        ['label' => 'Diproses', 'value' => $stats['diproses'], 'icon' => 'refresh', 'note' => 'sedang ditangani', 'tone' => 'bg-diproses-bg text-diproses-fg'],
        ['label' => 'Selesai', 'value' => $stats['selesai'], 'icon' => 'circle-check', 'note' => 'sudah ditutup', 'tone' => 'bg-selesai-bg text-selesai-fg'],
    ];
@endphp

<x-layouts.app title="Dashboard">
    <x-dashboard-greeting :user="$user">
        @if ($stats['total'] === 0)
            Belum ada tiket. Laporkan kendala akademik, administrasi, fasilitas, atau jaringan ke fakultas tujuan.
        @else
            Anda memiliki <b>{{ $stats['menunggu'] }} tiket menunggu</b> dan <b>{{ $stats['diproses'] }} sedang diproses</b>.
        @endif
        <x-slot:action>
            <div class="text-center">
                <x-ui.button variant="cta" :href="route('tickets.create')" icon="plus" class="px-6">Buat Tiket Baru</x-ui.button>
                <p class="mt-1.5 text-xs text-on-primary/80">Laporkan kendala ke fakultas tujuan</p>
            </div>
        </x-slot:action>
    </x-dashboard-greeting>

    <section aria-label="Ringkasan tiket" class="mb-4 grid grid-cols-2 gap-3 md:mb-5 md:gap-4 lg:grid-cols-4">
        @foreach ($cards as $card)
            <div class="rounded-card border border-line bg-surface/85 p-4 shadow-card">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-sm font-bold text-muted">{{ $card['label'] }}</p>
                    <span class="grid size-9 place-items-center rounded-control {{ $card['tone'] }}"><x-icon :name="$card['icon']" class="size-4" /></span>
                </div>
                <p class="mt-1 flex items-baseline gap-2">
                    <span class="font-heading text-3xl font-extrabold text-ink">{{ $card['value'] }}</span>
                    <span class="text-xs text-muted">{{ $card['note'] }}</span>
                </p>
            </div>
        @endforeach
    </section>

    <div class="grid gap-4 lg:grid-cols-[1.5fr_1fr]">
        <x-ui.card title="Tiket Terbaru" description="5 tiket terakhir yang Anda buat." class="p-0! md:p-0! [&>header]:px-4 [&>header]:pt-4 md:[&>header]:px-5 md:[&>header]:pt-5">
            @if ($latest->isNotEmpty())
                <x-slot:action>
                    <a href="{{ route('tickets.index') }}" class="inline-flex min-h-10 items-center gap-1 text-sm font-bold text-primary hover:underline">
                        Tiket Saya <x-icon name="chevron-right" class="size-4" />
                    </a>
                </x-slot:action>
            @endif

            @if ($latest->isEmpty())
                <div class="px-4 pb-4 md:px-5 md:pb-5">
                    <x-ui.empty-state icon="ticket" title="Belum ada tiket">
                        Tiket yang Anda buat akan muncul di sini, lengkap dengan status dan target waktunya.
                        <x-slot:action>
                            <x-ui.button href="{{ route('tickets.create') }}" icon="plus">Buat Tiket</x-ui.button>
                        </x-slot:action>
                    </x-ui.empty-state>
                </div>
            @else
                <ul class="divide-y divide-line border-t border-line">
                    @foreach ($latest as $ticket)
                        <li><x-ticket-row :ticket="$ticket" :href="$ticket->isOpen() ? route('tickets.show', $ticket) : route('history.show', $ticket)" /></li>
                    @endforeach
                </ul>
                <p class="flex items-center gap-1.5 border-t border-line px-4 py-3 text-xs text-muted md:px-5">
                    <x-icon name="info" class="size-4" /> Tiket yang selesai atau ditolak pindah ke <a href="{{ route('history.index') }}" class="font-semibold text-primary hover:underline">Riwayat Tiket</a>.
                </p>
            @endif
        </x-ui.card>

        <x-ui.card title="Profil" description="Data dari SSO kampus atau formulir registrasi.">
            <dl class="divide-y divide-line text-sm">
                <div class="flex justify-between gap-4 py-2.5 first:pt-0">
                    <dt class="text-muted">{{ $user->nidn ? 'NIDN' : 'NIM' }}</dt>
                    <dd class="font-mono font-semibold">{{ $user->nidn ?? $user->nim ?? '-' }}</dd>
                </div>
                <div class="flex justify-between gap-4 py-2.5">
                    <dt class="text-muted">Jenis pelapor</dt>
                    <dd class="font-semibold">{{ $user->user_type?->label() ?? '-' }}</dd>
                </div>
                @if ($user->graduation_year)
                    <div class="flex justify-between gap-4 py-2.5">
                        <dt class="text-muted">Tahun lulus</dt>
                        <dd class="font-semibold">{{ $user->graduation_year }}</dd>
                    </div>
                @endif
                <div class="flex justify-between gap-4 py-2.5">
                    <dt class="text-muted">Fakultas asal</dt>
                    <dd class="text-right font-semibold">{{ $user->originFaculty?->name ?? '-' }}</dd>
                </div>
                <div class="flex justify-between gap-4 py-2.5 last:pb-0">
                    <dt class="text-muted">Program studi</dt>
                    <dd class="text-right font-semibold">{{ $user->program_studi ?? '-' }}</dd>
                </div>
            </dl>
        </x-ui.card>
    </div>
</x-layouts.app>
