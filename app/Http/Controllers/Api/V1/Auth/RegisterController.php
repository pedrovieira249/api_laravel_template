<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Auth\Users\RegisterUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

/**
 * Três linhas, como todo controller do projeto: validar (FormRequest),
 * executar (Action) e apresentar (Resource). Erros sobem para o renderer
 * central (ADR 0004); nada de try/catch aqui.
 *
 * A rota não é pública (ADR 0011): quem chama é um sistema da casa, com token de
 * serviço e a ability users.register. A resposta traz os tokens do usuário recém-criado,
 * para que esse sistema já o deixe logado.
 */
final class RegisterController extends Controller
{
    public function __construct(private readonly RegisterUser $register) {}

    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $authenticated = ($this->register)(
            name: $request->string('name')->toString(),
            email: $request->string('email')->toString(),
            password: $request->string('password')->toString(),
        );

        return new UserResource($authenticated->user)
            ->additional($authenticated->tokens->toArray())
            ->response()
            ->setStatusCode(201);
    }
}
