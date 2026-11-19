<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * A permissão users.manage deixa trocar perfis de usuários, mas não o perfil
 * admin: se deixasse, quem a tem poderia se promover a administrador. Vira 403.
 */
final class OnlyAdministratorsManageAdministrators extends AccessDeniedHttpException
{
    public static function create(): self
    {
        return new self('Only an administrator can grant or remove the admin role.');
    }
}
