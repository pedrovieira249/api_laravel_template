<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceClientResource;
use App\Models\ServiceClient;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListServiceClientsController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        return ServiceClientResource::collection(ServiceClient::query()->orderBy('name')->get());
    }
}
