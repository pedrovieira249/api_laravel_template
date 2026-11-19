<?php

declare(strict_types=1);

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\EnsureUserHasRole;
use App\Models\ServiceClient;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
 * Os middlewares `role:` e `permission:` exercitados em rotas descartáveis, do
 * jeito que um projeto novo vai usá-los: um grupo de rotas, uma regra de acesso.
 */
beforeEach(function (): void {
    Route::middleware(['api', 'auth:api'])->prefix('api/acl')->group(function (): void {
        Route::get('admin-only', fn (): array => ['ok' => true])->middleware('role:admin');
        Route::get('staff', fn (): array => ['ok' => true])->middleware('role:admin,manager');
        Route::get('enum-role', fn (): array => ['ok' => true])->middleware(EnsureUserHasRole::using(RoleName::Admin, 'manager'));
        Route::get('write', fn (): array => ['ok' => true])->middleware('permission:roles.manage');
        Route::get('read-or-write', fn (): array => ['ok' => true])->middleware('permission:roles.read,roles.manage');
        Route::get('enum-permission', fn (): array => ['ok' => true])->middleware(EnsureUserHasPermission::using(PermissionName::RolesManage));
        Route::get('gate', fn (): array => ['ok' => true])->middleware('can:roles.manage');
    });

    // Um grupo sem auth: os middlewares precisam responder 401 por conta própria.
    Route::middleware('api')->get('api/acl/unguarded-role', fn (): array => ['ok' => true])->middleware('role:admin');
    Route::middleware('api')->get('api/acl/unguarded-permission', fn (): array => ['ok' => true])->middleware('permission:roles.read');

    // Um grupo interno com um middleware de usuário por engano.
    Route::middleware(['api', 'auth:internal'])->get('api/acl/service-with-role', fn (): array => ['ok' => true])->middleware('role:admin');
});

describe('role middleware', function (): void {
    it('lets a user with the role in', function (): void {
        actingAsUser(User::factory()->admin()->create());

        $this->getJson('/api/acl/admin-only')->assertOk();
    });

    it('forbids a user without the role', function (): void {
        actingAsUser(User::factory()->withRole('user')->create());

        $this->getJson('/api/acl/admin-only')
            ->assertForbidden()
            ->assertJsonPath('detail', 'Your profile does not give access to this resource.');
    });

    it('accepts any of several roles', function (string $role, int $status): void {
        actingAsUser(User::factory()->withRole($role)->create());

        $this->getJson('/api/acl/staff')->assertStatus($status);
        $this->getJson('/api/acl/enum-role')->assertStatus($status);
    })->with([
        'admin' => ['admin', 200],
        'manager' => ['manager', 200],
        'user' => ['user', 403],
    ]);

    it('forbids a user with no role at all', function (): void {
        actingAsUser(User::factory()->create());

        $this->getJson('/api/acl/staff')->assertForbidden();
    });

    it('answers 401 when nobody is authenticated', function (): void {
        $this->getJson('/api/acl/admin-only')->assertUnauthorized();
        $this->getJson('/api/acl/unguarded-role')->assertUnauthorized();
    });

    it('forbids a service client: roles belong to users', function (): void {
        actingAsService(ServiceClient::factory()->create());

        $this->getJson('/api/acl/service-with-role')->assertForbidden();
    });

    it('builds the middleware string from the enum', function (): void {
        expect(EnsureUserHasRole::using(RoleName::Admin))->toBe('role:admin')
            ->and(EnsureUserHasRole::using(RoleName::Admin, 'manager'))->toBe('role:admin,manager');
    });
});

describe('permission middleware', function (): void {
    it('lets a user in when one of their roles grants the permission', function (): void {
        actingAsUser(User::factory()->withPermissions(PermissionName::RolesManage)->create());

        $this->getJson('/api/acl/write')->assertOk();
        $this->getJson('/api/acl/enum-permission')->assertOk();
    });

    it('forbids a user whose roles do not grant it', function (): void {
        actingAsUser(User::factory()->withPermissions(PermissionName::RolesRead)->create());

        $this->getJson('/api/acl/write')
            ->assertForbidden()
            ->assertJsonPath('detail', 'You do not have permission to perform this action.');
    });

    it('accepts any of several permissions', function (): void {
        actingAsUser(User::factory()->withPermissions(PermissionName::RolesRead)->create());

        $this->getJson('/api/acl/read-or-write')->assertOk();
    });

    it('adds up the permissions of every role of the user', function (): void {
        $user = User::factory()
            ->withPermissions(PermissionName::RolesRead)
            ->withPermissions(PermissionName::RolesManage)
            ->create();
        actingAsUser($user);

        expect($user->permissionNames())->toBe(['roles.manage', 'roles.read']);
        $this->getJson('/api/acl/write')->assertOk();
    });

    it('gives administrators every permission without any pivot row', function (): void {
        actingAsUser(User::factory()->admin()->create());

        $this->getJson('/api/acl/write')->assertOk();
        $this->assertDatabaseCount('permission_role', 0);
    });

    it('answers 401 when nobody is authenticated', function (): void {
        $this->getJson('/api/acl/write')->assertUnauthorized();
        $this->getJson('/api/acl/unguarded-permission')->assertUnauthorized();
    });

    it('builds the middleware string from the enum', function (): void {
        expect(EnsureUserHasPermission::using(PermissionName::UsersRead, PermissionName::UsersManage))
            ->toBe('permission:users.read,users.manage');
    });
});

describe('gate integration', function (): void {
    it('exposes every permission as a gate ability', function (): void {
        $editor = User::factory()->withPermissions(PermissionName::RolesManage)->create();
        $reader = User::factory()->withPermissions(PermissionName::RolesRead)->create();

        expect($editor->can('roles.manage'))->toBeTrue()
            ->and($reader->can('roles.manage'))->toBeFalse()
            ->and($reader->can('roles.read'))->toBeTrue();
    });

    it('works with the native can: middleware', function (): void {
        actingAsUser(User::factory()->withPermissions(PermissionName::RolesRead)->create());
        $this->getJson('/api/acl/gate')->assertForbidden();

        actingAsUser(User::factory()->withPermissions(PermissionName::RolesManage)->create());
        $this->getJson('/api/acl/gate')->assertOk();
    });

    it('leaves abilities that are not permissions to the regular gates', function (): void {
        $user = User::factory()->withRole('user')->create();

        expect($user->can('viewPulse'))->toBeFalse()
            ->and(User::factory()->admin()->create()->can('viewPulse'))->toBeTrue()
            ->and($user->can('an.unknown.ability'))->toBeFalse();
    });
});
