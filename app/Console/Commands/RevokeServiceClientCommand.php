<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Auth\Services\RevokeServiceClient;
use App\Models\ServiceClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Revoke an internal API: it stops receiving tokens and its current tokens are deleted')]
#[Signature('service-client:revoke {client : The client_id or the name of the service client}')]
final class RevokeServiceClientCommand extends Command
{
    public function handle(RevokeServiceClient $revoke): int
    {
        $identifier = (string) $this->argument('client');

        $client = ServiceClient::query()
            ->where('id', $identifier)
            ->orWhere('name', $identifier)
            ->first();

        if ($client === null) {
            $this->components->error(sprintf('No service client with id or name "%s".', $identifier));

            return self::FAILURE;
        }

        $revoke($client);

        $this->components->info(sprintf('Service client "%s" revoked.', $client->name));

        return self::SUCCESS;
    }
}
