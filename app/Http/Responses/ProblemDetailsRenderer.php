<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Core\Shared\Application\Exception\ServiceUnavailable;
use Core\Shared\Domain\Exception\BusinessRuleViolation;
use Core\Shared\Domain\Exception\DomainException;
use Core\Shared\Domain\Exception\EntityNotFound;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Renderiza cada erro da API como RFC 9457 "Problem Details for HTTP APIs":
 *
 *   { "type": "...", "title": "...", "status": 409, "detail": "...", "errors": {...} }
 *
 * Exceções de domínio são mapeadas pela base abstrata (ADR 0004); exceções do
 * framework pela classe. Controllers nunca capturam nada.
 */
final readonly class ProblemDetailsRenderer
{
    private const string CONTENT_TYPE = 'application/problem+json';

    private const string APP_EXCEPTIONS = 'App\\Exceptions\\';

    /**
     * @param  string  $typeBase  base estável dos identificadores "type" (config api.problem_type_base)
     */
    public function __construct(
        private bool $debug,
        private string $typeBase,
    ) {}

    public function render(Throwable $exception, Request $request): JsonResponse
    {
        [$status, $title, $detail, $extra] = $this->describe($exception);

        $body = [
            'type' => $this->typeFor($exception, $status),
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'instance' => $request->getRequestUri(),
            ...$extra,
        ];

        // Exceções HTTP carregam headers que importam para os clientes (Retry-After, Allow).
        $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];

        return new JsonResponse($body, $status, [...$headers, 'Content-Type' => self::CONTENT_TYPE]);
    }

    /**
     * @return array{0: int, 1: string, 2: string, 3: array<string, mixed>}
     */
    private function describe(Throwable $exception): array
    {
        return match (true) {
            $exception instanceof ValidationException => [
                422, 'Validation failed', 'One or more fields are invalid.', ['errors' => $exception->errors()],
            ],
            $exception instanceof EntityNotFound => [404, 'Not found', $exception->getMessage(), []],
            // Route model binding: o framework embrulha a ModelNotFoundException em uma
            // NotFoundHttpException antes de chegar aqui. A mensagem original cita a
            // classe do model, então o cliente recebe um texto neutro.
            $exception instanceof ModelNotFoundException,
            $exception instanceof NotFoundHttpException && $exception->getPrevious() instanceof ModelNotFoundException => [
                404, 'Not found', 'The requested resource was not found.', [],
            ],
            $exception instanceof BusinessRuleViolation => [409, $this->titleFor($exception), $exception->getMessage(), []],
            $exception instanceof DomainException => [422, $this->titleFor($exception), $exception->getMessage(), []],
            $exception instanceof ServiceUnavailable => [503, 'Service unavailable', 'A dependency did not answer. Try again in a moment.', []],
            $exception instanceof AuthenticationException => [401, 'Unauthenticated', $this->authenticationDetail($exception), []],
            $exception instanceof AuthorizationException,
            $exception instanceof AccessDeniedHttpException => [403, 'Forbidden', $exception->getMessage(), []],
            $exception instanceof ThrottleRequestsException => [429, 'Too many requests', 'Slow down and retry later.', []],
            $exception instanceof HttpExceptionInterface => [
                $exception->getStatusCode(),
                $this->titleFor($exception),
                $exception->getMessage() !== '' ? $exception->getMessage() : 'The request could not be processed.',
                [],
            ],
            default => [
                500,
                'Internal server error',
                $this->debug ? $exception->getMessage() : 'An unexpected error occurred.',
                $this->debug ? ['exception' => $exception::class] : [],
            ],
        };
    }

    /**
     * O guard lança a mensagem padrão "Unauthenticated."; as actions de login
     * lançam uma mensagem própria (refresh token inválido, credenciais de
     * serviço erradas), que vale a pena repassar ao cliente.
     */
    private function authenticationDetail(AuthenticationException $exception): string
    {
        return in_array($exception->getMessage(), ['', 'Unauthenticated.'], true)
            ? 'Authentication is required.'
            : $exception->getMessage();
    }

    /**
     * "RoleIsAssignedToUsers" -> "Role is assigned to users".
     */
    private function titleFor(Throwable $exception): string
    {
        return Str::of(class_basename($exception))
            ->replaceMatches('/(Exception|HttpException)$/', '')
            ->headline()
            ->ucfirst()
            ->lower()
            ->ucfirst()
            ->toString();
    }

    /**
     * Um identificador estável e documentável por tipo de erro. "about:blank" é o que
     * a RFC 9457 prescreve quando o código de status sozinho já diz tudo.
     */
    private function typeFor(Throwable $exception, int $status): string
    {
        // Erros com nome próprio: os do domínio (Core\*) e os da aplicação (App\Exceptions).
        if ($exception instanceof DomainException || str_starts_with($exception::class, self::APP_EXCEPTIONS)) {
            return $this->typeBase.'/'.Str::kebab(class_basename($exception));
        }

        return $status === 422 ? $this->typeBase.'/validation' : 'about:blank';
    }
}
