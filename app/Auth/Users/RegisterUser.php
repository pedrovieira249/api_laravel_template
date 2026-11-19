<?php

declare(strict_types=1);

namespace App\Auth\Users;

use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;

/**
 * Action (classe de um método): o caso de uso "registrar usuário" fora do
 * controller. Identity não tem hexágono (ADR 0001), mas orquestração não é
 * assunto de controller: aqui dá para testar sem o kernel HTTP e garantir
 * atomicidade (usuário + perfil + tokens na mesma transação).
 *
 * A conta recebe sempre o perfil padrão (RoleName::User); o perfil nunca vem de
 * quem chama. Outros perfis são atribuídos por um administrador. A action não sabe
 * quem pode chamá-la: o registro é fechado na rota (token de serviço, ADR 0011).
 *
 * A transação é aberta na conexão do próprio model ($user->getConnection()),
 * não na conexão padrão: se um dia User apontar para outro banco, a transação
 * acompanha.
 */
final readonly class RegisterUser
{
    public function __construct(private UserTokenIssuer $tokens) {}

    public function __invoke(string $name, string $email, #[\SensitiveParameter] string $password): AuthenticatedUser
    {
        $user = new User([
            'name' => $name,
            'email' => mb_strtolower(mb_trim($email)),
            'password' => $password,
        ]);

        return $user->getConnection()->transaction(function () use ($user, $password): AuthenticatedUser {
            $user->save();
            $user->roles()->attach($this->defaultRole());

            // O password grant lê o usuário pela mesma conexão, então enxerga a linha
            // ainda não commitada; se a emissão falhar, o usuário não fica para trás.
            $tokens = $this->tokens->forCredentials($user->email, $password)
                ?? throw new \LogicException('A freshly registered user could not be authenticated.');

            return new AuthenticatedUser($user, $tokens);
        });
    }

    /**
     * firstOrCreate em vez de exigir o `auth:install`: o perfil padrão é parte do
     * contrato do registro, então ele passa a existir na primeira vez que for preciso.
     */
    private function defaultRole(): Role
    {
        return Role::query()->firstOrCreate(
            ['name' => RoleName::User->value],
            ['description' => RoleName::User->description()],
        );
    }
}
