<?php

declare(strict_types=1);

namespace Core\Shared\Application\Bus;

/**
 * Marcador de uma intenção de escrita ("PlaceOrder", "ArchiveProduct"). Commands
 * são DTOs imutáveis (`final readonly class`) e carregam só primitivos / VOs.
 *
 * @template-covariant TResult O valor que o handler retorna (void para a maioria dos commands).
 */
interface Command {}
