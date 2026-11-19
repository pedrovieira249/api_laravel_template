<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use Core\Shared\Application\Contract\TransactionManager;

/**
 * Executa a operação sem nenhuma transação: testes unitários não têm banco.
 */
final class NullTransactionManager implements TransactionManager
{
    public int $runs = 0;

    /**
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     */
    public function run(callable $operation): mixed
    {
        $this->runs++;

        return $operation();
    }
}
