<?php

declare(strict_types=1);

namespace Core\Shared\Domain\ValueObject;

use Core\Shared\Domain\Enum\Currency;
use Core\Shared\Domain\Exception\CurrencyMismatch;

/**
 * Dinheiro como um valor inteiro de unidades menores (centavos) mais uma moeda.
 *
 * Nunca float: 0.1 + 0.2 !== 0.3 em IEEE-754. Toda operação devolve uma nova
 * instância ("clone with" do PHP 8.5) e é marcada com #[\NoDiscard]: ignorar o
 * resultado de `$total->add($shipping)` é sempre um bug.
 *
 * Valores negativos são permitidos (estornos, descontos); "o total de um pedido
 * nunca é negativo" é invariante do agregado Order, não de Money.
 */
final readonly class Money implements \Stringable
{
    private function __construct(public int $amount, public Currency $currency) {}

    public static function fromCents(int $amount, Currency $currency): self
    {
        return new self($amount, $currency);
    }

    public static function zero(Currency $currency): self
    {
        return new self(0, $currency);
    }

    /**
     * @throws CurrencyMismatch
     */
    #[\NoDiscard]
    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return clone ($this, ['amount' => $this->amount + $other->amount]);
    }

    /**
     * @throws CurrencyMismatch
     */
    #[\NoDiscard]
    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return clone ($this, ['amount' => $this->amount - $other->amount]);
    }

    #[\NoDiscard]
    public function multiply(int $factor): self
    {
        return clone ($this, ['amount' => $this->amount * $factor]);
    }

    /**
     * Porcentagem só com inteiros, arredondando o meio para longe do zero (15% de 9.99 = 1.50).
     */
    #[\NoDiscard]
    public function percentage(int $percent): self
    {
        $product = $this->amount * $percent;
        $rounding = $product >= 0 ? 50 : -50;

        return clone ($this, ['amount' => intdiv($product + $rounding, 100)]);
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function isNegative(): bool
    {
        return $this->amount < 0;
    }

    /**
     * @throws CurrencyMismatch
     */
    public function isGreaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount > $other->amount;
    }

    /**
     * @throws CurrencyMismatch
     */
    public function isLessThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount < $other->amount;
    }

    /**
     * "BRL 15.00", "BRL -0.05". Só aritmética de inteiros.
     */
    public function __toString(): string
    {
        $scale = 10 ** $this->currency->decimals();
        $absolute = abs($this->amount);

        return sprintf(
            '%s %s%d.%0'.$this->currency->decimals().'d',
            $this->currency->value,
            $this->amount < 0 ? '-' : '',
            intdiv($absolute, $scale),
            $absolute % $scale,
        );
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw CurrencyMismatch::between($this->currency, $other->currency);
        }
    }
}
