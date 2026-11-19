<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::get('/api/ping', fn (): array => ['request_id' => Context::get('request_id')]);
});

it('assigns a request id, puts it in Context and echoes it in the response', function (): void {
    $response = $this->getJson('/api/ping')->assertOk();

    $id = $response->headers->get('X-Request-Id');
    expect($id)->not->toBeNull()
        ->and($response->json('request_id'))->toBe($id);
});

it('reuses a well-formed id sent by the client', function (): void {
    $this->withHeader('X-Request-Id', 'gateway-abc-123')->getJson('/api/ping')
        ->assertHeader('X-Request-Id', 'gateway-abc-123')
        ->assertJsonPath('request_id', 'gateway-abc-123');

    $this->withHeader('X-Request-Id', 'bad id with spaces')->getJson('/api/ping')
        ->assertJsonMissing(['request_id' => 'bad id with spaces']);
});
