<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Auth\Users\AuthenticateUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

/**
 * Login de usuário externo: e-mail + senha por access token (5 min) + refresh token.
 * A rota tem throttle:login contra força bruta (routes/api.php).
 */
final class LoginController extends Controller
{
    public function __construct(private readonly AuthenticateUser $authenticate) {}

    /**
     * @unauthenticated
     */
    public function __invoke(LoginRequest $request): JsonResponse
    {
        $authenticated = ($this->authenticate)(
            email: $request->string('email')->toString(),
            password: $request->string('password')->toString(),
        );

        return new UserResource($authenticated->user)
            ->additional($authenticated->tokens->toArray())
            ->response();
    }
}
