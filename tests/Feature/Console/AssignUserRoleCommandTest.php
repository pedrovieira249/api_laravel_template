<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;

it('gives a role to a user, which is how the first administrator is born', function (): void {
    $this->artisan('permissions:sync');
    $ana = User::factory()->withRole('user')->create(['email' => 'ana@example.com']);

    $this->artisan('user:assign-role', ['email' => 'ANA@example.com', 'role' => 'admin'])
        ->expectsOutputToContain('ana@example.com now has the "admin" role.')
        ->assertSuccessful();

    expect($ana->fresh()?->roleNames())->toBe(['admin', 'user']);
});

it('is idempotent', function (): void {
    $ana = User::factory()->admin()->create(['email' => 'ana@example.com']);

    $this->artisan('user:assign-role', ['email' => 'ana@example.com', 'role' => 'admin'])->assertSuccessful();

    $this->assertDatabaseCount('role_user', 1);
    expect($ana->fresh()?->isAdmin())->toBeTrue();
});

it('fails for an unknown user or role', function (): void {
    Role::factory()->create(['name' => 'manager']);
    User::factory()->create(['email' => 'ana@example.com']);

    $this->artisan('user:assign-role', ['email' => 'ghost@example.com', 'role' => 'manager'])
        ->expectsOutputToContain('No user with the e-mail "ghost@example.com".')
        ->assertFailed();

    $this->artisan('user:assign-role', ['email' => 'ana@example.com', 'role' => 'ghost'])
        ->expectsOutputToContain('No role named "ghost".')
        ->assertFailed();
});
