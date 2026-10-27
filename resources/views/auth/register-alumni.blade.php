{{--
    Registrasi alumni lulus ≤ 2021 lewat hidden link (US-01B). Tidak ditautkan dari halaman publik.
    Field: Nama Lengkap, Email Pribadi, NIM Lama, Tahun Lulus, Fakultas Asal, Password (+ konfirmasi).
    [M6] + captcha penjumlahan (gambar SVG buatan sendiri, tombol "Ganti soal" dengan Alpine). Akun baru menunggu
    verifikasi Admin Fakultas asal sebelum bisa dipakai login.
--}}
@php
    $captchaUrl = route('register.alumni.captcha', array_filter(['key' => $key]));
@endphp
<x-layouts.guest title="Registrasi Alumni" width="lg">
    <h1 class="text-2xl font-extrabold">Registrasi Alumni</h1>
    <p class="mt-1 text-sm text-muted">
        Khusus alumni yang lulus tahun 2021 atau sebelumnya. Lulus 2022 ke atas?
        <a href="{{ route('login') }}" class="font-semibold text-primary underline-offset-4 hover:underline">Masuk dengan NIM</a> melalui SSO kampus.
    </p>
    <x-ui.alert type="info" class="mt-4">
        Setelah mendaftar, akun Anda <b>diverifikasi dulu oleh Admin Fakultas asal</b>. Anda bisa login setelah menerima email persetujuan.
    </x-ui.alert>

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
                :options="$faculties->mapWithKeys(fn ($faculty) => [$faculty->id => $faculty->code.' · '.$faculty->name])"
                placeholder="Pilih fakultas"
                required
            />
        </div>

        <x-ui.input name="password" label="Password" type="password" autocomplete="new-password" minlength="8"
                    hint="Minimal 8 karakter. Password ini khusus aplikasi helpdesk, bukan password SSO." required />

        <x-ui.input name="password_confirmation" label="Ulangi Password" type="password" autocomplete="new-password" required />

        @if (config('helpdesk.captcha.enabled'))
            {{-- [M6] Captcha: gambar soal dari server. "Ganti soal" memuat ulang gambar dengan ?r=waktu (soal baru).
                 Sengaja lewat @click (bukan :src) supaya gambar tidak diminta dua kali saat halaman dibuka: setiap
                 permintaan gambar membuat soal baru, jadi permintaan ganda bisa membuat gambar & jawaban tidak cocok. --}}
            <div class="sm:col-span-2" x-data>
                <p class="mb-1.5 text-sm font-bold text-ink">Verifikasi: hitung soal pada gambar</p>
                <div class="flex flex-wrap items-center gap-3">
                    <img src="{{ $captchaUrl }}" x-ref="captcha" data-base="{{ $captchaUrl }}"
                         alt="Soal penjumlahan captcha" width="240" height="72" class="h-[72px] w-60 rounded-control border border-line">
                    <button type="button" @click="$refs.captcha.src = $refs.captcha.dataset.base + ($refs.captcha.dataset.base.includes('?') ? '&' : '?') + 'r=' + Date.now()" class="inline-flex min-h-11 items-center gap-2 rounded-control border border-line bg-surface px-3 text-sm font-bold text-primary hover:bg-sunken">
                        <x-icon name="refresh" class="size-4" /> Ganti soal
                    </button>
                </div>
                <div class="mt-3 max-w-60">
                    <x-ui.input name="captcha" label="Jawaban" inputmode="numeric" autocomplete="off" maxlength="3" required
                                hint="Soal berlaku 10 menit dan hanya untuk satu kali kirim." />
                </div>
            </div>
        @endif

        <div class="flex flex-col-reverse gap-3 sm:col-span-2 sm:flex-row sm:justify-end">
            <x-ui.button variant="secondary" href="{{ route('login') }}">Batal</x-ui.button>
            <x-ui.button type="submit">Daftar</x-ui.button>
        </div>
    </form>
</x-layouts.guest>
