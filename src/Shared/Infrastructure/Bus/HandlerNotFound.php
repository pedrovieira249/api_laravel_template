<?php

declare(strict_types=1);

namespace Core\Shared\Infrastructure\Bus;

/**
 * Um erro de programação, não de domínio: a convenção não foi seguida.
 */
final class HandlerNotFound extends \LogicException
{
    public static function for(string $messageClass, string $expectedHandler): self
    {
        return new self(sprintf(
            'No handler for %s: expected a class named %s with an __invoke method.',
            $messageClass,
            $expectedHandler,
        ));
    }
}
