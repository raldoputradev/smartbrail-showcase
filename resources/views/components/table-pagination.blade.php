@php
    $paginator = $paginator ?? null;
@endphp
@if($paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $paginator->total() > 0)
    <div class="table-pagination-wrap" style="padding:16px;border-top:1px solid var(--border-subtle);">
        {{ $paginator->withQueryString()->links() }}
    </div>
@endif
