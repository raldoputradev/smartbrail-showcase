<?php

namespace App\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

class TablePagination
{
    public const DEFAULT_PER_PAGE = 15;

    public static function perPage(): int
    {
        $per = (int) request()->query('per_page', self::DEFAULT_PER_PAGE);

        return max(5, min(100, $per));
    }

    /**
     * @param  iterable<int, mixed>|array<int, mixed>|Collection<int, mixed>  $items
     */
    public static function paginate(
        iterable $items,
        string $pageName = 'page',
        ?int $perPage = null
    ): LengthAwarePaginator {
        $collection = $items instanceof Collection ? $items : collect($items);
        $perPage = $perPage ?? self::perPage();
        $page = Paginator::resolveCurrentPage($pageName);
        $total = $collection->count();
        $slice = $collection->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'pageName' => $pageName,
                'query' => request()->query(),
            ]
        );
    }
}
