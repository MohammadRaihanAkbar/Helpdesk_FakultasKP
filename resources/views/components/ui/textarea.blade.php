{{--
    Textarea dengan label, petunjuk, pesan error, dan penghitung karakter (opsional).

    Pemakaian:
      <x-ui.textarea name="description" label="Deskripsi" :maxlength="2000" rows="6" required />

    - :maxlength diisi -> muncul penghitung "120/2000" (Alpine) dan browser membatasi ketikan.
      Server TETAP memvalidasi panjangnya (jangan percaya browser).
    - Nilai lama (old()) otomatis terisi setelah validasi gagal.
--}}
@props([
    'name',
    'label',
    'value' => null,
    'hint' => null,
    'maxlength' => null,
    'rows' => 4,
])

@php
    $id = $attributes->get('id', $name);
    $error = isset($errors) ? $errors->first($name) : null;
    $current = (string) old($name, $value);
    $describedBy = collect([$hint ? "{$id}-hint" : null, $error ? "{$id}-error" : null])->filter()->implode(' ');
@endphp

<div x-data="{ length: {{ mb_strlen($current) }} }">
    <div class="mb-1.5 flex items-end justify-between gap-3">
        <label for="{{ $id }}" class="block text-sm font-bold text-ink">{{ $label }}</label>
        @if ($maxlength)
            <span class="font-mono text-xs text-muted" aria-live="polite"><span x-text="length">{{ mb_strlen($current) }}</span>/{{ $maxlength }}</span>
        @endif
    </div>

    <textarea
        id="{{ $id }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        @if ($maxlength) maxlength="{{ $maxlength }}" @endif
        @input="length = $event.target.value.length"
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        @if ($error) aria-invalid="true" @endif
        {{ $attributes->except('id')->class([
            'block w-full rounded-control border bg-surface px-3.5 py-3 text-base text-ink shadow-sm placeholder:text-muted',
            'focus:border-accent focus:ring-4 focus:ring-accent/30 focus:outline-none',
            'border-line' => ! $error,
            'border-ditolak-fg' => $error,
        ]) }}
    >{{ $current }}</textarea>

    @if ($hint)
        <p id="{{ $id }}-hint" class="mt-1.5 text-sm text-muted">{{ $hint }}</p>
    @endif

    @if ($error)
        <p id="{{ $id }}-error" class="mt-1.5 flex items-start gap-1.5 text-sm text-ditolak-fg">
            <x-icon name="circle-alert" class="mt-0.5 size-4" />
            <span>{{ $error }}</span>
        </p>
    @endif
</div>
