<?php

declare(strict_types=1);

use App\Auth\Users\AuthenticateUser;
use App\Auth\Users\RefreshUserTokens;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->user = User::factory()->admin()->create(['email' => 'admin@example.com']);
    $this->authenticate = $this->app->make(AuthenticateUser::class);
});

it('issues tokens for the user, case-insensitive on the e-mail', function (): void {
    $result = ($this->authenticate)(email: ' Admin@Example.com ', password: 'password');

    expect($result->user->is($this->user))->toBeTrue()
        ->and($result->tokens->toArray())->toHaveKeys(['token_type', 'access_token', 'refresh_token', 'expires_in'])
        ->and($result->tokens->toArray()['token_type'])->toBe('Bearer');
});

it('rejects a wrong password and an unknown e-mail with the same message', function (): void {
    $messages = [];

    foreach ([['admin@example.com', 'nope'], ['ghost@example.com', 'password']] as [$email, $password]) {
        try {
            ($this->authenticate)(email: $email, password: $password);
        } catch (ValidationException $exception) {
            $messages[] = $exception->errors()['email'][0];
        }
    }

    expect($messages)->toHaveCount(2)
        ->and($messages[0])->toBe($messages[1])->toBe(__('auth.failed'));
});

it('refreshes a token pair and refuses to reuse the old refresh token', function (): void {
    $refresh = $this->app->make(RefreshUserTokens::class);
    $first = ($this->authenticate)(email: 'admin@example.com', password: 'password')->tokens;

    $second = $refresh($first->refreshToken);

    expect($second->accessToken)->not->toBe($first->accessToken);
    expect(fn () => $refresh($first->refreshToken))->toThrow(AuthenticationException::class);
});
