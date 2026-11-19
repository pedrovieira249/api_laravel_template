<?php

declare(strict_types=1);

namespace App\Auth\Users;

use Illuminate\Http\Request;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Response;

/**
 * Único ponto que conversa com o servidor OAuth2 do Passport para emitir
 * tokens de usuário.
 *
 * Em vez de expor POST /oauth/token e pedir que o cliente conheça grant types
 * e client_id, a API oferece /auth/login e /auth/refresh e este emissor faz a
 * requisição OAuth por dentro, em memória: monta o pedido no formato que o
 * servidor de autorização espera e lê a resposta. Nenhuma chamada HTTP acontece.
 *
 * Quem decide a validade dos tokens é o AppServiceProvider (Passport::tokensExpireIn).
 */
final readonly class UserTokenIssuer
{
    /**
     * Erro OAuth para "o que você apresentou não vale": senha errada, usuário
     * inexistente, refresh token inválido, expirado, revogado ou de outro client.
     */
    private const string INVALID_GRANT = 'invalid_grant';

    public function __construct(
        private AuthorizationServer $server,
        private FirstPartyClient $client,
    ) {}

    /**
     * Password grant: troca e-mail + senha por um par de tokens.
     * Devolve null quando as credenciais não conferem.
     */
    public function forCredentials(string $email, #[\SensitiveParameter] string $password): ?TokenPair
    {
        return $this->grant([
            'grant_type' => 'password',
            'username' => $email,
            'password' => $password,
        ]);
    }

    /**
     * Refresh token grant: troca um refresh token por um par novo. O Passport
     * revoga o par antigo no mesmo passo (rotação), então cada refresh token
     * só funciona uma vez. Devolve null quando o refresh token não é aceito.
     */
    public function forRefreshToken(#[\SensitiveParameter] string $refreshToken): ?TokenPair
    {
        return $this->grant([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
    }

    /**
     * @param  array<string, string>  $parameters
     */
    private function grant(#[\SensitiveParameter] array $parameters): ?TokenPair
    {
        $factory = new PsrHttpFactory;

        // Uma requisição nova, só com os parâmetros do grant: nada da requisição HTTP
        // em curso (headers, corpo) chega ao servidor de autorização.
        $request = $factory->createRequest(Request::create('/oauth/token', 'POST', [
            ...$parameters,
            'client_id' => $this->client->id(),
            'scope' => '',
        ]));

        try {
            $response = $this->server->respondToAccessTokenRequest($request, $factory->createResponse(new Response));
        } catch (OAuthServerException $exception) {
            if ($exception->getErrorType() === self::INVALID_GRANT) {
                return null;
            }

            // Qualquer outro erro OAuth aqui é de configuração, não do usuário: o caso
            // típico é o client da casa não existir neste banco.
            throw new \LogicException(
                sprintf('Could not issue user tokens (%s). Did you run "php artisan auth:install"?', $exception->getErrorType()),
                $exception->getCode(),
                $exception,
            );
        }

        return $this->toTokenPair((string) $response->getBody());
    }

    private function toTokenPair(#[\SensitiveParameter] string $json): TokenPair
    {
        $payload = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);

        if (
            ! is_array($payload)
            || ! is_string($payload['access_token'] ?? null)
            || ! is_string($payload['refresh_token'] ?? null)
            || ! is_int($payload['expires_in'] ?? null)
        ) {
            throw new \LogicException('The authorization server answered without the expected token fields.');
        }

        return new TokenPair($payload['access_token'], $payload['refresh_token'], $payload['expires_in']);
    }
}
