<?php

declare(strict_types=1);

namespace App\Auth\Services;

/**
 * O que uma API interna recebe ao se autenticar: o token Sanctum em texto puro
 * (só existe nesta resposta; o banco guarda o hash), o prazo e as abilities.
 */
final readonly class ServiceToken
{
    public const string TOKEN_TYPE = 'Bearer';

    /**
     * @param  list<string>  $abilities
     */
    public function __construct(
        public string $accessToken,
        public int $expiresIn,
        public array $abilities,
    ) {}

    /**
     * @return array{token_type: string, access_token: string, expires_in: int, abilities: list<string>}
     */
    public function toArray(): array
    {
        return [
            'token_type' => self::TOKEN_TYPE,
            'access_token' => $this->accessToken,
            'expires_in' => $this->expiresIn,
            'abilities' => $this->abilities,
        ];
    }
}
