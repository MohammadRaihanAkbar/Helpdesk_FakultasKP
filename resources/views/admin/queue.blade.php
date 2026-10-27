{{--
    Monitor Antrean Fakultas (/admin/queue) — US-16, F-ADM-05, US-14 (tombol Ping).

    - Bawaan: tiket AKTIF (Menunggu & Diproses), tertua di atas = yang paling lama menganggur.
    - Badge merah "Melewati SLA", kuning "Mendekati SLA" (helper SLA M3: Ticket::slaState()).
    - Kolom "Waktu menunggu" = sejak tiket dibuat (SLA juga dihitung dari created_at).
    - Admin hanya memantau & mengirim Ping. TIDAK ADA tombol ambil/pindah/tutup tiket (PRD 2.3).
--}}
@php
    use App\Enums\UserType;
    use App\Http\Controllers\Admin\QueueController;
@endphp

<x-layouts.app title="Monitor Antrean">
    <x-page-header title="Monitor Antrean"
                   description="Pantau seluruh tiket fakultas Anda. Kirim Ping ke Staff bila tiket terlalu lama menganggur." />

    <x-ui.card class="mb-4">
        <form method="GET" action="{{ route('admin.queue') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.select name="status" label="Status" :options="QueueController::STATUS_OPTIONS" :selected="$filters['status']" />
            <x-ui.select name="sla" label="Status Batas Waktu" placeholder="Semua" :options="QueueController::SLA_OPTIONS" :selected="$filters['sla']" />
            <x-ui.select name="category" label="Kelompok / Jenis Masalah" placeholder="Semua kategori" :options="$categoryOptions" :selected="$filters['category']" />
            <x-ui.select name="type" label="Jenis pelapor" placeholder="Semua"
                         :options="collect(UserType::cases())->mapWithKeys(fn ($type) => [$type->value => $type->label()])->all()"
                         :selected="$filters['type']?->value" />
            <x-ui.select name="staff" label="Staff penangan" placeholder="Semua staff"
                         :options="$staffOptions->mapWithKeys(fn ($s) => [$s->id => $s->name.($s->is_active ? '' : ' (nonaktif)')])->all()"
                         :selected="$filters['staff']" />
            <x-ui.input name="from" type="date" label="Dibuat dari" :value="$filters['from']?->format('Y-m-d')" />
            <x-ui.input name="to" type="date" label="Sampai" :value="$filters['to']?->format('Y-m-d')" />
            <x-ui.input name="q" type="search" label="Cari" placeholder="Nomor tiket atau judul" :value="$filters['q']" />
            <div class="flex gap-2 sm:col-span-2 lg:col-span-4">
                <x-ui.button type="submit" icon="search">Terapkan</x-ui.button>
                @if ($filtered)
                    <x-ui.button variant="secondary" :href="route('admin.queue')">Reset</x-ui.button>
                @endif
            </div>
        </form>
    </x-ui.card>

    <p class="mb-3 text-sm text-muted">
        {{ $tickets->total() }} tiket{{ $filtered ? ' sesuai filter' : ' aktif' }}. Urutan: paling lama dibuat di atas.
    </p>

    <x-ui.card class="p-0! md:p-0!">
        @if ($tickets->isEmpty())
            <div class="p-5">
                <x-ui.empty-state icon="inbox" :title="$filtered ? 'Tidak ada tiket yang cocok' : 'Tidak ada tiket aktif'">
                    @if ($filtered)
                        Coba ubah atau reset filter.
                    @else
                        Semua tiket sudah ditangani. Tiket baru akan muncul di sini.
                    @endif
                </x-ui.empty-state>
            </div>
        @else
            <ul class="divide-y divide-line">
                @foreach ($tickets as $ticket)
                    <li @class(['bg-ditolak-bg/30' => $ticket->slaState() === 'overdue'])>
                        <x-admin-ticket-row :ticket="$ticket" :recipients="$recipientCounts[$ticket->id] ?? 0" />
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    <div class="mt-4">{{ $tickets->links() }}</div>
</x-layouts.app>
