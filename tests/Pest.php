<?php

declare(strict_types=1);

use App\Auth\Users\FirstPartyClient;
use App\Models\ServiceClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Suítes de teste
|--------------------------------------------------------------------------
|
| Unit:        PHP puro, sem container, sem banco (Domain + Application).
| Integration: container Laravel + banco MySQL de teste (adapters, actions).
| Feature:     HTTP de ponta a ponta através do kernel.
| Architecture: regras de dependência, garantidas com arch().
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        // O client OAuth da casa precisa existir para qualquer teste que faça login
        // de verdade (em um ambiente real, é o `auth:install` que o cria).
        app(FirstPartyClient::class)->ensure();
    })
    ->in('Feature', 'Integration');

/*
|--------------------------------------------------------------------------
| Helpers de autenticação
|--------------------------------------------------------------------------
|
| Duas formas de "estar logado" em um teste:
|
|   actingAsUser() / actingAsService()  atalho: coloca o principal direto no guard.
|                                       Use na maioria dos testes (regra de acesso, payloads).
|   loginAs()                           login de verdade: passa pelo servidor OAuth e
|                                       devolve os tokens. Use para testar os próprios tokens.
|
*/

/**
 * Autentica como um usuário externo no guard "api" (Passport), sem emitir token.
 */
function actingAsUser(User $user): User
{
    Passport::actingAs($user, [], 'api');

    return $user;
}

/**
 * Autentica como uma API interna no guard "internal" (Sanctum) com as abilities dadas.
 *
 * @param  list<string>  $abilities
 */
function actingAsService(ServiceClient $client, array $abilities = ['*']): ServiceClient
{
    Sanctum::actingAs($client, $abilities, 'internal');

    return $client;
}

/**
 * Faz login pelo endpoint real e devolve o corpo da resposta (tokens incluídos).
 *
 * @return array{access_token: string, refresh_token: string, expires_in: int, token_type: string}
 */
function loginAs(User $user, string $password = 'password'): array
{
    return test()->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => $password])
        ->assertOk()
        ->json();
}

/**
 * Os guards guardam em cache o principal resolvido durante o processo. Descartá-los
 * faz a próxima requisição do teste autenticar do zero, como uma requisição HTTP nova.
 */
function freshRequest(): void
{
    app('auth')->forgetGuards();
}

/**
 * Lê as claims de um JWT sem validar a assinatura (só para asserções).
 *
 * @return array<string, mixed>
 */
function jwtClaims(string $jwt): array
{
    $payload = explode('.', $jwt)[1] ?? '';

    return json_decode(base64_decode(strtr($payload, '-_', '+/')), true, flags: JSON_THROW_ON_ERROR);
}
