<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Auth\Access\SaveRole;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Três usuários para explorar a API local, um por nível de acesso (senha: password):
 *
 *   admin@example.test    perfil de sistema "admin"   -> tudo
 *   manager@example.test  perfil "manager" (criado aqui, como um admin criaria pela API)
 *   user@example.test     perfil de sistema "user"    -> o mínimo (RoleName::defaultPermissions())
 *
 * Idempotente: pode rodar várias vezes.
 */
class DemoUsersSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(SaveRole $roles): void
    {
        if (! Role::query()->where('name', 'manager')->exists()) {
            $roles->create('manager', 'Example of a custom role, created the way an administrator would through the API.', [
                PermissionName::UsersRead->value,
            ]);
        }

        $this->user('Admin', 'admin@example.test', RoleName::Admin->value);
        $this->user('Maria Manager', 'manager@example.test', 'manager');
        $this->user('Ugo User', 'user@example.test', RoleName::User->value);
    }

    private function user(string $name, string $email, string $role): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => 'password'],
        );

        $user->roles()->syncWithoutDetaching(Role::query()->where('name', $role)->pluck('id'));
    }
}
