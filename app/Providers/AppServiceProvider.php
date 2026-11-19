<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\PermissionName;
use App\Models\User;
use Carbon\CarbonInterval;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // As rotas /oauth/* do Passport ficam desligadas: tokens de usuário só saem por
        // /api/v1/auth/login e /auth/refresh (ADR 0008). Precisa ser no register(): o
        // provider do Passport registra as rotas no boot() dele, que roda antes do nosso.
        Passport::ignoreRoutes();

        // Telescope é um pacote só de dev: não é auto-descoberto (ver composer.json
        // "dont-discover"), então é registrado aqui, e somente em local.
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    public function boot(): void
    {
        $this->configureModels();
        $this->configureRateLimiting();
        $this->configureUserTokens();
        $this->configureAuthorization();
        $this->configurePasswords();
        $this->configureApiDocs();
    }

    /**
     * Modo estrito fora de produção: lazy loading, atributos descartados em
     * silêncio e leituras de atributos inexistentes lançam exceção em vez de esconder bugs.
     */
    private function configureModels(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
    }

    /**
     * Limiters nomeados, aplicados nas rotas com throttle:<nome> (routes/api.php).
     * Os números vêm de config/api.php.
     *
     * Para limitar uma rota nova: um número em config/api.php, um RateLimiter::for()
     * aqui (com a janela e a chave: quem divide o mesmo teto) e throttle:<nome> na rota.
     * Quando o teto é requisito, use throttle.exact:<nome>: o throttle do framework pode
     * deixar passar requisições a mais se elas chegarem ao mesmo tempo ou na virada da
     * janela (ADR 0011).
     */
    private function configureRateLimiting(): void
    {
        $perMinute = fn (string $limiter): int => config()->integer("api.rate_limits.{$limiter}");

        // Rotas de usuário: a chave é o usuário autenticado (o middleware de auth roda
        // antes do throttle) ou, sem login, o IP.
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute($perMinute('api'))->by(
            $this->principalKey($request),
        ));

        // Rotas internas: a chave é o cliente de serviço dono do token.
        RateLimiter::for('internal', fn (Request $request): Limit => Limit::perMinute($perMinute('internal'))->by(
            $this->principalKey($request),
        ));

        // Proteção contra força bruta de senha: tentativas por minuto por e-mail + IP.
        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute($perMinute('login'))->by(
            $request->string('email')->lower()->toString().'|'.$request->ip(),
        ));

        // Renovação de token de usuário e emissão de token de serviço.
        RateLimiter::for('token', fn (Request $request): Limit => Limit::perMinute($perMinute('token'))->by(
            $request->string('client_id')->toString().'|'.$request->ip(),
        ));

        // Registro de usuários: o único limiter por SEGUNDO, aplicado com throttle.exact.
        // A chave é o cliente de serviço: cada sistema tem o próprio teto.
        RateLimiter::for('register', fn (Request $request): Limit => Limit::perSecond(config()->integer('api.rate_limits.register'))->by(
            $this->principalKey($request),
        ));
    }

    /**
     * Tokens de usuário (Passport). Os prazos vêm de config/identity.php:
     * access token de 5 minutos, renovado por um refresh token de vida mais longa.
     *
     * O password grant é o que permite o endpoint /auth/login trocar e-mail e
     * senha por tokens. Ele só é usado por dentro, pelo UserTokenIssuer.
     */
    private function configureUserTokens(): void
    {
        Passport::enablePasswordGrant();
        Passport::tokensExpireIn(CarbonInterval::minutes(config()->integer('identity.users.access_token_ttl')));
        Passport::refreshTokensExpireIn(CarbonInterval::minutes(config()->integer('identity.users.refresh_token_ttl')));
    }

    /**
     * Liga o RBAC ao Gate do Laravel: toda permissão do catálogo vira uma
     * ability. Assim, além do middleware `permission:`, funcionam as formas
     * nativas: `can:users.read`, #[Authorize('users.read')] e $user->can('users.read').
     */
    private function configureAuthorization(): void
    {
        // Gate::before roda antes de qualquer gate ou policy. Devolver null deixa a
        // decisão seguir o fluxo normal (abilities que não são permissões do catálogo).
        // O parâmetro é Authenticatable, não User: um ServiceClient também passa por aqui.
        Gate::before(function (Authenticatable $principal, string $ability): ?bool {
            if (PermissionName::tryFrom($ability) === null) {
                return null;
            }

            return $principal instanceof User && $principal->hasPermission($ability);
        });

        // Dashboard do Pulse (/pulse): só administradores fora de local.
        Gate::define('viewPulse', fn (User $user): bool => $user->isAdmin());

        // Documentação OpenAPI do Scramble (/docs/api): idem.
        Gate::define('viewApiDocs', fn (User $user): bool => $user->isAdmin());
    }

    private function configurePasswords(): void
    {
        Password::defaults(fn (): Password => $this->app->isProduction()
            ? Password::min(12)->letters()->numbers()->uncompromised()
            : Password::min(8));
    }

    /**
     * Documentação OpenAPI (Scramble, /docs/api): toda operação aparece como
     * protegida por Bearer token. As rotas públicas se declaram com a tag
     *
     * @unauthenticated no docblock do controller.
     */
    private function configureApiDocs(): void
    {
        Scramble::configure()->withDocumentTransformers(function (OpenApi $openApi): void {
            $openApi->secure(SecurityScheme::http('bearer'));
        });
    }

    private function principalKey(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_scalar($id) ? (string) $id : (string) $request->ip();
    }
}
