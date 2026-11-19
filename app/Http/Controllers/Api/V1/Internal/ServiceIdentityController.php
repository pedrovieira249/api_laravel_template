<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Internal;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceClientResource;
use App\Models\ServiceClient;
use Illuminate\Container\Attributes\CurrentUser;

/**
 * "Quem sou eu?" para uma API interna: útil para conferir, na integração, que
 * o token funciona e quais abilities ele carrega.
 */
final class ServiceIdentityController extends Controller
{
    public function __invoke(#[CurrentUser('internal')] ServiceClient $client): ServiceClientResource
    {
        return new ServiceClientResource($client);
    }
}
