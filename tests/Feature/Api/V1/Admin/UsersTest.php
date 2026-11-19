<?php

declare(strict_types=1);

use App\Enums\PermissionName;
use App\Models\User;

it('lists users, paginated, for whoever has users.read', function (): void {
    User::factory()->count(20)->create();
    actingAsUser(User::factory()->withPermissions(PermissionName::UsersRead)->create());

    $this->getJson('/api/v1/admin/users?per_page=10')
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('meta.total', 21)
        ->assertJsonPath('meta.last_page', 3)
        ->assertJsonStructure(['data' => [['id', 'name', 'email', 'roles', 'permissions', 'created_at']]]);
});

it('caps the page size', function (): void {
    actingAsUser(User::factory()->admin()->create());

    $this->getJson('/api/v1/admin/users?per_page=100000')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100);
});

it('shows one user and answers 404 for unknown or malformed ids', function (): void {
    $ana = User::factory()->withRole('user')->create(['name' => 'Ana']);
    actingAsUser(User::factory()->admin()->create());

    $this->getJson("/api/v1/admin/users/{$ana->id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'Ana')
        ->assertJsonPath('data.roles', ['user']);

    $this->getJson('/api/v1/admin/users/019a5c6e-7b3a-7c1d-8f2e-3a4b5c6d7eff')
        ->assertNotFound()
        ->assertJsonPath('detail', 'The requested resource was not found.');

    $this->getJson('/api/v1/admin/users/not-a-uuid')->assertNotFound();
});

it('forbids users without the permission and rejects anonymous requests', function (): void {
    $this->getJson('/api/v1/admin/users')->assertUnauthorized();

    actingAsUser(User::factory()->withRole('user')->create());

    $this->getJson('/api/v1/admin/users')
        ->assertForbidden()
        ->assertJson(['status' => 403, 'title' => 'Forbidden']);
});
