<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Models\Role;

final class ShowRoleController extends Controller
{
    public function __invoke(Role $role): RoleResource
    {
        return new RoleResource($role->load('permissions')->loadCount('users'));
    }
}
