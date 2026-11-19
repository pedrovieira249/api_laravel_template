<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Role;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Apagar um perfil em uso tiraria acesso de pessoas em silêncio. A API exige
 * que os usuários sejam movidos para outro perfil antes.
 */
final class RoleIsAssignedToUsers extends ConflictHttpException
{
    public static function for(Role $role, int $users): self
    {
        return new self(sprintf('The "%s" role is assigned to %d user(s). Reassign them before deleting it.', $role->name, $users));
    }
}
