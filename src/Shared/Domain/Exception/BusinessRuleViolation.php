<?php

declare(strict_types=1);

namespace Core\Shared\Domain\Exception;

/**
 * A requisição estava bem formada, mas conflita com o estado atual do domínio
 * (uma transição de estado inválida, estoque insuficiente, moedas misturadas...).
 */
abstract class BusinessRuleViolation extends DomainException {}
