<?php

declare(strict_types=1);

namespace Core\Shared\Application\Dto;

/**
 * Resultado paginado sem framework. Read models devolvem isto; a camada HTTP
 * o transforma em JSON. É o que o LengthAwarePaginator do Laravel carrega,
 * sem Illuminate na camada Application.
 *
 * @template T
 */
final readonly class Page
{
    /**
     * @param  list<T>  $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $perPage,
        public int $currentPage,
    ) {}

    public function lastPage(): int
    {
        return max(1, intdiv($this->total + $this->perPage - 1, $this->perPage));
    }
}
