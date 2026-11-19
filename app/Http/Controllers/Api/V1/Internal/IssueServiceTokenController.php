<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Internal;

use App\Auth\Services\IssueServiceToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\IssueServiceTokenRequest;
use Illuminate\Http\JsonResponse;

/**
 * Porta de entrada das APIs internas: client_id + client_secret por um token
 * Sanctum de 1 hora. O serviço guarda o token em memória e repete a chamada
 * quando ele expira (ou quando receber 401).
 */
final class IssueServiceTokenController extends Controller
{
    public function __construct(private readonly IssueServiceToken $issue) {}

    /**
     * @unauthenticated
     */
    public function __invoke(IssueServiceTokenRequest $request): JsonResponse
    {
        $token = ($this->issue)(
            clientId: $request->string('client_id')->toString(),
            clientSecret: $request->string('client_secret')->toString(),
        );

        return new JsonResponse($token->toArray());
    }
}
