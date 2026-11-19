<?php

declare(strict_types=1);

use App\Enums\PermissionName;
use App\Models\ServiceClient;
use App\Models\User;

/*
 * Estas rotas são protegidas por PERFIL (role:admin), não por permissão: nenhum
 * conjunto de permissões dá acesso a elas.
 */

it('lets an administrator register an internal API and shows the secret once', function (): void {
    actingAsUser(User::factory()->admin()->create());

    $response = $this->postJson('/api/v1/admin/service-clients', [
        'name' => 'billing-api',
        'abilities' => ['*'],
    ])->assertCreated()
        ->assertJsonPath('data.name', 'billing-api')
        ->assertJsonPath('data.abilities', ['*'])
        ->assertJsonStructure(['data' => ['id', 'revoked_at', 'created_at'], 'client_secret']);

    // As credenciais devolvidas funcionam no endpoint de token.
    $this->postJson('/api/v1/internal/auth/token', [
        'client_id' => $response->json('data.id'),
        'client_secret' => $response->json('client_secret'),
    ])->assertOk()->assertJsonPath('abilities', ['*']);

    // E o segredo nunca mais aparece.
    $this->getJson('/api/v1/admin/service-clients')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonMissingPath('data.0.secret')
        ->assertJsonMissingPath('data.0.client_secret');
});

it('validates the name and the abilities against the catalog', function (): void {
    ServiceClient::factory()->create(['name' => 'billing-api']);
    actingAsUser(User::factory()->admin()->create());

    $this->postJson('/api/v1/admin/service-clients', ['name' => 'billing-api', 'abilities' => ['fly.to.the.moon']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'abilities.0']);

    $this->postJson('/api/v1/admin/service-clients', ['name' => 'Bad Name', 'abilities' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'abilities']);
});

it('revokes a client: its tokens die and it cannot get new ones', function (): void {
    actingAsUser(User::factory()->admin()->create());
    $created = $this->postJson('/api/v1/admin/service-clients', ['name' => 'billing-api', 'abilities' => ['*']])->json();
    $credentials = ['client_id' => $created['data']['id'], 'client_secret' => $created['client_secret']];
    $token = $this->postJson('/api/v1/internal/auth/token', $credentials)->json('access_token');

    $this->deleteJson("/api/v1/admin/service-clients/{$created['data']['id']}")->assertNoContent();

    freshRequest();
    $this->withToken($token)->getJson('/api/v1/internal/auth/me')->assertUnauthorized();
    $this->postJson('/api/v1/internal/auth/token', $credentials)->assertUnauthorized();
    $this->assertDatabaseHas('service_clients', ['name' => 'billing-api']);
    expect(ServiceClient::query()->sole()->isRevoked())->toBeTrue();
});

it('is closed to every profile but admin, whatever the permissions', function (): void {
    $client = ServiceClient::factory()->create();
    actingAsUser(User::factory()->withPermissions(...PermissionName::cases())->create());

    $this->getJson('/api/v1/admin/service-clients')->assertForbidden();
    $this->postJson('/api/v1/admin/service-clients', ['name' => 'x', 'abilities' => ['*']])->assertForbidden();
    $this->deleteJson("/api/v1/admin/service-clients/{$client->id}")->assertForbidden();
});

it('requires authentication', function (): void {
    $this->getJson('/api/v1/admin/service-clients')->assertUnauthorized();
});
