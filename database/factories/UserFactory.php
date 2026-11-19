<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * A senha em uso pela factory (hash calculado uma única vez por processo).
     */
    protected static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Usuário com o perfil de sistema "admin" (todas as permissões).
     */
    public function admin(): static
    {
        return $this->withRole(RoleName::Admin);
    }

    /**
     * Dá ao usuário um perfil pelo nome, criando o perfil se ele ainda não existir.
     */
    public function withRole(RoleName|string $role): static
    {
        $name = $role instanceof RoleName ? $role->value : $role;

        return $this->afterCreating(function (User $user) use ($name): void {
            $user->roles()->syncWithoutDetaching([Role::query()->firstOrCreate(['name' => $name])->id]);
            $user->forgetCachedRoles();
        });
    }

    /**
     * Dá ao usuário exatamente estas permissões, por meio de um perfil
     * descartável. É o jeito de testar uma rota protegida por `permission:`
     * sem depender de como os perfis reais estão montados.
     */
    public function withPermissions(PermissionName ...$permissions): static
    {
        return $this->afterCreating(function (User $user) use ($permissions): void {
            $ids = array_map(
                fn (PermissionName $permission): string => Permission::query()->firstOrCreate(
                    ['name' => $permission->value],
                    ['description' => $permission->description()],
                )->id,
                $permissions,
            );

            $role = Role::factory()->create();
            $role->permissions()->sync($ids);

            $user->roles()->attach($role->id);
            $user->forgetCachedRoles();
        });
    }
}
