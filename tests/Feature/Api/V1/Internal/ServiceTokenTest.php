<?php

declare(strict_types=1);

use App\Models\ServiceClient;
use Database\Factories\ServiceClientFactory;

beforeEach(function (): void {
    $this->client = ServiceClient::factory()->create(['name' => 'billing-api', 'abilities' => ['reports.read', 'reports.export']]);
    $this->credentials = fn (array $overrides = []): array => [
        'client_id' => $this->client->id,
        'client_secret' => ServiceClientFactory::SECRET,
        ...$overrides,
    ];
});

it('exchanges client credentials for a one hour token with the client abilities', function (): void {
    $response = $this->postJson('/api/v1/internal/auth/token', ($this->credentials)())
        ->assertOk()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('expires_in', 3600)
        ->assertJsonPath('abilities', ['reports.read', 'reports.export'])
        ->assertJsonStructure(['access_token']);

    $this->withToken($response->json('access_token'))->getJson('/api/v1/internal/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $this->client->id)
        ->assertJsonPath('data.name', 'billing-api')
        ->assertJsonMissingPath('data.secret');
});

it('stores only the hash of the secret and of the token', function (): void {
    $token = $this->postJson('/api/v1/internal/auth/token', ($this->credentials)())->json('access_token');

    expect($this->client->fresh()?->secret)->not->toBe(ServiceClientFactory::SECRET);
    $this->assertDatabaseMissing('personal_access_tokens', ['token' => $token]);
    $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $this->client->id, 'name' => 'service-token']);
});

it('answers 401 with one message for a wrong secret, an unknown client and a revoked client', function (): void {
    $revoked = ServiceClient::factory()->revoked()->create();

    $attempts = [
        ($this->credentials)(['client_secret' => 'wrong']),
        ($this->credentials)(['client_id' => '019a5c6e-7b3a-7c1d-8f2e-3a4b5c6d7eff']),
        ($this->credentials)(['client_id' => $revoked->id]),
    ];

    foreach ($attempts as $attempt) {
        $this->postJson('/api/v1/internal/auth/token', $attempt)
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('detail', 'Invalid client credentials.');
    }

    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('validates the payload', function (): void {
    $this->postJson('/api/v1/internal/auth/token', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['client_id', 'client_secret']);
});

it('issues independent tokens for each instance of the same service', function (): void {
    $first = $this->postJson('/api/v1/internal/auth/token', ($this->credentials)())->json('access_token');
    $second = $this->postJson('/api/v1/internal/auth/token', ($this->credentials)())->json('access_token');

    expect($first)->not->toBe($second);

    $this->withToken($first)->getJson('/api/v1/internal/auth/me')->assertOk();
    freshRequest();
    $this->withToken($second)->getJson('/api/v1/internal/auth/me')->assertOk();
});

it('throttles token requests', function (): void {
    config()->set('api.rate_limits.token', 2);

    $this->postJson('/api/v1/internal/auth/token', ($this->credentials)())->assertOk();
    $this->postJson('/api/v1/internal/auth/token', ($this->credentials)())->assertOk();
    $this->postJson('/api/v1/internal/auth/token', ($this->credentials)())->assertStatus(429);
});
