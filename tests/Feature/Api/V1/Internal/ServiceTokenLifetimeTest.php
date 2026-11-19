<?php

declare(strict_types=1);

use App\Models\ServiceClient;
use Database\Factories\ServiceClientFactory;

/*
 * Tokens de serviço (Sanctum) valem 1 hora a partir da emissão. Aqui o relógio é
 * o do Carbon, então travelTo() basta para atravessar o prazo.
 */

/**
 * Autentica de verdade e devolve o token em texto puro, como um serviço receberia.
 */
function serviceToken(?ServiceClient $client = null): string
{
    $client ??= ServiceClient::factory()->create();

    return test()->postJson('/api/v1/internal/auth/token', [
        'client_id' => $client->id,
        'client_secret' => ServiceClientFactory::SECRET,
    ])->assertOk()->json('access_token');
}

it('accepts the token during its first hour', function (): void {
    $token = serviceToken();

    $this->travelTo(now()->addMinutes(59));

    $this->withToken($token)->getJson('/api/v1/internal/auth/me')->assertOk();
});

it('rejects the token once one hour has passed', function (): void {
    $token = serviceToken();

    $this->travelTo(now()->addMinutes(61));

    $this->withToken($token)->getJson('/api/v1/internal/auth/me')
        ->assertUnauthorized()
        ->assertJsonPath('title', 'Unauthenticated');
});

it('does not extend the lifetime when the token is used', function (): void {
    $token = serviceToken();

    $this->travelTo(now()->addMinutes(50));
    $this->withToken($token)->getJson('/api/v1/internal/auth/me')->assertOk();

    // 50 + 20 = 70 minutos desde a emissão: o uso aos 50 minutos não renovou o prazo.
    $this->travelTo(now()->addMinutes(20));
    freshRequest();
    $this->withToken($token)->getJson('/api/v1/internal/auth/me')->assertUnauthorized();
});

it('lets the service ask for a fresh token after expiry', function (): void {
    $client = ServiceClient::factory()->create();
    serviceToken($client);
    $this->travelTo(now()->addMinutes(61));

    $fresh = serviceToken($client);

    $this->withToken($fresh)->getJson('/api/v1/internal/auth/me')->assertOk();
});

it('follows the configured lifetime', function (): void {
    config()->set('identity.services.token_ttl', 10);

    $this->postJson('/api/v1/internal/auth/token', [
        'client_id' => ServiceClient::factory()->create()->id,
        'client_secret' => ServiceClientFactory::SECRET,
    ])->assertOk()->assertJsonPath('expires_in', 600);

    $expiresAt = DB::table('personal_access_tokens')->value('expires_at');

    expect(abs(now()->addMinutes(10)->diffInSeconds($expiresAt)))->toBeLessThan(5);
});
