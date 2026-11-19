<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Redis\Factory as Redis;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Limite de requisições com teto exato (alias "throttle.exact").
 *
 *   Route::post(...)->middleware('throttle.exact:register');
 *
 * Usa os mesmos limiters nomeados do `throttle` do framework (RateLimiter::for no
 * AppServiceProvider), mas com outra garantia: em NENHUM intervalo do tamanho da
 * janela passam mais que `maxAttempts` requisições. Com "5 por segundo", nunca há
 * 6 aceitas dentro de 1 segundo, cheguem elas juntas ou na virada do segundo.
 *
 * O `throttle` do framework não dá essa garantia por dois motivos (ADR 0011):
 *   - lê o contador e só depois o incrementa, então requisições simultâneas
 *     passam juntas;
 *   - usa janela fixa, então uma rajada na virada da janela passa dos dois lados.
 *
 * Aqui a decisão inteira acontece em um script Lua dentro do Redis, que roda de
 * forma atômica: ele descarta as requisições aceitas há mais de uma janela, conta
 * as que sobraram e só então aceita (e registra) a atual. É uma janela deslizante.
 * O relógio é o do próprio Redis, um só para todos os servidores de aplicação.
 *
 * Use quando o teto é um requisito. Para limites de cortesia, o `throttle` basta.
 * Declare-o depois do `auth:*`: a chave do limiter costuma ser o principal autenticado.
 * Exige Redis 7+ com o cliente phpredis (o padrão do template).
 */
final readonly class EnforceExactRateLimit
{
    /**
     * KEYS[1] = conjunto ordenado com o instante (ms) de cada requisição aceita.
     * ARGV    = tamanho da janela (ms), teto, identificador único desta requisição.
     * Retorno = { aceita (1/0), vagas restantes, ms até abrir uma vaga }.
     */
    public const string SCRIPT = <<<'LUA'
        local time = redis.call('TIME')
        local now = time[1] * 1000 + math.floor(time[2] / 1000)
        local window = tonumber(ARGV[1])
        local max = tonumber(ARGV[2])

        redis.call('ZREMRANGEBYSCORE', KEYS[1], '-inf', now - window)
        local accepted = redis.call('ZCARD', KEYS[1])

        if accepted >= max then
            local oldest = redis.call('ZRANGE', KEYS[1], 0, 0, 'WITHSCORES')
            return {0, 0, tonumber(oldest[2]) + window - now}
        end

        redis.call('ZADD', KEYS[1], now, ARGV[3])
        redis.call('PEXPIRE', KEYS[1], window)

        return {1, max - accepted - 1, 0}
        LUA;

    public function __construct(
        private RateLimiter $limiters,
        private Redis $redis,
    ) {}

    public function handle(Request $request, Closure $next, string $limiterName): Response
    {
        $limit = $this->resolveLimit($request, $limiterName);

        [$allowed, $remaining, $retryAfterMs] = $this->acquire(
            key: $this->key($limiterName, $limit),
            windowMs: $limit->decaySeconds * 1000,
            maxAttempts: $limit->maxAttempts,
        );

        if (! $allowed) {
            throw new ThrottleRequestsException('Too Many Attempts.', headers: [
                'Retry-After' => max(1, (int) ceil($retryAfterMs / 1000)),
                'X-RateLimit-Limit' => $limit->maxAttempts,
                'X-RateLimit-Remaining' => 0,
            ]);
        }

        $response = $next($request);

        $response->headers->set('X-RateLimit-Limit', (string) $limit->maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', (string) $remaining);

        return $response;
    }

    /**
     * @return array{0: bool, 1: int, 2: int}
     */
    private function acquire(string $key, int $windowMs, int $maxAttempts): array
    {
        $connection = $this->redis->connection();

        if (! $connection instanceof PhpRedisConnection) {
            throw new \LogicException('throttle.exact needs the phpredis client (REDIS_CLIENT=phpredis).');
        }

        // O identificador só precisa ser único: duas requisições aceitas no mesmo
        // milissegundo têm de ocupar duas posições diferentes no conjunto.
        $result = $connection->eval(self::SCRIPT, 1, $key, $windowMs, $maxAttempts, bin2hex(random_bytes(8)));

        $values = is_array($result) ? array_values(array_filter($result, is_numeric(...))) : [];

        if (count($values) !== 3) {
            throw new \LogicException('The rate limit script returned an unexpected value.');
        }

        return [(int) $values[0] === 1, (int) $values[1], (int) $values[2]];
    }

    private function key(string $limiterName, Limit $limit): string
    {
        // O hash só encurta e normaliza a chave do limiter (um id, um IP, um e-mail).
        $subject = is_scalar($limit->key) ? (string) $limit->key : '';

        return sprintf('exact-rate-limit:%s:%s', $limiterName, hash('sha256', $subject));
    }

    private function resolveLimit(Request $request, string $limiterName): Limit
    {
        $limiter = $this->limiters->limiter($limiterName)
            ?? throw new \LogicException("Rate limiter [{$limiterName}] is not defined.");

        $limit = $limiter($request);

        return $limit instanceof Limit
            ? $limit
            : throw new \LogicException("Rate limiter [{$limiterName}] must return a single Limit to be used with throttle.exact.");
    }
}
