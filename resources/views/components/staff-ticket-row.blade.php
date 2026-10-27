{{--
    Satu baris tiket di halaman Staff (Antrean, Tiket Saya, Riwayat, Dashboard).

    Pemakaian:
      <x-staff-ticket-row :ticket="$ticket" :href="route('staff.tickets.show', $ticket)" :show-reporter="true">
          <x-slot:action> ...tombol Ambil Tiket... </x-slot:action>     (opsional)
          <x-slot:meta> ...rating / alasan... </x-slot:meta>            (opsional)
      </x-staff-ticket-row>

    Butuh relasi: category.parent, reporter (bila show-reporter), handler (eager load supaya tidak N+1 query).
    show-reporter = false -> nama pelapor disamarkan (kategori sensitif "Integritas Dosen" yang belum diambil).
    Baris ini sengaja BUKAN satu <a> besar, karena tombol "Ambil Tiket" adalah form dan form tidak boleh
    berada di dalam tautan.
--}}
@props(['ticket', 'href', 'showReporter' => true])

<div {{ $attributes->merge(['class' => 'flex flex-col gap-3 px-4 py-4 md:flex-row md:items-center md:justify-between md:px-5']) }}>
    <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ $href }}" class="font-mono text-sm font-semibold text-accent-ink hover:underline">{{ $ticket->ticket_number }}</a>
            <x-ui.badge :status="$ticket->status" />
            <x-sla-badge :ticket="$ticket" :quiet="true" />
            @if ($ticket->category->isSensitive())
                <x-ui.badge tone="danger">Sensitif</x-ui.badge>
            @endif
        </div>
        <a href="{{ $href }}" class="mt-1 block truncate font-bold text-ink hover:text-primary">{{ $ticket->title }}</a>
        <p class="mt-0.5 truncate text-sm text-muted">{{ $ticket->category->pathLabel() }}</p>
        <p class="mt-0.5 text-sm text-muted">
            <x-icon name="user" class="inline size-3.5 align-[-2px]" />
            @if ($showReporter)
                {{ $ticket->reporter?->name }}
            @else
                <span class="italic">Identitas dirahasiakan</span>
            @endif
            · {{ $ticket->reporter_type->label() }}
        </p>
        {{ $meta ?? '' }}
    </div>

    <div class="flex shrink-0 flex-col gap-2 text-sm md:items-end md:text-right">
        <p class="text-muted">Target <span class="font-mono font-semibold text-ink">{{ $ticket->sla_target_hours }} jam</span></p>
        <p class="flex items-center gap-1 text-xs text-muted">
            <x-icon name="clock" class="size-3.5" />
            @if ($ticket->isOpen())
                Dibuat {{ $ticket->created_at->translatedFormat('j M Y, H:i') }} ({{ $ticket->created_at->diffForHumans() }})
            @else
                Ditutup {{ $ticket->resolved_at?->translatedFormat('j M Y, H:i') }}
            @endif
        </p>
        @isset($action)
            <div class="flex flex-wrap gap-2 md:justify-end">{{ $action }}</div>
        @endisset
    </div>
</div>
