{{--
    [M6] Template email notifikasi (salinan bawaan Laravel: vendor:publish --tag=laravel-notifications),
    diterjemahkan ke Bahasa Indonesia. Dipakai semua class di app/Notifications lewat MailMessage
    (->greeting(), ->line(), ->action(), ->salutation()). Format Markdown: baris "# ..." = judul.
--}}
<x-mail::message>
{{-- Greeting --}}
@if (! empty($greeting))
# {{ $greeting }}
@else
# Halo!
@endif

{{-- Intro Lines --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Action Button --}}
@isset($actionText)
<?php
    $color = match ($level) {
        'success', 'error' => $level,
        default => 'primary',
    };
?>
<x-mail::button :url="$actionUrl" :color="$color">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Outro Lines --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Salutation --}}
@if (! empty($salutation))
{{ $salutation }}
@else
Salam,<br>
{{ config('app.name') }}
@endif

{{-- Subcopy --}}
@isset($actionText)
<x-slot:subcopy>
Jika tombol "{{ $actionText }}" tidak bisa diklik, salin dan tempel alamat berikut ke browser:
<span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
