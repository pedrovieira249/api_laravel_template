<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Auth\Services\RegisterServiceClient;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateServiceClientRequest;
use App\Http\Resources\ServiceClientResource;
use Illuminate\Http\JsonResponse;

/**
 * Cadastra uma API interna. A resposta é o único momento em que o
 * client_secret aparece em texto puro: quem chama precisa guardá-lo agora.
 */
final class CreateServiceClientController extends Controller
{
    public function __construct(private readonly RegisterServiceClient $register) {}

    public function __invoke(CreateServiceClientRequest $request): JsonResponse
    {
        $created = ($this->register)(
            name: $request->string('name')->toString(),
            abilities: $request->abilities(),
        );

        return new ServiceClientResource($created->client)
            ->additional(['client_secret' => $created->plainSecret])
            ->response()
            ->setStatusCode(201);
    }
}
