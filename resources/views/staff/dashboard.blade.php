{{--
    Dashboard Staff Fakultas (M1: masih kosong). Ringkasan tugas & indikator SLA datang di M3.
--}}
<x-layouts.app title="Dashboard Staff">
    <x-dashboard-greeting :user="$user" />

    <div class="grid gap-4 lg:grid-cols-3">
        <x-ui.card title="Antrean Anda" class="lg:col-span-2">
            <x-ui.empty-state icon="inbox" title="Antrean masih kosong">
                Tiket dari kelompok layanan yang menjadi spesialisasi Anda akan muncul di sini.
            </x-ui.empty-state>
        </x-ui.card>

        <x-ui.card title="Spesialisasi layanan" description="Diatur oleh Admin Fakultas.">
            @if ($user->specialties->isEmpty())
                <p class="text-sm text-muted">Belum ada spesialisasi. Hubungi Admin Fakultas.</p>
            @else
                <ul class="divide-y divide-line text-sm">
                    @foreach ($user->specialties as $category)
                        <li class="py-2.5 first:pt-0 last:pb-0">{{ $category->name }}</li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>
</x-layouts.app>
