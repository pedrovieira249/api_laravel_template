<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\PermissionName;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Role
 */
final class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            // Perfis de sistema não podem ser apagados nem renomeados.
            'is_system' => $this->isSystem(),
            // O admin não tem linhas na tabela pivô: ele recebe o catálogo inteiro.
            'permissions' => $this->isSuperAdmin()
                ? PermissionName::values()
                : $this->permissions->pluck('name')->sort()->values()->all(),
            'users_count' => $this->whenCounted('users'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
