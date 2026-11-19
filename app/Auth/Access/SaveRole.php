<?php

declare(strict_types=1);

namespace App\Auth\Access;

use App\Exceptions\SystemRoleCannotBeChanged;
use App\Models\Permission;
use App\Models\Role;

/**
 * Action: cria ou altera um perfil e a lista de permissões que ele concede.
 *
 * As regras que protegem os perfis de sistema moram aqui, e não no FormRequest:
 * valem também para quem chamar esta action de um comando ou de um seeder.
 */
final readonly class SaveRole
{
    /**
     * @param  list<string>  $permissions  nomes de App\Enums\PermissionName
     */
    public function create(string $name, ?string $description, array $permissions): Role
    {
        $role = new Role(['name' => $name, 'description' => $description]);

        return $role->getConnection()->transaction(function () use ($role, $permissions): Role {
            $role->save();
            $this->syncPermissions($role, $permissions);

            return $role;
        });
    }

    /**
     * @param  list<string>|null  $permissions  null = não mexer nas permissões
     *
     * @throws SystemRoleCannotBeChanged
     */
    public function update(Role $role, string $name, ?string $description, ?array $permissions): Role
    {
        if ($role->isSystem() && $name !== $role->name) {
            throw SystemRoleCannotBeChanged::renaming($role);
        }

        if ($role->isSuperAdmin() && $permissions !== null) {
            throw SystemRoleCannotBeChanged::changingPermissionsOf($role);
        }

        return $role->getConnection()->transaction(function () use ($role, $name, $description, $permissions): Role {
            $role->fill(['name' => $name, 'description' => $description])->save();

            if ($permissions !== null) {
                $this->syncPermissions($role, $permissions);
            }

            return $role;
        });
    }

    /**
     * @param  list<string>  $permissions
     */
    private function syncPermissions(Role $role, array $permissions): void
    {
        $role->permissions()->sync(Permission::query()->whereIn('name', $permissions)->pluck('id'));
        $role->unsetRelation('permissions');
    }
}
