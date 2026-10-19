{{--
    Tiket Saya — Staff (/staff/tickets) — US-08, F-TKS-03: tiket berstatus Diproses yang sedang saya tangani.
    Diurutkan dari yang paling lama dibuat (paling dekat batas SLA).
--}}
<x-layouts.app title="Tiket Saya">
    <x-page-header title="Tiket Saya" description="Tiket yang sudah Anda ambil dan sedang diproses.">
        <x-slot:action>
            <x-ui.button variant="secondary" :href="route('staff.queue')" icon="inbox">Ke Antrean</x-ui.button>
        </x-slot:action>
    </x-page-header>

    @if ($search !== '')
        <p class="mb-3 text-sm text-muted">
            Hasil pencarian "<b class="text-ink">{{ $search }}</b>" ({{ $tickets->total() }} tiket).
            <a href="{{ route('staff.tickets.index') }}" class="font-semibold text-primary hover:underline">Hapus pencarian</a>
        </p>
    @endif

    <x-ui.card class="p-0! md:p-0!">
        @if ($tickets->isEmpty())
            <div class="p-5">
                <x-ui.empty-state icon="ticket" :title="$search !== '' ? 'Tidak ada tiket yang cocok' : 'Belum ada tiket yang Anda proses'">
                    Ambil tiket dari Antrean Tiket. Tiket yang sudah ditutup pindah ke Riwayat Tiket.
                    <x-slot:action>
                        <x-ui.button :href="route('staff.queue')" icon="inbox">Buka Antrean</x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            </div>
        @else
            <ul class="divide-y divide-line">
                @foreach ($tickets as $ticket)
                    <li>
                        <x-staff-ticket-row :ticket="$ticket" :href="route('staff.tickets.show', $ticket)">
                            <x-slot:meta>
                                <p class="mt-1 text-xs text-muted">
                                    Diambil {{ $ticket->assigned_at?->translatedFormat('j M Y, H:i') }} ·
                                    <span class="font-mono">{{ $ticket->public_comments_count }}/{{ \App\Models\Ticket::THREAD_LIMIT }}</span> pesan
                                </p>
                            </x-slot:meta>
                            <x-slot:action>
                                <x-ui.button :href="route('staff.tickets.show', $ticket)" class="min-h-11! px-4! text-sm!">Kelola</x-ui.button>
                            </x-slot:action>
                        </x-staff-ticket-row>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    <div class="mt-4">{{ $tickets->links() }}</div>
</x-layouts.app>
