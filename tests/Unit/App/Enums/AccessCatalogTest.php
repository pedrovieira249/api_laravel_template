<?php

declare(strict_types=1);

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Enums\ServiceAbility;

it('names permissions as resource.action in snake_case', function (PermissionName $permission): void {
    expect($permission->value)->toMatch('/^[a-z_]+\.[a-z_]+$/')
        ->and($permission->description())->not->toBe('');
})->with(PermissionName::cases());

it('lists the permission values', function (): void {
    expect(PermissionName::values())->toContain('users.read', 'roles.manage')
        ->and(PermissionName::values())->toHaveCount(count(PermissionName::cases()));
});

it('knows the two system roles', function (): void {
    expect(RoleName::values())->toBe(['admin', 'user'])
        ->and(RoleName::Admin->description())->not->toBe('')
        ->and(RoleName::User->description())->not->toBe('');
});

it('gives the admin no explicit defaults: it already has everything', function (): void {
    expect(RoleName::Admin->defaultPermissions())->toBe([])
        ->and(RoleName::User->defaultPermissions())->each->toBeInstanceOf(PermissionName::class);
});

it('never starts the default role with administrative permissions', function (): void {
    $dangerous = [PermissionName::UsersManage, PermissionName::RolesManage];

    expect(array_intersect(
        array_column(RoleName::User->defaultPermissions(), 'value'),
        array_column($dangerous, 'value'),
    ))->toBe([]);
});

it('lists the service abilities, including the wildcard', function (): void {
    expect(ServiceAbility::values())->toContain('*')
        ->and(ServiceAbility::from('*'))->toBe(ServiceAbility::All)
        ->and(ServiceAbility::values())->toHaveCount(count(ServiceAbility::cases()));
});
