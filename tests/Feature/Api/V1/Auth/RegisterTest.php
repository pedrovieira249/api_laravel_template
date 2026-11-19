<?php

declare(strict_types=1);

use App\Enums\ServiceAbility;
use App\Models\ServiceClient;
use App\Models\User;

/*
 * O registro de usuários não é público (ADR 0011): quem cria contas é um sistema
 * da casa, com token de serviço e a ability users.register, a no máximo 5 por segundo.
 */
beforeEach(function (): void {
    $this->asRegistrar = fn (): ServiceClient => actingAsService(
        ServiceClient::factory()->create(),
        [ServiceAbility::UsersRegister->value],
    );

    $this->register = fn (array $overrides = [], array $headers = []) => $this->postJson('/api/v1/internal/auth/register', [
        'name' => 'Ana',
        'email' => 'ana@example.com',
        'password' => 'secret-123',
        'password_confirmation' => 'secret-123',
        ...$overrides,
    ], $headers);
});

describe('creating the account', function (): void {
    it('registers a user with the default role and returns a token pair', function (): void {
        ($this->asRegistrar)();

        ($this->register)(['email' => 'Ana@Example.com'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Ana')
            ->assertJsonPath('data.email', 'ana@example.com')
            ->assertJsonPath('data.roles', ['user'])
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('expires_in', 300)
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'roles', 'permissions', 'created_at'], 'access_token', 'refresh_token']);

        $this->assertDatabaseHas('users', ['email' => 'ana@example.com']);
        $this->assertDatabaseCount('role_user', 1);
    });

    it('returns tokens of the new user that already work on user routes', function (): void {
        ($this->asRegistrar)();

        $token = ($this->register)()->assertCreated()->json('access_token');

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'ana@example.com');
    });

    it('never lets the caller pick the role', function (): void {
        ($this->asRegistrar)();

        ($this->register)(['email' => 'mallory@example.com', 'role' => 'admin', 'roles' => ['admin']])->assertCreated();

        $mallory = User::query()->where('email', 'mallory@example.com')->sole();

        expect($mallory->roleNames())->toBe(['user'])
            ->and($mallory->isAdmin())->toBeFalse();
    });

    it('validates the payload and answers with problem details', function (): void {
        ($this->asRegistrar)();

        $this->postJson('/api/v1/internal/auth/register', ['email' => 'nope'])
            ->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('title', 'Validation failed')
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    });

    it('rejects an e-mail already in use, whatever the letter case', function (): void {
        User::factory()->create(['email' => 'ana@example.com']);
        ($this->asRegistrar)();

        ($this->register)(['email' => 'ANA@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('translates validation messages from the Accept-Language header', function (): void {
        ($this->asRegistrar)();

        $this->postJson('/api/v1/internal/auth/register', [], ['Accept-Language' => 'pt-BR'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'O campo e-mail é obrigatório.');
    });
});

describe('who may call it', function (): void {
    it('is closed to the public', function (): void {
        ($this->register)()
            ->assertUnauthorized()
            ->assertJsonPath('title', 'Unauthenticated');

        $this->assertDatabaseCount('users', 0);
    });

    it('no longer exists as a public route', function (): void {
        $this->postJson('/api/v1/auth/register', ['name' => 'Ana'])->assertNotFound();
    });

    it('requires the users.register ability', function (): void {
        actingAsService(ServiceClient::factory()->create(), ['something.else']);

        ($this->register)()->assertForbidden();
        $this->assertDatabaseCount('users', 0);
    });

    it('accepts a service token with the wildcard ability', function (): void {
        actingAsService(ServiceClient::factory()->create(), [ServiceAbility::All->value]);

        ($this->register)()->assertCreated();
    });

    it('does not accept a user token, not even an administrator one', function (): void {
        $tokens = loginAs(User::factory()->admin()->create());

        $this->withToken($tokens['access_token'])->postJson('/api/v1/internal/auth/register', [
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'secret-123',
            'password_confirmation' => 'secret-123',
        ])->assertUnauthorized();

        $this->assertDatabaseMissing('users', ['email' => 'ana@example.com']);
    });
});

/*
 * O limite usa o Redis e o relógio dele (throttle.exact): aqui não há freezeTime().
 * Cada teste tem o próprio cliente de serviço, logo a própria cota.
 */
describe('rate limit', function (): void {
    it('allows at most five registrations per second for each service client', function (): void {
        ($this->asRegistrar)();

        foreach (range(1, 5) as $n) {
            ($this->register)(['email' => "user{$n}@example.com"])->assertCreated();
        }

        ($this->register)(['email' => 'user6@example.com'])
            ->assertStatus(429)
            ->assertJsonPath('title', 'Too many requests')
            ->assertHeader('Retry-After');

        $this->assertDatabaseCount('users', 5);
    });

    it('accepts again once the earlier registrations are more than a second old', function (): void {
        ($this->asRegistrar)();

        foreach (range(1, 5) as $n) {
            ($this->register)(['email' => "user{$n}@example.com"])->assertCreated();
        }
        ($this->register)(['email' => 'user6@example.com'])->assertStatus(429);

        usleep(1_100_000);

        ($this->register)(['email' => 'user6@example.com'])->assertCreated();
    });

    it('counts each service client on its own', function (): void {
        ($this->asRegistrar)();

        foreach (range(1, 5) as $n) {
            ($this->register)(['email' => "user{$n}@example.com"])->assertCreated();
        }
        ($this->register)(['email' => 'blocked@example.com'])->assertStatus(429);

        // Outro sistema, outra cota.
        ($this->asRegistrar)();

        ($this->register)(['email' => 'other-system@example.com'])->assertCreated();
    });

    it('follows the configured ceiling', function (): void {
        config()->set('api.rate_limits.register', 2);
        ($this->asRegistrar)();

        ($this->register)(['email' => 'a@example.com'])->assertCreated();
        ($this->register)(['email' => 'b@example.com'])->assertCreated();
        ($this->register)(['email' => 'c@example.com'])->assertStatus(429);
    });
});
