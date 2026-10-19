{{-- 429: terlalu banyak request dari alamat IP yang sama (middleware throttle, lihat AppServiceProvider). --}}
<x-layouts.error code="429" title="Terlalu banyak percobaan">
    Anda mengirim formulir terlalu sering dalam waktu singkat. Tunggu sekitar satu menit, lalu coba lagi.
</x-layouts.error>
