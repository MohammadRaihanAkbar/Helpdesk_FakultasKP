{{--
    Halaman login (PRD 10.1, mockup design/a-login.png). Satu kolom untuk NIM / NIDN / Email,
    lihat App\Services\Auth\LoginService.

    Semua pesan gagal login (password salah, SSO tidak bisa dihubungi, akun nonaktif, terlalu banyak
    percobaan) dikirim sebagai error field "login" dan ditampilkan SEKALI di kotak alert di atas form
    (id="login-error", dirujuk aria-describedby milik input "login").
--}}
<x-layouts.guest title="Masuk">
    <x-slot:aside>
        <x-login-showcase />
    </x-slot:aside>

    <div class="flex flex-col items-center text-center">
        <img src="{{ asset('images/logo-uinssc.png') }}" alt="Logo UIN SSC" width="72" height="96" class="h-24 w-[72px]">
        <h1 class="mt-3 text-2xl font-extrabold">Helpdesk Fakultas Terpadu</h1>
        <p class="mt-1 text-sm text-muted">Masuk untuk membuat dan memantau tiket aduan</p>
    </div>

    @error('login')
        <x-ui.alert type="error" id="login-error" class="mt-6">{{ $message }}</x-ui.alert>
    @enderror

    <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4">
        @csrf

        <x-ui.input
            name="login"
            label="NIM / NIDN / Email"
            icon="user"
            hint="Mahasiswa & alumni lulus 2022 ke atas: NIM. Dosen: NIDN. Staff, admin, dan alumni lulus 2021 ke bawah: email."
            autocomplete="username"
            autocapitalize="none"
            spellcheck="false"
            required
            autofocus
            :show-error="false"
        />

        <x-ui.input name="password" label="Password" type="password" icon="lock" autocomplete="current-password" required />

        <label class="inline-flex min-h-12 cursor-pointer items-center gap-2.5 text-base">
            <input type="checkbox" name="remember" value="1" @checked(old('remember'))
                   class="size-5 rounded border-line-strong accent-primary">
            Ingat sesi saya
        </label>

        <x-ui.button type="submit" icon="shield-check" class="w-full">Masuk dengan SSO Kampus</x-ui.button>
    </form>

    <ul class="mt-6 space-y-3 border-t border-dashed border-line pt-5 text-sm text-muted">
        <li class="flex gap-3">
            <span class="grid size-8 shrink-0 place-items-center rounded-[10px] bg-accent-soft text-accent-ink"><x-icon name="key" class="size-4" /></span>
            <span>
                <b class="text-ink">Lupa sandi?</b>
                <a href="{{ $passwordResetUrl }}" class="font-semibold text-primary underline underline-offset-4" rel="noopener">Reset melalui portal SSO kampus</a>, bukan di halaman ini.
            </span>
        </li>
        <li class="flex gap-3">
            <span class="grid size-8 shrink-0 place-items-center rounded-[10px] bg-primary-soft text-primary"><x-icon name="graduation-cap" class="size-4" /></span>
            <span><b class="text-ink">Alumni lulus ≥ 2022:</b> masuk langsung dengan NIM dan sandi SSO Anda.</span>
        </li>
        <li class="flex gap-3">
            <span class="grid size-8 shrink-0 place-items-center rounded-[10px] bg-primary-soft text-primary"><x-icon name="link" class="size-4" /></span>
            <span><b class="text-ink">Alumni lulus ≤ 2021:</b> daftar lewat tautan khusus yang dibagikan oleh fakultas Anda.</span>
        </li>
    </ul>
</x-layouts.guest>
