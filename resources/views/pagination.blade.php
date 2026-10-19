{{--
    Navigasi halaman (dipakai {{ $tickets->links() }}). Didaftarkan di AppServiceProvider: Paginator::defaultView('pagination').
    Dibuat sendiri karena tampilan bawaan Laravel memakai warna Tailwind standar yang tidak ada di tema kita.
--}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Navigasi halaman" class="flex items-center justify-between gap-3 text-sm">
        <p class="text-muted">
            Menampilkan {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $paginator->total() }}
        </p>
        <div class="flex gap-2">
            @if ($paginator->onFirstPage())
                <span class="inline-flex min-h-11 items-center rounded-control border border-line px-4 text-muted opacity-60">Sebelumnya</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex min-h-11 items-center rounded-control border border-line bg-surface px-4 font-semibold shadow-sm hover:bg-sunken">Sebelumnya</a>
            @endif
            <span class="inline-flex min-h-11 items-center px-2 font-mono text-muted">{{ $paginator->currentPage() }}/{{ $paginator->lastPage() }}</span>
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex min-h-11 items-center rounded-control border border-line bg-surface px-4 font-semibold shadow-sm hover:bg-sunken">Berikutnya</a>
            @else
                <span class="inline-flex min-h-11 items-center rounded-control border border-line px-4 text-muted opacity-60">Berikutnya</span>
            @endif
        </div>
    </nav>
@endif
