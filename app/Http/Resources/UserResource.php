<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Além dos dados da conta, devolve os perfis e as permissões efetivas: é o que
 * um front-end usa para decidir quais telas e botões mostrar.
 *
 * @mixin User
 */
final class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'roles' => $this->roleNames(),
            'permissions' => $this->permissionNames(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
