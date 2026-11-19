# API Laravel Template — guia para agentes

Template de API REST em **PHP 8.5 + Laravel 13**: arquitetura hexagonal, Passport (usuários) +
Sanctum (APIs internas) e RBAC por middleware de rota. Todo projeto criado a partir dele herda
estas regras. A explicação longa de cada decisão está em `docs/` (comece por `docs/README.md`).

## 1. Como trabalhar aqui

1. **Idioma:** explicações, `README.md`, `docs/` e **todos os comentários de código** (docblocks,
   `//`, Dockerfile, compose, ini, Makefile, CI) em português (pt-BR). Código, identificadores,
   commits, nomes de arquivo, nomes de testes (`it('...')`), mensagens de erro da API e anotações
   de docblock (`@param`, `@return`) em inglês. Arquivos publicados por pacotes ficam como vieram.
2. **Tudo roda nos containers.** Nunca use o PHP do host: `make artisan cmd="..."`,
   `make composer cmd="..."`, `make test`, ou `docker compose exec -T app ...`.
3. **`make check` verde antes de dar algo por pronto** (Pint + Larastan nível 9 + testes). Para o
   que o CI cobra, `make ci` (inclui cobertura ≥ 80% e type coverage 100%).
4. **Sempre o porquê.** Comentários explicam a razão da escolha, não repetem a assinatura.
5. **Confirme na fonte** antes de usar uma API de pacote: Laravel Boost `search-docs`, a
   documentação oficial ou o código em `vendor/`.
6. **Decisão de arquitetura nova = ADR nova** em `docs/adr/NNNN-titulo.md`
   (Contexto, Decisão, Consequências). Não edite as existentes para mudar de ideia.
7. **Nada destrutivo sem combinar** (apagar branch, `reset --hard`, dropar banco com dados).
   Não faça `push`.

## 2. Arquitetura (ADR 0001)

```
Domain  ◀──  Application  ◀──  Infrastructure / Interfaces (app/)
```

| Onde | O quê | Proibido |
| --- | --- | --- |
| `src/<Ctx>/Domain` | Entidades, VOs, enums, eventos, exceções, interfaces de repositório | `Illuminate\*`, `App\*`, outros contextos |
| `src/<Ctx>/Application` | Commands, queries, handlers, DTOs, ports | `Illuminate\*`, `App\*`, Infrastructure, outros contextos |
| `src/<Ctx>/Infrastructure` | Models Eloquent, repositórios, mappers, read models, listeners, provider | — |
| `app/Http` | Controllers single-action, FormRequests, Resources, middleware | Lógica de negócio; tocar em Infrastructure ou repositórios |
| `app/Auth`, `app/Models` | Identity: sem hexágono (actions `final readonly`) | — |

- Namespace `Core\` → `src/`; `App\` → `app/`.
- **Escrita:** FormRequest → Command → `CommandBus` → Handler (transação, agregado, repositório,
  eventos) → Resource. **Leitura:** Query → `QueryBus` → read model (query builder) → DTO → Resource.
- Handlers são achados por convenção: `FooCommand` → `FooHandler` na mesma pasta (ADR 0006).
- Erros: lance exceções; nunca `try/catch` em controller. O status vem da classe base
  (`EntityNotFound` 404, `BusinessRuleViolation` 409, `DomainException` 422; em `App\Exceptions`,
  as exceções HTTP do Symfony). Tudo vira Problem Details (ADR 0004).
- Handlers pedem tempo, ids, transação e eventos aos ports (`Clock`, `IdGenerator`,
  `TransactionManager`, `EventDispatcher`), nunca a facades.
- Módulo novo: siga `docs/guide/04-criando-um-modulo.md`, que traz o molde de cada peça. O
  template não tem módulo de negócio; `src/` começa só com `Shared`.

## 3. Autenticação e permissões (ADRs 0008 e 0009)

| | Usuários externos | APIs internas |
| --- | --- | --- |
| Pacote / guard | Passport / `auth:api` | Sanctum / `auth:internal` |
| Model | `App\Models\User` | `App\Models\ServiceClient` |
| Token | JWT, 5 min + refresh token | opaco, 1 h, sem refresh |
| Autorização na rota | `role:<perfil>`, `permission:<permissão>` | `ability:<ability>` |
| Rotas | `/api/v1/...` | `/api/v1/internal/...` |

Regras que não se negociam:

- **Toda rota nova entra em um grupo de `routes/api.php`** com `auth:api` ou `auth:internal`, um
  throttle e um nome. Rota pública de propósito entra em `PUBLIC_ROUTES` no
  `tests/Feature/Api/RouteProtectionTest.php`.
- **Nunca misture os públicos:** `role`/`permission` só com `auth:api`; `ability` só com
  `auth:internal`. Passport só aparece em `App\Auth\Users`, `User`, `AppServiceProvider` e
  `AuthInstallCommand`; Sanctum só em `App\Auth\Services`, `ServiceClient` e nos controllers
  `Internal`. Testes de arquitetura garantem.
- **Permissão nova:** case + descrição em `App\Enums\PermissionName`, `permission:` na rota,
  `make permissions`. Perfis são dados (API de administração), não código.
- **Prefira `permission:` a `role:`.** `role:` só quando a regra é "quem a pessoa é" e não pode
  ser delegada.
- Não exponha as rotas `/oauth/*` do Passport nem mude os prazos dos tokens fora de
  `config/identity.php`.
- Tokens de usuário só são emitidos por `App\Auth\Users\UserTokenIssuer`.
- O registro de usuários não é público (ADR 0011): `POST /api/v1/internal/auth/register`, com
  `auth:internal`, `ability:users.register` e `throttle.exact:register` (5/s por cliente). Não o
  reabra sem uma ADR nova.
- Limite de requisições: número em `config/api.php`, limiter em
  `AppServiceProvider::configureRateLimiting()`, `throttle:<nome>` na rota. Quando o teto é
  requisito, `throttle.exact:<nome>` (janela deslizante atômica no Redis): o `throttle` do
  framework e o `throttleWithRedis()` cedem sob requisições simultâneas e na virada da janela.
- No controller, pegue o principal com `#[CurrentUser('api')] User $user` ou
  `#[CurrentUser('internal')] ServiceClient $client`.
- As travas do perfil `admin` (só admin concede/retira admin; o último admin não cai; perfis de
  sistema não são apagados nem renomeados) vivem em `App\Auth\Access`. Não as contorne.

## 4. Convenções de código

- `declare(strict_types=1)` em todo arquivo; classes `final` por padrão; VOs, commands, queries,
  handlers, eventos e actions `final readonly`.
- Sem `mixed` (exceto nas assinaturas do bus) e sem `array` sem shape: use `list<Foo>` /
  `array<string, int>` em docblock.
- Enums no lugar de constantes string: cases em PascalCase, valores em snake_case.
- Sufixos: `*Command`, `*Query`, `*Handler`, `*Repository` (interface sem prefixo `I`),
  `Eloquent*Repository`, `*Controller`, `*Request`, `*Resource`.
- Controllers: `final`, um `__invoke`, três passos (validar, executar, apresentar).
- FormRequest valida formato; o domínio valida invariantes.
- **Banco:** tabelas no plural em snake_case; PK `uuid` (v7); dinheiro em `*_cents bigint
  unsigned`; enum como `string` + enum PHP; toda FK com índice; migration commitada não se edita.
- **API:** prefixo `/api/v1`; JSON; 422 validação, 409 conflito de estado, 404 não encontrado,
  401 sem autenticação, 403 sem permissão; transições como sub-recursos (`POST /orders/{id}/ship`).
- **Testes:** nome descreve comportamento (`it('refuses to pay an invoice twice')`); cada caso de uso tem
  teste unitário do handler (fakes em memória) + teste de feature HTTP. Helpers em
  `tests/Pest.php`: `actingAsUser()`, `actingAsService()`, `loginAs()`, `freshRequest()`.
- **Git:** Conventional Commits (`feat(billing): issue invoice use case`).

## 5. Comandos

```
make up / down / build / logs s=app       ciclo de vida dos containers
make sh                                   bash no container app
make artisan cmd="..." / composer cmd="..."
make test | test-unit | test-integration | test-feature | test-arch
make pint | stan | rector                 qualidade
make check                                pint --test + stan + test (antes de commitar)
make ci                                   o que o GitHub Actions roda
make fresh                                migrate:fresh --seed
make routes                               rotas da API com middlewares
make auth-install | permissions           autenticação e catálogo de permissões
make openapi                              exporta docs/api/openapi.json
```

Artisan do template: `auth:install`, `permissions:sync`, `user:assign-role {email} {role}`,
`service-client:create|list|revoke`.

## 6. Onde olhar primeiro

| Preciso de... | Arquivo |
| --- | --- |
| Quem pode chamar o quê | `routes/api.php` |
| Prazos dos tokens | `config/identity.php` |
| Rate limits, Gate, Passport | `app/Providers/AppServiceProvider.php` |
| Port → adapter, contextos registrados | `app/Providers/DomainServiceProvider.php` |
| Formato dos erros | `app/Http/Responses/ProblemDetailsRenderer.php` |
| Molde de módulo | `docs/guide/04-criando-um-modulo.md` |
| Regras de arquitetura | `tests/Architecture/` |

## 7. Referências

- Laravel 13: https://laravel.com/docs/13.x · Passport: https://laravel.com/docs/13.x/passport ·
  Sanctum: https://laravel.com/docs/13.x/sanctum
- PHP 8.5: https://www.php.net/releases/8.5/en.php
- Pest 5: https://pestphp.com/docs · Larastan: https://github.com/larastan/larastan

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
