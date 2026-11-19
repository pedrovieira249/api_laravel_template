<?php

declare(strict_types=1);

use App\Models\User;

/*
 * Fora do ambiente local, os dashboards só abrem para administradores.
 */

it('opens the Pulse dashboard for admins only', function (): void {
    $this->actingAs(User::factory()->withRole('user')->create())->get('/pulse')->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())->get('/pulse')->assertOk();
});

it('opens the Horizon dashboard for admins only', function (): void {
    $this->actingAs(User::factory()->withRole('user')->create())->get('/horizon')->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())->get('/horizon')->assertOk();
});

it('opens the API docs for admins only', function (): void {
    $this->actingAs(User::factory()->withRole('user')->create())->get('/docs/api')->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())->get('/docs/api')->assertOk();
});
