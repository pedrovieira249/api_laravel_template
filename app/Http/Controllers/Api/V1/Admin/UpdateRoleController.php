<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Auth\Access\SaveRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;

final class UpdateRoleController extends Controller
{
    public function __construct(private readonly SaveRole $roles) {}

    public function __invoke(UpdateRoleRequest $request, Role $role): RoleResource
    {
        $role = $this->roles->update(
            role: $role,
            name: $request->string('name')->toString(),
            description: $request->filled('description') ? $request->string('description')->toString() : null,
            permissions: $request->permissions(),
        );

        return new RoleResource($role->load('permissions'));
    }
}
