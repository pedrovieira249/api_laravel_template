<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;

final class ShowUserController extends Controller
{
    /**
     * Route model binding: {user} é um UUID resolvido para o model; id inexistente vira 404.
     */
    public function __invoke(User $user): UserResource
    {
        return new UserResource($user);
    }
}
