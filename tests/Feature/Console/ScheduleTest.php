<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

it('schedules the cleanup of expired tokens of both kinds', function (): void {
    $commands = collect(Schedule::events())->map(fn ($event): string => (string) $event->command)->implode("\n");

    expect($commands)->toContain('sanctum:prune-expired')
        ->toContain('passport:purge')
        ->toContain('horizon:snapshot')
        ->toContain('telescope:prune');
});

it('seeds a database in which the demo users can log in', function (): void {
    $this->seed();

    $this->postJson('/api/v1/auth/login', ['email' => 'admin@example.test', 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('data.roles', ['admin']);

    $manager = $this->postJson('/api/v1/auth/login', ['email' => 'manager@example.test', 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('data.roles', ['manager'])
        ->json('data.permissions');

    expect($manager)->toContain('users.read')->not->toContain('roles.manage');

    $this->postJson('/api/v1/auth/login', ['email' => 'user@example.test', 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('data.roles', ['user']);

    // Rodar de novo não duplica nada.
    $this->seed();
    $this->assertDatabaseCount('users', 3);
    $this->assertDatabaseCount('roles', 3);
});

it('seeds no demo data in production', function (): void {
    $this->app->detectEnvironment(fn (): string => 'production');

    // Em produção o db:seed pede confirmação; --force é o "sim" explícito.
    $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('roles', 2);
    $this->assertDatabaseCount('oauth_clients', 1);
});
