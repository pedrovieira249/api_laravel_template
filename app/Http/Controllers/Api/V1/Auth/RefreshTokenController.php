<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Auth\Users\RefreshUserTokens;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RefreshTokenRequest;
use Illuminate\Http\JsonResponse;

/**
 * Rota pública de propósito: quem chama está justamente com o access token
 * vencido. A credencial é o próprio refresh token.
 */
final class RefreshTokenController extends Controller
{
    public function __construct(private readonly RefreshUserTokens $refresh) {}

    /**
     * @unauthenticated
     */
    public function __invoke(RefreshTokenRequest $request): JsonResponse
    {
        $tokens = ($this->refresh)($request->string('refresh_token')->toString());

        return new JsonResponse($tokens->toArray());
    }
}
