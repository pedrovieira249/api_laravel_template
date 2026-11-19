<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListRolesController extends Controller
{
    /**
     * Sem paginação: perfis são poucos (dezenas, não milhares).
     */
    public function __invoke(): AnonymousResourceCollection
    {
        return RoleResource::collection(
            Role::query()->with('permissions')->withCount('users')->orderBy('name')->get(),
        );
    }
}
