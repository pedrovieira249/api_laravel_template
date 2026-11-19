<?php

declare(strict_types=1);

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;

it('mirrors the enum into the permissions table', function (): void {
    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Permission::query()->count())->toBe(count(PermissionName::cases()));
    $this->assertDatabaseHas('permissions', ['name' => 'users.read', 'description' => PermissionName::UsersRead->description()]);
});

it('removes permissions that left the enum, together with their grants', function (): void {
    $stale = Permission::factory()->create(['name' => 'legacy.export']);
    $role = Role::factory()->create();
    $role->permissions()->attach($stale->id);

    $this->artisan('permissions:sync')->assertSuccessful();

    $this->assertDatabaseMissing('permissions', ['name' => 'legacy.export']);
    expect($role->permissions()->count())->toBe(0);
});

it('creates the system roles with their default permissions only once', function (): void {
    $this->artisan('permissions:sync')->assertSuccessful();

    $defaults = collect(RoleName::User->defaultPermissions())->map->value->all();
    $user = Role::query()->where('name', 'user')->sole();
    expect($user->permissions->pluck('name')->all())->toEqualCanonicalizing($defaults);

    // Um administrador redefine as permissões do perfil: o próximo deploy não as desfaz.
    $user->permissions()->sync(Permission::query()->where('name', 'users.read')->pluck('id'));
    $this->artisan('permissions:sync')->assertSuccessful();

    expect($user->fresh()?->permissions->pluck('name')->all())->toBe(['users.read']);
});
