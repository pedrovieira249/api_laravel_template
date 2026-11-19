<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Negociação de conteúdo para idioma: "Accept-Language: pt-BR" troca as
 * mensagens de validação e auth para lang/pt_BR. Idiomas desconhecidos caem
 * no config('app.locale').
 */
final class SetLocale
{
    private const array SUPPORTED = ['en', 'pt_BR'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->getPreferredLanguage(self::SUPPORTED);

        App::setLocale($locale ?? config()->string('app.locale'));

        return $next($request);
    }
}
