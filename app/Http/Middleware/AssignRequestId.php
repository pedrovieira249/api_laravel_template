<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Um id por requisição, de ponta a ponta: reaproveitado do X-Request-Id quando um
 * gateway já o atribuiu, devolvido na resposta e guardado no Context para que
 * toda linha de log desta requisição (e dos jobs que ela despacha, o Context é
 * desidratado no payload do job) o carregue.
 */
final class AssignRequestId
{
    public const string HEADER = 'X-Request-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $given = (string) $request->header(self::HEADER, '');
        $requestId = preg_match('/^[A-Za-z0-9._-]{8,128}$/', $given) === 1 ? $given : (string) Str::uuid7();

        Context::add('request_id', $requestId);

        $response = $next($request);
        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }
}
