{{--
    Kartu "Riwayat Aktivitas" (US-04, PRD 1.2 & 10.5): Dibuat -> Diambil -> Respons pertama -> Selesai/Ditolak,
    disusun dari kolom waktu tabel tickets, DITAMBAH Ping dari ping_logs (urut waktu, seperti mockup 10.5).

    Variabel: $ticket. Opsional: $pings (koleksi PingLog) — hanya dikirim halaman Staff/Admin, karena
    baris Ping tidak terlihat oleh pelapor (catatan PRD 10.5). Satu aksi Ping ke beberapa staff digabung
    menjadi satu baris (PingLog::groupForDisplay).
--}}
@php
    use App\Models\PingLog;

    $steps = collect($ticket->timeline())->map(fn ($step) => $step + ['ping' => null]);
    $pingRows = collect(PingLog::groupForDisplay($pings ?? []))->map(fn ($ping) => [
        'label' => 'Ping dari Admin', 'at' => $ping['at'], 'done' => true, 'ping' => $ping,
    ]);
    // Yang sudah terjadi diurutkan menurut waktu; langkah yang belum terjadi di bawah.
    $events = $steps->where('done', true)->concat($pingRows)->sortBy(fn ($event) => $event['at']->getTimestamp())->values()
        ->concat($steps->where('done', false));
@endphp

<x-ui.card title="Riwayat Aktivitas">
    <ol class="relative space-y-5 border-l-2 border-line pl-5">
        @foreach ($events as $step)
            <li class="relative">
                <span @class([
                    'absolute top-1 -left-[27px] size-3 rounded-full ring-4 ring-surface',
                    'bg-menunggu-dot' => $step['ping'],
                    'bg-selesai-dot' => $step['done'] && ! $step['ping'],
                    'bg-line-strong/50' => ! $step['done'],
                ]) aria-hidden="true"></span>
                <p @class(['text-sm font-bold', 'text-ink' => $step['done'], 'text-muted' => ! $step['done']])>{{ $step['label'] }}</p>
                <p class="text-xs text-muted">{{ $step['at']?->translatedFormat('j M Y, H:i') ?? 'Belum' }}</p>
                @if ($step['ping'])
                    <div class="mt-1.5 rounded-control bg-menunggu-bg/60 px-3 py-2 text-sm">
                        <p class="text-xs text-muted">
                            {{ $step['ping']['admin'] ?? 'Admin' }} → {{ implode(', ', $step['ping']['staff']) ?: 'Staff' }}
                        </p>
                        <p class="text-ink">{{ $step['ping']['message'] ?? 'Mohon tiket ini segera ditindaklanjuti.' }}</p>
                    </div>
                @endif
            </li>
        @endforeach
    </ol>
</x-ui.card>
