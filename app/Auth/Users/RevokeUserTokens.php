<?php

declare(strict_types=1);

namespace App\Auth\Users;

use App\Models\User;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Passport;

/**
 * Action: logout. Revoga o access token usado na requisição e o refresh token
 * emitido junto com ele. Outros dispositivos (outros pares de tokens) continuam logados.
 *
 * Revogar o refresh token é o que importa de verdade: sem isso o access token
 * morreria em 5 minutos, mas o cliente poderia renová-lo por dias.
 */
final readonly class RevokeUserTokens
{
    public function __invoke(User $user): void
    {
        $token = $user->token();

        if (! $token instanceof AccessToken || ! isset($token->oauth_access_token_id)) {
            return;
        }

        $token->revoke();

        Passport::refreshToken()->newQuery()
            ->where('access_token_id', $token->oauth_access_token_id)
            ->update(['revoked' => true]);
    }
}
