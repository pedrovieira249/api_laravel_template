<?php

declare(strict_types=1);

namespace Core\Shared\Domain\Exception;

/**
 * Raiz de qualquer erro de regra de negócio lançado pelo domínio.
 *
 * Estende a \DomainException da SPL (mesmo nome, namespace diferente) para que
 * código genérico ainda consiga capturá-la como uma exceção padrão do PHP. A camada
 * HTTP mapeia esta hierarquia para códigos de status (ver App\Http\Responses\ProblemDetailsRenderer):
 *   - EntityNotFound          -> 404
 *   - BusinessRuleViolation   -> 409
 *   - qualquer outra DomainException (value objects inválidos) -> 422
 */
abstract class DomainException extends \DomainException {}
