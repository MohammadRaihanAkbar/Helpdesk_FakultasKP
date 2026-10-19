{{--
    Tiket Saya (/tickets) — US-04, F-TKU-04: hanya tiket Menunggu & Diproses milik pelapor.
--}}
<x-layouts.app title="Tiket Saya">
    <x-page-header title="Tiket Saya" description="Tiket yang masih menunggu diambil atau sedang diproses staff.">
        <x-slot:action>
            <x-ui.button variant="cta" :href="route('tickets.create')" icon="plus">Buat Tiket Baru</x-ui.button>
        </x-slot:action>
    </x-page-header>

    @if ($search)
        <p class="mb-3 text-sm text-muted">
            Hasil pencarian "<b class="text-ink">{{ $search }}</b>" ({{ $tickets->total() }} tiket).
            <a href="{{ route('tickets.index') }}" class="font-semibold text-primary hover:underline">Hapus pencarian</a>
        </p>
    @endif

    <x-ui.card class="p-0! md:p-0!">
        @if ($tickets->isEmpty())
            <div class="p-5">
                <x-ui.empty-state icon="ticket" :title="$search ? 'Tidak ada tiket yang cocok' : 'Belum ada tiket aktif'">
                    @if ($search)
                        Coba cari dengan nomor tiket lengkap, misalnya TKT-202610-0001.
                    @else
                        Tiket yang sudah selesai atau ditolak ada di menu Riwayat Tiket.
                    @endif
                    <x-slot:action>
                        <x-ui.button :href="route('tickets.create')" icon="plus">Buat Tiket</x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            </div>
        @else
            <ul class="divide-y divide-line">
                @foreach ($tickets as $ticket)
                    <li><x-ticket-row :ticket="$ticket" :href="route('tickets.show', $ticket)" /></li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    <div class="mt-4">{{ $tickets->links() }}</div>
</x-layouts.app>
