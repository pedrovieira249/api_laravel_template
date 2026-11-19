<?php

declare(strict_types=1);

namespace App\Auth\Access;

use App\Enums\RoleName;
use App\Exceptions\LastAdministratorCannotBeDemoted;
use App\Exceptions\OnlyAdministratorsManageAdministrators;
use App\Models\Role;
use App\Models\User;

/**
 * Action: define o conjunto de perfis de um usuário (substitui o anterior).
 *
 * A mudança vale na próxima requisição do usuário: as permissões são lidas do
 * banco a cada requisição, não ficam gravadas no token.
 *
 * Duas travas protegem o perfil admin:
 *   - só um administrador concede ou retira o perfil admin. Sem isso, qualquer
 *     perfil com a permissão users.manage poderia se promover a admin;
 *   - o último administrador não pode perder o perfil.
 */
final readonly class AssignUserRoles
{
    /**
     * @param  list<string>  $roles  nomes de perfis existentes
     * @param  User|null  $actor  quem está fazendo a mudança; null = o próprio sistema (console, seeder)
     *
     * @throws OnlyAdministratorsManageAdministrators
     * @throws LastAdministratorCannotBeDemoted
     */
    public function __invoke(User $user, array $roles, ?User $actor = null): User
    {
        return $user->getConnection()->transaction(function () use ($user, $roles, $actor): User {
            $wasAdmin = $user->forgetCachedRoles()->isAdmin();
            $willBeAdmin = in_array(RoleName::Admin->value, $roles, true);

            if ($wasAdmin !== $willBeAdmin && $actor instanceof User && ! $actor->isAdmin()) {
                throw OnlyAdministratorsManageAdministrators::create();
            }

            if ($wasAdmin && ! $willBeAdmin && $this->isLastAdministrator($user)) {
                throw LastAdministratorCannotBeDemoted::create();
            }

            $user->roles()->sync(Role::query()->whereIn('name', $roles)->pluck('id'));

            return $user->forgetCachedRoles();
        });
    }

    /**
     * lockForUpdate: duas requisições simultâneas rebaixando os dois últimos
     * admins se enfileiram aqui, e a segunda já enxerga que sobrou um só.
     */
    private function isLastAdministrator(User $user): bool
    {
        $admin = Role::query()->where('name', RoleName::Admin->value)->lockForUpdate()->first();

        return $admin === null
            || ! $admin->users()->whereKeyNot($user->getKey())->exists();
    }
}
