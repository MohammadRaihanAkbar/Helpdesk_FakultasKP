{{--
    [M6] Buat akun lokal Dekan (/super-admin/users/dekan/create).
    Untuk dekan yang BELUM pernah login lewat SSO (jadi belum ada di daftar Pengguna). Kalau dekan sudah pernah
    login SSO, cukup tunjuk akunnya dari daftar Pengguna (tombol "Tunjuk Dekan").
--}}
<x-layouts.app title="Buat Akun Dekan">
    <x-page-header title="Buat Akun Dekan" :back="route('superadmin.users.index')"
                   description="Akun lokal (email + password) dengan peran Dekan: hanya bisa memantau dashboard statistik dan mengunduh laporan fakultasnya." />

    <x-ui.card class="max-w-2xl">
        <form method="POST" action="{{ route('superadmin.users.dekan.store') }}" class="space-y-5">
            @csrf
            <x-ui.input name="name" label="Nama lengkap *" icon="user" maxlength="100" required autocomplete="off" />
            <x-ui.input name="email" label="Email *" type="email" maxlength="150" required autocomplete="off"
                        hint="Dipakai dekan untuk login dan menerima email. Contoh: dekan.fitk@uinssc.ac.id" />
            <x-ui.select name="faculty_id" label="Fakultas *" :options="$facultyOptions" placeholder="Pilih fakultas" required />
            <x-ui.input name="nip" label="NIP (opsional)" inputmode="numeric" maxlength="18"
                        hint="Isi kalau dekan juga akan login lewat SSO dengan NIP. Login SSO itu akan masuk ke akun yang sama (perannya tetap Dekan)." />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.input name="password" label="Password awal *" type="password" icon="lock" required autocomplete="new-password"
                            hint="Minimal 8 karakter. Sampaikan secara pribadi." />
                <x-ui.input name="password_confirmation" label="Ulangi password *" type="password" icon="lock" required autocomplete="new-password" />
            </div>

            <div class="flex flex-col-reverse gap-2 border-t border-line pt-5 sm:flex-row sm:justify-end">
                <x-ui.button variant="secondary" :href="route('superadmin.users.index')">Batal</x-ui.button>
                <x-ui.button type="submit" icon="plus">Buat Akun Dekan</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layouts.app>
