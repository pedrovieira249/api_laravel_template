<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Sem nenhum administrador ninguém mais conseguiria gerenciar perfis: a API
 * impede que o último admin perca o perfil (inclusive por conta própria).
 */
final class LastAdministratorCannotBeDemoted extends ConflictHttpException
{
    public static function create(): self
    {
        return new self('This is the last administrator. Promote another user before removing the admin role.');
    }
}
