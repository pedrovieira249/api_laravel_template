<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Auth\Access\SaveRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateRoleRequest;
use App\Http\Resources\RoleResource;
use Illuminate\Http\JsonResponse;

final class CreateRoleController extends Controller
{
    public function __construct(private readonly SaveRole $roles) {}

    public function __invoke(CreateRoleRequest $request): JsonResponse
    {
        $role = $this->roles->create(
            name: $request->string('name')->toString(),
            description: $request->filled('description') ? $request->string('description')->toString() : null,
            permissions: $request->permissions(),
        );

        return new RoleResource($role->load('permissions'))
            ->response()
            ->setStatusCode(201)
            ->header('Location', route('api.v1.admin.roles.show', $role->id));
    }
}
