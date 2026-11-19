<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Internal;

use App\Http\Controllers\Controller;
use App\Models\ServiceClient;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

/**
 * Descarta o token usado na requisição (por exemplo, no desligamento de uma
 * instância do serviço). Os outros tokens do mesmo cliente continuam válidos.
 */
final class RevokeServiceTokenController extends Controller
{
    public function __invoke(#[CurrentUser('internal')] ServiceClient $client): Response
    {
        $client->currentAccessToken()->delete();

        return response()->noContent();
    }
}
