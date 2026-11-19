<?php

declare(strict_types=1);

namespace App\Auth\Access;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\ConnectionResolverInterface;

/**
 * Action: deixa o banco de acordo com o que o código declara.
 *
 *   - cada case de PermissionName vira (ou atualiza) uma linha em permissions;
 *   - permissões que saíram do enum são apagadas (os vínculos caem por cascade);
 *   - os perfis de sistema (RoleName) passam a existir.
 *
 * Idempotente e segura para produção: roda em todo deploy via `auth:install`
 * ou `permissions:sync`. Nunca mexe nas permissões de um perfil que já existe:
 * isso é decisão de quem administra, não do deploy.
 */
final readonly class SyncAccessControl
{
    public function __construct(private ConnectionResolverInterface $connections) {}

    public function __invoke(): void
    {
        $this->connections->connection()->transaction(function (): void {
            foreach (PermissionName::cases() as $permission) {
                Permission::query()->updateOrCreate(
                    ['name' => $permission->value],
                    ['description' => $permission->description()],
                );
            }

            Permission::query()->whereNotIn('name', PermissionName::values())->delete();

            foreach (RoleName::cases() as $role) {
                $model = Role::query()->firstOrCreate(
                    ['name' => $role->value],
                    ['description' => $role->description()],
                );

                // Permissões iniciais só no nascimento do perfil; depois disso ele é do administrador.
                if ($model->wasRecentlyCreated) {
                    $this->grantDefaults($model, $role);
                }
            }
        });
    }

    private function grantDefaults(Role $model, RoleName $role): void
    {
        $defaults = array_map(
            fn (PermissionName $permission): string => $permission->value,
            $role->defaultPermissions(),
        );

        if ($defaults === []) {
            return;
        }

        $model->permissions()->sync(Permission::query()->whereIn('name', $defaults)->pluck('id'));
    }
}
