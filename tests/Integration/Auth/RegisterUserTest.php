<?php

declare(strict_types=1);

use App\Auth\Users\RegisterUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

it('creates a user with a hashed password, the default role and a working token pair', function (): void {
    $result = $this->app->make(RegisterUser::class)(name: 'Ana', email: ' Ana@Example.com ', password: 'secret-123');

    expect($result->user->email)->toBe('ana@example.com')
        ->and(Hash::check('secret-123', $result->user->password))->toBeTrue()
        ->and($result->user->roleNames())->toBe(['user'])
        ->and($result->tokens->expiresIn)->toBe(300)
        ->and(jwtClaims($result->tokens->accessToken)['sub'])->toBe($result->user->id);

    $this->assertDatabaseCount('oauth_access_tokens', 1);
    $this->assertDatabaseCount('oauth_refresh_tokens', 1);
});

it('leaves no user behind when the tokens cannot be stored', function (): void {
    // Injeção de falha: a primeira query que tocar a tabela de tokens explode.
    // Se usuário, perfil e tokens não estivessem na mesma transação, o usuário sobraria.
    DB::connection()->beforeExecuting(function (string $query): void {
        if (str_contains($query, 'oauth_access_tokens')) {
            throw new RuntimeException('token storage is down');
        }
    });

    expect(fn () => $this->app->make(RegisterUser::class)(name: 'Ana', email: 'ana@example.com', password: 'secret-123'))
        ->toThrow(RuntimeException::class);

    expect(User::query()->where('email', 'ana@example.com')->exists())->toBeFalse();
    $this->assertDatabaseCount('role_user', 0);
});
