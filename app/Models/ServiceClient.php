<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ServiceClientFactory;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

/**
 * Uma API interna que consome esta API (comunicação serviço a serviço).
 *
 * É "autenticável" como um usuário, mas não é uma pessoa: não tem e-mail,
 * senha de login nem perfis. Identifica-se com client_id (o id) + client_secret
 * e recebe um token Sanctum de vida curta com as suas abilities (ADR 0008).
 *
 * O HasApiTokens aqui é o do Sanctum (guard "internal"); o do Passport fica em User.
 *
 * @property string $id
 * @property string $name
 * @property string $secret
 * @property list<string> $abilities
 * @property CarbonImmutable|null $revoked_at
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['name', 'secret', 'abilities'])]
#[Hidden(['secret'])]
class ServiceClient extends Model implements AuthenticatableContract
{
    /** @use HasFactory<ServiceClientFactory> */
    use Authenticatable, HasApiTokens, HasFactory, HasUuids;

    /**
     * A "senha" deste autenticável é a coluna secret (o trait Authenticatable assume "password").
     */
    public function getAuthPasswordName(): string
    {
        return 'secret';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // "hashed": atribuir o segredo em texto puro grava o hash (bcrypt).
            'secret' => 'hashed',
            'abilities' => 'array',
            'revoked_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
