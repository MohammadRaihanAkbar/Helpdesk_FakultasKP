{{--
    Tombol "Ping / Ingatkan" Admin Fakultas (US-14, US-16) + dialog pesan kustom opsional.

    Pemakaian: <x-ping-button :ticket="$ticket" :recipients="$recipientCounts[$ticket->id] ?? 0" />

    - Tiket ditutup               -> tidak tampil.
    - Belum ada calon penerima    -> tombol nonaktif + kalimat PRD US-14 ("Belum ada Staff untuk kelompok ini — ...").
    - Ada penerima                -> dialog konfirmasi berisi kotak pesan, lalu POST /admin/tickets/{id}/ping.
    Tiket yang melewati SLA memakai tombol merah supaya menonjol. Tombol kirim dikunci setelah diklik
    (mencegah klik ganda mengirim dua Ping).
--}}
@props(['ticket', 'recipients' => 0, 'size' => 'sm'])

@php
    use App\Enums\TicketStatus;

    $urgent = $ticket->slaState() === 'overdue';
    $small = $size === 'sm' ? 'min-h-11! px-4! text-sm!' : '';
    $target = $ticket->status === TicketStatus::Diproses ? 'Staff penangan' : $recipients.' staff berspesialisasi kelompok ini';
@endphp

@if ($ticket->isOpen())
    @if ($recipients === 0)
        <div {{ $attributes->merge(['class' => 'flex flex-col gap-1 md:items-end']) }}>
            <x-ui.button variant="secondary" icon="ping" disabled class="{{ $small }} cursor-not-allowed opacity-60" aria-describedby="ping-off-{{ $ticket->id }}">Ping</x-ui.button>
            <p id="ping-off-{{ $ticket->id }}" class="max-w-64 text-xs text-ditolak-fg md:text-right">
                {{ $ticket->status === TicketStatus::Diproses
                    ? 'Staff penangan sedang nonaktif, Ping tidak dapat dikirim.'
                    : 'Belum ada Staff untuk kelompok ini — tetapkan spesialisasi terlebih dahulu.' }}
            </p>
        </div>
    @else
        <div x-data="{ sending: false }" {{ $attributes }}>
            <x-ui.button :variant="$urgent ? 'danger' : 'secondary'" icon="ping" class="{{ $small }}"
                         x-on:click="$refs.pingDialog.showModal()">Ping / Ingatkan</x-ui.button>

            <dialog x-ref="pingDialog" class="m-auto w-[min(92vw,30rem)] rounded-panel border border-line bg-surface p-6 text-left text-ink shadow-pop backdrop:bg-ink/40">
                <form method="POST" action="{{ route('admin.tickets.ping', $ticket) }}" @submit="sending = true">
                    @csrf
                    <h2 class="text-lg font-extrabold">Ping / Ingatkan Staff</h2>
                    <p class="mt-1 text-sm text-muted">
                        <span class="font-mono font-semibold text-accent-ink">{{ $ticket->ticket_number }}</span> · {{ $ticket->title }}
                    </p>
                    <p class="mt-2 text-sm text-muted">
                        Notifikasi teguran dikirim ke <b class="text-ink">{{ $target }}</b>, berisi nomor & judul tiket,
                        kategori, lama menunggu, dan pesan Anda.
                    </p>
                    <div class="mt-4">
                        <x-ui.textarea name="message" id="ping-message-{{ $ticket->id }}" label="Pesan teguran (opsional)" rows="3"
                                       :maxlength="\App\Services\Tickets\PingService::MESSAGE_MAX"
                                       placeholder="Contoh: Mohon segera ditindaklanjuti, tiket ini sudah menunggu 3 hari." />
                    </div>
                    <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <x-ui.button variant="secondary" x-on:click="$refs.pingDialog.close()">Batal</x-ui.button>
                        <x-ui.button type="submit" icon="send" x-bind:disabled="sending">Kirim Ping</x-ui.button>
                    </div>
                </form>
            </dialog>
        </div>
    @endif
@endif
