{{--
    Riwayat Tiket (/history) — US-05, F-TKU-06: tiket Selesai & Ditolak milik pelapor.
    Rating CSAT menyusul di M3.
--}}
<x-layouts.app title="Riwayat Tiket">
    <x-page-header title="Riwayat Tiket" description="Tiket yang sudah selesai atau ditolak." />

    <x-ui.card class="p-0! md:p-0!">
        @if ($tickets->isEmpty())
            <div class="p-5">
                <x-ui.empty-state icon="history" title="Belum ada riwayat">
                    Tiket akan pindah ke sini setelah staff menandainya Selesai atau Ditolak.
                </x-ui.empty-state>
            </div>
        @else
            <ul class="divide-y divide-line">
                @foreach ($tickets as $ticket)
                    <li>
                        <x-ticket-row :ticket="$ticket" :href="route('history.show', $ticket)" />
                        @if ($ticket->status === \App\Enums\TicketStatus::Ditolak && $ticket->rejection_reason)
                            <p class="-mt-2 px-4 pb-4 text-sm text-ditolak-fg md:px-5">Alasan: {{ \Illuminate\Support\Str::limit($ticket->rejection_reason, 140) }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    <div class="mt-4">{{ $tickets->links() }}</div>
</x-layouts.app>
