{{--
    Detail Tiket — Admin Fakultas (/admin/tickets/{id}) — HANYA BACA (US-16, PRD 2.3, 10.5).

    Admin melihat semuanya: data tiket + identitas pelapor (termasuk kategori sensitif, PRD 2.7 aturan 10),
    lampiran, thread + Catatan Internal, bukti penyelesaian, rating, dan linimasa + riwayat Ping.
    Admin TIDAK bisa membalas, mengambil, memindahkan, atau menutup tiket. Satu-satunya aksi: Ping / Ingatkan.
--}}
@php
    use App\Enums\TicketStatus;
    use App\Models\Ticket;
    use App\Support\Format;

    $limit = Ticket::THREAD_LIMIT;
@endphp

<x-layouts.app :title="$ticket->ticket_number">
    <a href="{{ url()->previous() !== url()->current() && str_contains(url()->previous(), '/admin/') ? url()->previous() : route('admin.queue') }}"
       class="mb-3 inline-flex min-h-10 items-center gap-1.5 text-sm font-semibold text-primary hover:underline">
        <x-icon name="arrow-left" class="size-4" /> Kembali
    </a>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="min-w-0 space-y-4">
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
                    <div><dt class="text-muted">Fakultas tujuan</dt><dd class="font-semibold">{{ $ticket->faculty->code }} — {{ $ticket->faculty->name }}</dd></div>
                    <div>
                        <dt class="text-muted">Pelapor</dt>
                        <dd class="font-semibold">
                            {{ $ticket->reporter->name }}
                            <span class="block font-mono text-xs font-normal text-muted">
                                {{ $ticket->reporter->nidn ? 'NIDN '.$ticket->reporter->nidn : ($ticket->reporter->nim ? 'NIM '.$ticket->reporter->nim : $ticket->reporter->email) }}
                            </span>
                            @if ($ticket->category->isSensitive())
                                <span class="block text-xs font-normal text-ditolak-fg">Kategori sensitif: identitas hanya untuk Admin Fakultas & Staff penangan. Jaga kerahasiaannya.</span>
                            @endif
                        </dd>
                    </div>
                    <div><dt class="text-muted">Jenis pelapor</dt><dd class="font-semibold">{{ $ticket->reporter_type->label() }}</dd></div>
                    <div><dt class="text-muted">Target SLA</dt><dd class="font-mono font-semibold">{{ $ticket->sla_target_hours }} jam <span class="font-sans font-normal text-muted">(batas {{ $ticket->slaDeadline()->translatedFormat('j M Y, H:i') }})</span></dd></div>
                    <div><dt class="text-muted">Dibuat</dt><dd class="font-semibold">{{ $ticket->created_at->translatedFormat('j M Y, H:i') }}</dd></div>
                    <div class="sm:col-span-2">
                        <dt class="text-muted">Ditangani oleh</dt>
                        <dd class="font-semibold">
                            @if ($ticket->handler)
                                {{ $ticket->handler->name }}
                                @if (! $ticket->handler->is_active)
                                    <x-ui.badge tone="danger" class="ml-1">Akun nonaktif</x-ui.badge>
                                @endif
                            @else
                                <span class="text-menunggu-fg">Belum diambil — menunggu di antrean</span>
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

                <x-ui.alert type="info" class="mt-5">
                    Halaman ini hanya untuk memantau. Admin Fakultas tidak mengambil, memindahkan, membalas, atau menutup tiket;
                    gunakan <b>Ping / Ingatkan</b> untuk menegur Staff.
                </x-ui.alert>
            </x-ui.card>

            @if ($ticket->status === TicketStatus::Ditolak)
                <x-ui.card title="Alasan penolakan">
                    <p class="text-[15px] whitespace-pre-line text-ditolak-fg">{{ $ticket->rejection_reason }}</p>
                    <p class="mt-2 text-xs text-muted">Ditolak {{ $ticket->resolved_at?->translatedFormat('j M Y, H:i') }}. Tiket Ditolak tidak dihitung dalam SLA Compliance.</p>
                </x-ui.card>
            @elseif ($ticket->status === TicketStatus::Selesai)
                <x-ui.card title="Bukti penyelesaian">
                    <x-slot:action><x-sla-badge :ticket="$ticket" /></x-slot:action>
                    @include('enduser.tickets._files', ['files' => $ticket->resolutionProofs, 'route' => 'proofs.show'])
                    <p class="mt-2 text-xs text-muted">
                        Diselesaikan {{ $ticket->resolved_at->translatedFormat('j M Y, H:i') }}
                        ({{ Format::duration($ticket->created_at, $ticket->resolved_at) }} sejak dibuat, target {{ $ticket->sla_target_hours }} jam).
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

            <x-ui.card id="thread">
                <header class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="text-[17px] font-extrabold text-ink">Thread Diskusi</h2>
                    <span class="rounded-full bg-sunken px-2.5 py-0.5 font-mono text-xs font-semibold text-ink">{{ $publicCount }}/{{ $limit }} pesan</span>
                </header>
                @if ($comments->isEmpty())
                    <p class="rounded-control bg-sunken/60 px-4 py-6 text-center text-sm text-muted">Belum ada pesan.</p>
                @else
                    @include('tickets._comments', ['comments' => $comments, 'viewer' => $viewer])
                @endif
                <p class="mt-4 text-xs text-muted">Catatan Internal (kuning) hanya terlihat oleh Staff dan Admin Fakultas.</p>
            </x-ui.card>
        </div>

        <aside class="space-y-4">
            @if ($ticket->isOpen())
                <x-ui.card title="Ping / Ingatkan Staff" id="ping">
                    @if ($recipients->isEmpty())
                        <x-ui.alert type="warning">
                            {{ $ticket->status === TicketStatus::Diproses
                                ? 'Staff penangan sedang nonaktif, Ping tidak dapat dikirim.'
                                : 'Belum ada Staff untuk kelompok ini — tetapkan spesialisasi terlebih dahulu.' }}
                        </x-ui.alert>
                        @if ($ticket->status === TicketStatus::Menunggu)
                            <x-ui.button variant="secondary" :href="route('admin.staff.index')" icon="users" class="mt-3 w-full">Atur Spesialisasi Staff</x-ui.button>
                        @endif
                    @else
                        <p class="text-sm text-muted">Penerima:</p>
                        <ul class="mt-1 mb-3 flex flex-wrap gap-1.5">
                            @foreach ($recipients as $staff)
                                <li><x-ui.badge tone="info">{{ $staff->name }}</x-ui.badge></li>
                            @endforeach
                        </ul>
                        <form method="POST" action="{{ route('admin.tickets.ping', $ticket) }}" class="space-y-3" x-data="{ sending: false }" @submit="sending = true">
                            @csrf
                            <x-ui.textarea name="message" label="Pesan teguran (opsional)" rows="3"
                                           :maxlength="\App\Services\Tickets\PingService::MESSAGE_MAX"
                                           placeholder="Contoh: Mohon segera ditindaklanjuti." />
                            <x-ui.button type="submit" :variant="$ticket->slaState() === 'overdue' ? 'danger' : 'primary'" icon="ping" class="w-full" x-bind:disabled="sending">
                                Kirim Ping
                            </x-ui.button>
                        </form>
                    @endif
                </x-ui.card>
            @endif

            @include('tickets._timeline', ['ticket' => $ticket, 'pings' => $ticket->pingLogs])

            @if ($ticket->isOpen())
                <x-ui.card title="Target SLA">
                    <p class="text-sm text-muted">Batas: <b class="text-ink">{{ $ticket->slaDeadline()->translatedFormat('j M Y, H:i') }}</b></p>
                    <p class="mt-1 text-sm text-muted">
                        @if ($ticket->slaState() === 'overdue')
                            <span class="font-semibold text-ditolak-fg">Sudah lewat {{ Format::duration($ticket->slaDeadline(), now()) }}.</span>
                        @else
                            Sisa {{ Format::duration(now(), $ticket->slaDeadline()) }}.
                        @endif
                    </p>
                </x-ui.card>
            @endif
        </aside>
    </div>
</x-layouts.app>
