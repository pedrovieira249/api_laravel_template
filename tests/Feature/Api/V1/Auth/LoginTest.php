<?php

declare(strict_types=1);

use App\Models\User;

it('issues an access token and a refresh token for valid credentials', function (): void {
    $user = User::factory()->withRole('user')->create(['email' => 'ana@example.com']);

    $this->postJson('/api/v1/auth/login', ['email' => 'ANA@example.com', 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.roles', ['user'])
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('expires_in', 300)
        ->assertJsonStructure(['access_token', 'refresh_token']);
});

it('rejects a wrong password with the same message as an unknown e-mail', function (): void {
    User::factory()->create(['email' => 'ana@example.com']);

    $wrongPassword = $this->postJson('/api/v1/auth/login', ['email' => 'ana@example.com', 'password' => 'nope'])
        ->assertUnprocessable()
        ->json('errors.email.0');

    $unknownEmail = $this->postJson('/api/v1/auth/login', ['email' => 'ghost@example.com', 'password' => 'nope'])
        ->assertUnprocessable()
        ->json('errors.email.0');

    expect($wrongPassword)->toBe($unknownEmail)->toBe('These credentials do not match our records.');
});

it('answers in portuguese when asked to', function (): void {
    $this->postJson('/api/v1/auth/login', ['email' => 'ghost@example.com', 'password' => 'nope'], ['Accept-Language' => 'pt-BR'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'Estas credenciais não foram encontradas em nossos registros.');
});

it('throttles brute force attempts after 5 tries per minute', function (): void {
    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/login', ['email' => 'ana@example.com', 'password' => 'wrong'])
            ->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/login', ['email' => 'ana@example.com', 'password' => 'wrong'])
        ->assertStatus(429)
        ->assertJsonPath('title', 'Too many requests')
        ->assertHeader('Retry-After');
});

it('does not expose the raw OAuth endpoints', function (): void {
    // Tokens só saem por /auth/login e /auth/refresh: as rotas do Passport estão desligadas.
    $this->postJson('/oauth/token', ['grant_type' => 'password'])->assertNotFound();
    $this->getJson('/oauth/authorize')->assertNotFound();
});

it('fails loudly when the first-party client is missing', function (): void {
    $user = User::factory()->create();
    DB::table('oauth_clients')->delete();

    $this->withoutExceptionHandling();

    expect(fn () => $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password']))
        ->toThrow(LogicException::class, 'auth:install');
});
