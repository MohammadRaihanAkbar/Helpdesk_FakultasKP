{{--
    Form tambah / ubah fakultas (US-17): Kode, Nama, Slug, Deskripsi, Aktif.
    Saat TAMBAH, sistem otomatis menyalin Katalog Kategori Layanan Standar (12 kategori + audiens + SLA awal).
--}}
@php
    $isEdit = $faculty->exists;
    $title = $isEdit ? "Ubah Fakultas {$faculty->code}" : 'Tambah Fakultas';
@endphp

<x-layouts.app :title="$title">
    <x-page-header :title="$title" :back="route('superadmin.faculties.index')"
                   :description="$isEdit ? $faculty->name : 'Fakultas baru langsung mendapat Katalog Kategori Layanan Standar.'" />

    <x-ui.card class="max-w-3xl">
        <form method="POST" action="{{ $isEdit ? route('superadmin.faculties.update', $faculty) : route('superadmin.faculties.store') }}" class="space-y-5">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            @unless ($isEdit)
                <x-ui.alert type="info" title="Katalog standar otomatis">
                    Setelah disimpan, sistem menyalin {{ count(\App\Services\CategoryCatalogService::CATALOG) }} Kelompok Layanan standar beserta Jenis Masalah, audiens, dan usulan target waktu awal.
                    Pengaturan kategori & target waktu selanjutnya wewenang Admin Fakultas.
                </x-ui.alert>
            @endunless

            <div class="grid gap-5 sm:grid-cols-[12rem_1fr]">
                <x-ui.input name="code" label="Kode *" :value="$faculty->code" maxlength="20" required autocomplete="off" class="font-mono uppercase"
                            hint="Unik, huruf/angka. Contoh: FITK." />
                <x-ui.input name="name" label="Nama fakultas *" :value="$faculty->name" maxlength="150" required autocomplete="off"
                            hint="Contoh: Fakultas Ilmu Tarbiyah dan Keguruan" />
            </div>

            <x-ui.input name="slug" label="Slug" :value="$faculty->slug" maxlength="50" autocomplete="off" class="font-mono"
                        hint="Dipakai di alamat (URL). Kosongkan untuk memakai kode huruf kecil, mis. fitk." />

            @if ($isEdit)
                <p class="-mt-2 flex items-start gap-1.5 text-sm text-muted">
                    <x-icon name="info" class="mt-0.5 size-4 shrink-0" />
                    Kode juga dicocokkan dengan kode fakultas dari SSO kampus. Bila kode diubah, sesuaikan <code class="font-mono">faculty_code_map</code> di <code class="font-mono">config/sso.php</code>.
                </p>
            @endif

            <x-ui.textarea name="description" label="Deskripsi" :value="$faculty->description" :maxlength="1000" rows="3" />

            <div>
                <input type="hidden" name="is_active" value="0">
                <label class="inline-flex min-h-12 cursor-pointer items-center gap-3">
                    <input type="checkbox" name="is_active" value="1" class="size-5 accent-primary" @checked(old('is_active', $faculty->is_active ?? true))>
                    <span><span class="font-bold">Aktif</span> <span class="text-sm text-muted">(muncul di pilihan Fakultas Tujuan form buat tiket)</span></span>
                </label>
            </div>

            <div class="flex flex-col-reverse gap-2 border-t border-line pt-5 sm:flex-row sm:justify-end">
                <x-ui.button variant="secondary" :href="route('superadmin.faculties.index')">Batal</x-ui.button>
                <x-ui.button type="submit" icon="circle-check">{{ $isEdit ? 'Simpan Perubahan' : 'Tambah Fakultas' }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layouts.app>
