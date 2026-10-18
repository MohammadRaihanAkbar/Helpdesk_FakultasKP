{{--
    Pilihan (dropdown) dengan label dan pesan error, pasangan dari <x-ui.input>.

    Pemakaian:
      <x-ui.select name="origin_faculty_id" label="Fakultas Asal" :options="$options" placeholder="Pilih fakultas" required />

    :options = array [nilai => teks], misalnya [1 => 'FITK — Fakultas Ilmu Tarbiyah dan Keguruan'].
    Panah dropdown memakai bawaan browser (paling mudah dibaca & tidak butuh gambar).
--}}
@props([
    'name',
    'label',
    'options' => [],
    'selected' => null,
    'placeholder' => null,
    'hint' => null,
])

@php
    $id = $attributes->get('id', $name);
    $error = isset($errors) ? $errors->first($name) : null;
    $current = (string) old($name, $selected);
    $describedBy = collect([$hint ? "{$id}-hint" : null, $error ? "{$id}-error" : null])->filter()->implode(' ');
@endphp

<div>
    <label for="{{ $id }}" class="mb-1.5 block text-sm font-bold text-ink">{{ $label }}</label>

    <select
        id="{{ $id }}"
        name="{{ $name }}"
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        @if ($error) aria-invalid="true" @endif
        {{ $attributes->except('id')->class([
            'block min-h-13 w-full rounded-control border bg-surface px-3 text-base text-ink shadow-sm',
            'focus:border-accent focus:ring-4 focus:ring-accent/30 focus:outline-none',
            'border-line' => ! $error,
            'border-ditolak-fg' => $error,
        ]) }}
    >
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $value => $text)
            <option value="{{ $value }}" @selected($current === (string) $value)>{{ $text }}</option>
        @endforeach
    </select>

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
