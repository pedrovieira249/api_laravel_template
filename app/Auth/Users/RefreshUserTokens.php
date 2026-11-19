<?php

declare(strict_types=1);

namespace App\Auth\Users;

use Illuminate\Auth\AuthenticationException;

/**
 * Action: troca um refresh token válido por um par novo, sem pedir a senha.
 *
 * É o que torna viável um access token de 5 minutos: o cliente renova em
 * segundo plano quando recebe 401 (ou pouco antes de expires_in). Refresh
 * token inválido, expirado ou já usado vira 401: o cliente precisa fazer login de novo.
 */
final readonly class RefreshUserTokens
{
    public function __construct(private UserTokenIssuer $tokens) {}

    /**
     * @throws AuthenticationException
     */
    public function __invoke(#[\SensitiveParameter] string $refreshToken): TokenPair
    {
        return $this->tokens->forRefreshToken($refreshToken)
            ?? throw new AuthenticationException('The refresh token is invalid, expired or already used.');
    }
}
