<?php

declare(strict_types=1);

namespace Core\Shared\Application\Contract;

/**
 * Port de saída (driven): executa uma unidade de trabalho atomicamente. O código
 * de Application não sabe nada de PDO nem de DB::transaction(); só pede "tudo ou nada".
 */
interface TransactionManager
{
    /**
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     */
    public function run(callable $operation): mixed;
}
