{{--
    Input teks lengkap dengan label, ikon opsional, petunjuk, dan pesan error validasi.

    Pemakaian:
      <x-ui.input name="email" label="Email Pribadi" type="email" autocomplete="email" required />
      <x-ui.input name="login" label="NIM / NIDN / Email" icon="user" />

    - Nilai lama (old()) otomatis terisi setelah validasi gagal, KECUALI untuk password.
    - Pesan error diambil dari $errors->first($name); field ditandai aria-invalid dan
      pesannya dihubungkan lewat aria-describedby supaya dibacakan pembaca layar.
    - Atribut lain (required, autocomplete, inputmode, maxlength, ...) diteruskan lewat $attributes.
    - :show-error="false" menyembunyikan teks error di bawah field (dipakai halaman login yang
      menampilkan error di kotak alert ber-id "{name}-error"); aria-invalid tetap dipasang.
    - Fokus: garis emas + cincin emas lembut, seperti mockup.
--}}
@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'icon' => null,
    'showError' => true,
])

@php
    $id = $attributes->get('id', $name);
    // $errors dibagikan middleware ke semua view; saat komponen dirender sendirian (test) bisa belum ada.
    $error = isset($errors) ? $errors->first($name) : null;
    $describedBy = collect([$hint ? "{$id}-hint" : null, $error ? "{$id}-error" : null])->filter()->implode(' ');
@endphp

<div>
    <label for="{{ $id }}" class="mb-1.5 block text-sm font-bold text-ink">{{ $label }}</label>

    <div class="relative">
        @if ($icon)
            <x-icon :name="$icon" class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-muted" />
        @endif
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="{{ $type }}"
            @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if ($error) aria-invalid="true" @endif
            {{ $attributes->except('id')->class([
                'block min-h-13 w-full rounded-control border bg-surface px-3.5 text-base text-ink shadow-sm placeholder:text-muted',
                'focus:border-accent focus:ring-4 focus:ring-accent/30 focus:outline-none',
                'pl-11' => $icon,
                'border-line' => ! $error,
                'border-ditolak-fg' => $error,
            ]) }}
        >
    </div>

    @if ($hint)
        <p id="{{ $id }}-hint" class="mt-1.5 text-sm text-muted">{{ $hint }}</p>
    @endif

    @if ($error && $showError)
        <p id="{{ $id }}-error" class="mt-1.5 flex items-start gap-1.5 text-sm text-ditolak-fg">
            <x-icon name="circle-alert" class="mt-0.5 size-4" />
            <span>{{ $error }}</span>
        </p>
    @endif
</div>
