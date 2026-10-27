{{--
    [M6] Verifikasi Alumni (/admin/verifikasi-alumni): pendaftar alumni hidden link yang memilih fakultas admin
    sebagai fakultas asal. Akun mereka belum bisa login sebelum disetujui di sini.

    - Daftar menunggu (terlama di atas): nama, NIM lama, tahun lulus, email, fakultas asal, waktu daftar.
    - Setujui (konfirmasi) / Tolak (alasan wajib; dikirim ke email pendaftar). Keduanya mengirim email ke pendaftar.
    - Riwayat 10 verifikasi terakhir.
--}}
@php
    use App\Enums\AccountStatus;
@endphp

<x-layouts.app title="Verifikasi Alumni">
    <x-page-header title="Verifikasi Alumni"
                   :description="'Pendaftar alumni lulusan 2021 ke bawah dengan fakultas asal '.$faculty->code.'. Cocokkan dengan data alumni fakultas sebelum menyetujui.'" />

    @if ($errors->any())
        <x-ui.alert type="error" class="mb-4">{{ $errors->first() }}</x-ui.alert>
    @endif

    <x-ui.card :title="'Menunggu verifikasi ('.$pending->count().')'" class="mb-4 p-0! md:p-0! [&>header]:px-4 [&>header]:pt-4 md:[&>header]:px-5 md:[&>header]:pt-5">
        @if ($pending->isEmpty())
            <div class="px-4 pb-4 md:px-5 md:pb-5">
                <x-ui.empty-state icon="circle-check" title="Tidak ada pendaftar yang menunggu">
                    Pendaftar baru muncul di sini dan Anda mendapat notifikasi (lonceng + email).
                </x-ui.empty-state>
            </div>
        @else
            <ul class="divide-y divide-line border-t border-line">
                @foreach ($pending as $alumni)
                    <li class="flex flex-col gap-4 px-4 py-4 md:px-5 lg:flex-row lg:items-start">
                        <div class="min-w-0 flex-1">
                            <p class="font-bold text-ink">{{ $alumni->name }}</p>
                            <dl class="mt-1 grid gap-x-6 gap-y-1 text-sm text-muted sm:grid-cols-2">
                                <div><dt class="inline">NIM lama:</dt> <dd class="inline font-mono font-semibold text-ink">{{ $alumni->nim }}</dd></div>
                                <div><dt class="inline">Tahun lulus:</dt> <dd class="inline font-semibold text-ink">{{ $alumni->graduation_year }}</dd></div>
                                <div class="break-all"><dt class="inline">Email:</dt> <dd class="inline text-ink">{{ $alumni->email }}</dd></div>
                                <div><dt class="inline">Fakultas asal:</dt> <dd class="inline text-ink">{{ $faculty->code }}</dd></div>
                                <div class="sm:col-span-2"><dt class="inline">Mendaftar:</dt> <dd class="inline text-ink">{{ $alumni->created_at->translatedFormat('j M Y, H:i') }} ({{ $alumni->created_at->diffForHumans() }})</dd></div>
                            </dl>
                        </div>

                        <div class="flex flex-wrap items-start gap-2 lg:justify-end">
                            <x-ui.confirm-form :action="route('admin.alumni.approve', $alumni)" method="PATCH" compact trigger-variant="secondary" icon="circle-check"
                                               trigger-label="Setujui" title="Setujui akun {{ $alumni->name }}?" confirm-label="Ya, setujui">
                                Akun ini langsung bisa dipakai login dan pendaftar dikabari lewat email {{ $alumni->email }}.
                            </x-ui.confirm-form>

                            {{-- Tolak: alasan wajib, diketik di dialog. --}}
                            <div x-data class="inline-block">
                                <button type="button" @click="$refs.reject.showModal()"
                                        class="inline-flex min-h-10 items-center justify-center gap-2 rounded-control border border-ditolak-dot/40 bg-ditolak-bg px-3 font-heading text-sm font-extrabold text-ditolak-fg hover:brightness-95">
                                    <x-icon name="x" class="size-4" /> Tolak
                                </button>
                                <dialog x-ref="reject" class="m-auto w-[min(32rem,calc(100%-2rem))] rounded-panel border border-line bg-surface p-0 text-ink shadow-pop backdrop:bg-ink/40">
                                    <form method="POST" action="{{ route('admin.alumni.reject', $alumni) }}" class="p-5 md:p-6">
                                        @csrf
                                        @method('PATCH')
                                        <h2 class="text-lg font-extrabold">Tolak pendaftaran {{ $alumni->name }}?</h2>
                                        <p class="mt-1 text-sm text-muted">Alasan dikirim ke email pendaftar dan tampil saat ia mencoba login. Ia boleh mendaftar ulang dengan data yang benar.</p>
                                        <div class="mt-4">
                                            <x-ui.textarea name="reason" label="Alasan penolakan" rows="3" maxlength="500" required
                                                           placeholder="Contoh: NIM tidak ditemukan di data alumni FITK angkatan tersebut." />
                                        </div>
                                        <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                            <x-ui.button variant="secondary" @click="$refs.reject.close()">Batal</x-ui.button>
                                            <x-ui.button type="submit" variant="danger" icon="x">Tolak pendaftaran</x-ui.button>
                                        </div>
                                    </form>
                                </dialog>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    <x-ui.card title="Riwayat verifikasi" description="10 keputusan terakhir.">
        @if ($recent->isEmpty())
            <p class="text-sm text-muted">Belum ada pendaftaran yang diverifikasi.</p>
        @else
            <ul class="divide-y divide-line text-sm">
                @foreach ($recent as $alumni)
                    <li class="flex flex-wrap items-center gap-x-3 gap-y-1 py-2.5">
                        <span class="font-semibold text-ink">{{ $alumni->name }}</span>
                        <span class="font-mono text-xs text-muted">{{ $alumni->nim }}</span>
                        <x-ui.badge :tone="$alumni->account_status === AccountStatus::Approved ? 'success' : 'danger'">{{ $alumni->account_status->label() }}</x-ui.badge>
                        <span class="text-xs text-muted">{{ $alumni->verified_at?->translatedFormat('j M Y, H:i') }}{{ $alumni->verifier ? ' · oleh '.$alumni->verifier->name : '' }}</span>
                        @if ($alumni->account_status === AccountStatus::Rejected && $alumni->rejection_reason)
                            <span class="basis-full text-xs text-muted">Alasan: {{ $alumni->rejection_reason }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
</x-layouts.app>
