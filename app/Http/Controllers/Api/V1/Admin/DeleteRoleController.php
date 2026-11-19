<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Auth\Access\DeleteRole;
use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Response;

final class DeleteRoleController extends Controller
{
    public function __construct(private readonly DeleteRole $delete) {}

    public function __invoke(Role $role): Response
    {
        ($this->delete)($role);

        return response()->noContent();
    }
}
