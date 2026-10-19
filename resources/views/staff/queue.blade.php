{{--
    Antrean Tiket Staff (/staff/queue) — US-07, F-TKS-01, F-TKS-09, PRD 4.6.

    - Hanya tiket MENUNGGU di fakultas staff yang Kelompok Layanannya termasuk spesialisasinya.
    - Urutan FIFO: tiket paling lama di atas. Badge merah/kuning = melewati/mendekati SLA.
    - Kategori sensitif "Integritas Dosen": nama pelapor disamarkan sampai tiket diambil.
    - Tombol "Ambil Tiket" = form PATCH ke /staff/tickets/{id}/assign. Kalau staff lain lebih dulu mengambil,
      halaman kembali ke sini dengan pesan merah (409 TICKET_ALREADY_ASSIGNED).
--}}
@php
    use App\Enums\UserType;
@endphp

<x-layouts.app title="Antrean Tiket">
    <x-page-header title="Antrean Tiket"
                   description="Tiket yang menunggu diambil, sesuai spesialisasi layanan Anda. Yang paling lama di atas." />

    @if ($specialties->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="inbox" title="Anda belum memiliki spesialisasi layanan">
                Antrean hanya berisi tiket dari Kelompok Layanan yang menjadi spesialisasi Anda.
                Hubungi Admin Fakultas agar spesialisasi Anda diatur di menu Kelola Staff.
            </x-ui.empty-state>
        </x-ui.card>
    @else
        {{-- Filter: kelompok (dari spesialisasi), jenis pelapor, rentang tanggal, dan pencarian --}}
        <x-ui.card class="mb-4">
            <form method="GET" action="{{ route('staff.queue') }}" class="grid gap-3 md:grid-cols-2 xl:grid-cols-[1.4fr_1fr_1fr_1fr_1.4fr_auto] xl:items-end">
                <x-ui.select name="group" label="Kelompok Layanan" placeholder="Semua spesialisasi saya"
                             :options="$specialties->pluck('name', 'id')->all()" :selected="$filters['group']" />
                <x-ui.select name="type" label="Jenis pelapor" placeholder="Semua"
                             :options="collect(UserType::cases())->mapWithKeys(fn ($type) => [$type->value => $type->label()])->all()"
                             :selected="$filters['type']?->value" />
                <x-ui.input name="from" type="date" label="Dibuat dari" :value="$filters['from']?->format('Y-m-d')" />
                <x-ui.input name="to" type="date" label="Sampai" :value="$filters['to']?->format('Y-m-d')" />
                <x-ui.input name="q" type="search" label="Cari" placeholder="Nomor tiket atau judul" :value="$filters['q']" />
                <div class="flex gap-2">
                    <x-ui.button type="submit" icon="search">Terapkan</x-ui.button>
                    @if ($filtered)
                        <x-ui.button variant="secondary" :href="route('staff.queue')">Reset</x-ui.button>
                    @endif
                </div>
            </form>
        </x-ui.card>

        <p class="mb-3 text-sm text-muted">
            {{ $tickets->total() }} tiket menunggu{{ $filtered ? ' (sesuai filter)' : '' }}.
            Spesialisasi: {{ $specialties->pluck('name')->implode(', ') }}.
        </p>

        <x-ui.card class="p-0! md:p-0!">
            @if ($tickets->isEmpty())
                <div class="p-5">
                    <x-ui.empty-state icon="inbox" :title="$filtered ? 'Tidak ada tiket yang cocok' : 'Antrean kosong'">
                        @if ($filtered)
                            Coba ubah atau reset filter.
                        @else
                            Belum ada tiket yang menunggu di kelompok layanan Anda. Tiket baru akan muncul di sini.
                        @endif
                    </x-ui.empty-state>
                </div>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($tickets as $ticket)
                        <li>
                            <x-staff-ticket-row :ticket="$ticket" :href="route('staff.tickets.show', $ticket)"
                                                :show-reporter="! $ticket->category->isSensitive()">
                                <x-slot:action>
                                    <x-ui.button variant="secondary" :href="route('staff.tickets.show', $ticket)" class="min-h-11! px-4! text-sm!">Lihat</x-ui.button>
                                    <form method="POST" action="{{ route('staff.tickets.assign', $ticket) }}">
                                        @csrf
                                        @method('PATCH')
                                        <x-ui.button type="submit" icon="plus" class="min-h-11! px-4! text-sm!">Ambil Tiket</x-ui.button>
                                    </form>
                                </x-slot:action>
                            </x-staff-ticket-row>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        <div class="mt-4">{{ $tickets->links() }}</div>
    @endif
</x-layouts.app>
