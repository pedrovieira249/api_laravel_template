<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;

/**
 * A API só fala JSON. Um cliente que o recusa explicitamente (Accept:
 * text/html) recebe 406; um curinga ou a ausência do header é aceita para que o curl funcione.
 */
final class RequireJsonAccept
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->accepts(['application/json', 'application/problem+json'])) {
            throw new NotAcceptableHttpException('This API only produces application/json.');
        }

        // Curinga ou sem header: deixa a intenção explícita para que toda decisão
        // posterior ($request->expectsJson(), o middleware de auth, o handler de
        // exceções) veja um cliente JSON e nunca tente redirecionar para uma página de login.
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
