{{--
    Kartu angka (dashboard & laporan Admin). Nilai null ditampilkan "–" (belum ada data), bukan 0.

    Pemakaian:
      <x-kpi-card label="SLA Compliance" :value="$kpi['sla_rate']" suffix="%" note="target ≥ 85%" icon="shield-check"
                  tone="bg-selesai-bg text-selesai-fg" :href="route(...)" :status="'good'" />

    status (opsional): good | bad -> garis bawah hijau/merah dibanding target PRD 1.4.
--}}
@props(['label', 'value' => null, 'suffix' => '', 'note' => null, 'icon' => 'chart', 'tone' => 'bg-primary-soft text-primary', 'href' => null, 'status' => null])

@php
    $display = $value === null ? '–' : (is_float($value) ? number_format($value, $value == floor($value) ? 0 : 1, ',', '.') : $value).$suffix;
    $tag = $href ? 'a' : 'div';
    $long = mb_strlen((string) $display) > 8; // mis. "1 hari 2 jam" -> huruf sedikit lebih kecil supaya muat
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class([
        'block rounded-card border border-line bg-surface/85 p-4 shadow-card',
        'hover:border-primary/40 hover:bg-surface' => $href,
        'border-b-4 border-b-selesai-dot' => $status === 'good',
        'border-b-4 border-b-ditolak-dot' => $status === 'bad',
    ]) }}>
    <div class="flex items-start justify-between gap-2">
        <p class="text-sm font-bold text-muted">{{ $label }}</p>
        <span class="grid size-9 shrink-0 place-items-center rounded-control {{ $tone }}"><x-icon :name="$icon" class="size-4" /></span>
    </div>
    <p @class(['mt-1 font-heading leading-tight font-extrabold text-ink', 'text-[26px]' => ! $long, 'text-xl' => $long])>{{ $display }}</p>
    @if ($note)
        <p class="text-xs text-muted">{{ $note }}</p>
    @endif
    {{ $slot }}
</{{ $tag }}>
