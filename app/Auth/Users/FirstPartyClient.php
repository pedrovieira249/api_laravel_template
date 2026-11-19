<?php

declare(strict_types=1);

namespace App\Auth\Users;

use Illuminate\Contracts\Config\Repository;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;

/**
 * O client OAuth "da casa" que os endpoints /auth/login e /auth/refresh usam.
 *
 * O OAuth2 exige que todo token pertença a um client. Como aqui quem pede o
 * token é a própria API (em nome do usuário que mandou e-mail e senha), existe
 * um único client, com id fixo em config/identity.php. Ele é público (sem
 * segredo): nunca é exposto, porque as rotas /oauth/* do Passport estão
 * desligadas e só o UserTokenIssuer o utiliza.
 */
final readonly class FirstPartyClient
{
    /**
     * Precisa ser o nome do provider do guard "api" (config/auth.php): o guard do
     * Passport recusa tokens de um client cujo provider seja outro.
     */
    private const string PROVIDER = 'users';

    public function __construct(private Repository $config) {}

    public function id(): string
    {
        return $this->config->string('identity.users.client.id');
    }

    /**
     * Cria o client se ele ainda não existir e garante que continua ativo.
     * Idempotente: é o que `php artisan auth:install` executa em todo deploy.
     */
    public function ensure(): Client
    {
        /** @var Client $client */
        $client = Passport::client()->newQuery()->firstOrCreate(['id' => $this->id()], [
            'name' => $this->config->string('identity.users.client.name'),
            'secret' => null,
            'provider' => self::PROVIDER,
            'redirect_uris' => [],
            'grant_types' => ['password', 'refresh_token'],
            'revoked' => false,
        ]);

        return $client;
    }
}
