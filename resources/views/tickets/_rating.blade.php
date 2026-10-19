{{--
    Rating CSAT yang sudah diberikan (US-05, US-09). Variabel: $rating (Rating), $label (judul kecil).
--}}
<div class="text-sm">
    <p class="font-bold text-ink">{{ $label }}</p>
    <p class="mt-1 flex items-center gap-2">
        <x-stars :score="$rating->score" class="size-5" />
        <span class="font-mono font-semibold text-ink">{{ $rating->score }}/5</span>
    </p>
    @if ($rating->comment)
        <p class="mt-1 whitespace-pre-line text-muted">"{{ $rating->comment }}"</p>
    @endif
    <p class="mt-1 text-xs text-muted">Dinilai {{ $rating->created_at?->translatedFormat('j M Y, H:i') }}</p>
</div>
