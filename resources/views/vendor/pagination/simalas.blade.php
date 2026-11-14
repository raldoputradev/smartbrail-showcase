@if ($paginator->total() > 0)
    <nav class="simalas-pagination" role="navigation" aria-label="Pagination">
        <span class="simalas-pagination-info">
            Menampilkan {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $paginator->total() }}
        </span>
        @if ($paginator->hasPages())
            <div class="simalas-pagination-links">
                @if ($paginator->onFirstPage())
                    <span class="simalas-pagination-btn is-disabled">Sebelumnya</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" class="simalas-pagination-btn">Sebelumnya</a>
                @endif

                @foreach ($paginator->getUrlRange(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="simalas-pagination-btn is-active">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="simalas-pagination-btn">{{ $page }}</a>
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" class="simalas-pagination-btn">Berikutnya</a>
                @else
                    <span class="simalas-pagination-btn is-disabled">Berikutnya</span>
                @endif
            </div>
        @endif
    </nav>
@endif
