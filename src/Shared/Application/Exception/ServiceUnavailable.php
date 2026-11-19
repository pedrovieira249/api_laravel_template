<?php

declare(strict_types=1);

namespace Core\Shared\Application\Exception;

/**
 * Uma dependência externa atrás de um port (gateway de pagamento, API da
 * transportadora) não respondeu direito. Não é erro de negócio nem bug: a
 * camada HTTP mapeia para 503 e o trabalho em fila tenta de novo.
 */
abstract class ServiceUnavailable extends \RuntimeException {}
