{{--
    Dashboard Pelapor (M1: belum ada fitur tiket). Ringkasan tiket & peringkat SLA fakultas datang di M2,
    jadi di sini TIDAK ada angka contoh: hanya banner sapaan, tampilan kosong, dan profil dari SSO/registrasi.
--}}
<x-layouts.app title="Dashboard">
    <x-dashboard-greeting :user="$user" />

    <div class="grid gap-4 lg:grid-cols-[1.5fr_1fr]">
        <x-ui.card title="Tiket Aktif Terbaru">
            <x-ui.empty-state icon="ticket" title="Belum ada tiket">
                Tiket yang Anda buat akan muncul di sini, lengkap dengan status dan target waktunya.
                @if (Route::has('tickets.create'))
                    <x-slot:action>
                        <x-ui.button href="{{ route('tickets.create') }}" icon="plus">Buat Tiket</x-ui.button>
                    </x-slot:action>
                @endif
            </x-ui.empty-state>
        </x-ui.card>

        <x-ui.card title="Profil" description="Data dari SSO kampus atau formulir registrasi.">
            <dl class="divide-y divide-line text-sm">
                <div class="flex justify-between gap-4 py-2.5 first:pt-0">
                    <dt class="text-muted">{{ $user->nidn ? 'NIDN' : 'NIM' }}</dt>
                    <dd class="font-mono font-semibold">{{ $user->nidn ?? $user->nim ?? '-' }}</dd>
                </div>
                @if ($user->graduation_year)
                    <div class="flex justify-between gap-4 py-2.5">
                        <dt class="text-muted">Tahun lulus</dt>
                        <dd class="font-semibold">{{ $user->graduation_year }}</dd>
                    </div>
                @endif
                <div class="flex justify-between gap-4 py-2.5">
                    <dt class="text-muted">Fakultas asal</dt>
                    <dd class="text-right font-semibold">{{ $user->originFaculty?->name ?? '-' }}</dd>
                </div>
                <div class="flex justify-between gap-4 py-2.5 last:pb-0">
                    <dt class="text-muted">Program studi</dt>
                    <dd class="text-right font-semibold">{{ $user->program_studi ?? '-' }}</dd>
                </div>
            </dl>
        </x-ui.card>
    </div>
</x-layouts.app>
