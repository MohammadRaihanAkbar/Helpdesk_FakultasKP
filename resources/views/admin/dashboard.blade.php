{{--
    Dashboard Admin Fakultas (M1: masih kosong). Ringkasan operasional datang di M4.
--}}
<x-layouts.app title="Dashboard Admin Fakultas">
    <x-dashboard-greeting :user="$user" />

    <div class="grid gap-4 lg:grid-cols-3">
        <x-ui.card title="Ringkasan fakultas" class="lg:col-span-2">
            <x-ui.empty-state icon="chart" title="Belum ada data operasional">
                Ringkasan antrean dan kinerja staff {{ $user->faculty?->code }} akan tampil di sini setelah ada tiket masuk.
            </x-ui.empty-state>
        </x-ui.card>

        <x-ui.card title="Fakultas">
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-muted">Nama</dt>
                    <dd class="font-semibold">{{ $user->faculty?->name ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-muted">Kode</dt>
                    <dd class="font-mono font-semibold">{{ $user->faculty?->code ?? '-' }}</dd>
                </div>
            </dl>
        </x-ui.card>
    </div>
</x-layouts.app>
