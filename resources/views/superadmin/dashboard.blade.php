{{--
    Dashboard Super Admin (M1: masih kosong). Metrik global & celah coverage staff datang di M5.
--}}
<x-layouts.app title="Dashboard Super Admin">
    <x-dashboard-greeting :user="$user" />

    <div class="grid gap-4 lg:grid-cols-3">
        <x-ui.card title="Metrik global" class="lg:col-span-2">
            <x-ui.empty-state icon="landmark" title="Belum ada metrik">
                Ringkasan tiket seluruh fakultas akan tampil di sini setelah ada tiket masuk.
            </x-ui.empty-state>
        </x-ui.card>

        <x-ui.card title="Akun">
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-muted">Email</dt>
                    <dd class="font-semibold break-all">{{ $user->email }}</dd>
                </div>
                <div>
                    <dt class="text-muted">Wewenang</dt>
                    <dd>Master data fakultas dan penunjukan Admin Fakultas.</dd>
                </div>
            </dl>
        </x-ui.card>
    </div>
</x-layouts.app>
