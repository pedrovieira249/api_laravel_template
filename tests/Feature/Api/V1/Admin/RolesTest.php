<?php

declare(strict_types=1);

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

beforeEach(function (): void {
    $this->artisan('permissions:sync');
    $this->admin = User::factory()->admin()->create();

    // As permissões com que o perfil "user" nasce (RoleName::defaultPermissions()).
    $this->userDefaults = collect(RoleName::User->defaultPermissions())->map->value->sort()->values()->all();
});

it('lists roles with their permissions and how many users have each', function (): void {
    User::factory()->count(2)->withRole('user')->create();
    actingAsUser($this->admin);

    $roles = collect($this->getJson('/api/v1/admin/roles')->assertOk()->json('data'))->keyBy('name');

    expect($roles->keys()->all())->toBe(['admin', 'user'])
        ->and($roles['admin']['is_system'])->toBeTrue()
        ->and($roles['admin']['permissions'])->toBe(PermissionName::values())
        ->and($roles['admin']['users_count'])->toBe(1)
        ->and($roles['user']['permissions'])->toBe($this->userDefaults)
        ->and($roles['user']['users_count'])->toBe(2);
});

it('creates a role made of permissions from the catalog', function (): void {
    actingAsUser($this->admin);

    $response = $this->postJson('/api/v1/admin/roles', [
        'name' => 'manager',
        'description' => 'Sees users and roles',
        'permissions' => ['users.read', 'roles.read'],
    ])->assertCreated()
        ->assertJsonPath('data.name', 'manager')
        ->assertJsonPath('data.is_system', false)
        ->assertJsonPath('data.permissions', ['roles.read', 'users.read']);

    $response->assertHeader('Location', url('/api/v1/admin/roles/'.$response->json('data.id')));
});

it('makes a new role usable right away by the route middlewares', function (): void {
    actingAsUser($this->admin);
    $this->postJson('/api/v1/admin/roles', ['name' => 'support', 'permissions' => ['users.read']])->assertCreated();

    actingAsUser(User::factory()->withRole('support')->create());

    $this->getJson('/api/v1/admin/users')->assertOk();
    $this->getJson('/api/v1/admin/roles')->assertForbidden();
});

it('validates name format, uniqueness and permission names', function (): void {
    actingAsUser($this->admin);

    $this->postJson('/api/v1/admin/roles', ['name' => 'Not A Slug', 'permissions' => ['users.fly']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'permissions.0']);

    $this->postJson('/api/v1/admin/roles', ['name' => 'user'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('shows one role', function (): void {
    $role = Role::query()->where('name', 'user')->sole();
    actingAsUser($this->admin);

    $this->getJson("/api/v1/admin/roles/{$role->id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'user')
        ->assertJsonPath('data.users_count', 0);
});

it('updates the description and the permissions of a role', function (): void {
    $role = Role::factory()->create(['name' => 'manager']);
    actingAsUser($this->admin);

    $this->putJson("/api/v1/admin/roles/{$role->id}", [
        'name' => 'manager',
        'description' => 'Updated',
        'permissions' => ['users.read'],
    ])->assertOk()
        ->assertJsonPath('data.description', 'Updated')
        ->assertJsonPath('data.permissions', ['users.read']);
});

it('keeps the permissions when the field is omitted', function (): void {
    $role = Role::query()->where('name', 'user')->sole();
    actingAsUser($this->admin);

    $role->permissions()->sync(Permission::query()->where('name', 'users.read')->pluck('id'));

    $this->putJson("/api/v1/admin/roles/{$role->id}", ['name' => 'user', 'description' => 'Still the default'])
        ->assertOk()
        ->assertJsonPath('data.description', 'Still the default')
        ->assertJsonPath('data.permissions', ['users.read']);
});

it('protects system roles from being renamed, emptied or deleted', function (): void {
    $admin = Role::query()->where('name', 'admin')->sole();
    $user = Role::query()->where('name', 'user')->sole();
    actingAsUser($this->admin);

    $this->putJson("/api/v1/admin/roles/{$user->id}", ['name' => 'member'])
        ->assertStatus(409)
        ->assertJsonPath('title', 'System role cannot be changed');

    $this->putJson("/api/v1/admin/roles/{$admin->id}", ['name' => 'admin', 'permissions' => []])
        ->assertStatus(409)
        ->assertJsonPath('detail', 'The "admin" role always has every permission; its list cannot be edited.');

    $this->deleteJson("/api/v1/admin/roles/{$admin->id}")->assertStatus(409);
    $this->deleteJson("/api/v1/admin/roles/{$user->id}")->assertStatus(409);

    expect(Role::query()->count())->toBe(2);
});

it('deletes a custom role only when nobody has it', function (): void {
    $role = Role::factory()->create(['name' => 'support']);
    $ana = User::factory()->withRole('support')->create();
    actingAsUser($this->admin);

    $this->deleteJson("/api/v1/admin/roles/{$role->id}")
        ->assertStatus(409)
        ->assertJsonPath('title', 'Role is assigned to users');

    $ana->roles()->detach();

    $this->deleteJson("/api/v1/admin/roles/{$role->id}")->assertNoContent();
    $this->assertDatabaseMissing('roles', ['name' => 'support']);
});

it('lists the permission catalog, read-only', function (): void {
    actingAsUser($this->admin);

    $names = $this->getJson('/api/v1/admin/permissions')
        ->assertOk()
        ->assertJsonStructure(['data' => [['name', 'description']]])
        ->json('data.*.name');

    expect($names)->toEqualCanonicalizing(PermissionName::values());
});

it('separates reading roles from managing them', function (): void {
    $role = Role::factory()->create(['name' => 'support']);
    actingAsUser(User::factory()->withPermissions(PermissionName::RolesRead)->create());

    $this->getJson('/api/v1/admin/roles')->assertOk();
    $this->getJson('/api/v1/admin/permissions')->assertOk();
    $this->postJson('/api/v1/admin/roles', ['name' => 'x'])->assertForbidden();
    $this->putJson("/api/v1/admin/roles/{$role->id}", ['name' => 'support'])->assertForbidden();
    $this->deleteJson("/api/v1/admin/roles/{$role->id}")->assertForbidden();
});
