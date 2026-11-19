<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Auth\Access\SyncAccessControl;
use App\Auth\Users\FirstPartyClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Laravel\Passport\Passport;

/**
 * Deixa a autenticação pronta para uso. Idempotente: rode na instalação e em
 * todo deploy, depois do `migrate`.
 *
 *   1. chaves RSA do Passport (assinam os JWT), se ainda não existirem;
 *   2. o client OAuth da casa, usado por /auth/login e /auth/refresh;
 *   3. o catálogo de permissões e os perfis de sistema.
 */
#[Description('Prepare authentication: Passport keys, first-party OAuth client, permissions and system roles')]
#[Signature('auth:install')]
final class AuthInstallCommand extends Command
{
    public function handle(FirstPartyClient $client, SyncAccessControl $sync, Repository $config): int
    {
        $this->ensureKeys($config);

        $client->ensure();
        $this->components->info(sprintf('First-party OAuth client ready (%s).', $client->id()));

        $sync();
        $this->components->info('Permissions and system roles are in sync.');

        return self::SUCCESS;
    }

    /**
     * Em produção com mais de um servidor as chaves precisam ser as mesmas em
     * todos: injete-as pelas variáveis PASSPORT_PRIVATE_KEY / PASSPORT_PUBLIC_KEY.
     * Quando elas existem, nenhum arquivo é gerado.
     */
    private function ensureKeys(Repository $config): void
    {
        if (is_string($config->get('passport.private_key')) && $config->get('passport.private_key') !== '') {
            $this->components->info('Passport keys come from the environment.');

            return;
        }

        if (file_exists(Passport::keyPath('oauth-private.key')) && file_exists(Passport::keyPath('oauth-public.key'))) {
            $this->components->info('Passport keys already exist.');

            return;
        }

        $this->call('passport:keys', ['--force' => true]);
    }
}
