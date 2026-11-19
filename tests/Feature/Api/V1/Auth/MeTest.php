<?php

declare(strict_types=1);

use App\Enums\PermissionName;
use App\Models\User;

it('returns the authenticated user with roles and effective permissions', function (): void {
    $user = User::factory()->withPermissions(PermissionName::UsersRead)->create(['name' => 'Ana']);
    actingAsUser($user);

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.name', 'Ana')
        ->assertJsonPath('data.permissions', ['users.read'])
        ->assertJsonCount(1, 'data.roles')
        ->assertJsonMissingPath('data.password');
});

it('lists every permission for an administrator', function (): void {
    actingAsUser(User::factory()->admin()->create());

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.roles', ['admin'])
        ->assertJsonPath('data.permissions', PermissionName::values());
});

it('rejects anonymous requests with 401 problem details', function (): void {
    $this->getJson('/api/v1/auth/me')
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJson(['status' => 401, 'title' => 'Unauthenticated', 'detail' => 'Authentication is required.']);
});

it('refuses clients that do not accept json', function (): void {
    $this->get('/api/v1/auth/me', ['Accept' => 'text/html'])
        ->assertStatus(406)
        ->assertJsonPath('status', 406);
});

it('accepts clients with a wildcard accept header', function (): void {
    $this->get('/api/v1/auth/me', ['Accept' => '*/*'])->assertUnauthorized();
});
