<?php

declare(strict_types=1);

use Core\Shared\Domain\Exception\InvalidEmail;
use Core\Shared\Domain\ValueObject\Email;

it('accepts a valid email', function (): void {
    expect(Email::fromString('ana@example.com')->value)->toBe('ana@example.com');
});

it('trims and lowercases the value', function (): void {
    expect(Email::fromString("  Ana.Silva@Example.COM \n")->value)->toBe('ana.silva@example.com');
});

it('rejects an invalid email', function (string $value): void {
    Email::fromString($value);
})->with([
    'empty string' => '',
    'missing at sign' => 'ana.example.com',
    'missing domain' => 'ana@',
    'spaces inside' => 'ana silva@example.com',
    'double at sign' => 'ana@@example.com',
])->throws(InvalidEmail::class);

it('names the rejected value in the exception message', function (): void {
    expect(fn (): Email => Email::fromString('nope'))->toThrow(InvalidEmail::class, '"nope"');
});

it('compares by normalized value', function (): void {
    expect(Email::fromString('ANA@example.com')->equals(Email::fromString('ana@example.com')))->toBeTrue()
        ->and(Email::fromString('ana@example.com')->equals(Email::fromString('bob@example.com')))->toBeFalse();
});

it('converts to string', function (): void {
    expect((string) Email::fromString('ana@example.com'))->toBe('ana@example.com');
});
