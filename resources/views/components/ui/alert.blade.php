{{--
    Kotak pesan (flash message, error login, info).

    Pemakaian:
      <x-ui.alert type="success">Registrasi berhasil.</x-ui.alert>
      <x-ui.alert type="error" title="Gagal masuk">NIM/NIDN atau password salah.</x-ui.alert>

    type: info · success · warning · error. Warnanya memakai token status tiket
    (warna + ikon + teks, jadi tidak hanya bergantung pada warna).
    Error memakai role="alert" (langsung dibacakan pembaca layar), lainnya role="status".
--}}
@props([
    'type' => 'info',
    'title' => null,
])

@php
    $styles = [
        'info' => ['bg-diproses-bg text-diproses-fg', 'info'],
        'success' => ['bg-selesai-bg text-selesai-fg', 'circle-check'],
        'warning' => ['bg-menunggu-bg text-menunggu-fg', 'circle-alert'],
        'error' => ['bg-ditolak-bg text-ditolak-fg', 'circle-alert'],
    ];
    [$colors, $icon] = $styles[$type] ?? $styles['info'];
@endphp

<div role="{{ $type === 'error' ? 'alert' : 'status' }}" {{ $attributes->merge(['class' => "flex items-start gap-3 rounded-control border border-current/15 px-4 py-3 text-sm {$colors}"]) }}>
    <x-icon :name="$icon" class="mt-0.5 size-5" />
    <div class="min-w-0">
        @if ($title)
            <p class="font-semibold">{{ $title }}</p>
        @endif
        <div>{{ $slot }}</div>
    </div>
</div>
