<?php

declare(strict_types=1);

namespace Tests\Support\Bus;

final readonly class WhoAmIHandler
{
    public function __invoke(WhoAmIQuery $query): string
    {
        return 'template';
    }
}
