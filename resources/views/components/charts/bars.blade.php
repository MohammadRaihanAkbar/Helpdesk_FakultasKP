{{--
    Grafik batang vertikal ringan (HTML + CSS, tanpa library & tanpa CDN). Dipakai tren 7 hari dan volume laporan.

    Pemakaian:
      <x-charts.bars caption="Tiket masuk per hari" :labels="['1 Okt', '2 Okt']"
                     :series="[['name' => 'Masuk', 'values' => [3, 5], 'class' => 'bg-primary']]" />

    - Beberapa seri (mis. Masuk & Selesai) tampil berdampingan per titik waktu.
    - Tinggi batang = nilai / nilai terbesar. Angka persis muncul saat kursor diarahkan (title) dan
      di tabel tersembunyi untuk pembaca layar (aksesibilitas).
    - Kalau titiknya banyak (mis. 30 hari), label bawah hanya ditulis setiap beberapa titik.
--}}
@props(['labels', 'series', 'caption'])

@php
    $max = max(1, ...collect($series)->flatMap(fn ($s) => $s['values'])->all());
    $count = count($labels);
    $every = $count > 16 ? (int) ceil($count / 8) : 1;
@endphp

<figure {{ $attributes->merge(['class' => 'w-full']) }}>
    @if (count($series) > 1)
        <figcaption class="mb-3 flex flex-wrap gap-4 text-xs text-muted" aria-hidden="true">
            @foreach ($series as $s)
                <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm {{ $s['class'] }}"></span>{{ $s['name'] }}</span>
            @endforeach
        </figcaption>
    @endif

    <div class="relative" aria-hidden="true">
        <div class="pointer-events-none absolute inset-x-0 top-0 border-t border-dashed border-line"></div>
        <div class="pointer-events-none absolute inset-x-0 top-1/2 border-t border-dashed border-line"></div>
        <span class="absolute -top-2 right-0 bg-surface pl-1 font-mono text-[10px] text-muted">{{ $max }}</span>
        <div class="flex h-40 items-end gap-1 border-b border-line-strong/40 sm:gap-1.5">
            @foreach ($labels as $i => $label)
                <div class="flex h-full min-w-0 flex-1 items-end justify-center gap-0.5">
                    @foreach ($series as $s)
                        @php($value = $s['values'][$i] ?? 0)
                        <div class="w-full max-w-7 rounded-t-[4px] {{ $s['class'] }} {{ $value === 0 ? 'opacity-25' : '' }}"
                             style="height: {{ $value === 0 ? 2 : max(4, round($value / $max * 100, 1)) }}%"
                             title="{{ $label }} · {{ $s['name'] }}: {{ $value }}"></div>
                    @endforeach
                </div>
            @endforeach
        </div>
        <div class="mt-1.5 flex gap-1 sm:gap-1.5">
            @foreach ($labels as $i => $label)
                <span class="flex min-w-0 flex-1 justify-center overflow-visible text-[10px] whitespace-nowrap text-muted sm:text-[11px]">{{ $i % $every === 0 ? $label : '' }}</span>
            @endforeach
        </div>
    </div>

    <table class="sr-only">
        <caption>{{ $caption }}</caption>
        <thead><tr><th scope="col">Waktu</th>@foreach ($series as $s)<th scope="col">{{ $s['name'] }}</th>@endforeach</tr></thead>
        <tbody>
            @foreach ($labels as $i => $label)
                <tr><th scope="row">{{ $label }}</th>@foreach ($series as $s)<td>{{ $s['values'][$i] ?? 0 }}</td>@endforeach</tr>
            @endforeach
        </tbody>
    </table>
</figure>
