<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\PermissionName;
use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Restringe um grupo de rotas a quem tem uma permissão (alias "permission").
 *
 *   Route::middleware(['auth:api', 'permission:roles.manage'])->group(...);
 *   Route::middleware(['auth:api', 'permission:users.read,users.manage'])->group(...);  // uma OU outra
 *
 * A permissão vem dos perfis do usuário (tabelas roles/permissions), lidos do
 * banco nesta requisição: mudar o perfil de alguém vale na chamada seguinte.
 * Sem usuário responde 401; sem a permissão responde 403.
 */
final class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user() ?? throw new AuthenticationException;

        if (! $user instanceof User || ! $user->hasPermission(...$permissions)) {
            throw new AccessDeniedHttpException('You do not have permission to perform this action.');
        }

        return $next($request);
    }

    /**
     * Monta a string do middleware a partir do enum, à prova de erro de digitação:
     * EnsureUserHasPermission::using(PermissionName::RolesManage).
     */
    public static function using(PermissionName ...$permissions): string
    {
        return 'permission:'.implode(',', array_map(
            fn (PermissionName $permission): string => $permission->value,
            $permissions,
        ));
    }
}
