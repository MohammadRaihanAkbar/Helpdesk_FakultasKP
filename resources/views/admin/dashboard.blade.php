{{--
    Dashboard Admin Fakultas. M2: pintasan + cakupan spesialisasi (data asli).
    Ringkasan operasional (antrean, SLA compliance, tren) datang di M4.
--}}
<x-layouts.app title="Dashboard Admin Fakultas">
    <x-dashboard-greeting :user="$user" />

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-ui.card title="Akses cepat">
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ([
                        ['admin.staff.index', 'users', 'Kelola Staff', 'Tambah akun staff, aktif/nonaktif, dan atur spesialisasinya.'],
                        ['admin.services.index', 'sliders', 'Pengaturan Layanan', 'Target SLA, On/Off kategori, dan kategori tambahan fakultas.'],
                    ] as [$route, $icon, $label, $text])
                        <a href="{{ route($route) }}" class="flex items-start gap-3 rounded-card border border-line bg-surface p-4 shadow-sm hover:border-primary hover:bg-primary-soft">
                            <span class="grid size-10 shrink-0 place-items-center rounded-control bg-primary-soft text-primary"><x-icon :name="$icon" /></span>
                            <span>
                                <span class="block font-heading font-extrabold text-ink">{{ $label }}</span>
                                <span class="mt-0.5 block text-sm text-muted">{{ $text }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </x-ui.card>

            <x-ui.card title="Ringkasan fakultas">
                <x-ui.empty-state icon="chart" title="Ringkasan operasional belum tersedia">
                    Kartu antrean, SLA compliance, dan tren tiket {{ $user->faculty?->code }} akan tampil di sini pada pembaruan berikutnya.
                </x-ui.empty-state>
            </x-ui.card>
        </div>

        @if ($coverage->isNotEmpty())
            <x-coverage-summary :coverage="$coverage" />
        @else
            <x-ui.card title="Fakultas">
                <p class="text-sm text-muted">Akun Anda belum ditugaskan ke fakultas. Hubungi Super Admin.</p>
            </x-ui.card>
        @endif
    </div>
</x-layouts.app>
