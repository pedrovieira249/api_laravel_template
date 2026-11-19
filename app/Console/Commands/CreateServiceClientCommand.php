<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Auth\Services\RegisterServiceClient;
use App\Enums\ServiceAbility;
use App\Models\ServiceClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Cadastra uma API interna e mostra as credenciais dela, uma única vez:
 *
 *   php artisan service-client:create billing-api --ability=<ability>
 *
 * As abilities aceitas são os cases de App\Enums\ServiceAbility.
 *
 * O console é só mais uma porta de entrada: a action é a mesma do endpoint
 * POST /api/v1/admin/service-clients.
 */
#[Description('Register an internal API (service client) and print its credentials once')]
#[Signature('service-client:create {name : Unique slug, e.g. billing-api} {--ability=* : Ability granted to the client (repeatable)}')]
final class CreateServiceClientCommand extends Command
{
    public function handle(RegisterServiceClient $register): int
    {
        $name = (string) $this->argument('name');
        $abilities = array_values(array_filter((array) $this->option('ability'), is_string(...)));

        if (preg_match('/^[a-z][a-z0-9_-]{0,99}$/', $name) !== 1) {
            $this->components->error('The name must be a slug: lower-case letters, digits, "-" and "_".');

            return self::FAILURE;
        }

        if (ServiceClient::query()->where('name', $name)->exists()) {
            $this->components->error(sprintf('A service client named "%s" already exists.', $name));

            return self::FAILURE;
        }

        $unknown = array_diff($abilities, ServiceAbility::values());

        if ($abilities === [] || $unknown !== []) {
            $this->components->error(sprintf(
                'Pass at least one valid --ability. Available: %s.',
                implode(', ', ServiceAbility::values()),
            ));

            return self::FAILURE;
        }

        $created = $register($name, $abilities);

        $this->components->info('Service client created.');
        $this->components->twoColumnDetail('client_id', $created->client->id);
        $this->components->twoColumnDetail('client_secret', $created->plainSecret);
        $this->components->twoColumnDetail('abilities', implode(', ', $created->client->abilities));
        $this->components->warn('Store the client_secret now. It cannot be shown again.');

        return self::SUCCESS;
    }
}
