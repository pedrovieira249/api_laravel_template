<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Auth\Services\RevokeServiceClient;
use App\Http\Controllers\Controller;
use App\Models\ServiceClient;
use Illuminate\Http\Response;

final class RevokeServiceClientController extends Controller
{
    public function __construct(private readonly RevokeServiceClient $revoke) {}

    public function __invoke(ServiceClient $serviceClient): Response
    {
        ($this->revoke)($serviceClient);

        return response()->noContent();
    }
}
