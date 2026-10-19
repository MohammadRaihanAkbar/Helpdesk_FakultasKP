{{--
    Riwayat Tiket Staff (/staff/history) — US-09, F-TKS-06: tiket Selesai/Ditolak yang pernah saya tangani.
    Setiap baris menampilkan apakah tiket selesai sesuai SLA, dan rating CSAT dari pelapor (bila ada).
    Filter (US-09): rentang tanggal ditutup, status, dan kategori (Kelompok Layanan atau Jenis Masalah).
--}}
@php
    use App\Enums\TicketStatus;
@endphp

<x-layouts.app title="Riwayat Tiket">
    <x-page-header title="Riwayat Tiket" description="Tiket yang pernah Anda tangani dan sudah ditutup (Selesai atau Ditolak)." />

    <x-ui.card class="mb-4">
        <form method="GET" action="{{ route('staff.history') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_1.6fr_auto] lg:items-end">
            <x-ui.input name="from" type="date" label="Ditutup dari" :value="$filters['from']" />
            <x-ui.input name="to" type="date" label="Sampai" :value="$filters['to']" />
            <x-ui.select name="status" label="Status" placeholder="Selesai & Ditolak"
                         :options="[TicketStatus::Selesai->value => 'Selesai', TicketStatus::Ditolak->value => 'Ditolak']"
                         :selected="$filters['status']?->value" />
            <x-ui.select name="category" label="Kategori (Kelompok / Jenis)" placeholder="Semua kategori"
                         :options="$categoryOptions" :selected="$filters['category']" />
            <div class="flex gap-2">
                <x-ui.button type="submit" icon="search">Terapkan</x-ui.button>
                @if (array_filter($filters))
                    <x-ui.button variant="secondary" :href="route('staff.history')">Reset</x-ui.button>
                @endif
            </div>
        </form>
    </x-ui.card>

    <x-ui.card class="p-0! md:p-0!">
        @if ($tickets->isEmpty())
            <div class="p-5">
                <x-ui.empty-state icon="history" :title="array_filter($filters) ? 'Tidak ada tiket yang cocok' : 'Belum ada riwayat'">
                    @if (array_filter($filters))
                        Coba ubah atau reset filter.
                    @else
                        Tiket yang Anda tandai Selesai atau Ditolak akan muncul di sini.
                    @endif
                </x-ui.empty-state>
            </div>
        @else
            <ul class="divide-y divide-line">
                @foreach ($tickets as $ticket)
                    <li>
                        <x-staff-ticket-row :ticket="$ticket" :href="route('staff.history.show', $ticket)">
                            <x-slot:meta>
                                <div class="mt-1.5 flex flex-wrap items-center gap-2 text-sm">
                                    @if ($ticket->status === TicketStatus::Selesai)
                                        <x-sla-badge :ticket="$ticket" />
                                        @if ($ticket->rating)
                                            <x-stars :score="$ticket->rating->score" class="size-4" />
                                        @else
                                            <span class="text-xs text-muted">Belum dinilai pelapor</span>
                                        @endif
                                    @else
                                        <span class="truncate text-ditolak-fg">Alasan: {{ \Illuminate\Support\Str::limit($ticket->rejection_reason, 90) }}</span>
                                    @endif
                                </div>
                            </x-slot:meta>
                        </x-staff-ticket-row>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    <div class="mt-4">{{ $tickets->links() }}</div>
</x-layouts.app>
