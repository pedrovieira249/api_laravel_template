<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Core\Shared\Application\Dto\Page;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforma o DTO Page, livre de framework, na forma usual { data, meta }.
 * O Laravel não embrulha duas vezes porque o array já tem uma chave "data".
 *
 * @template T of object
 */
final class PageResource extends JsonResource
{
    /**
     * @param  Page<T>  $page
     * @param  class-string<JsonResource>  $itemResource
     */
    public function __construct(
        private readonly Page $page,
        private readonly string $itemResource,
    ) {
        parent::__construct($page);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => array_map(
                fn (object $item): array => new $this->itemResource($item)->resolve($request),
                $this->page->items,
            ),
            'meta' => [
                'total' => $this->page->total,
                'per_page' => $this->page->perPage,
                'current_page' => $this->page->currentPage,
                'last_page' => $this->page->lastPage(),
            ],
        ];
    }
}
