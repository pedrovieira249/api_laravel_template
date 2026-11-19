<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Role;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Perfis de sistema (App\Enums\RoleName) sustentam o próprio controle de
 * acesso: apagá-los ou renomeá-los quebraria o registro de usuários e trancaria
 * a administração. Vira 409 pelo renderer central.
 */
final class SystemRoleCannotBeChanged extends ConflictHttpException
{
    public static function deleting(Role $role): self
    {
        return new self(sprintf('The "%s" role is a system role and cannot be deleted.', $role->name));
    }

    public static function renaming(Role $role): self
    {
        return new self(sprintf('The "%s" role is a system role and cannot be renamed.', $role->name));
    }

    public static function changingPermissionsOf(Role $role): self
    {
        return new self(sprintf('The "%s" role always has every permission; its list cannot be edited.', $role->name));
    }
}
