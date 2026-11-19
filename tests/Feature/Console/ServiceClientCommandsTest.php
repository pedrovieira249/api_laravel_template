<?php

declare(strict_types=1);

use App\Models\ServiceClient;

it('creates a service client and prints its credentials once', function (): void {
    $this->artisan('service-client:create', ['name' => 'billing-api', '--ability' => ['*']])
        ->expectsOutputToContain('Service client created.')
        ->expectsOutputToContain('client_id')
        ->expectsOutputToContain('cannot be shown again')
        ->assertSuccessful();

    $client = ServiceClient::query()->where('name', 'billing-api')->sole();

    expect($client->abilities)->toBe(['*'])
        ->and($client->isRevoked())->toBeFalse();
});

it('refuses an invalid name, a duplicated name and unknown abilities', function (array $arguments, string $message): void {
    ServiceClient::factory()->create(['name' => 'taken']);

    $this->artisan('service-client:create', $arguments)
        ->expectsOutputToContain($message)
        ->assertFailed();

    expect(ServiceClient::query()->count())->toBe(1);
})->with([
    'invalid name' => [['name' => 'Bad Name', '--ability' => ['*']], 'The name must be a slug'],
    'duplicated name' => [['name' => 'taken', '--ability' => ['*']], 'already exists'],
    'no ability' => [['name' => 'billing-api'], 'Pass at least one valid --ability'],
    'unknown ability' => [['name' => 'billing-api', '--ability' => ['fly']], 'Pass at least one valid --ability'],
]);

it('revokes a client by name or id and deletes its tokens', function (): void {
    $byName = ServiceClient::factory()->create(['name' => 'billing-api']);
    $byId = ServiceClient::factory()->create();
    $byName->createToken('service-token');

    $this->artisan('service-client:revoke', ['client' => 'billing-api'])
        ->expectsOutputToContain('Service client "billing-api" revoked.')
        ->assertSuccessful();
    $this->artisan('service-client:revoke', ['client' => $byId->id])->assertSuccessful();

    expect($byName->fresh()?->isRevoked())->toBeTrue()
        ->and($byId->fresh()?->isRevoked())->toBeTrue();
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('fails to revoke a client that does not exist', function (): void {
    $this->artisan('service-client:revoke', ['client' => 'ghost'])
        ->expectsOutputToContain('No service client with id or name "ghost".')
        ->assertFailed();
});

it('lists the clients and their status', function (): void {
    ServiceClient::factory()->create(['name' => 'billing-api']);
    ServiceClient::factory()->revoked()->create(['name' => 'old-worker']);

    $this->artisan('service-client:list')
        ->expectsOutputToContain('billing-api')
        ->expectsOutputToContain('revoked')
        ->assertSuccessful();
});
