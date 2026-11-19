<?php

declare(strict_types=1);

namespace App\Auth\Services;

use App\Models\ServiceClient;

/**
 * Um cliente de serviço recém-criado junto com o segredo em texto puro.
 * Este objeto é a única chance de ler o segredo: o banco só guarda o hash.
 */
final readonly class NewServiceClient
{
    public function __construct(
        public ServiceClient $client,
        public string $plainSecret,
    ) {}
}
