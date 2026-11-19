<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Auth\Users\RevokeUserTokens;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final class LogoutController extends Controller
{
    public function __construct(private readonly RevokeUserTokens $revoke) {}

    /**
     * #[CurrentUser('api')] injeta o usuário autenticado pelo guard do Passport,
     * já tipado como User (sem $request->user() e sem checagem de null).
     */
    public function __invoke(#[CurrentUser('api')] User $user): Response
    {
        ($this->revoke)($user);

        return response()->noContent();
    }
}
