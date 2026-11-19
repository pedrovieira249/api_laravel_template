<?php

declare(strict_types=1);

namespace Core\Shared\Infrastructure\Bus;

/**
 * Convenção sobre configuração: `Foo\PlaceOrderCommand` é tratado por
 * `Foo\PlaceOrderHandler`, `Foo\ListProductsQuery` por `Foo\ListProductsHandler`.
 * Nenhum registro para manter; os arch tests garantem os sufixos.
 */
final readonly class HandlerResolver
{
    private const array SUFFIXES = ['Command', 'Query'];

    /**
     * @param  class-string  $messageClass
     * @return class-string
     *
     * @throws HandlerNotFound
     */
    public function resolve(string $messageClass): string
    {
        foreach (self::SUFFIXES as $suffix) {
            if (! str_ends_with($messageClass, $suffix)) {
                continue;
            }

            $handlerClass = substr($messageClass, 0, -strlen($suffix)).'Handler';

            if (class_exists($handlerClass) && method_exists($handlerClass, '__invoke')) {
                return $handlerClass;
            }

            throw HandlerNotFound::for($messageClass, $handlerClass);
        }

        throw HandlerNotFound::for($messageClass, $messageClass.'Handler');
    }
}
