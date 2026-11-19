<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Auth\Access\AssignUserRoles;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SyncUserRolesRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;

/**
 * PUT /admin/users/{user}/roles: substitui os perfis do usuário pela lista enviada.
 */
final class SyncUserRolesController extends Controller
{
    public function __construct(private readonly AssignUserRoles $assign) {}

    public function __invoke(SyncUserRolesRequest $request, User $user, #[CurrentUser('api')] User $actor): UserResource
    {
        return new UserResource(($this->assign)($user, $request->roles(), $actor));
    }
}
