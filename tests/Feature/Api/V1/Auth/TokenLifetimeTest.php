<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Passport;

/*
 * Tokens de usuário (Passport): access token de 5 minutos, refresh token de 7 dias.
 *
 * O relógio que valida um JWT é o do sistema, não o do Carbon: travelTo() não faz
 * um token vencer. Por isso os testes conferem o prazo gravado no token e, para o
 * caso "venceu", emitem um token que já nasce vencido.
 */

it('issues access tokens that live exactly five minutes', function (): void {
    $tokens = loginAs(User::factory()->create());
    $claims = jwtClaims($tokens['access_token']);

    expect($tokens['expires_in'])->toBe(300)
        ->and((int) round($claims['exp'] - $claims['iat']))->toBe(300);

    $expiresAt = DB::table('oauth_access_tokens')->where('id', $claims['jti'])->value('expires_at');

    expect(abs(now()->addMinutes(5)->diffInSeconds($expiresAt)))->toBeLessThan(5);
});

it('issues refresh tokens that live seven days', function (): void {
    loginAs(User::factory()->create());

    $expiresAt = DB::table('oauth_refresh_tokens')->value('expires_at');

    expect(abs(now()->addDays(7)->diffInSeconds($expiresAt)))->toBeLessThan(5);
});

it('puts the user id in the token and no scopes', function (): void {
    $user = User::factory()->create();

    $claims = jwtClaims(loginAs($user)['access_token']);

    expect($claims['sub'])->toBe($user->id)
        ->and($claims['scopes'])->toBe([]);
});

it('rejects an access token once it has expired', function (): void {
    Passport::tokensExpireIn(CarbonInterval::minutes(1)->invert());

    $tokens = loginAs(User::factory()->create());

    $this->withToken($tokens['access_token'])->getJson('/api/v1/auth/me')
        ->assertUnauthorized()
        ->assertJsonPath('title', 'Unauthenticated');
});

it('does not log an error every time an expired or invalid token shows up', function (): void {
    Log::spy();

    $this->withToken('not.a.jwt')->getJson('/api/v1/auth/me')->assertUnauthorized();

    Log::shouldNotHaveReceived('error');
});
