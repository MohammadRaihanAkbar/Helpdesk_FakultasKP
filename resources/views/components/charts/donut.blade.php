{{--
    Diagram donat (SVG) + legenda. Dipakai untuk proporsi tiket per Kelompok Layanan (US-15).

    Pemakaian:
      <x-charts.donut caption="Tiket per Kelompok Layanan" :slices="[['label' => 'Akademik', 'value' => 12], ...]" />

    Teknik: lingkaran dengan keliling 100 (pathLength="100"). Setiap potongan adalah garis lingkaran
    sepanjang persentasenya (stroke-dasharray) yang digeser (stroke-dashoffset) sesudah potongan sebelumnya.
    Warna dari App\Support\ChartPalette. Legenda di sebelahnya memuat angka & persen (tidak hanya warna).
--}}
@props(['slices', 'caption', 'centerLabel' => 'tiket'])

@php
    $total = collect($slices)->sum('value');
    $offset = 0;
@endphp

<figure {{ $attributes->merge(['class' => 'flex flex-col items-center gap-5 sm:flex-row sm:items-start']) }}>
    <div class="relative size-40 shrink-0">
        <svg viewBox="0 0 42 42" class="size-40 -rotate-90" role="img" aria-label="{{ $caption }}">
            <circle cx="21" cy="21" r="15.9155" fill="none" stroke-width="6" class="stroke-sunken" />
            @if ($total > 0)
                @foreach ($slices as $i => $slice)
                    @php($pct = $slice['value'] / $total * 100)
                    @if ($pct > 0)
                        <circle cx="21" cy="21" r="15.9155" fill="none" stroke-width="6" pathLength="100"
                                class="{{ \App\Support\ChartPalette::at($i)['stroke'] }}"
                                stroke-dasharray="{{ round($pct, 3) }} {{ round(100 - $pct, 3) }}"
                                stroke-dashoffset="{{ round(-$offset, 3) }}">
                            <title>{{ $slice['label'] }}: {{ $slice['value'] }}</title>
                        </circle>
                        @php($offset += $pct)
                    @endif
                @endforeach
            @endif
        </svg>
        <div class="absolute inset-0 grid place-items-center text-center" aria-hidden="true">
            <div>
                <p class="font-heading text-2xl font-extrabold text-ink">{{ $total }}</p>
                <p class="text-xs text-muted">{{ $centerLabel }}</p>
            </div>
        </div>
    </div>

    <figcaption class="w-full min-w-0 text-sm">
        <span class="sr-only">{{ $caption }}</span>
        @if ($slot->isNotEmpty())
            {{ $slot }}
        @else
            <ul class="space-y-1.5">
                @foreach ($slices as $i => $slice)
                    <li class="flex items-center gap-2">
                        <span class="size-2.5 shrink-0 rounded-sm {{ \App\Support\ChartPalette::at($i)['bg'] }}" aria-hidden="true"></span>
                        <span class="min-w-0 flex-1 truncate">{{ $slice['label'] }}</span>
                        <span class="font-mono text-xs font-semibold text-ink">{{ $slice['value'] }}</span>
                        <span class="w-11 text-right font-mono text-xs text-muted">{{ $total ? number_format($slice['value'] / $total * 100, 0) : 0 }}%</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </figcaption>
</figure>
