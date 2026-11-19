<?php

declare(strict_types=1);

namespace App\Auth\Access;

use App\Exceptions\RoleIsAssignedToUsers;
use App\Exceptions\SystemRoleCannotBeChanged;
use App\Models\Role;

/**
 * Action: apaga um perfil que não é de sistema e que ninguém mais usa.
 */
final readonly class DeleteRole
{
    /**
     * @throws SystemRoleCannotBeChanged
     * @throws RoleIsAssignedToUsers
     */
    public function __invoke(Role $role): void
    {
        if ($role->isSystem()) {
            throw SystemRoleCannotBeChanged::deleting($role);
        }

        $users = $role->users()->count();

        if ($users > 0) {
            throw RoleIsAssignedToUsers::for($role, $users);
        }

        $role->delete();
    }
}
