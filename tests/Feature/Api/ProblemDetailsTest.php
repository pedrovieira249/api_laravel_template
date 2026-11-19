<?php

declare(strict_types=1);

use Core\Shared\Domain\Exception\BusinessRuleViolation;
use Core\Shared\Domain\Exception\DomainException;
use Core\Shared\Domain\Exception\EntityNotFound;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

/**
 * Rotas descartáveis: cada uma lança uma exceção específica para que o handler
 * central possa ser exercitado sem nenhum endpoint real.
 */
beforeEach(function (): void {
    Route::get('/api/problems/not-found', fn (): never => throw new class('Order 42 was not found.') extends EntityNotFound {});
    Route::get('/api/problems/conflict', fn (): never => throw new class('Order cannot be shipped before payment.') extends BusinessRuleViolation {});
    Route::get('/api/problems/invalid', fn (): never => throw new class('Invalid email: "x".') extends DomainException {});
    Route::get('/api/problems/validation', fn () => Validator::make([], ['name' => 'required'])->validate());
    Route::get('/api/problems/crash', fn (): never => throw new RuntimeException('secret detail'));
});

it('maps EntityNotFound to 404', function (): void {
    $this->getJson('/api/problems/not-found')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJson([
            'title' => 'Not found',
            'status' => 404,
            'detail' => 'Order 42 was not found.',
            'instance' => '/api/problems/not-found',
        ]);
});

it('maps BusinessRuleViolation to 409', function (): void {
    $this->getJson('/api/problems/conflict')
        ->assertStatus(409)
        ->assertJson(['status' => 409, 'detail' => 'Order cannot be shipped before payment.']);
});

it('maps other domain exceptions to 422', function (): void {
    $this->getJson('/api/problems/invalid')
        ->assertUnprocessable()
        ->assertJson(['status' => 422, 'detail' => 'Invalid email: "x".']);
});

it('maps validation errors to 422 with an errors map', function (): void {
    $this->getJson('/api/problems/validation')
        ->assertUnprocessable()
        ->assertJson([
            'type' => 'https://api.example.com/problems/validation',
            'title' => 'Validation failed',
            'status' => 422,
        ])
        ->assertJsonPath('errors.name.0', 'The name field is required.');
});

it('maps unknown routes to 404', function (): void {
    $this->getJson('/api/v1/definitely-not-here')
        ->assertNotFound()
        ->assertJson(['type' => 'about:blank', 'status' => 404]);
});

it('hides internal details of unexpected errors when debug is off', function (): void {
    config()->set('app.debug', false);

    $this->getJson('/api/problems/crash')
        ->assertStatus(500)
        ->assertJson(['title' => 'Internal server error', 'detail' => 'An unexpected error occurred.'])
        ->assertJsonMissing(['detail' => 'secret detail']);
});

it('still renders HTML for non-API requests', function (): void {
    Route::get('/web/problems/crash', fn (): never => throw new RuntimeException('boom'));

    $this->get('/web/problems/crash')
        ->assertStatus(500)
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8');
});
