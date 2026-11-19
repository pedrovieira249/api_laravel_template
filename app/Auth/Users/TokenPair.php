<?php

declare(strict_types=1);

namespace App\Auth\Users;

/**
 * O que um login ou uma renovação entrega: o access token (JWT de vida curta),
 * o refresh token que o renova e em quantos segundos o access token expira.
 */
final readonly class TokenPair
{
    public const string TOKEN_TYPE = 'Bearer';

    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public int $expiresIn,
    ) {}

    /**
     * Os nomes de campo do padrão OAuth2 (RFC 6749 §5.1), que os clientes HTTP já conhecem.
     *
     * @return array{token_type: string, access_token: string, refresh_token: string, expires_in: int}
     */
    public function toArray(): array
    {
        return [
            'token_type' => self::TOKEN_TYPE,
            'access_token' => $this->accessToken,
            'refresh_token' => $this->refreshToken,
            'expires_in' => $this->expiresIn,
        ];
    }
}
