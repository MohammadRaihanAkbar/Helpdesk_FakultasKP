{{--
    Indikator SLA sebuah tiket (PRD US-06, US-07, US-09, F-TKS-09).

    Pemakaian: <x-sla-badge :ticket="$ticket" />   (atribut :quiet="true" -> tiket aman tidak diberi badge)

    Tiket TERBUKA (Menunggu/Diproses), dihitung dari created_at + sla_target_hours:
      merah  "Melewati SLA"   -> batas sudah lewat
      kuning "Mendekati SLA"  -> sisa waktu < 25% target
      netral "Sisa ± X jam"   -> masih aman (disembunyikan bila quiet)
    Tiket SELESAI: hijau "Sesuai SLA" atau merah "Melewati SLA" (Ticket::slaMet()).
    Tiket DITOLAK: tidak dinilai SLA (PRD 8.2), jadi tidak ada badge.
--}}
@props(['ticket', 'quiet' => false])

@php
    $state = $ticket->slaState();
    $met = $ticket->slaMet();
@endphp

@if ($state === 'overdue')
    <x-ui.badge tone="danger" {{ $attributes }}>Melewati Target</x-ui.badge>
@elseif ($state === 'near')
    <x-ui.badge tone="warning" {{ $attributes }}>Mendekati Batas Waktu</x-ui.badge>
@elseif ($state === 'ok' && ! $quiet)
    <x-ui.badge {{ $attributes }}>Sisa ± {{ max(1, (int) floor(now()->diffInHours($ticket->slaDeadline()))) }} jam</x-ui.badge>
@elseif ($met === true)
    <x-ui.badge tone="success" {{ $attributes }}>Sesuai Target</x-ui.badge>
@elseif ($met === false)
    <x-ui.badge tone="danger" {{ $attributes }}>Melewati Target</x-ui.badge>
@endif
