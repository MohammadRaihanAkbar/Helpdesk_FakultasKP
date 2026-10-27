{{--
    [M6] Setelah registrasi alumni: pendaftaran diterima, akun menunggu verifikasi Admin Fakultas asal.
    $registered (dari session flash, boleh kosong kalau halaman dibuka langsung): name, email, faculty.
--}}
<x-layouts.guest title="Pendaftaran Diterima" width="lg">
    <div class="flex flex-col items-center text-center">
        <span class="grid size-14 place-items-center rounded-full bg-selesai-bg text-selesai-fg">
            <x-icon name="circle-check" class="size-7" />
        </span>
        <h1 class="mt-4 text-2xl font-extrabold">Pendaftaran diterima</h1>
        <p class="mt-2 max-w-md text-[15px] text-muted">
            @if ($registered)
                Terima kasih, <b class="text-ink">{{ $registered['name'] }}</b>. Akun Anda sedang <b class="text-ink">menunggu verifikasi Admin Fakultas{{ $registered['faculty'] ? ' '.$registered['faculty'] : '' }}</b>.
            @else
                Akun Anda sedang <b class="text-ink">menunggu verifikasi Admin Fakultas</b> asal Anda.
            @endif
        </p>
    </div>

    <ol class="mt-6 space-y-3 border-t border-dashed border-line pt-5 text-sm text-muted">
        <li class="flex gap-3">
            <span class="grid size-8 shrink-0 place-items-center rounded-[10px] bg-primary-soft font-heading font-extrabold text-primary">1</span>
            <span>Admin Fakultas mencocokkan data Anda (NIM lama, tahun lulus) dengan data alumni fakultas.</span>
        </li>
        <li class="flex gap-3">
            <span class="grid size-8 shrink-0 place-items-center rounded-[10px] bg-primary-soft font-heading font-extrabold text-primary">2</span>
            <span>Hasilnya dikirim ke email {!! $registered ? '<b class="text-ink">'.e($registered['email']).'</b>' : 'yang Anda daftarkan' !!}. Periksa juga folder Spam.</span>
        </li>
        <li class="flex gap-3">
            <span class="grid size-8 shrink-0 place-items-center rounded-[10px] bg-primary-soft font-heading font-extrabold text-primary">3</span>
            <span>Setelah disetujui, masuk dengan NIM lama atau email beserta password yang baru Anda buat.</span>
        </li>
    </ol>

    <div class="mt-6 flex justify-center">
        <x-ui.button variant="secondary" :href="route('login')" icon="arrow-left">Ke halaman Masuk</x-ui.button>
    </div>
</x-layouts.guest>
