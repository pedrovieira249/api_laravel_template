<?php

declare(strict_types=1);

namespace Core\Shared\Infrastructure\Persistence;

use Core\Shared\Application\Contract\TransactionManager;
use Illuminate\Database\ConnectionResolverInterface;

final readonly class DbTransactionManager implements TransactionManager
{
    /**
     * O MySQL pode abortar um dos lados de um deadlock; repetir a unidade de
     * trabalho inteira é a resposta padrão, então pedimos 3 tentativas à conexão.
     */
    private const int ATTEMPTS = 3;

    public function __construct(private ConnectionResolverInterface $connections) {}

    /**
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     */
    public function run(callable $operation): mixed
    {
        return $this->connections->connection()->transaction(
            fn (): mixed => $operation(),
            self::ATTEMPTS,
        );
    }
}
