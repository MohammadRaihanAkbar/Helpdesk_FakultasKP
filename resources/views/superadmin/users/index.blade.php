{{--
    Daftar Pengguna (/super-admin/users) — US-18, F-SPA-02, F-SPA-04, PRD 7.4.

    - Read-only untuk semua akun: nama, email, NIM/NIP, peran, jenis pelapor, fakultas.
    - Cari NIM / NIP / email / nama (kotak pencarian top bar juga mengarah ke sini) + filter peran.
    - Aksi HANYA untuk Pelapor & Admin Fakultas: Tunjuk Admin (pilih fakultas), Pindah fakultas, Cabut Admin.
      Akun Staff dikelola Admin Fakultas; akun Super Admin tidak bisa diubah dari sini.
--}}
@php
    use App\Enums\Role;
@endphp

<x-layouts.app title="Pengguna">
    <x-page-header title="Pengguna" description="Seluruh akun terdaftar (hanya baca). Super Admin hanya bisa menunjuk dan mencabut Admin Fakultas." />

    @if ($errors->hasAny(['faculty_id', 'action']))
        <x-ui.alert type="error" class="mb-4">{{ $errors->first('faculty_id') ?: $errors->first('action') }}</x-ui.alert>
    @endif

    <x-ui.card class="mb-4">
        <form method="GET" action="{{ route('superadmin.users.index') }}" class="grid gap-3 sm:grid-cols-[1fr_14rem_auto] sm:items-end">
            <x-ui.input name="q" type="search" label="Cari" placeholder="NIM, NIP, email, atau nama" :value="$filters['q']" icon="search" />
            <x-ui.select name="role" label="Peran" placeholder="Semua peran"
                         :options="collect(Role::cases())->mapWithKeys(fn ($role) => [$role->value => $role->label()])->all()"
                         :selected="$filters['role']?->value" />
            <div class="flex gap-2">
                <x-ui.button type="submit" icon="search">Cari</x-ui.button>
                @if ($filters['q'] !== '' || $filters['role'])
                    <x-ui.button variant="secondary" :href="route('superadmin.users.index')">Reset</x-ui.button>
                @endif
            </div>
        </form>
    </x-ui.card>

    <p class="mb-3 text-sm text-muted">{{ $users->total() }} pengguna{{ $filters['q'] !== '' || $filters['role'] ? ' sesuai pencarian' : '' }}.</p>

    <x-ui.card class="p-0! md:p-0!">
        @if ($users->isEmpty())
            <div class="p-5">
                <x-ui.empty-state icon="users" title="Tidak ada pengguna yang cocok">
                    Coba kata kunci lain. Dosen dicari dengan NIP, mahasiswa dan alumni dengan NIM.
                </x-ui.empty-state>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[56rem] text-left text-sm">
                    <thead class="border-b border-line bg-sunken/60 text-xs font-extrabold tracking-wide text-muted uppercase">
                        <tr>
                            <th scope="col" class="px-5 py-3">Pengguna</th>
                            <th scope="col" class="px-3 py-3">NIM / NIP</th>
                            <th scope="col" class="px-3 py-3">Peran</th>
                            <th scope="col" class="px-3 py-3">Fakultas</th>
                            <th scope="col" class="px-5 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($users as $member)
                            @php($identity = $member->identity())
                            <tr @class(['align-top', 'opacity-70' => ! $member->is_active])>
                                <td class="px-5 py-3.5">
                                    <p class="font-bold text-ink">{{ $member->name }}</p>
                                    <p class="break-all text-muted">{{ $member->email }}</p>
                                </td>
                                <td class="px-3 py-3.5">
                                    @if ($identity)
                                        <span class="text-xs text-muted">{{ $identity[0] }}</span>
                                        <span class="block font-mono font-semibold">{{ $identity[1] }}</span>
                                    @else
                                        <span class="text-muted">–</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3.5">
                                    <x-ui.badge :tone="match ($member->role) { Role::SuperAdmin => 'danger', Role::Admin => 'primary', Role::Staff => 'info', default => 'neutral' }">{{ $member->role->label() }}</x-ui.badge>
                                    @if ($member->user_type)
                                        <span class="mt-1 block text-xs text-muted">{{ $member->user_type->label() }}{{ $member->graduation_year ? ' '.$member->graduation_year : '' }}</span>
                                    @endif
                                    @unless ($member->is_active)
                                        <span class="mt-1 block text-xs text-ditolak-fg">nonaktif</span>
                                    @endunless
                                </td>
                                <td class="px-3 py-3.5">
                                    @if ($member->faculty)
                                        <span class="font-semibold">{{ $member->faculty->code }}</span> <span class="text-xs text-muted">bertugas</span>
                                    @elseif ($member->originFaculty)
                                        <span class="font-semibold">{{ $member->originFaculty->code }}</span> <span class="text-xs text-muted">asal</span>
                                    @else
                                        <span class="text-muted">–</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5">
                                    @can('changeRole', $member)
                                        <div class="flex flex-wrap items-start justify-end gap-2">
                                            <form method="POST" action="{{ route('superadmin.users.role', $member) }}" class="flex flex-wrap items-center justify-end gap-2">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="action" value="appoint">
                                                <label for="faculty-{{ $member->id }}" class="sr-only">Fakultas untuk {{ $member->name }}</label>
                                                <select id="faculty-{{ $member->id }}" name="faculty_id" required
                                                        class="min-h-10 max-w-48 rounded-control border border-line bg-surface px-2 text-sm text-ink shadow-sm focus:border-accent focus:ring-4 focus:ring-accent/30 focus:outline-none">
                                                    <option value="">Pilih fakultas…</option>
                                                    @foreach ($facultyOptions as $id => $label)
                                                        <option value="{{ $id }}" @selected($member->faculty_id === $id)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="inline-flex min-h-10 items-center rounded-control bg-primary-soft px-3 font-heading text-sm font-extrabold text-primary hover:brightness-95">
                                                    {{ $member->isAdmin() ? 'Pindah' : 'Tunjuk Admin' }}<span class="sr-only"> {{ $member->name }}</span>
                                                </button>
                                            </form>
                                            @if ($member->isAdmin())
                                                <x-ui.confirm-form :action="route('superadmin.users.role', $member)" method="PATCH" compact
                                                                   trigger-label="Cabut Admin" title="Cabut peran Admin {{ $member->name }}?" confirm-label="Ya, cabut">
                                                    <x-slot:fields><input type="hidden" name="action" value="revoke"></x-slot:fields>
                                                    Akun ini kembali menjadi Pelapor dan tidak bisa membuka halaman Admin {{ $member->faculty?->code }} lagi.
                                                    @if (! $member->user_type)
                                                        Akun ini tidak punya jenis pelapor (bukan akun SSO), jadi setelah dicabut ia tidak bisa membuat tiket.
                                                    @endif
                                                </x-ui.confirm-form>
                                            @endif
                                        </div>
                                    @else
                                        <p class="text-right text-xs text-muted">
                                            {{ match (true) {
                                                $member->id === auth()->id() => 'Akun Anda',
                                                $member->isStaff() => 'Dikelola Admin Fakultas',
                                                default => '–',
                                            } }}
                                        </p>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>

    <div class="mt-4">{{ $users->links() }}</div>

</x-layouts.app>
