<?php

declare(strict_types=1);

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('answers role questions with enums or plain names', function (): void {
    $user = User::factory()->withRole('manager')->create();

    expect($user->hasRole('manager'))->toBeTrue()
        ->and($user->hasRole(RoleName::Admin, 'manager'))->toBeTrue()
        ->and($user->hasRole(RoleName::Admin))->toBeFalse()
        ->and($user->isAdmin())->toBeFalse()
        ->and($user->roleNames())->toBe(['manager']);
});

it('answers permission questions with enums or plain names', function (): void {
    $user = User::factory()->withPermissions(PermissionName::UsersRead)->create();

    expect($user->hasPermission(PermissionName::UsersRead))->toBeTrue()
        ->and($user->hasPermission('users.read'))->toBeTrue()
        ->and($user->hasPermission(PermissionName::UsersManage, PermissionName::UsersRead))->toBeTrue()
        ->and($user->hasPermission(PermissionName::UsersManage))->toBeFalse()
        ->and($user->hasPermission('not.in.the.catalog'))->toBeFalse();
});

it('has no access at all without a role', function (): void {
    $user = User::factory()->create();

    expect($user->roleNames())->toBe([])
        ->and($user->permissionNames())->toBe([])
        ->and($user->hasRole('admin'))->toBeFalse();
});

it('loads roles and permissions once per instance', function (): void {
    $user = User::factory()->withPermissions(PermissionName::UsersRead)->create()->fresh();

    DB::enableQueryLog();
    $user->hasPermission(PermissionName::UsersRead);
    $afterFirstCheck = count(DB::getQueryLog());

    $user->hasRole('admin');
    $user->permissionNames();
    $user->hasPermission(PermissionName::RolesManage);

    expect($afterFirstCheck)->toBe(2)
        ->and(count(DB::getQueryLog()))->toBe(2);
});

it('sees a change of roles after forgetting the cached ones', function (): void {
    $user = User::factory()->create();
    expect($user->isAdmin())->toBeFalse();

    $user->roles()->attach(Role::query()->firstOrCreate(['name' => 'admin'])->id);

    expect($user->isAdmin())->toBeFalse() // ainda o estado em memória
        ->and($user->forgetCachedRoles()->isAdmin())->toBeTrue();
});
