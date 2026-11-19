<?php

declare(strict_types=1);

use App\Enums\PermissionName;
use App\Models\Role;
use App\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
    Role::factory()->create(['name' => 'manager']);
    Role::factory()->create(['name' => 'support']);
});

it('replaces the roles of a user', function (): void {
    $ana = User::factory()->withRole('support')->create();
    actingAsUser($this->admin);

    $this->putJson("/api/v1/admin/users/{$ana->id}/roles", ['roles' => ['manager', 'admin']])
        ->assertOk()
        ->assertJsonPath('data.roles', ['admin', 'manager']);

    expect($ana->fresh()?->roleNames())->toBe(['admin', 'manager']);
});

it('takes effect on the next request of that user, without a new login', function (): void {
    $ana = User::factory()->withRole('user')->create();
    $tokens = loginAs($ana);

    $this->withToken($tokens['access_token'])->getJson('/api/v1/admin/users')->assertForbidden();

    $ana->roles()->sync(Role::query()->where('name', 'admin')->pluck('id'));

    // Mesmo token: as permissões vêm do banco a cada requisição, não do JWT.
    freshRequest();
    $this->withToken($tokens['access_token'])->getJson('/api/v1/admin/users')->assertOk();
});

it('accepts an empty list, leaving the user with no role', function (): void {
    $ana = User::factory()->withRole('support')->create();
    actingAsUser($this->admin);

    $this->putJson("/api/v1/admin/users/{$ana->id}/roles", ['roles' => []])
        ->assertOk()
        ->assertJsonPath('data.roles', [])
        ->assertJsonPath('data.permissions', []);
});

it('validates that every role exists', function (): void {
    $ana = User::factory()->create();
    actingAsUser($this->admin);

    $this->putJson("/api/v1/admin/users/{$ana->id}/roles", ['roles' => ['manager', 'ghost']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['roles.1']);

    $this->putJson("/api/v1/admin/users/{$ana->id}/roles", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['roles']);
});

it('refuses to demote the last administrator', function (): void {
    actingAsUser($this->admin);

    $this->putJson("/api/v1/admin/users/{$this->admin->id}/roles", ['roles' => ['manager']])
        ->assertStatus(409)
        ->assertJsonPath('title', 'Last administrator cannot be demoted')
        ->assertJsonPath('type', 'https://api.example.com/problems/last-administrator-cannot-be-demoted');

    expect($this->admin->fresh()?->isAdmin())->toBeTrue();
});

it('lets an administrator be demoted while another one remains', function (): void {
    $other = User::factory()->admin()->create();
    actingAsUser($this->admin);

    $this->putJson("/api/v1/admin/users/{$other->id}/roles", ['roles' => ['manager']])
        ->assertOk()
        ->assertJsonPath('data.roles', ['manager']);
});

it('requires users.manage: reading users is not enough', function (): void {
    $ana = User::factory()->create();
    actingAsUser(User::factory()->withPermissions(PermissionName::UsersRead)->create());

    $this->putJson("/api/v1/admin/users/{$ana->id}/roles", ['roles' => ['admin']])->assertForbidden();

    expect($ana->fresh()?->roleNames())->toBe([]);
});

it('lets only administrators grant or remove the admin role', function (): void {
    $ana = User::factory()->withRole('support')->create();
    $otherAdmin = User::factory()->admin()->create();
    $manager = User::factory()->withPermissions(PermissionName::UsersManage)->create();
    actingAsUser($manager);

    // Quem só tem users.manage troca perfis comuns...
    $this->putJson("/api/v1/admin/users/{$ana->id}/roles", ['roles' => ['manager']])->assertOk();

    // ...mas não promove ninguém (nem a si mesmo) a admin, nem rebaixa um admin.
    $this->putJson("/api/v1/admin/users/{$manager->id}/roles", ['roles' => ['admin']])
        ->assertForbidden()
        ->assertJsonPath('detail', 'Only an administrator can grant or remove the admin role.');
    $this->putJson("/api/v1/admin/users/{$otherAdmin->id}/roles", ['roles' => ['manager']])->assertForbidden();

    expect($manager->fresh()?->isAdmin())->toBeFalse()
        ->and($otherAdmin->fresh()?->isAdmin())->toBeTrue();
});

it('lets a non-admin manager change the other roles of an administrator', function (): void {
    $otherAdmin = User::factory()->admin()->create();
    actingAsUser(User::factory()->withPermissions(PermissionName::UsersManage)->create());

    // O perfil admin continua na lista: nada muda quanto a ser administrador.
    $this->putJson("/api/v1/admin/users/{$otherAdmin->id}/roles", ['roles' => ['admin', 'support']])
        ->assertOk()
        ->assertJsonPath('data.roles', ['admin', 'support']);
});
