{{--
    Tambah Staff Baru (PRD 4.7, US-12): Nama Lengkap, Email, Password Awal.
    Fakultas tidak dipilih: akun otomatis masuk fakultas admin yang membuat.
--}}
<x-layouts.app title="Tambah Staff">
    <x-page-header title="Tambah Staff Baru" :back="route('admin.staff.index')"
                   description="Akun akan terdaftar sebagai Staff {{ $faculty->code }} ({{ $faculty->name }})." />

    <x-ui.card class="max-w-2xl">
        <form method="POST" action="{{ route('admin.staff.store') }}" class="space-y-5">
            @csrf
            <x-ui.input name="name" label="Nama lengkap *" icon="user" maxlength="255" required autocomplete="off" />
            <x-ui.input name="email" label="Email *" type="email" maxlength="255" required autocomplete="off"
                        hint="Dipakai staff untuk login. Contoh: nama.staff@uinssc.ac.id" />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.input name="password" label="Password awal *" type="password" icon="lock" required autocomplete="new-password"
                            hint="Minimal 8 karakter. Sampaikan ke staff secara pribadi." />
                <x-ui.input name="password_confirmation" label="Ulangi password *" type="password" icon="lock" required autocomplete="new-password" />
            </div>

            <div class="flex flex-col-reverse gap-2 border-t border-line pt-5 sm:flex-row sm:justify-end">
                <x-ui.button variant="secondary" :href="route('admin.staff.index')">Batal</x-ui.button>
                <x-ui.button type="submit" icon="plus">Buat Akun Staff</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layouts.app>
