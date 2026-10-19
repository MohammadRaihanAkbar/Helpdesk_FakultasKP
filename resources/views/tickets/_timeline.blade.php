{{--
    Kartu "Riwayat Aktivitas" (US-04, PRD 10.5): Dibuat -> Diambil -> Respons pertama -> Selesai/Ditolak.
    Variabel: $ticket. Opsional: $pings (koleksi PingLog) — hanya dikirim halaman Staff/Admin, karena
    baris Ping tidak terlihat oleh pelapor (catatan PRD 10.5).
--}}
<x-ui.card title="Riwayat Aktivitas">
    <ol class="relative space-y-5 border-l-2 border-line pl-5">
        @foreach ($ticket->timeline() as $step)
            <li class="relative">
                <span @class([
                    'absolute top-1 -left-[27px] size-3 rounded-full ring-4 ring-surface',
                    'bg-selesai-dot' => $step['done'],
                    'bg-line-strong/50' => ! $step['done'],
                ]) aria-hidden="true"></span>
                <p @class(['text-sm font-bold', 'text-ink' => $step['done'], 'text-muted' => ! $step['done']])>{{ $step['label'] }}</p>
                <p class="text-xs text-muted">{{ $step['at']?->translatedFormat('j M Y, H:i') ?? 'Belum' }}</p>
            </li>
        @endforeach
    </ol>

    @if (! empty($pings) && $pings->isNotEmpty())
        <div class="mt-5 border-t border-line pt-4">
            <p class="text-sm font-extrabold text-ink">Ping dari Admin</p>
            <ul class="mt-2 space-y-2 text-sm">
                @foreach ($pings->sortBy('created_at') as $ping)
                    <li class="rounded-control bg-menunggu-bg/60 px-3 py-2">
                        <p class="text-xs text-muted">{{ $ping->created_at?->translatedFormat('j M Y, H:i') }} · {{ $ping->admin?->name }}</p>
                        <p class="text-ink">{{ $ping->message }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</x-ui.card>
