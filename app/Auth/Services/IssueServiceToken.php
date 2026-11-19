<?php

declare(strict_types=1);

namespace App\Auth\Services;

use App\Models\ServiceClient;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\Date;

/**
 * Action: uma API interna troca client_id + client_secret por um token Sanctum
 * de vida curta (1 hora por padrão, config identity.services.token_ttl).
 *
 * Não há refresh token: quando o prazo vence, o serviço repete esta chamada.
 * Cada chamada emite um token novo e não invalida os anteriores, porque um
 * mesmo serviço costuma rodar em várias instâncias, cada uma com o seu.
 */
final readonly class IssueServiceToken
{
    private const string TOKEN_NAME = 'service-token';

    public function __construct(
        private Hasher $hasher,
        private Repository $config,
    ) {}

    /**
     * @throws AuthenticationException
     */
    public function __invoke(string $clientId, #[\SensitiveParameter] string $clientSecret): ServiceToken
    {
        $client = ServiceClient::query()->find($clientId);

        if ($client === null) {
            // Gasta o mesmo tempo de uma verificação de hash, para que a demora da
            // resposta não revele se o client_id existe.
            $this->hasher->make($clientSecret);

            throw $this->invalidCredentials();
        }

        if (! $this->hasher->check($clientSecret, $client->secret) || $client->isRevoked()) {
            throw $this->invalidCredentials();
        }

        $minutes = $this->config->integer('identity.services.token_ttl');

        $token = $client->createToken(
            name: self::TOKEN_NAME,
            abilities: $client->abilities,
            expiresAt: Date::now()->addMinutes($minutes),
        );

        return new ServiceToken($token->plainTextToken, $minutes * 60, $client->abilities);
    }

    /**
     * Uma única mensagem para client_id desconhecido, segredo errado e cliente revogado.
     */
    private function invalidCredentials(): AuthenticationException
    {
        return new AuthenticationException('Invalid client credentials.');
    }
}
