<?php

declare(strict_types=1);

use App\Auth\Services\IssueServiceToken;
use App\Auth\Services\RegisterServiceClient;
use App\Auth\Services\RevokeServiceClient;
use App\Models\ServiceClient;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;

it('registers a client with a random secret stored as a hash', function (): void {
    $created = $this->app->make(RegisterServiceClient::class)('billing-api', ['*', '*']);

    expect(strlen($created->plainSecret))->toBe(48)
        ->and($created->client->abilities)->toBe(['*'])
        ->and(Hash::check($created->plainSecret, $created->client->fresh()->secret))->toBeTrue();
});

it('refuses abilities that are not in the catalog', function (): void {
    $this->app->make(RegisterServiceClient::class)('billing-api', ['fly.to.the.moon']);
})->throws(ValueError::class);

it('issues a token carrying the abilities and the expiry of the client', function (): void {
    $created = $this->app->make(RegisterServiceClient::class)('billing-api', ['*']);

    $token = $this->app->make(IssueServiceToken::class)($created->client->id, $created->plainSecret);

    expect($token->expiresIn)->toBe(3600)
        ->and($token->abilities)->toBe(['*'])
        ->and($token->toArray())->toHaveKeys(['token_type', 'access_token', 'expires_in', 'abilities']);

    $stored = $created->client->tokens()->sole();
    expect($stored->abilities)->toBe(['*'])
        ->and($stored->expires_at->isFuture())->toBeTrue();
});

it('stops issuing tokens to a revoked client and deletes the ones it had', function (): void {
    $created = $this->app->make(RegisterServiceClient::class)('billing-api', ['*']);
    $issue = $this->app->make(IssueServiceToken::class);
    $issue($created->client->id, $created->plainSecret);

    $revoke = $this->app->make(RevokeServiceClient::class);
    $revoke($created->client);
    $revokedAt = $created->client->fresh()->revoked_at;
    $revoke($created->client); // revogar de novo não muda a data

    expect($created->client->tokens()->count())->toBe(0)
        ->and(ServiceClient::query()->sole()->revoked_at?->equalTo($revokedAt))->toBeTrue()
        ->and(fn () => $issue($created->client->id, $created->plainSecret))->toThrow(AuthenticationException::class);
});
