<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Chaves de idempotência para POSTs que criam coisas (ADR 0010).
 *
 * Um cliente que sofre timeout e retenta não pode criar o mesmo recurso duas
 * vezes. Aplique com o alias "idempotent" nas rotas POST em que isso importa
 * (pedidos, pagamentos, envios): ->middleware('idempotent'). O cliente envia
 * um Idempotency-Key único por série de tentativas; a primeira
 * resposta é guardada sob (usuário, chave) e repetida em toda retentativa, com
 * o header Idempotency-Replayed: true. Enquanto a primeira requisição ainda
 * está rodando, uma retentativa concorrente recebe 409 (lock de cache) em vez de uma duplicata.
 */
final readonly class EnsureIdempotency
{
    public const string HEADER = 'Idempotency-Key';

    private const int TTL_SECONDS = 86_400;

    private const int LOCK_SECONDS = 30;

    public function __construct(private CacheManager $cache) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header(self::HEADER);

        if (! is_string($key) || mb_trim($key) === '' || mb_strlen($key) > 128) {
            throw new BadRequestHttpException(sprintf('The %s header is required (1-128 characters).', self::HEADER));
        }

        $store = $this->cache->store();
        $backend = $store->getStore();

        // Locks atômicos precisam de um store que os suporte (redis, database, array).
        if (! $backend instanceof LockProvider) {
            throw new \LogicException('Idempotency needs a cache store with atomic locks.');
        }
        $owner = $request->user()?->getAuthIdentifier();
        $cacheKey = sprintf('idempotency:%s:%s', is_scalar($owner) ? (string) $owner : 'guest', hash('sha256', $key));

        $stored = $store->get($cacheKey);

        if (is_array($stored)) {
            /** @var array{status: int, body: string|false, headers: array<string, string|null>} $stored */
            // Um cliente que ainda retenta é um cliente que pode retentar de novo: mantenha
            // a resposta guardada viva (Cache::touch só estende o TTL).
            $store->touch($cacheKey, self::TTL_SECONDS);

            return $this->replay($stored);
        }

        $lock = $backend->lock($cacheKey.':lock', self::LOCK_SECONDS);

        if (! $lock->get()) {
            throw new ConflictHttpException('A request with the same Idempotency-Key is still being processed.');
        }

        try {
            $response = $next($request);

            if ($response instanceof JsonResponse && $response->isSuccessful()) {
                $store->put($cacheKey, [
                    'status' => $response->getStatusCode(),
                    'body' => $response->getContent(),
                    'headers' => ['Location' => $response->headers->get('Location')],
                ], self::TTL_SECONDS);
            }

            return $response;
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array{status: int, body: string|false, headers: array<string, string|null>}  $stored
     */
    private function replay(array $stored): JsonResponse
    {
        $response = JsonResponse::fromJsonString($stored['body'] === false ? '{}' : $stored['body'], $stored['status']);
        $response->headers->set('Idempotency-Replayed', 'true');

        foreach (array_filter($stored['headers']) as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }
}
