<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

/**
 * Usuário externo: uma pessoa (ou aplicação cliente) que consome a API.
 *
 * Identity continua nativo do Laravel (ADR 0001): aqui um model Eloquent é o
 * modelo de domínio, porque não há regra de negócio rica a proteger.
 *
 * O HasApiTokens é o do Passport (guard "api"). O do Sanctum, de mesmo nome,
 * fica em ServiceClient: cada trait em um model evita o conflito de métodos
 * entre os dois pacotes e deixa explícito quem usa qual mecanismo (ADR 0008).
 *
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property CarbonImmutable|null $created_at
 * @property-read Collection<int, Role> $roles
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements OAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Verdadeiro se o usuário tem pelo menos um dos perfis informados.
     */
    public function hasRole(RoleName|string ...$roles): bool
    {
        $wanted = array_map(
            fn (RoleName|string $role): string => $role instanceof RoleName ? $role->value : $role,
            $roles,
        );

        return array_intersect($wanted, $this->roleNames()) !== [];
    }

    /**
     * Verdadeiro se algum perfil do usuário concede pelo menos uma das permissões.
     */
    public function hasPermission(PermissionName|string ...$permissions): bool
    {
        $wanted = array_map(
            fn (PermissionName|string $permission): string => $permission instanceof PermissionName ? $permission->value : $permission,
            $permissions,
        );

        return array_intersect($wanted, $this->permissionNames()) !== [];
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(RoleName::Admin);
    }

    /**
     * @return list<string>
     */
    public function roleNames(): array
    {
        $names = $this->loadedRoles()->map(fn (Role $role): string => $role->name)->all();
        sort($names);

        return $names;
    }

    /**
     * União das permissões de todos os perfis. O perfil admin não depende de
     * linhas na tabela pivô: ele recebe o catálogo inteiro, então uma permissão
     * nova já vale para administradores no deploy que a introduz.
     *
     * @return list<string>
     */
    public function permissionNames(): array
    {
        if ($this->isAdmin()) {
            return PermissionName::values();
        }

        $names = [];

        foreach ($this->loadedRoles() as $role) {
            foreach ($role->permissions as $permission) {
                $names[$permission->name] = $permission->name;
            }
        }

        sort($names);

        return $names;
    }

    /**
     * Descarta os perfis em memória. Chame depois de alterar os vínculos, para
     * que a próxima verificação leia o estado novo do banco.
     */
    public function forgetCachedRoles(): static
    {
        return $this->unsetRelation('roles');
    }

    /**
     * Hook do Passport para o password grant: localiza o usuário pelo "username"
     * enviado no login. Normaliza o e-mail como o registro faz ao gravar.
     */
    public function findForPassport(string $username): ?self
    {
        return self::query()->where('email', mb_strtolower(mb_trim($username)))->first();
    }

    /**
     * Carregamento explícito (eager) de perfis + permissões, uma vez por instância:
     * duas consultas na primeira verificação da requisição, nenhuma nas seguintes.
     * As permissões são lidas do banco a cada requisição, não do token, então
     * trocar o perfil de alguém vale imediatamente, sem esperar o token expirar.
     *
     * @return Collection<int, Role>
     */
    private function loadedRoles(): Collection
    {
        return $this->loadMissing('roles.permissions')->roles;
    }
}
