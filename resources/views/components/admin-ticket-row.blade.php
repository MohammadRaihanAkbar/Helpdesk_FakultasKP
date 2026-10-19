{{--
    Satu baris tiket di halaman Admin Fakultas (Monitor Antrean, dashboard "Tiket Menganggur").

    Pemakaian: <x-admin-ticket-row :ticket="$ticket" :recipients="$recipientCounts[$ticket->id] ?? 0" />

    Menampilkan: nomor, status, badge SLA, judul, kategori, pelapor (Admin boleh melihat identitas kategori
    sensitif, PRD 2.7 aturan 10), Staff penangan, kolom "Waktu Menunggu" (US-16), jumlah Ping, tombol Lihat & Ping.
    Butuh relasi: category.parent, reporter, handler. Opsional: ping_logs_count (withCount).
--}}
@props(['ticket', 'recipients' => 0])

@php
    use App\Enums\TicketStatus;
    use App\Support\Format;

    $href = route('admin.tickets.show', $ticket);
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col gap-3 px-4 py-4 md:flex-row md:items-center md:justify-between md:px-5']) }}>
    <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ $href }}" class="font-mono text-sm font-semibold text-accent-ink hover:underline">{{ $ticket->ticket_number }}</a>
            <x-ui.badge :status="$ticket->status" />
            <x-sla-badge :ticket="$ticket" :quiet="true" />
            @if ($ticket->category->isSensitive())
                <x-ui.badge tone="danger">Sensitif</x-ui.badge>
            @endif
            @if (($ticket->ping_logs_count ?? 0) > 0)
                <x-ui.badge tone="warning">{{ $ticket->ping_logs_count }}× di-Ping</x-ui.badge>
            @endif
        </div>
        <a href="{{ $href }}" class="mt-1 block truncate font-bold text-ink hover:text-primary">{{ $ticket->title }}</a>
        <p class="mt-0.5 truncate text-sm text-muted">{{ $ticket->category->pathLabel() }}</p>
        <p class="mt-0.5 text-sm text-muted">
            <x-icon name="user" class="inline size-3.5 align-[-2px]" /> {{ $ticket->reporter?->name }} · {{ $ticket->reporter_type->label() }}
            · Penangan:
            @if ($ticket->handler)
                <span class="font-semibold text-ink">{{ $ticket->handler->name }}</span>@if (! $ticket->handler->is_active) <span class="text-ditolak-fg">(nonaktif)</span>@endif
            @else
                <span class="text-menunggu-fg">belum diambil</span>
            @endif
        </p>
    </div>

    <div class="flex shrink-0 flex-col gap-2 text-sm md:items-end md:text-right">
        @if ($ticket->isOpen())
            <p class="text-muted">
                {{ $ticket->status === TicketStatus::Menunggu ? 'Waktu menunggu' : 'Umur tiket' }}
                <span @class(['font-mono font-semibold', 'text-ditolak-fg' => $ticket->slaState() === 'overdue', 'text-ink' => $ticket->slaState() !== 'overdue'])>{{ Format::duration($ticket->created_at, now()) }}</span>
                <span class="text-xs">/ SLA {{ $ticket->sla_target_hours }} jam</span>
            </p>
            @if ($ticket->assigned_at)
                <p class="text-xs text-muted">Diambil {{ $ticket->assigned_at->diffForHumans() }}</p>
            @else
                <p class="text-xs text-muted">Dibuat {{ $ticket->created_at->translatedFormat('j M Y, H:i') }}</p>
            @endif
        @else
            <p class="text-muted">SLA <span class="font-mono font-semibold text-ink">{{ $ticket->sla_target_hours }} jam</span></p>
            <p class="text-xs text-muted">Ditutup {{ $ticket->resolved_at?->translatedFormat('j M Y, H:i') }}</p>
        @endif
        <div class="flex flex-wrap items-start gap-2 md:justify-end">
            <x-ui.button variant="secondary" :href="$href" class="min-h-11! px-4! text-sm!">Lihat</x-ui.button>
            <x-ping-button :ticket="$ticket" :recipients="$recipients" />
        </div>
    </div>
</div>
