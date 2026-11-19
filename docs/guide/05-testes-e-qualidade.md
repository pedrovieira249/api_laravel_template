# Testes e qualidade

## As quatro suítes

| Suíte | Pasta | O que sobe | O que cobre |
| --- | --- | --- | --- |
| **Unit** | `tests/Unit` | Nada (PHP puro) | Entidades, value objects, handlers com ports falsos em memória, enums |
| **Integration** | `tests/Integration` | Container Laravel + MySQL de teste | Repositórios, read models, actions de `app/Auth`, adapters do kernel |
| **Feature** | `tests/Feature` | O kernel HTTP inteiro | Endpoints de ponta a ponta, comandos Artisan, agendamentos |
| **Architecture** | `tests/Architecture` | Só análise de código | A regra de dependência e as convenções |

```bash
make test               # tudo
make test-unit          # só Unit (milissegundos)
make test-integration
make test-feature
make test-arch
make artisan cmd="test --filter=LoginTest"
```

Feature e Integration usam `RefreshDatabase` no banco `<DB_DATABASE>_test`, criado pelo script
[`docker/mysql/init/01-test-database.sh`](../../docker/mysql/init/01-test-database.sh). O nome
está fixado em [`phpunit.xml`](../../phpunit.xml) (`app_test`): se você trocar `DB_DATABASE` no
`.env`, troque lá também.

## O que cada caso de uso deve ter

1. **Teste unitário do domínio**: as invariantes do agregado.
2. **Teste unitário do handler**, com fakes em memória.
3. **Teste de integração do adapter**, contra o banco.
4. **Teste de feature HTTP**: status, corpo, permissões.

O [guia 04](04-criando-um-modulo.md#7-testes) traz um exemplo de cada. Na base do template, bons
modelos para ler são [`MoneyTest`](../../tests/Unit/Shared/Domain/ValueObject/MoneyTest.php)
(unitário), [`RegisterUserTest`](../../tests/Integration/Auth/RegisterUserTest.php) (integração, com
injeção de falha para provar a transação) e
[`RolesTest`](../../tests/Feature/Api/V1/Admin/RolesTest.php) (feature).

Nomes descrevem comportamento: `it('refuses to demote the last administrator')`, não `test_demote()`.

## Ferramentas de apoio aos testes

| Peça | Onde | Para quê |
| --- | --- | --- |
| `actingAsUser()`, `actingAsService()`, `loginAs()`, `freshRequest()`, `jwtClaims()` | [`tests/Pest.php`](../../tests/Pest.php) | Autenticar nos testes ([guia 02](02-autenticacao.md#5-nos-testes)) |
| `User::factory()->admin()`, `->withRole()`, `->withPermissions()` | [`UserFactory`](../../database/factories/UserFactory.php) | Usuário com o acesso exato que o teste precisa |
| `ServiceClient::factory()->withAbilities()`, `->revoked()` | [`ServiceClientFactory`](../../database/factories/ServiceClientFactory.php) | Clientes de serviço (segredo fixo em `ServiceClientFactory::SECRET`) |
| `FrozenClock`, `SequentialIdGenerator`, `InMemoryEventDispatcher`, `NullTransactionManager` | [`tests/Support/Fakes`](../../tests/Support/Fakes/) | Ports falsos para testes unitários |

Detalhes que economizam tempo:

- **Chaves do Passport**: [`tests/TestCase.php`](../../tests/TestCase.php) gera um par RSA em
  memória, uma vez por processo. A suíte não depende de `storage/oauth-*.key`.
- **Client OAuth**: criado antes de cada teste de Feature/Integration (`tests/Pest.php`).
- **Duas requisições, dois tokens**: os guards guardam o principal em cache durante o teste.
  Chame `freshRequest()` entre requisições autenticadas com tokens diferentes.
- **Expiração de JWT**: o validador usa o relógio do sistema, não o do Carbon. `travelTo()` não
  vence um token de usuário; veja como
  [`TokenLifetimeTest`](../../tests/Feature/Api/V1/Auth/TokenLifetimeTest.php) resolve. Tokens de
  serviço (Sanctum) vencem normalmente com `travelTo()`.
- **Limite exato (`throttle.exact`)**: a contagem acontece no Redis, com o relógio dele. Esses
  testes precisam do Redis no ar e usam tempo de verdade (`usleep`), não `freezeTime()`.
- **Saída de comandos**: cada linha de saída satisfaz no máximo um `expectsOutputToContain()`.

## Redes de segurança

Dois arquivos merecem ser lidos, porque protegem o projeto de erros fáceis de cometer:

- [`tests/Feature/Api/RouteProtectionTest.php`](../../tests/Feature/Api/RouteProtectionTest.php):
  toda rota de `/api/v1` precisa de nome, throttle e autenticação, a menos que esteja na lista
  explícita `PUBLIC_ROUTES`. Permissões e abilities citadas nas rotas precisam existir nos enums.
  Nenhuma rota mistura os dois públicos.
- [`tests/Architecture/`](../../tests/Architecture/): camadas, convenções e o confinamento de
  Passport e Sanctum cada um ao seu lado.

## Portões de qualidade

| Comando | O que garante |
| --- | --- |
| `make pint` / `make pint-test` | Estilo: preset `laravel` + `declare_strict_types`, `strict_comparison`, `void_return` |
| `make stan` | Larastan nível 9 em `app/`, `database/`, `routes/` e `src/` |
| `make rector` / `make rector-fix` | Modernização automática (PHP 8.5, Laravel 13) |
| `make coverage` | Cobertura de linhas ≥ 80% |
| `make type-coverage` | 100% dos parâmetros, retornos e propriedades tipados |
| `make mutate` | Mutation testing no domínio (`src/*/Domain`), score ≥ 70% |
| **`make check`** | `pint-test` + `stan` + `test`: obrigatório antes de commitar |
| `make ci` | O que o GitHub Actions roda ([`ci.yml`](../../.github/workflows/ci.yml)) |

Convenções de código que os portões cobram: `declare(strict_types=1)`, classes `final`, sem
`mixed` (exceto nas assinaturas do bus), arrays com shape em docblock (`list<Foo>`,
`array<string, int>`), enums no lugar de constantes string.

## CI

[`.github/workflows/ci.yml`](../../.github/workflows/ci.yml) roda em todo push para `main` e em
todo pull request: Pint, Larastan, Architecture tests, Pest com cobertura mínima de 80% e type
coverage de 100%, com MySQL e Redis como serviços. Não precisa de segredos nem de chaves.
