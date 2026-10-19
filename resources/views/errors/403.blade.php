{{-- 403: sudah login, tetapi perannya tidak boleh membuka halaman ini (middleware role / abort(403)). --}}
<x-layouts.error code="403" title="Akses ditolak">
    {{ $exception->getMessage() ?: 'Anda tidak memiliki akses ke halaman ini.' }}
</x-layouts.error>
