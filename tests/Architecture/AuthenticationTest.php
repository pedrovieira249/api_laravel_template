<?php

declare(strict_types=1);
use App\Auth\Users\UserTokenIssuer;
use App\Console\Commands\AuthInstallCommand;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\EnsureUserHasRole;
use App\Models\Role;
use App\Models\ServiceClient;
use App\Models\User;
use App\Providers\AppServiceProvider;

/*
 * Dois mecanismos de autenticação, cada um no seu lado (ADR 0008):
 *
 *   Passport -> usuários externos (App\Models\User, guard "api")
 *   Sanctum  -> APIs internas     (App\Models\ServiceClient, guard "internal")
 *
 * Os dois pacotes têm um trait HasApiTokens com os mesmos métodos. Mantê-los
 * em lados separados do código é o que impede um token de um mundo de valer no outro.
 */

arch('passport is confined to the user side')
    ->expect('Laravel\Passport')
    ->toOnlyBeUsedIn([
        'App\Auth\Users',
        User::class,
        AppServiceProvider::class,
        AuthInstallCommand::class,
    ]);

arch('sanctum is confined to the internal side')
    ->expect('Laravel\Sanctum')
    ->toOnlyBeUsedIn([
        'App\Auth\Services',
        ServiceClient::class,
        'App\Http\Controllers\Api\V1\Internal',
    ]);

arch('the oauth server is only reached through the token issuer')
    ->expect('League\OAuth2\Server')
    ->toOnlyBeUsedIn(UserTokenIssuer::class);

arch('the user side never touches service clients')
    ->expect('App\Auth\Users')
    ->not->toUse([ServiceClient::class, 'App\Auth\Services', 'Laravel\Sanctum']);

arch('the internal side never touches users or roles')
    ->expect('App\Auth\Services')
    ->not->toUse([User::class, Role::class, 'App\Auth\Users', 'Laravel\Passport']);

arch('access control middlewares do not depend on a token mechanism')
    ->expect([EnsureUserHasRole::class, EnsureUserHasPermission::class])
    ->not->toUse(['Laravel\Passport', 'Laravel\Sanctum']);
