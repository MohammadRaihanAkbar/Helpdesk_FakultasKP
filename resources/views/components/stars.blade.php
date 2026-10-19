{{--
    Bintang rating 1–5 (hanya tampilan). Pemakaian: <x-stars :score="4" class="size-4" />
    Teks "4 dari 5 bintang" tetap dibaca pembaca layar, jadi tidak hanya mengandalkan gambar.
--}}
@props(['score'])

<span class="inline-flex items-center gap-0.5 text-accent" role="img" aria-label="{{ $score }} dari 5 bintang">
    @for ($i = 1; $i <= 5; $i++)
        <x-icon name="star" {{ $attributes->class(['fill-current' => $i <= $score, 'text-line-strong' => $i > $score]) }} />
    @endfor
</span>
