{{--
    Form Buat Tiket Baru (/tickets/new) — PRD 10.7, US-03, US-05B.

    Dropdown bertingkat dengan Alpine.js (tanpa request tambahan):
      1. Fakultas Tujuan  (terisi fakultas asal pelapor)
      2. Kelompok Layanan (hanya yang aktif & sesuai jenis pelapor di fakultas itu)
      3. Jenis Masalah    (HANYA muncul kalau kelompok punya Jenis Masalah)
    Ganti fakultas -> pilihan 2 & 3 dikosongkan. Ganti kelompok -> pilihan 3 dikosongkan.

    Data pilihan ($tree) di-embed sebagai JSON: { faculty_id: [ {id, name, sla_hours, children: [...]}, ... ] }.
    Yang dikirim ke server hanya category_id (kategori LEAF). Server tetap memvalidasi ulang semuanya
    (INVALID_CATEGORY), karena isi form bisa dimanipulasi dari browser.

    ?from={id} (Buat Ulang Tiket, US-05): fakultas, kategori, judul, deskripsi, dan lampiran tiket lama terisi.
--}}
@php
    $titleMax = \App\Http\Requests\Tickets\StoreTicketRequest::TITLE_MAX;
    $descriptionMax = \App\Http\Requests\Tickets\StoreTicketRequest::DESCRIPTION_MAX;
    $maxFiles = \App\Services\Tickets\AttachmentStorage::MAX_FILES;
    $copyIds = array_map('intval', old('copy_attachments', $from?->attachments->pluck('id')->all() ?? []));
    $fieldClass = 'block min-h-13 w-full rounded-control border bg-surface px-3 text-base text-ink shadow-sm focus:border-accent focus:ring-4 focus:ring-accent/30 focus:outline-none';
@endphp

<x-layouts.app title="Buat Tiket">
    <x-page-header title="Buat Tiket Baru">
        <x-slot:action>
            <x-ui.badge tone="primary" class="px-3! py-1.5! text-sm!">Anda melapor sebagai: {{ $user->user_type->label() }}</x-ui.badge>
        </x-slot:action>
    </x-page-header>

    @if ($from)
        <x-ui.alert type="warning" title="Buat ulang dari {{ $from->ticket_number }}" class="mb-4">
            Tiket lama ditolak karena <b>salah fakultas/kategori</b>. Data lama sudah terisi,
            <b>periksa dan perbaiki Fakultas Tujuan serta kategorinya</b> sebelum mengirim. Tiket baru akan mendapat nomor baru.
        </x-ui.alert>
    @endif

    <form method="POST" action="{{ route('tickets.store') }}" enctype="multipart/form-data" novalidate
          class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]"
          x-data="{
              tree: @js((object) $tree),
              faculty: @js($initial['faculty']),
              group: @js($initial['group']),
              category: @js($initial['category']),
              get groups() { return this.tree[this.faculty] || []; },
              get selectedGroup() { return this.groups.find(g => String(g.id) === String(this.group)) || null; },
              get children() { return this.selectedGroup ? this.selectedGroup.children : []; },
              get selectedChild() { return this.children.find(c => String(c.id) === String(this.category)) || null; },
              get leafId() {
                  if (! this.selectedGroup) return '';
                  return this.children.length ? (this.selectedChild ? this.selectedChild.id : '') : this.selectedGroup.id;
              },
              get leaf() { return this.children.length ? this.selectedChild : this.selectedGroup; },
              init() {
                  {{-- Nilai awal yang tidak ada di pilihan (mis. kategori lama sudah nonaktif) dikosongkan. --}}
                  if (! this.selectedGroup) { this.group = ''; this.category = ''; }
                  else if (! this.selectedChild) { this.category = ''; }
              },
          }">
        @csrf
        @if ($from)
            <input type="hidden" name="from" value="{{ $from->id }}">
        @endif
        <input type="hidden" name="category_id" :value="leafId">

        <x-ui.card>
            <div class="space-y-5">
                {{-- 1. Fakultas Tujuan --}}
                <div>
                    <label for="faculty_id" class="mb-1.5 block text-sm font-bold text-ink">1. Fakultas Tujuan *</label>
                    <select id="faculty_id" name="faculty_id" x-model="faculty" @change="group = ''; category = ''" required
                            @class([$fieldClass, 'border-line' => ! $errors->has('faculty_id'), 'border-ditolak-fg' => $errors->has('faculty_id')])>
                        <option value="">Pilih fakultas tujuan</option>
                        @foreach ($faculties as $faculty)
                            <option value="{{ $faculty->id }}" @selected($initial['faculty'] === (string) $faculty->id)>
                                {{ $faculty->code }} · {{ $faculty->name }}{{ $originFaculty?->is($faculty) ? ' (fakultas asal Anda)' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1.5 text-sm text-muted">Tiket masuk ke antrean staff fakultas ini.</p>
                    @error('faculty_id')
                        <p class="mt-1.5 flex items-start gap-1.5 text-sm text-ditolak-fg"><x-icon name="circle-alert" class="mt-0.5 size-4" /><span>{{ $message }}</span></p>
                    @enderror
                </div>

                {{-- 2. Kelompok Layanan --}}
                <div>
                    <label for="group_id" class="mb-1.5 block text-sm font-bold text-ink">2. Kelompok Layanan *</label>
                    <select id="group_id" name="group_id" x-model="group" @change="category = ''" :disabled="! faculty"
                            @class([$fieldClass, 'disabled:bg-sunken disabled:text-muted', 'border-line' => ! $errors->has('category_id'), 'border-ditolak-fg' => $errors->has('category_id')])>
                        <option value="" x-text="faculty ? 'Pilih kelompok layanan' : 'Pilih fakultas dulu'">Pilih kelompok layanan</option>
                        <template x-for="g in groups" :key="g.id">
                            <option :value="g.id" x-text="g.name" :selected="String(g.id) === String(group)"></option>
                        </template>
                    </select>
                    <p class="mt-1.5 text-sm text-muted" x-show="faculty && ! groups.length" x-cloak>Belum ada layanan aktif untuk {{ mb_strtolower($user->user_type->label()) }} di fakultas ini.</p>
                    <p class="mt-1.5 text-sm text-muted" x-show="selectedGroup && selectedGroup.description" x-text="selectedGroup?.description"></p>
                </div>

                {{-- 3. Jenis Masalah: hanya kalau kelompok punya anak --}}
                <div x-show="children.length" x-cloak>
                    <label for="child_id" class="mb-1.5 block text-sm font-bold text-ink">3. Jenis Masalah *</label>
                    <select id="child_id" x-model="category"
                            @class([$fieldClass, 'border-line' => ! $errors->has('category_id'), 'border-ditolak-fg' => $errors->has('category_id')])>
                        <option value="">Pilih jenis masalah</option>
                        <template x-for="c in children" :key="c.id">
                            <option :value="c.id" x-text="c.name" :selected="String(c.id) === String(category)"></option>
                        </template>
                    </select>
                    <p class="mt-1.5 text-sm text-muted" x-show="selectedChild && selectedChild.description" x-text="selectedChild?.description"></p>
                </div>

                @error('category_id')
                    <p class="flex items-start gap-1.5 text-sm text-ditolak-fg"><x-icon name="circle-alert" class="mt-0.5 size-4" /><span>{{ $message }}</span></p>
                @enderror

                <p x-show="leaf" x-cloak class="flex items-center gap-2 rounded-control bg-selesai-bg px-4 py-3 text-sm text-selesai-fg">
                    <x-icon name="clock" class="size-4" />
                    <span>Target waktu penanganan: <b class="font-mono" x-text="leaf ? leaf.sla_hours + ' jam' : ''"></b> setelah tiket diambil staff.</span>
                </p>

                <hr class="border-line">

                <x-ui.input name="title" label="Judul *" :value="$from?->title" :maxlength="$titleMax" required
                            hint="Ringkas dan jelas, maks. {{ $titleMax }} karakter. Contoh: KRS semester ganjil belum di-ACC Dosen PA" />

                <x-ui.textarea name="description" label="Deskripsi *" :value="$from?->description" :maxlength="$descriptionMax" rows="7" required
                               hint="Ceritakan kronologinya: sejak kapan, apa yang sudah dicoba, dan bantuan yang Anda harapkan." />

                {{-- Lampiran --}}
                <div x-data="{ files: [], tooBig() { return this.files.some(f => f.size > 5 * 1024 * 1024) } }">
                    <span class="mb-1.5 block text-sm font-bold text-ink">Lampiran <span class="font-normal text-muted">(opsional · JPG/PNG/PDF · maks. 5 MB · maks. {{ $maxFiles }} berkas)</span></span>

                    @if ($from && $from->attachments->isNotEmpty())
                        <fieldset class="mb-3 rounded-control border border-line bg-sunken/50 p-3">
                            <legend class="px-1 text-sm font-semibold">Lampiran dari tiket lama</legend>
                            @foreach ($from->attachments as $old)
                                <label class="flex min-h-11 items-center gap-3">
                                    <input type="checkbox" name="copy_attachments[]" value="{{ $old->id }}" class="size-5 accent-primary" @checked(in_array($old->id, $copyIds, true))>
                                    <x-icon name="paperclip" class="size-4 text-muted" />
                                    <span class="text-sm">{{ $old->file_name }} <span class="text-muted">({{ \App\Support\Format::fileSize($old->file_size) }})</span></span>
                                </label>
                            @endforeach
                        </fieldset>
                    @endif

                    <label for="attachments" class="flex min-h-24 cursor-pointer flex-col items-center justify-center gap-1 rounded-control border-2 border-dashed border-line bg-surface px-4 py-5 text-center hover:border-accent">
                        <x-icon name="paperclip" class="size-6 text-accent" />
                        <span class="font-semibold text-primary">Pilih berkas</span>
                        <span class="text-xs text-muted">Bisa memilih beberapa sekaligus</span>
                    </label>
                    <input id="attachments" name="attachments[]" type="file" multiple accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"
                           class="sr-only" @change="files = Array.from($event.target.files)">
                    <ul class="mt-2 space-y-1 text-sm" x-show="files.length" x-cloak>
                        <template x-for="f in files" :key="f.name">
                            <li class="flex items-center gap-2"><x-icon name="file" class="size-4 text-muted" /><span x-text="f.name"></span>
                                <span class="text-muted" x-text="'(' + (f.size / 1024 / 1024).toFixed(1) + ' MB)'"></span></li>
                        </template>
                    </ul>
                    <p x-show="files.length > {{ $maxFiles }}" x-cloak class="mt-1.5 text-sm text-ditolak-fg">Maksimal {{ $maxFiles }} berkas.</p>
                    <p x-show="tooBig()" x-cloak class="mt-1.5 text-sm text-ditolak-fg">Ada berkas yang lebih dari 5 MB.</p>
                    @foreach (array_merge($errors->get('attachments'), $errors->get('attachments.*')) as $messages)
                        @foreach ((array) $messages as $message)
                            <p class="mt-1.5 flex items-start gap-1.5 text-sm text-ditolak-fg"><x-icon name="circle-alert" class="mt-0.5 size-4" /><span>{{ $message }}</span></p>
                        @endforeach
                    @endforeach
                </div>
            </div>
        </x-ui.card>

        <div class="space-y-4">
            <x-ui.card title="Sebelum mengirim">
                <ul class="space-y-2.5 text-sm text-muted">
                    <li class="flex gap-2"><x-icon name="circle-check" class="size-4 shrink-0 text-selesai-dot" /> Pilih kelompok yang paling sesuai supaya tiket sampai ke staff yang tepat.</li>
                    <li class="flex gap-2"><x-icon name="circle-check" class="size-4 shrink-0 text-selesai-dot" /> Tiket tidak kedaluwarsa: statusnya "Menunggu" sampai diambil staff.</li>
                    <li class="flex gap-2"><x-icon name="circle-check" class="size-4 shrink-0 text-selesai-dot" /> Anda bisa menambah informasi lewat thread diskusi (maks. 20 pesan).</li>
                </ul>
            </x-ui.card>
            <x-ui.button type="submit" variant="cta" icon="send" class="w-full">Kirim Tiket</x-ui.button>
            <x-ui.button variant="secondary" :href="route('tickets.index')" class="w-full">Batal</x-ui.button>
        </div>
    </form>
</x-layouts.app>
