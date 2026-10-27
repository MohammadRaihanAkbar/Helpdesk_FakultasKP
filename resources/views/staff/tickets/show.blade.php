{{--
    Detail Tiket — Staff (/staff/tickets/{id} dan /staff/history/{id}) — PRD 10.5, US-07, US-08, US-09.

    Siapa melihat apa:
      - Staff berspesialisasi (belum ada penangan): bisa melihat + tombol "Ambil Tiket".
      - Staff PENANGAN (handler) tiket Diproses: balas thread, tulis Catatan Internal, tutup tiket (Selesai/Tolak).
      - Staff lain yang berspesialisasi sama: hanya melihat (keputusan M2 no. 11).
      - Kategori sensitif "Integritas Dosen": identitas pelapor disamarkan sampai tiket diambil ($showReporter).

    Menutup tiket memakai dialog konfirmasi (<dialog> bawaan browser) karena tidak bisa dibatalkan:
      Selesai -> wajib unggah bukti (proofs[]), Tolak -> wajib pilih alasan (reason) + keterangan.
--}}
@php
    use App\Enums\TicketStatus;
    use App\Models\Ticket;

    $limit = Ticket::THREAD_LIMIT;
    $full = $publicCount >= $limit;
    $backRoute = $ticket->isOpen()
        ? ($ticket->handler_id === $viewer->id ? route('staff.tickets.index') : route('staff.queue'))
        : route('staff.history');
    $backLabel = $ticket->isOpen() ? ($ticket->handler_id === $viewer->id ? 'Tiket Saya' : 'Antrean Tiket') : 'Riwayat Tiket';
    $closeTab = old('status') === 'ditolak' || $errors->has('reason') || $errors->has('reason_note') ? 'tolak' : 'selesai';
@endphp

<x-layouts.app :title="$ticket->ticket_number">
    <a href="{{ $backRoute }}" class="mb-3 inline-flex min-h-10 items-center gap-1.5 text-sm font-semibold text-primary hover:underline">
        <x-icon name="arrow-left" class="size-4" /> Kembali ke {{ $backLabel }}
    </a>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="min-w-0 space-y-4">
            {{-- Ringkasan tiket --}}
            <x-ui.card>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-mono font-semibold text-accent-ink">{{ $ticket->ticket_number }}</span>
                    <x-ui.badge :status="$ticket->status" />
                    <x-sla-badge :ticket="$ticket" />
                    @if ($ticket->category->isSensitive())
                        <x-ui.badge tone="danger">Sensitif</x-ui.badge>
                    @endif
                </div>
                <h1 class="mt-2 text-xl font-extrabold text-ink md:text-2xl">{{ $ticket->title }}</h1>

                <dl class="mt-4 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-muted">Kategori</dt><dd class="font-semibold">{{ $ticket->category->pathLabel() }}</dd></div>
                    <div><dt class="text-muted">Fakultas tujuan</dt><dd class="font-semibold">{{ $ticket->faculty->code }} · {{ $ticket->faculty->name }}</dd></div>
                    <div>
                        <dt class="text-muted">Pelapor</dt>
                        <dd class="font-semibold">
                            @if ($showReporter)
                                {{ $ticket->reporter->name }}
                                <span class="block font-mono text-xs font-normal text-muted">
                                    {{ $ticket->reporter->identity() ? implode(' ', $ticket->reporter->identity()) : $ticket->reporter->email }}
                                </span>
                            @else
                                <span class="italic text-muted">Identitas dirahasiakan</span>
                                <span class="block text-xs font-normal text-muted">Kategori sensitif: identitas tampil setelah tiket diambil.</span>
                            @endif
                        </dd>
                    </div>
                    <div><dt class="text-muted">Jenis pelapor</dt><dd class="font-semibold">{{ $ticket->reporter_type->label() }}</dd></div>
                    <div><dt class="text-muted">Target batas waktu</dt><dd class="font-mono font-semibold">{{ $ticket->sla_target_hours }} jam <span class="font-sans font-normal text-muted">(batas {{ $ticket->slaDeadline()->translatedFormat('j M Y, H:i') }})</span></dd></div>
                    <div><dt class="text-muted">Dibuat</dt><dd class="font-semibold">{{ $ticket->created_at->translatedFormat('j M Y, H:i') }}</dd></div>
                    <div class="sm:col-span-2">
                        <dt class="text-muted">Ditangani oleh</dt>
                        <dd class="font-semibold">
                            @if ($ticket->handler)
                                {{ $ticket->handler_id === $viewer->id ? 'Anda' : $ticket->handler->name }}
                            @else
                                <span class="text-menunggu-fg">Belum diambil (menunggu di antrean)</span>
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
                        <h2 class="text-sm font-extrabold text-ink">Lampiran pelapor</h2>
                        @include('enduser.tickets._files', ['files' => $ticket->attachments, 'route' => 'attachments.show'])
                    </div>
                @endif

                @if ($canClaim)
                    <form method="POST" action="{{ route('staff.tickets.assign', $ticket) }}"
                          class="mt-5 flex flex-col gap-3 rounded-control bg-primary-soft/60 p-4 sm:flex-row sm:items-center sm:justify-between">
                        @csrf
                        @method('PATCH')
                        <p class="text-sm text-ink">Tiket ini ada di antrean spesialisasi Anda. Setelah diambil, statusnya menjadi <b>Diproses</b> dan pindah ke Tiket Saya.</p>
                        <x-ui.button type="submit" icon="plus" class="shrink-0">Ambil Tiket</x-ui.button>
                    </form>
                @elseif ($ticket->status === TicketStatus::Diproses && ! $canHandle)
                    <x-ui.alert type="info" class="mt-5">
                        Tiket ini ditangani oleh {{ $ticket->handler?->name }}. Anda hanya dapat melihat; balasan dan perubahan status dilakukan oleh staff penangan.
                    </x-ui.alert>
                @endif
            </x-ui.card>

            {{-- Tutup tiket: Selesai (wajib bukti) atau Tolak (wajib alasan) — hanya staff penangan --}}
            @if ($canHandle)
                <x-ui.card title="Tutup tiket" description="Pilih hasil penanganan. Tiket yang sudah ditutup tidak dapat dibuka lagi." id="tutup"
                           x-data="{ tab: '{{ $closeTab }}' }">
                    <div class="mb-4 grid grid-cols-2 gap-2 rounded-control bg-sunken p-1" role="tablist">
                        <button type="button" role="tab" :aria-selected="(tab === 'selesai').toString()" @click="tab = 'selesai'"
                                class="min-h-11 rounded-control text-sm font-bold" :class="tab === 'selesai' ? 'bg-surface text-selesai-fg shadow-sm' : 'text-muted'">
                            Tandai Selesai
                        </button>
                        <button type="button" role="tab" :aria-selected="(tab === 'tolak').toString()" @click="tab = 'tolak'"
                                class="min-h-11 rounded-control text-sm font-bold" :class="tab === 'tolak' ? 'bg-surface text-ditolak-fg shadow-sm' : 'text-muted'">
                            Tolak Tiket
                        </button>
                    </div>

                    {{-- ===== Selesai ===== --}}
                    <form method="POST" action="{{ route('staff.tickets.status', $ticket) }}" enctype="multipart/form-data"
                          x-show="tab === 'selesai'" x-ref="resolveForm" x-data="{ files: [] }" class="space-y-3">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="selesai">
                        <div>
                            <label for="proofs" class="mb-1.5 block text-sm font-bold text-ink">Bukti penyelesaian <span class="text-ditolak-fg">*</span></label>
                            <input id="proofs" name="proofs[]" type="file" multiple required accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"
                                   @change="files = Array.from($event.target.files).map(f => f.name)"
                                   class="block w-full rounded-control border border-line bg-surface text-sm file:mr-3 file:min-h-11 file:border-0 file:bg-primary-soft file:px-4 file:font-semibold file:text-primary">
                            <p class="mt-1.5 text-sm text-muted">Wajib minimal 1 berkas: foto/tangkapan layar hasil perbaikan atau dokumen PDF. JPG/PNG/PDF, maks. 5 MB, maks. 3 berkas. Pelapor dapat mengunduhnya.</p>
                            @foreach (array_merge($errors->get('proofs'), $errors->get('proofs.*')) as $messages)
                                @foreach ((array) $messages as $message)
                                    <p class="mt-1 flex items-start gap-1.5 text-sm text-ditolak-fg"><x-icon name="circle-alert" class="mt-0.5 size-4" /> {{ $message }}</p>
                                @endforeach
                            @endforeach
                        </div>
                        <x-ui.button type="button" icon="circle-check" @click="$refs.resolveForm.reportValidity() && $refs.resolveDialog.showModal()">Tandai Selesai…</x-ui.button>

                        <dialog x-ref="resolveDialog" class="m-auto w-[min(92vw,28rem)] rounded-panel border border-line bg-surface p-6 text-left text-ink shadow-pop backdrop:bg-ink/40">
                            <h2 class="text-lg font-extrabold">Tandai tiket Selesai?</h2>
                            <p class="mt-1 text-sm text-muted">
                                Tiket {{ $ticket->ticket_number }} akan ditutup dengan <span x-text="files.length"></span> berkas bukti
                                (<span x-text="files.join(', ')"></span>). Pelapor dapat memberi rating, dan tiket tidak dapat dibuka lagi.
                            </p>
                            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                <x-ui.button variant="secondary" x-on:click="$refs.resolveDialog.close()">Batal</x-ui.button>
                                <x-ui.button type="submit" icon="circle-check">Ya, tandai Selesai</x-ui.button>
                            </div>
                        </dialog>
                    </form>

                    {{-- ===== Tolak ===== --}}
                    <form method="POST" action="{{ route('staff.tickets.status', $ticket) }}" x-cloak
                          x-show="tab === 'tolak'" x-ref="rejectForm" x-data="{ reason: @js(old('reason', '')) }" class="space-y-3">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="ditolak">
                        <x-ui.select name="reason" label="Alasan penolakan *" placeholder="Pilih alasan" required x-model="reason"
                                     :options="Ticket::REJECTION_REASONS" />
                        <p class="-mt-1 text-sm text-muted" x-show="reason === 'salah_tujuan'">
                            Pelapor akan melihat tombol <b>Buat Ulang Tiket</b> untuk mengirim ulang ke fakultas/kategori yang benar.
                            Sebutkan tujuan yang benar di keterangan.
                        </p>
                        <x-ui.textarea name="reason_note" label="Keterangan" :maxlength="\App\Http\Requests\Staff\UpdateTicketStatusRequest::NOTE_MAX" rows="3"
                                       placeholder="Contoh: mata kuliah ini milik FEBI, silakan buat ulang tiket ke FEBI."
                                       hint="Opsional, kecuali memilih 'Alasan lainnya'. Terlihat oleh pelapor." x-bind:required="reason === 'lainnya'" />
                        <x-ui.button type="button" variant="danger" icon="x" @click="$refs.rejectForm.reportValidity() && $refs.rejectDialog.showModal()">Tolak Tiket…</x-ui.button>

                        <dialog x-ref="rejectDialog" class="m-auto w-[min(92vw,28rem)] rounded-panel border border-line bg-surface p-6 text-left text-ink shadow-pop backdrop:bg-ink/40">
                            <h2 class="text-lg font-extrabold">Tolak tiket ini?</h2>
                            <p class="mt-1 text-sm text-muted">Tiket {{ $ticket->ticket_number }} akan ditutup dengan status Ditolak dan alasan Anda dikirim ke pelapor. Tiket tidak dapat dibuka lagi.</p>
                            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                <x-ui.button variant="secondary" x-on:click="$refs.rejectDialog.close()">Batal</x-ui.button>
                                <x-ui.button type="submit" variant="danger">Ya, tolak tiket</x-ui.button>
                            </div>
                        </dialog>
                    </form>
                </x-ui.card>
            @endif

            {{-- Hasil penanganan (tiket ditutup) --}}
            @if ($ticket->status === TicketStatus::Ditolak)
                <x-ui.card title="Alasan penolakan">
                    <p class="text-[15px] whitespace-pre-line text-ditolak-fg">{{ $ticket->rejection_reason }}</p>
                    @if ($ticket->isRejectedForWrongTarget())
                        <p class="mt-2 text-sm text-muted">Pelapor dapat menekan <b>Buat Ulang Tiket</b> untuk mengirim tiket baru ke fakultas/kategori yang benar.</p>
                    @endif
                    <p class="mt-2 text-xs text-muted">Tiket Ditolak tidak dihitung dalam ketepatan waktu layanan.</p>
                </x-ui.card>
            @elseif ($ticket->status === TicketStatus::Selesai)
                <x-ui.card title="Bukti penyelesaian">
                    <x-slot:action><x-sla-badge :ticket="$ticket" /></x-slot:action>
                    @include('enduser.tickets._files', ['files' => $ticket->resolutionProofs, 'route' => 'proofs.show'])
                    <p class="mt-2 text-xs text-muted">
                        Diselesaikan {{ $ticket->resolved_at->translatedFormat('j M Y, H:i') }}
                        ({{ \App\Support\Format::duration($ticket->created_at, $ticket->resolved_at) }} sejak dibuat, target {{ $ticket->sla_target_hours }} jam).
                    </p>
                    <div class="mt-4 border-t border-line pt-4">
                        @if ($ticket->rating)
                            @include('tickets._rating', ['rating' => $ticket->rating, 'label' => 'Rating dari pelapor'])
                        @else
                            <p class="text-sm text-muted">Pelapor belum memberi rating.</p>
                        @endif
                    </div>
                </x-ui.card>
            @endif

            {{-- Thread diskusi + Catatan Internal --}}
            <x-ui.card id="thread">
                <header class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="text-[17px] font-extrabold text-ink">Thread Diskusi</h2>
                    <span @class(['rounded-full px-2.5 py-0.5 font-mono text-xs font-semibold', 'bg-sunken text-ink' => ! $full, 'bg-ditolak-bg text-ditolak-fg' => $full])>{{ $publicCount }}/{{ $limit }} pesan</span>
                </header>

                @if ($comments->isEmpty())
                    <p class="rounded-control bg-sunken/60 px-4 py-6 text-center text-sm text-muted">Belum ada pesan.</p>
                @else
                    @include('tickets._comments', [
                        'comments' => $comments,
                        'viewer' => $viewer,
                        'maskUserId' => $showReporter ? null : $ticket->reporter_id,
                    ])
                @endif

                <div class="mt-5 border-t border-line pt-5">
                    @if (! $ticket->isOpen())
                        <x-ui.alert type="info">Tiket sudah ditutup, thread tidak dapat dibalas lagi.</x-ui.alert>
                    @elseif (! $canHandle)
                        <p class="text-sm text-muted">Hanya staff penangan yang dapat membalas thread dan menulis catatan internal.</p>
                    @else
                        @if ($full)
                            <x-ui.alert type="warning" class="mb-3">
                                Batas diskusi tercapai ({{ $limit }}/{{ $limit }} pesan). Pesan publik terkunci, tetapi Catatan Internal tetap dapat ditambahkan.
                            </x-ui.alert>
                        @endif
                        <form method="POST" action="{{ route('staff.tickets.comments.store', $ticket) }}" enctype="multipart/form-data" class="space-y-3"
                              x-data="{ files: [], internal: {{ $full || old('is_internal_note') ? 'true' : 'false' }} }">
                            @csrf
                            <x-ui.textarea name="body" :label="$full ? 'Catatan internal' : 'Kirim pesan'" :maxlength="\App\Http\Requests\Tickets\StoreCommentRequest::BODY_MAX" rows="3"
                                           placeholder="Ketik pesan…" required />
                            @if ($full)
                                <input type="hidden" name="is_internal_note" value="1">
                            @else
                                <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-control border border-line px-3 text-sm"
                                       :class="internal ? 'border-accent/60 bg-accent-soft' : 'bg-surface'">
                                    <input type="checkbox" name="is_internal_note" value="1" x-model="internal" class="size-5 accent-primary">
                                    <span><b>Catatan Internal</b> (Staff Only): tidak terlihat pelapor dan tidak dihitung dalam batas {{ $limit }} pesan.</span>
                                </label>
                            @endif
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
                                    <x-ui.button type="submit" icon="send"><span x-text="internal ? 'Simpan Catatan' : 'Kirim'">Kirim</span></x-ui.button>
                                </div>
                            </div>
                        </form>
                    @endif
                </div>
            </x-ui.card>
        </div>

        {{-- Linimasa (+ Ping dari Admin, hanya untuk staff/admin) --}}
        <aside class="space-y-4">
            @include('tickets._timeline', ['ticket' => $ticket, 'pings' => $ticket->pingLogs])

            @if ($ticket->isOpen())
                <x-ui.card title="Target Batas Waktu">
                    <p class="text-sm text-muted">Batas: <b class="text-ink">{{ $ticket->slaDeadline()->translatedFormat('j M Y, H:i') }}</b></p>
                    <p class="mt-1 text-sm text-muted">
                        @if ($ticket->slaState() === 'overdue')
                            <span class="font-semibold text-ditolak-fg">Sudah lewat {{ \App\Support\Format::duration($ticket->slaDeadline(), now()) }}.</span>
                        @else
                            Sisa {{ \App\Support\Format::duration(now(), $ticket->slaDeadline()) }}.
                        @endif
                    </p>
                </x-ui.card>
            @endif
        </aside>
    </div>
</x-layouts.app>
