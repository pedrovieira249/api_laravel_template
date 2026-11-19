<?php

declare(strict_types=1);

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnforceExactRateLimit;
use App\Http\Middleware\EnsureIdempotency;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\RequireJsonAccept;
use App\Http\Middleware\SetLocale;
use App\Http\Responses\ProblemDetailsRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use League\OAuth2\Server\Exception\OAuthServerException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // Listeners vivem ao lado dos seus adapters, não em app/Listeners: a descoberta
    // varre o diretório Infrastructure/Listener de cada contexto.
    ->withEvents(discover: glob(__DIR__.'/../src/*/Infrastructure/Listener') ?: [])
    ->withMiddleware(function (Middleware $middleware): void {
        // Global: cada requisição (API ou web) ganha um request id no Context e na resposta.
        $middleware->prepend(AssignRequestId::class);

        // O grupo "api" inteiro: só JSON e idioma negociado por Accept-Language.
        $middleware->api(prepend: [
            RequireJsonAccept::class,
            SetLocale::class,
        ]);

        // Aliases usados nos grupos de rota (routes/api.php).
        $middleware->alias([
            // Usuários (Passport, guard "api"): perfis e permissões do RBAC.
            'role' => EnsureUserHasRole::class,
            'permission' => EnsureUserHasPermission::class,
            // APIs internas (Sanctum, guard "internal"): abilities do token.
            // abilities:a,b exige todas; ability:a,b exige pelo menos uma.
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            // POSTs que não podem ser executados duas vezes (ADR 0010).
            'idempotent' => EnsureIdempotency::class,
            // Limite de requisições com teto exato, para quando o teto é requisito
            // (ADR 0011). O "throttle" do framework continua valendo para os demais.
            'throttle.exact' => EnforceExactRateLimit::class,
        ]);

        // Mesma posição do throttle do framework: depois da autenticação, porque a
        // chave do limiter costuma ser o usuário ou o cliente de serviço autenticado.
        $middleware->appendToPriorityList(ThrottleRequests::class, EnforceExactRateLimit::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // O guard do Passport reporta um erro OAuth a cada Bearer token recusado. Com
        // access tokens de 5 minutos, token expirado é rotina (o cliente renova e segue),
        // não um incidente: fora do log.
        $exceptions->dontReport(OAuthServerException::class);

        $wantsJson = fn (Request $request): bool => $request->is('api/*') || $request->expectsJson();

        $exceptions->shouldRenderJsonWhen($wantsJson);

        // Um renderer para qualquer Throwable: devolver null entrega a exceção
        // de volta ao tratamento padrão (HTML) do Laravel para requisições fora da API.
        $exceptions->render(function (Throwable $exception, Request $request) use ($wantsJson): ?JsonResponse {
            if (! $wantsJson($request)) {
                return null;
            }

            return new ProblemDetailsRenderer(
                debug: (bool) config('app.debug'),
                typeBase: config()->string('api.problem_type_base'),
            )->render($exception, $request);
        });
    })->create();
