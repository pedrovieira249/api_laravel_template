<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\CreateRoleController;
use App\Http\Controllers\Api\V1\Admin\CreateServiceClientController;
use App\Http\Controllers\Api\V1\Admin\DeleteRoleController;
use App\Http\Controllers\Api\V1\Admin\ListPermissionsController;
use App\Http\Controllers\Api\V1\Admin\ListRolesController;
use App\Http\Controllers\Api\V1\Admin\ListServiceClientsController;
use App\Http\Controllers\Api\V1\Admin\ListUsersController;
use App\Http\Controllers\Api\V1\Admin\RevokeServiceClientController;
use App\Http\Controllers\Api\V1\Admin\ShowRoleController;
use App\Http\Controllers\Api\V1\Admin\ShowUserController;
use App\Http\Controllers\Api\V1\Admin\SyncUserRolesController;
use App\Http\Controllers\Api\V1\Admin\UpdateRoleController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\RefreshTokenController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Internal\IssueServiceTokenController;
use App\Http\Controllers\Api\V1\Internal\RevokeServiceTokenController;
use App\Http\Controllers\Api\V1\Internal\ServiceIdentityController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mapa da API (/api/v1)
|--------------------------------------------------------------------------
|
| Este arquivo é o lugar onde se lê QUEM pode chamar O QUÊ. Cada rota é um
| controller single-action; autenticação e autorização são declaradas aqui,
| nos grupos, com estas peças (docs/guide/02 e 03):
|
|   auth:api        usuário externo, Bearer JWT do Passport (5 min + refresh)
|   auth:internal   API interna, token do Sanctum (1 h)
|   role:<perfil>   o usuário tem um destes perfis            (role:admin,manager)
|   permission:<p>  algum perfil do usuário concede a permissão (permission:users.read)
|   ability:<a>     o token do serviço carrega a ability       (ability:reports.read)
|   throttle:<n>    limite de requisições do limiter <n>; throttle.exact:<n> quando o teto é requisito
|
| Regra prática: grupo de rotas = um público + uma regra de acesso.
|
*/
Route::prefix('v1')->name('api.v1.')->group(function (): void {
    /*
     * ---------------------------------------------------------------------
     * Usuários externos (Passport). throttle:api limita por usuário ou IP.
     * ---------------------------------------------------------------------
     */
    Route::middleware('throttle:api')->group(function (): void {
        // Autenticação: as duas primeiras são públicas (é por elas que se obtém o token).
        // O registro NÃO está aqui: ele é fechado, no grupo interno mais abaixo (ADR 0011).
        Route::prefix('auth')->name('auth.')->group(function (): void {
            Route::post('login', LoginController::class)->middleware('throttle:login')->name('login');
            Route::post('refresh', RefreshTokenController::class)->middleware('throttle:token')->name('refresh');

            Route::middleware('auth:api')->group(function (): void {
                Route::post('logout', LogoutController::class)->name('logout');
                Route::get('me', MeController::class)->name('me');
            });
        });

        // Administração: o grupo inteiro exige login; cada subgrupo, uma permissão ou perfil.
        Route::prefix('admin')->name('admin.')->middleware('auth:api')->group(function (): void {
            // Por PERMISSÃO: qualquer perfil que receba users.read enxerga os usuários,
            // sem precisar ser admin (ex.: um perfil "support").
            Route::middleware('permission:users.read')->group(function (): void {
                Route::get('users', ListUsersController::class)->name('users.index');
                Route::get('users/{user}', ShowUserController::class)->name('users.show');
            });

            Route::put('users/{user}/roles', SyncUserRolesController::class)
                ->middleware('permission:users.manage')
                ->name('users.roles.sync');

            Route::middleware('permission:roles.read')->group(function (): void {
                Route::get('roles', ListRolesController::class)->name('roles.index');
                Route::get('roles/{role}', ShowRoleController::class)->name('roles.show');
                Route::get('permissions', ListPermissionsController::class)->name('permissions.index');
            });

            Route::middleware('permission:roles.manage')->group(function (): void {
                Route::post('roles', CreateRoleController::class)->name('roles.store');
                Route::put('roles/{role}', UpdateRoleController::class)->name('roles.update');
                Route::delete('roles/{role}', DeleteRoleController::class)->name('roles.destroy');
            });

            // Por PERFIL: credenciais de outros sistemas são assunto só de administrador,
            // não uma permissão que se delega.
            Route::middleware('role:admin')->prefix('service-clients')->name('service-clients.')->group(function (): void {
                Route::get('/', ListServiceClientsController::class)->name('index');
                Route::post('/', CreateServiceClientController::class)->name('store');
                Route::delete('{serviceClient}', RevokeServiceClientController::class)->name('revoke');
            });
        });

        // Os módulos do projeto entram aqui: um grupo por recurso, com auth:api e a
        // permissão (ou o perfil) que cada conjunto de rotas exige.
    });

    /*
     * ---------------------------------------------------------------------
     * APIs internas (Sanctum), sob /api/v1/internal. Outro público, outro
     * guard, outro limite de requisições.
     * ---------------------------------------------------------------------
     */
    Route::prefix('internal')->name('internal.')->group(function (): void {
        // Pública: client_id + client_secret por um token de 1 hora.
        Route::post('auth/token', IssueServiceTokenController::class)->middleware('throttle:token')->name('auth.token');

        Route::middleware(['auth:internal', 'throttle:internal'])->group(function (): void {
            Route::delete('auth/token', RevokeServiceTokenController::class)->name('auth.token.revoke');
            Route::get('auth/me', ServiceIdentityController::class)->name('auth.me');

            // Registro de usuários: fechado ao público (ADR 0011). Só um sistema da casa cujo
            // token carregue a ability users.register cria contas, e no máximo 5 por segundo
            // (limiter "register": config/api.php + AppServiceProvider). throttle.exact em
            // vez de throttle: aqui o teto é requisito e não pode ceder sob requisições
            // simultâneas nem na virada do segundo.
            Route::post('auth/register', RegisterController::class)
                ->middleware(['ability:users.register', 'throttle.exact:register'])
                ->name('auth.register');

            // As rotas de negócio para outros sistemas entram aqui, cada uma com a
            // ability que exige: ->middleware('ability:<ability>').
        });
    });
});
