{{--
    Detail Tiket + Linimasa + Thread (/tickets/{id} dan /history/{id}) — PRD 10.5, US-04, US-05.

    - Linimasa: Dibuat -> Diambil Staff (+ nama) -> Respons pertama -> Selesai/Ditolak.
    - Thread: maks. 20 pesan publik. Pelapor TIDAK PERNAH melihat catatan internal (difilter di controller).
      Pesan staff diberi label "Staff" dan warna berbeda.
    - Tiket ditutup: alasan penolakan / bukti penyelesaian; tombol "Buat Ulang Tiket" bila ditolak karena salah fakultas/kategori.
    - Tiket Selesai tanpa rating: form rating CSAT bintang 1–5 (M3). Rating yang sudah ada ditampilkan.
--}}
@php
    $limit = \App\Models\Ticket::THREAD_LIMIT;
    $full = $publicCount >= $limit;
    $backRoute = $ticket->isOpen() ? route('tickets.index') : route('history.index');
@endphp

<x-layouts.app :title="$ticket->ticket_number">
    <a href="{{ $backRoute }}" class="mb-3 inline-flex min-h-10 items-center gap-1.5 text-sm font-semibold text-primary hover:underline">
        <x-icon name="arrow-left" class="size-4" /> Kembali ke {{ $ticket->isOpen() ? 'Tiket Saya' : 'Riwayat Tiket' }}
    </a>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="min-w-0 space-y-4">
            {{-- Ringkasan tiket --}}
            <x-ui.card>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-mono font-semibold text-accent-ink">{{ $ticket->ticket_number }}</span>
                    <x-ui.badge :status="$ticket->status" />
                    @if ($ticket->category->isSensitive())
                        <x-ui.badge tone="danger">Sensitif</x-ui.badge>
                    @endif
                </div>
                <h1 class="mt-2 text-xl font-extrabold text-ink md:text-2xl">{{ $ticket->title }}</h1>

                <dl class="mt-4 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-muted">Fakultas tujuan</dt><dd class="font-semibold">{{ $ticket->faculty->code }} — {{ $ticket->faculty->name }}</dd></div>
                    <div><dt class="text-muted">Kategori</dt><dd class="font-semibold">{{ $ticket->category->pathLabel() }}</dd></div>
                    <div><dt class="text-muted">Target SLA</dt><dd class="font-mono font-semibold">{{ $ticket->sla_target_hours }} jam</dd></div>
                    <div><dt class="text-muted">Dibuat</dt><dd class="font-semibold">{{ $ticket->created_at->translatedFormat('j M Y, H:i') }}</dd></div>
                    <div class="sm:col-span-2">
                        <dt class="text-muted">Ditangani oleh</dt>
                        <dd class="font-semibold">
                            @if ($ticket->handler)
                                {{ $ticket->handler->name }}
                            @else
                                <span class="text-menunggu-fg">Menunggu diambil Staff</span>
                            @endif
                        </dd>
                    </div>
                </dl>

                <div class="mt-5 border-t border-line pt-4">
                    <h2 class="text-sm font-extrabold text-ink">Deskripsi</h2>
                    <p class="mt-1.5 text-[15px] leading-relaxed whitespace-pre-line text-ink">{{ $ticket->description }}</p>
                </div>

                @if ($ticket->attachments->isNotEmpty())
                    <div class="mt-4">
                        <h2 class="text-sm font-extrabold text-ink">Lampiran</h2>
                        @include('enduser.tickets._files', ['files' => $ticket->attachments, 'route' => 'attachments.show'])
                    </div>
                @endif
            </x-ui.card>

            {{-- Hasil penanganan (tiket ditutup) --}}
            @if ($ticket->status === \App\Enums\TicketStatus::Ditolak)
                <x-ui.card title="Alasan penolakan">
                    <p class="text-[15px] whitespace-pre-line text-ditolak-fg">{{ $ticket->rejection_reason }}</p>
                    @if ($canRecreate)
                        <div class="mt-4 flex flex-col gap-3 rounded-control bg-sunken/60 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-sm text-muted">Tiket ini salah tujuan. Buat tiket baru dengan data yang sama, lalu perbaiki fakultas/kategorinya.</p>
                            <x-ui.button :href="route('tickets.create', ['from' => $ticket->id])" icon="refresh" class="shrink-0">Buat Ulang Tiket</x-ui.button>
                        </div>
                    @endif
                </x-ui.card>
            @elseif ($ticket->status === \App\Enums\TicketStatus::Selesai)
                <x-ui.card title="Bukti penyelesaian" description="Diunggah staff saat menutup tiket.">
                    @if ($ticket->resolutionProofs->isEmpty())
                        <p class="text-sm text-muted">Tidak ada berkas bukti.</p>
                    @else
                        @include('enduser.tickets._files', ['files' => $ticket->resolutionProofs, 'route' => 'proofs.show'])
                    @endif
                    @if ($ticket->rating)
                        <div class="mt-4 border-t border-line pt-4">
                            @include('tickets._rating', ['rating' => $ticket->rating, 'label' => 'Penilaian Anda'])
                        </div>
                    @elseif ($canRate)
                        {{-- Form rating CSAT (US-05): bintang 1–5 + komentar opsional maks. 500 karakter, sekali saja. --}}
                        <form method="POST" action="{{ route('tickets.rating.store', $ticket) }}" id="rating"
                              class="mt-4 space-y-3 border-t border-line pt-4" x-data="{ score: {{ (int) old('score', 0) }}, hover: 0 }">
                            @csrf
                            <fieldset>
                                <legend class="text-sm font-extrabold text-ink">Beri penilaian layanan</legend>
                                <p class="text-sm text-muted">Seberapa puas Anda dengan penanganan tiket ini? Rating hanya bisa diberikan satu kali.</p>
                                <div class="mt-2 flex items-center gap-1" @mouseleave="hover = 0">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <label class="cursor-pointer rounded-control p-1 focus-within:ring-4 focus-within:ring-accent/30" @mouseenter="hover = {{ $i }}">
                                            <input type="radio" name="score" value="{{ $i }}" class="sr-only" x-model.number="score" @checked((int) old('score') === $i) required>
                                            <x-icon name="star" class="size-8 text-accent" x-bind:class="(hover || score) >= {{ $i }} ? 'fill-current' : 'text-line-strong'" />
                                            <span class="sr-only">{{ $i }} bintang</span>
                                        </label>
                                    @endfor
                                    <span class="ml-2 font-mono text-sm text-muted" x-text="score ? score + '/5' : ''"></span>
                                </div>
                                @error('score')
                                    <p class="mt-1 text-sm text-ditolak-fg">{{ $message }}</p>
                                @enderror
                            </fieldset>
                            <x-ui.textarea name="comment" label="Komentar (opsional)" :maxlength="\App\Services\Tickets\RatingService::COMMENT_MAX" rows="3"
                                           placeholder="Ceritakan pengalaman Anda…" />
                            <x-ui.button type="submit" icon="star">Kirim Rating</x-ui.button>
                        </form>
                    @endif
                </x-ui.card>
            @endif

            {{-- Thread diskusi --}}
            <x-ui.card id="thread">
                <header class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="text-[17px] font-extrabold text-ink">Thread Diskusi</h2>
                    <span @class(['rounded-full px-2.5 py-0.5 font-mono text-xs font-semibold', 'bg-sunken text-ink' => ! $full, 'bg-ditolak-bg text-ditolak-fg' => $full])>{{ $publicCount }}/{{ $limit }} pesan</span>
                </header>

                @if ($comments->isEmpty())
                    <p class="rounded-control bg-sunken/60 px-4 py-6 text-center text-sm text-muted">
                        Belum ada pesan. {{ $ticket->isOpen() ? 'Gunakan thread ini untuk menambah informasi atau menjawab pertanyaan staff.' : '' }}
                    </p>
                @else
                    @include('tickets._comments', ['comments' => $comments, 'viewer' => $viewer])
                @endif

                <div class="mt-5 border-t border-line pt-5">
                    @if (! $ticket->isOpen())
                        <x-ui.alert type="info">Tiket sudah ditutup, thread tidak dapat dibalas lagi.</x-ui.alert>
                    @elseif ($full)
                        <x-ui.alert type="warning">Batas diskusi tercapai ({{ $limit }}/{{ $limit }} pesan). Hubungi staf secara langsung jika diperlukan.</x-ui.alert>
                    @elseif ($canReply)
                        <form method="POST" action="{{ route('tickets.comments.store', $ticket) }}" enctype="multipart/form-data" class="space-y-3"
                              x-data="{ files: [] }">
                            @csrf
                            <x-ui.textarea name="body" label="Balas pesan" :maxlength="\App\Http\Requests\Tickets\StoreCommentRequest::BODY_MAX" rows="3"
                                           placeholder="Ketik pesan…" required />
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <label for="reply-files" class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-control border border-line bg-surface px-3 text-sm font-semibold shadow-sm hover:bg-sunken">
                                        <x-icon name="paperclip" class="size-4" /> Lampirkan berkas
                                    </label>
                                    <input id="reply-files" name="attachments[]" type="file" multiple accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"
                                           class="sr-only" @change="files = Array.from($event.target.files).map(f => f.name)">
                                    <span class="ml-2 text-sm text-muted" x-text="files.join(', ')"></span>
                                    <p class="mt-1 text-xs text-muted">JPG/PNG/PDF, maks. 5 MB, maks. 3 berkas.</p>
                                    @foreach (array_merge($errors->get('attachments'), $errors->get('attachments.*')) as $messages)
                                        @foreach ((array) $messages as $message)
                                            <p class="mt-1 text-sm text-ditolak-fg">{{ $message }}</p>
                                        @endforeach
                                    @endforeach
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="font-mono text-xs text-muted">{{ $publicCount }}/{{ $limit }} pesan</span>
                                    <x-ui.button type="submit" icon="send">Kirim</x-ui.button>
                                </div>
                            </div>
                        </form>
                    @endif
                </div>
            </x-ui.card>
        </div>

        {{-- Linimasa --}}
        <aside class="space-y-4">
            @include('tickets._timeline', ['ticket' => $ticket])

            @if ($ticket->status === \App\Enums\TicketStatus::Menunggu)
                <x-ui.alert type="warning" title="Menunggu diambil Staff">
                    Tiket tidak punya batas kedaluwarsa. Target SLA {{ $ticket->sla_target_hours }} jam menjadi acuan staff fakultas.
                </x-ui.alert>
            @endif
        </aside>
    </div>
</x-layouts.app>
