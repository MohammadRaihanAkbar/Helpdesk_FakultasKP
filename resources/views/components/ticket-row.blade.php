{{--
    Satu baris tiket di daftar (Tiket Saya, Riwayat, Dashboard), mengikuti mockup A "Tiket Aktif Terbaru".

    Pemakaian: <x-ticket-row :ticket="$ticket" :href="route('tickets.show', $ticket)" />
    Butuh relasi: category.parent, faculty, handler (eager load supaya tidak N+1 query).

    Info SLA (US-04): tiket Menunggu -> "Menunggu diambil Staff" TANPA hitung mundur (tidak ada auto-expire).
--}}
@props(['ticket', 'href'])

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'group flex flex-col gap-3 px-4 py-4 hover:bg-sunken/60 focus-visible:bg-sunken/60 md:flex-row md:items-center md:justify-between md:px-5']) }}>
    <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-2">
            <span class="font-mono text-sm font-semibold text-accent-ink">{{ $ticket->ticket_number }}</span>
            <x-ui.badge :status="$ticket->status" />
            <x-ui.badge>{{ $ticket->faculty->code }}</x-ui.badge>
        </div>
        <p class="mt-1 truncate font-bold text-ink group-hover:text-primary">{{ $ticket->title }}</p>
        <p class="mt-0.5 truncate text-sm text-muted">{{ $ticket->category->pathLabel() }}</p>
    </div>

    <div class="shrink-0 text-sm md:text-right">
        <p class="text-muted">SLA <span class="font-mono font-semibold text-ink">{{ $ticket->sla_target_hours }} jam</span></p>
        <p class="mt-0.5 font-semibold">
            @switch($ticket->status)
                @case(\App\Enums\TicketStatus::Menunggu)
                    <span class="text-menunggu-fg">Menunggu diambil Staff</span>
                    @break
                @case(\App\Enums\TicketStatus::Diproses)
                    <span class="text-diproses-fg">Ditangani {{ $ticket->handler?->name ?? 'Staff' }}</span>
                    @break
                @case(\App\Enums\TicketStatus::Selesai)
                    <span class="text-selesai-fg">Selesai {{ $ticket->resolved_at?->translatedFormat('j M Y') }}</span>
                    @break
                @default
                    <span class="text-ditolak-fg">Ditolak {{ $ticket->resolved_at?->translatedFormat('j M Y') }}</span>
            @endswitch
        </p>
        <p class="mt-0.5 flex items-center gap-1 text-xs text-muted md:justify-end">
            <x-icon name="clock" class="size-3.5" />
            <span>Dibuat {{ $ticket->created_at->translatedFormat('j M Y, H:i') }}</span>
        </p>
    </div>
</a>
