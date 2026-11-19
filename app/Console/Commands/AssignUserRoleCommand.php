<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Dá um perfil a um usuário pelo terminal. É como nasce o primeiro
 * administrador de um ambiente novo, quando ainda não há ninguém com acesso à
 * API de administração:
 *
 *   php artisan user:assign-role ana@empresa.com admin
 */
#[Description('Add a role to an existing user (e.g. to create the first administrator)')]
#[Signature('user:assign-role {email : E-mail of an existing user} {role : Name of an existing role}')]
final class AssignUserRoleCommand extends Command
{
    public function handle(): int
    {
        $email = mb_strtolower(mb_trim((string) $this->argument('email')));
        $roleName = (string) $this->argument('role');

        $user = User::query()->where('email', $email)->first();
        $role = Role::query()->where('name', $roleName)->first();

        if ($user === null) {
            $this->components->error(sprintf('No user with the e-mail "%s".', $email));

            return self::FAILURE;
        }

        if ($role === null) {
            $this->components->error(sprintf('No role named "%s". Did you run "php artisan auth:install"?', $roleName));

            return self::FAILURE;
        }

        $user->roles()->syncWithoutDetaching([$role->id]);

        $this->components->info(sprintf('%s now has the "%s" role.', $user->email, $role->name));

        return self::SUCCESS;
    }
}
