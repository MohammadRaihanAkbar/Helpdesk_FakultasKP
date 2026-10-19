{{--
    Registrasi alumni lulus ≤ 2021 lewat hidden link (US-01B). Tidak ditautkan dari halaman publik.
    Field: Nama Lengkap, Email Pribadi, NIM Lama, Tahun Lulus, Fakultas Asal, Password (+ konfirmasi).
--}}
<x-layouts.guest title="Registrasi Alumni" width="lg">
    <h1 class="text-2xl font-extrabold">Registrasi Alumni</h1>
    <p class="mt-1 text-sm text-muted">
        Khusus alumni yang lulus tahun 2021 atau sebelumnya. Lulus 2022 ke atas?
        <a href="{{ route('login') }}" class="font-semibold text-primary underline-offset-4 hover:underline">Masuk dengan NIM</a> melalui SSO kampus.
    </p>

    {{-- Kunci hidden link ikut dikirim di URL action supaya POST juga lolos middleware penjaga. --}}
    <form method="POST" action="{{ route('register.alumni.store', array_filter(['key' => $key])) }}" class="mt-6 grid gap-5 sm:grid-cols-2">
        @csrf

        <div class="sm:col-span-2">
            <x-ui.input name="name" label="Nama Lengkap" autocomplete="name" maxlength="100" required />
        </div>

        <div class="sm:col-span-2">
            <x-ui.input name="email" label="Email Pribadi" type="email" autocomplete="email" maxlength="150"
                        hint="Dipakai untuk login dan menerima kabar tiket." required />
        </div>

        <x-ui.input name="nim" label="NIM Lama" inputmode="numeric" maxlength="20"
                    hint="Hanya angka. Bisa dipakai untuk login selain email." required />

        <x-ui.input name="graduation_year" label="Tahun Lulus" type="number" min="1960" max="2021" inputmode="numeric" required />

        <div class="sm:col-span-2">
            <x-ui.select
                name="origin_faculty_id"
                label="Fakultas Asal"
                :options="$faculties->mapWithKeys(fn ($faculty) => [$faculty->id => $faculty->code.' — '.$faculty->name])"
                placeholder="Pilih fakultas"
                required
            />
        </div>

        <x-ui.input name="password" label="Password" type="password" autocomplete="new-password" minlength="8"
                    hint="Minimal 8 karakter. Password ini khusus aplikasi helpdesk, bukan password SSO." required />

        <x-ui.input name="password_confirmation" label="Ulangi Password" type="password" autocomplete="new-password" required />

        <div class="flex flex-col-reverse gap-3 sm:col-span-2 sm:flex-row sm:justify-end">
            <x-ui.button variant="secondary" href="{{ route('login') }}">Batal</x-ui.button>
            <x-ui.button type="submit">Daftar</x-ui.button>
        </div>
    </form>
</x-layouts.guest>
