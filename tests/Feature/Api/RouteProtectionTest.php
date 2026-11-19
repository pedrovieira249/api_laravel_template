<?php

declare(strict_types=1);

use App\Enums\PermissionName;
use App\Enums\ServiceAbility;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

/*
 * Rede de segurança do mapa de rotas (routes/api.php). Uma rota nova esquecida
 * fora de um grupo protegido, ou uma permissão digitada errado, quebra aqui e
 * não em produção.
 */

/**
 * Rotas que são públicas de propósito. Para abrir uma rota nova sem login,
 * acrescente o nome dela aqui: é uma decisão, não um esquecimento.
 */
const PUBLIC_ROUTES = [
    'api.v1.auth.login',
    'api.v1.auth.refresh',
    'api.v1.internal.auth.token',
];

/**
 * @return list<RoutingRoute>
 */
function apiRoutes(): array
{
    return array_values(array_filter(
        Route::getRoutes()->getRoutes(),
        fn (RoutingRoute $route): bool => str_starts_with($route->uri(), 'api/v1'),
    ));
}

/**
 * Os parâmetros de um middleware da rota: 'permission:a,b' -> ['a', 'b'].
 *
 * @return list<string>
 */
function middlewareParameters(RoutingRoute $route, string $alias): array
{
    $parameters = [];

    foreach ($route->gatherMiddleware() as $middleware) {
        if (is_string($middleware) && str_starts_with($middleware, $alias.':')) {
            $parameters = [...$parameters, ...explode(',', substr($middleware, strlen($alias) + 1))];
        }
    }

    return $parameters;
}

/*
 * Cada teste junta as violações em uma lista e exige que ela esteja vazia: a
 * mensagem de falha mostra todas de uma vez, e o teste continua válido mesmo
 * quando nenhuma rota usa a regra em questão.
 */

it('has routes to check', function (): void {
    expect(count(apiRoutes()))->toBeGreaterThan(15);
});

it('requires authentication on every route that is not explicitly public', function (): void {
    $unprotected = [];

    foreach (apiRoutes() as $route) {
        if (middlewareParameters($route, 'auth') === [] && ! in_array($route->getName(), PUBLIC_ROUTES, true)) {
            $unprotected[] = $route->getName() ?? $route->uri();
        }
    }

    expect($unprotected)->toBe([]);
});

it('keeps the public routes really public', function (): void {
    $names = array_map(fn (RoutingRoute $route): ?string => $route->getName(), apiRoutes());
    $guarded = [];

    foreach (apiRoutes() as $route) {
        if (in_array($route->getName(), PUBLIC_ROUTES, true) && middlewareParameters($route, 'auth') !== []) {
            $guarded[] = $route->getName();
        }
    }

    expect($guarded)->toBe([])
        ->and(array_diff(PUBLIC_ROUTES, $names))->toBe([], 'PUBLIC_ROUTES lists a route that no longer exists');
});

it('names every route', function (): void {
    $unnamed = [];

    foreach (apiRoutes() as $route) {
        if ($route->getName() === null) {
            $unnamed[] = $route->uri();
        }
    }

    expect($unnamed)->toBe([]);
});

it('only references permissions that exist in the catalog', function (): void {
    $unknown = [];

    foreach (apiRoutes() as $route) {
        foreach (middlewareParameters($route, 'permission') as $permission) {
            if (PermissionName::tryFrom($permission) === null) {
                $unknown[] = "{$route->getName()}: {$permission}";
            }
        }
    }

    expect($unknown)->toBe([]);
});

it('only references service abilities that exist in the catalog', function (): void {
    $unknown = [];

    foreach (apiRoutes() as $route) {
        $abilities = [...middlewareParameters($route, 'ability'), ...middlewareParameters($route, 'abilities')];

        foreach ($abilities as $ability) {
            if (ServiceAbility::tryFrom($ability) === null) {
                $unknown[] = "{$route->getName()}: {$ability}";
            }
        }
    }

    expect($unknown)->toBe([]);
});

it('never mixes the two audiences in one route', function (): void {
    $mixed = [];

    foreach (apiRoutes() as $route) {
        $guards = middlewareParameters($route, 'auth');
        $userRules = [...middlewareParameters($route, 'role'), ...middlewareParameters($route, 'permission')];
        $serviceRules = [...middlewareParameters($route, 'ability'), ...middlewareParameters($route, 'abilities')];

        // Perfis e permissões só fazem sentido com o guard de usuários...
        if ($userRules !== [] && $guards !== ['api']) {
            $mixed[] = "{$route->getName()}: role/permission without auth:api";
        }

        // ...e abilities, com o guard das APIs internas.
        if ($serviceRules !== [] && $guards !== ['internal']) {
            $mixed[] = "{$route->getName()}: ability without auth:internal";
        }
    }

    expect($mixed)->toBe([]);
});

it('keeps internal routes under /internal and user routes out of it', function (): void {
    $misplaced = [];

    foreach (apiRoutes() as $route) {
        $isInternalUri = str_starts_with($route->uri(), 'api/v1/internal');
        $guards = middlewareParameters($route, 'auth');

        if ($guards === ['internal'] && ! $isInternalUri) {
            $misplaced[] = "{$route->getName()}: auth:internal outside /internal";
        }

        if ($guards === ['api'] && $isInternalUri) {
            $misplaced[] = "{$route->getName()}: auth:api under /internal";
        }
    }

    expect($misplaced)->toBe([]);
});

it('rate limits every route', function (): void {
    $unthrottled = [];

    foreach (apiRoutes() as $route) {
        if (middlewareParameters($route, 'throttle') === []) {
            $unthrottled[] = $route->getName() ?? $route->uri();
        }
    }

    expect($unthrottled)->toBe([]);
});
