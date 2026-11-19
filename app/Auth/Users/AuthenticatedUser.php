<?php

declare(strict_types=1);

namespace App\Auth\Users;

use App\Models\User;

/**
 * Resultado de registrar ou autenticar: o usuário e os tokens recém-emitidos.
 * O controller só precisa disso para montar a resposta.
 */
final readonly class AuthenticatedUser
{
    public function __construct(
        public User $user,
        public TokenPair $tokens,
    ) {}
}
