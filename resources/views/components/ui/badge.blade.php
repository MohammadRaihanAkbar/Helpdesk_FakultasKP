{{--
    Badge berbentuk pil: titik warna + teks (tidak hanya mengandalkan warna).

    Pemakaian:
      <x-ui.badge :status="$ticket->status" />        status tiket: label & warna dari enum TicketStatus
      <x-ui.badge>Staff Fakultas</x-ui.badge>         netral
      <x-ui.badge tone="primary">FITK</x-ui.badge>

    tone: neutral · primary · warning · info · success · danger
    (sama dengan kunci TicketStatus::badgeColor()).
--}}
@props([
    'status' => null,
    'tone' => 'neutral',
])

@php
    if ($status instanceof \App\Enums\TicketStatus) {
        $tone = $status->badgeColor();
    }

    // [latar & teks, titik]; nama kelas utuh supaya terbaca Tailwind.
    $tones = [
        'neutral' => ['bg-sunken text-muted', 'bg-line-strong'],
        'primary' => ['bg-primary-soft text-primary', 'bg-primary'],
        'warning' => ['bg-menunggu-bg text-menunggu-fg', 'bg-menunggu-dot'],
        'info' => ['bg-diproses-bg text-diproses-fg', 'bg-diproses-dot'],
        'success' => ['bg-selesai-bg text-selesai-fg', 'bg-selesai-dot'],
        'danger' => ['bg-ditolak-bg text-ditolak-fg', 'bg-ditolak-dot'],
    ];
    [$colors, $dot] = $tones[$tone] ?? $tones['neutral'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-bold whitespace-nowrap {$colors}"]) }}>
    <span class="size-1.5 rounded-full {{ $dot }}" aria-hidden="true"></span>
    {{ $status instanceof \App\Enums\TicketStatus ? $status->label() : $slot }}
</span>
