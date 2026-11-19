<?php

declare(strict_types=1);

namespace Core\Shared\Application\Bus;

/**
 * Marcador de uma intenção de leitura. Queries nunca tocam em agregados: os
 * handlers leem direto por um port de read model e devolvem DTOs (CQRS-lite).
 *
 * @template-covariant TResult
 */
interface Query {}
