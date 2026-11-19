<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ServiceClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('List the internal APIs (service clients) registered in this environment')]
#[Signature('service-client:list')]
final class ListServiceClientsCommand extends Command
{
    public function handle(): int
    {
        $rows = ServiceClient::query()->orderBy('name')->get()->map(fn (ServiceClient $client): array => [
            $client->id,
            $client->name,
            implode(', ', $client->abilities),
            $client->isRevoked() ? 'revoked' : 'active',
        ]);

        $this->table(['client_id', 'name', 'abilities', 'status'], $rows->all());

        return self::SUCCESS;
    }
}
