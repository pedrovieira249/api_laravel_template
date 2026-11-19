<?php

declare(strict_types=1);

use Core\Shared\Domain\Exception\InvalidUuid;
use Core\Shared\Domain\ValueObject\Uuid;

// Um UUID v7 válido: o terceiro grupo começa com a versão (7), o quarto com a variante (8).
const VALID_UUID = '019a5c6e-7b3a-7c1d-8f2e-3a4b5c6d7e8f';

it('accepts a valid uuid', function (): void {
    $uuid = Uuid::fromString(VALID_UUID);

    expect($uuid->value)->toBe(VALID_UUID);
});

it('normalizes the value to lowercase', function (): void {
    $uuid = Uuid::fromString(strtoupper(VALID_UUID));

    expect($uuid->value)->toBe(VALID_UUID);
});

it('rejects an invalid uuid', function (string $value): void {
    Uuid::fromString($value);
})->with([
    'empty string' => '',
    'random text' => 'not-a-uuid',
    'missing dashes' => '019a5c6e7b3a7c1d8f2e3a4b5c6d7e8f',
    'short last group' => '019a5c6e-7b3a-7c1d-8f2e-3a4b5c6d7e8',
    'non-hex character' => '019a5c6e-7b3a-7c1d-8f2e-3a4b5c6d7e8g',
    'version 0' => '019a5c6e-7b3a-0c1d-8f2e-3a4b5c6d7e8f',
    'invalid variant' => '019a5c6e-7b3a-7c1d-7f2e-3a4b5c6d7e8f',
    'nil uuid' => '00000000-0000-0000-0000-000000000000',
])->throws(InvalidUuid::class);

it('names the rejected value in the exception message', function (): void {
    expect(fn (): Uuid => Uuid::fromString('not-a-uuid'))
        ->toThrow(InvalidUuid::class, 'not-a-uuid');
});

it('is equal to another uuid with the same value', function (): void {
    $uuid = Uuid::fromString(VALID_UUID);
    $same = Uuid::fromString(strtoupper(VALID_UUID));

    expect($uuid->equals($same))->toBeTrue();
});

it('is not equal to a uuid with a different value', function (): void {
    $uuid = Uuid::fromString(VALID_UUID);
    $other = Uuid::fromString('019a5c6e-7b3a-7c1d-8f2e-3a4b5c6d7e90');

    expect($uuid->equals($other))->toBeFalse();
});

it('converts to string', function (): void {
    $uuid = Uuid::fromString(VALID_UUID);

    expect($uuid)->toBeInstanceOf(Stringable::class)
        ->and((string) $uuid)->toBe(VALID_UUID);
});

it('cannot be built without validation', function (): void {
    (new Uuid('not-a-uuid'));
})->throws(Error::class);
