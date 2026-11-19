<?php

declare(strict_types=1);

use App\Models\User;

beforeEach(function (): void {
    $this->tokens = loginAs(User::factory()->create(['email' => 'ana@example.com']));
});

it('exchanges a refresh token for a new pair without asking for the password', function (): void {
    $new = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $this->tokens['refresh_token']])
        ->assertOk()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('expires_in', 300)
        ->assertJsonStructure(['access_token', 'refresh_token'])
        ->json();

    expect($new['access_token'])->not->toBe($this->tokens['access_token'])
        ->and($new['refresh_token'])->not->toBe($this->tokens['refresh_token']);

    $this->withToken($new['access_token'])->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'ana@example.com');
});

it('rotates: a refresh token works only once', function (): void {
    $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $this->tokens['refresh_token']])->assertOk();

    $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $this->tokens['refresh_token']])
        ->assertUnauthorized()
        ->assertJsonPath('detail', 'The refresh token is invalid, expired or already used.');
});

it('revokes the previous access token when refreshing', function (): void {
    $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $this->tokens['refresh_token']])->assertOk();

    freshRequest();
    $this->withToken($this->tokens['access_token'])->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('rejects a refresh token it did not issue', function (): void {
    $this->postJson('/api/v1/auth/refresh', ['refresh_token' => 'not-a-real-token'])
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/problem+json');
});

it('does not accept an access token in place of the refresh token', function (): void {
    $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $this->tokens['access_token']])->assertUnauthorized();
});

it('requires the refresh_token field', function (): void {
    $this->postJson('/api/v1/auth/refresh', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['refresh_token']);
});
