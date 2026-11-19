<?php

declare(strict_types=1);

use Core\Shared\Domain\Enum\Currency;
use Core\Shared\Domain\Exception\CurrencyMismatch;
use Core\Shared\Domain\ValueObject\Money;

it('is created from cents', function (): void {
    $money = Money::fromCents(1500, Currency::Brl);

    expect($money->amount)->toBe(1500)
        ->and($money->currency)->toBe(Currency::Brl);
});

it('has a zero factory', function (): void {
    expect(Money::zero(Currency::Usd)->isZero())->toBeTrue();
});

it('adds amounts of the same currency', function (): void {
    $sum = Money::fromCents(1000, Currency::Brl)->add(Money::fromCents(250, Currency::Brl));

    expect($sum->amount)->toBe(1250);
});

it('subtracts amounts of the same currency', function (): void {
    $difference = Money::fromCents(1000, Currency::Brl)->subtract(Money::fromCents(1250, Currency::Brl));

    expect($difference->amount)->toBe(-250)
        ->and($difference->isNegative())->toBeTrue();
});

it('multiplies by an integer factor', function (): void {
    expect(Money::fromCents(999, Currency::Brl)->multiply(3)->amount)->toBe(2997);
});

it('calculates a percentage rounding half away from zero', function (int $amount, int $percent, int $expected): void {
    expect(Money::fromCents($amount, Currency::Brl)->percentage($percent)->amount)->toBe($expected);
})->with([
    '15% of 9.99 is 1.50' => [999, 15, 150],
    '10% of 0.05 is 0.01' => [5, 10, 1],
    '10% of 0.04 is 0.00' => [4, 10, 0],
    '50% of -0.03 is -0.02' => [-3, 50, -2],
    '100% is identity' => [1234, 100, 1234],
]);

it('keeps the original untouched (immutability)', function (): void {
    $original = Money::fromCents(1000, Currency::Brl);
    $result = $original->add(Money::fromCents(1, Currency::Brl));

    expect($original->amount)->toBe(1000)
        ->and($result)->not->toBe($original);
});

it('refuses to mix currencies', function (): void {
    // (void) diz ao PHP que o resultado #[\NoDiscard] é ignorado de propósito.
    (void) Money::fromCents(100, Currency::Brl)->add(Money::fromCents(100, Currency::Usd));
})->throws(CurrencyMismatch::class, 'expected BRL, got USD');

it('compares amount and currency', function (): void {
    $brl = Money::fromCents(100, Currency::Brl);

    expect($brl->equals(Money::fromCents(100, Currency::Brl)))->toBeTrue()
        ->and($brl->equals(Money::fromCents(101, Currency::Brl)))->toBeFalse()
        ->and($brl->equals(Money::fromCents(100, Currency::Usd)))->toBeFalse();
});

it('orders amounts of the same currency', function (): void {
    $ten = Money::fromCents(1000, Currency::Brl);
    $five = Money::fromCents(500, Currency::Brl);

    expect($ten->isGreaterThan($five))->toBeTrue()
        ->and($five->isLessThan($ten))->toBeTrue()
        ->and($ten->isLessThan($ten))->toBeFalse();
});

it('formats as currency and decimal amount', function (int $amount, string $expected): void {
    expect((string) Money::fromCents($amount, Currency::Brl))->toBe($expected);
})->with([
    [1500, 'BRL 15.00'],
    [5, 'BRL 0.05'],
    [-5, 'BRL -0.05'],
    [123456, 'BRL 1234.56'],
]);

it('warns when the result of an operation is discarded', function (): void {
    $warning = null;
    set_error_handler(function (int $errno, string $message) use (&$warning): bool {
        $warning = $message;

        return true;
    });

    try {
        Money::fromCents(100, Currency::Brl)->add(Money::fromCents(1, Currency::Brl));
    } finally {
        restore_error_handler();
    }

    expect($warning)->toContain('Money::add()', 'should either be used');
});
