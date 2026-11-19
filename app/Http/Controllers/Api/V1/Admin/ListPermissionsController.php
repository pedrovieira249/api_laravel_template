<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermissionResource;
use App\Models\Permission;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * O catálogo de permissões é só leitura pela API: permissões nascem no código
 * (App\Enums\PermissionName) e chegam ao banco pelo `permissions:sync`.
 */
final class ListPermissionsController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        return PermissionResource::collection(Permission::query()->orderBy('name')->get());
    }
}
