<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RoleName;
use Carbon\CarbonImmutable;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Perfil de usuário: um nome que agrupa permissões ("admin", "manager").
 * É o que os middlewares de rota consultam (`role:admin`).
 *
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property CarbonImmutable|null $created_at
 * @property-read Collection<int, Permission> $permissions
 * @property-read int|null $users_count
 */
#[Fillable(['name', 'description'])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * Perfis de sistema (App\Enums\RoleName) não podem ser apagados nem renomeados.
     */
    public function isSystem(): bool
    {
        return RoleName::tryFrom($this->name) !== null;
    }

    /**
     * O perfil admin tem todas as permissões implicitamente; sua lista não é editável.
     */
    public function isSuperAdmin(): bool
    {
        return $this->name === RoleName::Admin->value;
    }
}
