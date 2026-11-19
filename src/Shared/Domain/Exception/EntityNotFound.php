<?php

declare(strict_types=1);

namespace Core\Shared\Domain\Exception;

/**
 * Um agregado ou entidade referenciado pelo chamador não existe.
 */
abstract class EntityNotFound extends DomainException {}
