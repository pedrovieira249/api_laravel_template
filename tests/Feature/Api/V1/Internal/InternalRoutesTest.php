<?php

declare(strict_types=1);

use App\Models\ServiceClient;
use App\Models\User;
use Database\Factories\ServiceClientFactory;
use Illuminate\Support\Facades\Route;

/*
 * As rotas /internal são de outro público: quem entra é um ServiceClient com
 * token Sanctum, e o que autoriza é a ability do token (não um perfil).
 */
beforeEach(function (): void {
    // Rotas descartáveis, protegidas como uma rota interna de verdade seria.
    Route::middleware(['api', 'auth:internal'])->prefix('api/v1/internal/probe')->group(function (): void {
        Route::get('any', fn (): array => ['ok' => true])->middleware('ability:reports.read,reports.export');
        Route::get('all', fn (): array => ['ok' => true])->middleware('abilities:reports.read,reports.export');
    });
});

it('lets a service in when its token carries the ability', function (): void {
    actingAsService(ServiceClient::factory()->create(), ['reports.read']);

    $this->getJson('/api/v1/internal/probe/any')->assertOk();
});

it('forbids a service whose token lacks the ability', function (): void {
    actingAsService(ServiceClient::factory()->create(), ['something.else']);

    $this->getJson('/api/v1/internal/probe/any')
        ->assertForbidden()
        ->assertJsonPath('title', 'Forbidden');
});

it('tells "any of" from "all of"', function (): void {
    actingAsService(ServiceClient::factory()->create(), ['reports.read']);
    $this->getJson('/api/v1/internal/probe/any')->assertOk();
    $this->getJson('/api/v1/internal/probe/all')->assertForbidden();

    actingAsService(ServiceClient::factory()->create(), ['reports.read', 'reports.export']);
    $this->getJson('/api/v1/internal/probe/all')->assertOk();
});

it('accepts the wildcard ability', function (): void {
    actingAsService(ServiceClient::factory()->create(), ['*']);

    $this->getJson('/api/v1/internal/probe/all')->assertOk();
});

it('requires a service token', function (): void {
    $this->getJson('/api/v1/internal/probe/any')->assertUnauthorized();
    $this->getJson('/api/v1/internal/auth/me')->assertUnauthorized();
});

it('does not accept a user token on internal routes', function (): void {
    $tokens = loginAs(User::factory()->admin()->create());

    $this->withToken($tokens['access_token'])->getJson('/api/v1/internal/auth/me')->assertUnauthorized();
    $this->withToken($tokens['access_token'])->getJson('/api/v1/internal/probe/any')->assertUnauthorized();
});

it('does not accept a service token on user routes', function (): void {
    $token = $this->postJson('/api/v1/internal/auth/token', [
        'client_id' => ServiceClient::factory()->create()->id,
        'client_secret' => ServiceClientFactory::SECRET,
    ])->json('access_token');

    $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    $this->withToken($token)->getJson('/api/v1/admin/users')->assertUnauthorized();
});

it('revokes only the token used in the request', function (): void {
    $client = ServiceClient::factory()->create();
    $issue = fn (): string => $this->postJson('/api/v1/internal/auth/token', [
        'client_id' => $client->id,
        'client_secret' => ServiceClientFactory::SECRET,
    ])->json('access_token');
    $first = $issue();
    $second = $issue();

    $this->withToken($first)->deleteJson('/api/v1/internal/auth/token')->assertNoContent();

    freshRequest();
    $this->withToken($first)->getJson('/api/v1/internal/auth/me')->assertUnauthorized();
    freshRequest();
    $this->withToken($second)->getJson('/api/v1/internal/auth/me')->assertOk();
});
