<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Auth\Access\SyncAccessControl;
use App\Auth\Users\FirstPartyClient;
use Illuminate\Database\Seeder;

/**
 * O mínimo para a autenticação funcionar em um banco recém-criado: o client
 * OAuth da casa, o catálogo de permissões e os perfis de sistema. É o mesmo
 * que o `auth:install` faz (menos as chaves), para que `migrate:fresh --seed`
 * devolva uma aplicação em que dá para fazer login.
 */
class IdentitySeeder extends Seeder
{
    public function run(FirstPartyClient $client, SyncAccessControl $sync): void
    {
        $client->ensure();
        $sync();
    }
}
