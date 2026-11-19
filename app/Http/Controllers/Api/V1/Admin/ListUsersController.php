<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListUsersController extends Controller
{
    private const int MAX_PER_PAGE = 100;

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        // with(): perfis e permissões de todos os usuários da página em duas
        // consultas, em vez de duas por usuário (N+1) quando o Resource os lê.
        $users = User::query()
            ->with('roles.permissions')
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(perPage: min(max($request->integer('per_page', 15), 1), self::MAX_PER_PAGE));

        return UserResource::collection($users);
    }
}
