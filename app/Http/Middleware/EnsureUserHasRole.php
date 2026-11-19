<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\RoleName;
use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Restringe um grupo de rotas a perfis de usuário (alias "role").
 *
 *   Route::middleware(['auth:api', 'role:admin'])->group(...);            // só admin
 *   Route::middleware(['auth:api', 'role:admin,manager'])->group(...);    // admin OU manager
 *
 * Vem sempre depois de `auth:api`: quem autentica é o guard, este middleware
 * só pergunta "esse usuário tem o perfil?". Sem usuário responde 401; com
 * usuário sem o perfil (ou com um cliente de serviço no lugar) responde 403.
 *
 * Prefira `permission:` quando a regra for sobre o que pode ser feito, e
 * `role:` quando for sobre quem a pessoa é (docs/guide/03-permissoes.md).
 */
final class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user() ?? throw new AuthenticationException;

        if (! $user instanceof User || ! $user->hasRole(...$roles)) {
            throw new AccessDeniedHttpException('Your profile does not give access to this resource.');
        }

        return $next($request);
    }

    /**
     * Monta a string do middleware a partir do enum, para referenciar perfis de
     * sistema sem digitar o nome: EnsureUserHasRole::using(RoleName::Admin).
     */
    public static function using(RoleName|string ...$roles): string
    {
        return 'role:'.implode(',', array_map(
            fn (RoleName|string $role): string => $role instanceof RoleName ? $role->value : $role,
            $roles,
        ));
    }
}
