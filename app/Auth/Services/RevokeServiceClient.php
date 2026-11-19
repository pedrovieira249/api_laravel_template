<?php

declare(strict_types=1);

namespace App\Auth\Services;

use App\Models\ServiceClient;
use Illuminate\Support\Facades\Date;

/**
 * Action: corta o acesso de uma API interna, agora.
 *
 * Marca o cliente como revogado (não emite mais tokens) e apaga os tokens que
 * ele já tem (os em uso param de funcionar na próxima requisição). A linha do
 * cliente fica, para auditoria.
 */
final readonly class RevokeServiceClient
{
    public function __invoke(ServiceClient $client): void
    {
        $client->getConnection()->transaction(function () use ($client): void {
            if (! $client->isRevoked()) {
                $client->forceFill(['revoked_at' => Date::now()])->save();
            }

            $client->tokens()->delete();
        });
    }
}
