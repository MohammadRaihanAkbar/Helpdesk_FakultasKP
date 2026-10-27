{{-- [M6] Bingkai semua email (header nama aplikasi, isi, catatan kaki berbahasa Indonesia). --}}
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
Email ini dikirim otomatis oleh {{ config('app.name') }} (UIN Siber Syekh Nurjati Cirebon). Mohon tidak membalas email ini;
gunakan thread tiket di aplikasi. © {{ date('Y') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
