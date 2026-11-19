<?php

declare(strict_types=1);

namespace Core\Shared\Domain\ValueObject;

use Core\Shared\Domain\Exception\InvalidEmail;

final readonly class Email implements \Stringable
{
    private function __construct(public string $value) {}

    /**
     * @throws InvalidEmail
     */
    public static function fromString(string $value): self
    {
        // Operador pipe do PHP 8.5: cada etapa recebe o resultado anterior como seu
        // único argumento. Lê-se da esquerda para a direita; equivale a mb_strtolower(mb_trim($value)).
        $normalized = $value |> mb_trim(...) |> mb_strtolower(...);

        if (filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidEmail::fromString($value);
        }

        return new self($normalized);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
