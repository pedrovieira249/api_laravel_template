<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
 * O middleware "idempotent" em uma rota descartável (ADR 0010). Nenhuma rota do
 * template o usa por padrão: aplique-o nos POSTs que não podem rodar duas vezes.
 */
beforeEach(function (): void {
    $this->calls = 0;

    Route::middleware(['api', 'idempotent'])->post('/api/idempotent/orders', function () {
        $this->calls++;

        return response()->json(['order' => $this->calls], 201, ['Location' => '/api/orders/'.$this->calls]);
    });
});

it('requires the Idempotency-Key header', function (): void {
    $this->postJson('/api/idempotent/orders')
        ->assertStatus(400)
        ->assertJsonPath('detail', 'The Idempotency-Key header is required (1-128 characters).');

    expect($this->calls)->toBe(0);
});

it('runs the action once and replays the first response on retries', function (): void {
    $first = $this->postJson('/api/idempotent/orders', [], ['Idempotency-Key' => 'attempt-1'])
        ->assertCreated()
        ->assertHeaderMissing('Idempotency-Replayed');

    $retry = $this->postJson('/api/idempotent/orders', [], ['Idempotency-Key' => 'attempt-1'])
        ->assertCreated()
        ->assertHeader('Idempotency-Replayed', 'true')
        ->assertHeader('Location', '/api/orders/1');

    expect($this->calls)->toBe(1)
        ->and($retry->json())->toBe($first->json());
});

it('treats different keys as different operations', function (): void {
    $this->postJson('/api/idempotent/orders', [], ['Idempotency-Key' => 'a'])->assertJsonPath('order', 1);
    $this->postJson('/api/idempotent/orders', [], ['Idempotency-Key' => 'b'])->assertJsonPath('order', 2);
});

it('scopes the key to the authenticated principal', function (): void {
    actingAsUser(User::factory()->create());
    $this->postJson('/api/idempotent/orders', [], ['Idempotency-Key' => 'same'])->assertJsonPath('order', 1);

    actingAsUser(User::factory()->create());
    $this->postJson('/api/idempotent/orders', [], ['Idempotency-Key' => 'same'])
        ->assertJsonPath('order', 2)
        ->assertHeaderMissing('Idempotency-Replayed');
});

it('does not store failed responses', function (): void {
    Route::middleware(['api', 'idempotent'])->post('/api/idempotent/fail', function () {
        $this->calls++;

        return response()->json(['error' => true], 422);
    });

    $this->postJson('/api/idempotent/fail', [], ['Idempotency-Key' => 'x'])->assertUnprocessable();
    $this->postJson('/api/idempotent/fail', [], ['Idempotency-Key' => 'x'])->assertUnprocessable();

    expect($this->calls)->toBe(2);
});
