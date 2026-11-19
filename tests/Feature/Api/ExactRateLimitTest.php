<?php

declare(strict_types=1);

use App\Http\Middleware\EnforceExactRateLimit;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Http\Request;
use Illuminate\Process\Pool;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
 * O middleware "throttle.exact" em rotas descartáveis (ADR 0011).
 *
 * A contagem acontece em um script Lua dentro do Redis, com o relógio do Redis:
 * estes testes usam o Redis de verdade e tempo de verdade (freezeTime() não tem
 * efeito aqui). Cada teste usa uma chave própria, e as chaves expiram sozinhas em
 * um segundo, então nada vaza de um teste para o outro.
 */
beforeEach(function (): void {
    RateLimiter::for('probe', fn (Request $request): Limit => Limit::perSecond(3)->by($request->header('X-Client')));

    Route::middleware(['api', 'throttle.exact:probe'])->get('/api/exact/probe', fn (): array => ['ok' => true]);

    $this->client = 'client-'.Str::uuid();
    $this->probe = fn (?string $client = null) => $this->getJson('/api/exact/probe', ['X-Client' => $client ?? $this->client]);
});

it('lets exactly the ceiling through and reports what is left', function (): void {
    ($this->probe)()->assertOk()->assertHeader('X-RateLimit-Limit', '3')->assertHeader('X-RateLimit-Remaining', '2');
    ($this->probe)()->assertOk()->assertHeader('X-RateLimit-Remaining', '1');
    ($this->probe)()->assertOk()->assertHeader('X-RateLimit-Remaining', '0');

    ($this->probe)()
        ->assertStatus(429)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertHeader('Retry-After', '1')
        ->assertHeader('X-RateLimit-Remaining', '0')
        ->assertJsonPath('title', 'Too many requests');
});

it('keeps rejecting however many requests arrive, without extending the wait', function (): void {
    foreach (range(1, 3) as $n) {
        ($this->probe)()->assertOk();
    }

    foreach (range(1, 20) as $n) {
        ($this->probe)()->assertStatus(429);
    }

    // As recusadas não entram na conta: um segundo depois das aceitas, tudo reabre.
    usleep(1_050_000);

    ($this->probe)()->assertOk()->assertHeader('X-RateLimit-Remaining', '2');
});

it('slides: a slot opens only when an accepted request is older than the window', function (): void {
    ($this->probe)()->assertOk();          // A, em t = 0

    usleep(600_000);
    ($this->probe)()->assertOk();          // B, em t = 0,6 s
    ($this->probe)()->assertOk();          // C
    ($this->probe)()->assertStatus(429);   // três aceitas no último segundo

    usleep(450_000);                       // t = 1,05 s: só A saiu da janela
    ($this->probe)()->assertOk();          // uma vaga...
    ($this->probe)()->assertStatus(429);   // ...e só uma: B e C ainda contam
});

it('gives each key its own ceiling', function (): void {
    $other = 'client-'.Str::uuid();

    foreach (range(1, 3) as $n) {
        ($this->probe)()->assertOk();
    }

    ($this->probe)()->assertStatus(429);
    ($this->probe)($other)->assertOk();
});

it('lets exactly the ceiling through under real concurrency', function (): void {
    // 25 processos PHP disputam, ao mesmo tempo, 3 vagas de uma janela de 5 segundos.
    // Cada um roda o mesmo script do middleware direto no Redis e imprime 1 (aceita) ou 0.
    $redis = config('database.redis.default');
    $key = 'exact-rate-limit:concurrency-test:'.Str::uuid();
    $worker = <<<'PHP'
        [$host, $port, $password, $key, $script] = array_slice($argv, 1);
        $redis = new Redis;
        $redis->connect($host, (int) $port);
        if ($password !== '') { $redis->auth($password); }
        echo (int) $redis->eval($script, [$key, 5000, 3, bin2hex(random_bytes(8))], 1)[0];
        PHP;

    $results = Process::pool(function (Pool $pool) use ($redis, $key, $worker): void {
        foreach (range(1, 25) as $n) {
            $pool->command([PHP_BINARY, '-r', $worker, $redis['host'], (string) $redis['port'], (string) $redis['password'], $key, EnforceExactRateLimit::SCRIPT]);
        }
    })->start()->wait();

    $outputs = collect($results)->map(fn (ProcessResult $result): string => trim($result->output()));

    expect($outputs->filter(fn (string $output): bool => $output === '1'))->toHaveCount(3)
        ->and($outputs->filter(fn (string $output): bool => $output === '0'))->toHaveCount(22);
});

it('does not share its counter with the framework throttle on the same limiter', function (): void {
    Route::middleware(['api', 'throttle:probe'])->get('/api/exact/default', fn (): array => ['ok' => true]);
    $this->freezeTime();

    foreach (range(1, 3) as $n) {
        $this->getJson('/api/exact/default', ['X-Client' => $this->client])->assertOk();
    }

    $this->getJson('/api/exact/default', ['X-Client' => $this->client])->assertStatus(429);
    ($this->probe)()->assertOk();
});

it('fails loudly for a limiter that does not exist or is not a single limit', function (): void {
    RateLimiter::for('several', fn (): array => [Limit::perSecond(1), Limit::perMinute(10)]);
    Route::middleware(['api', 'throttle.exact:ghost'])->get('/api/exact/ghost', fn (): array => ['ok' => true]);
    Route::middleware(['api', 'throttle.exact:several'])->get('/api/exact/several', fn (): array => ['ok' => true]);

    $this->withoutExceptionHandling();

    expect(fn () => $this->getJson('/api/exact/ghost'))->toThrow(LogicException::class, 'Rate limiter [ghost] is not defined.')
        ->and(fn () => $this->getJson('/api/exact/several'))->toThrow(LogicException::class, 'must return a single Limit');
});

it('refuses to run on a redis client it was not written for', function (): void {
    $factory = Mockery::mock(RedisFactory::class);
    $factory->shouldReceive('connection')->andReturn(Mockery::mock(Connection::class));
    $this->app->instance(RedisFactory::class, $factory);

    $this->withoutExceptionHandling();

    expect(fn () => ($this->probe)())->toThrow(LogicException::class, 'phpredis');
});

it('runs after authentication, so the limiter can key on the authenticated principal', function (): void {
    $priority = $this->app->make(Kernel::class)->getMiddlewarePriority();

    expect(array_search(EnforceExactRateLimit::class, $priority, true))
        ->toBeGreaterThan(array_search(AuthenticatesRequests::class, $priority, true));
});
