<?php

declare(strict_types=1);

use App\Models\User;

it('revokes the access token used in the request and its refresh token', function (): void {
    $user = User::factory()->create();
    $phone = loginAs($user);
    $laptop = loginAs($user);

    $this->withToken($phone['access_token'])->postJson('/api/v1/auth/logout')->assertNoContent();

    // O token do celular morreu...
    freshRequest();
    $this->withToken($phone['access_token'])->getJson('/api/v1/auth/me')->assertUnauthorized();

    // ...e não dá para ressuscitar a sessão pelo refresh token dele.
    $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $phone['refresh_token']])->assertUnauthorized();

    // O outro dispositivo continua logado.
    freshRequest();
    $this->withToken($laptop['access_token'])->getJson('/api/v1/auth/me')->assertOk();
});

it('requires authentication', function (): void {
    $this->postJson('/api/v1/auth/logout')
        ->assertUnauthorized()
        ->assertJsonPath('title', 'Unauthenticated');
});
