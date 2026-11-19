# A arquitetura em uma página

Monólito modular em arquitetura hexagonal (Ports & Adapters), com as camadas da Clean
Architecture. A decisão completa está na [ADR 0001](../adr/0001-architecture.md).

## A regra de dependência

```
Domain  ◀──  Application  ◀──  Infrastructure / Interfaces (HTTP, CLI, Eloquent, filas)
```

Camadas internas nunca importam camadas externas. É a única regra, e ela é executável: os testes
em [`tests/Architecture/`](../../tests/Architecture/) quebram o build quando alguém a viola.

| Camada | O que contém | O que não pode |
| --- | --- | --- |
| **Domain** | Entidades, value objects, enums, eventos, exceções, interfaces de repositório | Importar `Illuminate\*`, `App\*`, Application ou Infrastructure |
| **Application** | Commands, queries, handlers, DTOs, ports (`Clock`, gateways...) | Importar `Illuminate\*`, `App\*` ou Infrastructure |
| **Infrastructure** | Models Eloquent, repositórios, mappers, read models, listeners, clients HTTP | (Laravel é bem-vindo aqui) |
| **Interfaces** (`app/`) | Controllers, FormRequests, Resources, middleware, comandos | Lógica de negócio; tocar em Infrastructure ou em repositórios |

## Onde cada coisa fica

```
app/                                   Mundo Laravel: entrada HTTP/CLI e wiring
  Auth/                                Identity: actions de login, tokens, perfis (sem hexágono)
    Users/                             Passport: usuários externos
    Services/                          Sanctum: APIs internas
    Access/                            Perfis e permissões
  Console/Commands/                    auth:install, permissions:sync, service-client:*, user:assign-role
  Enums/                               RoleName, PermissionName, ServiceAbility
  Exceptions/                          Erros de Identity com nome próprio (409/403)
  Http/
    Controllers/Api/V1/                Controllers single-action (__invoke), finos
    Middleware/                        role, permission, idempotent, request id, JSON, idioma
    Requests/                          FormRequests: validação de formato
    Resources/                         Saída JSON
    Responses/ProblemDetailsRenderer   Todo erro vira Problem Details (ADR 0004)
  Models/                              User, Role, Permission, ServiceClient
  Providers/
    AppServiceProvider                 Rate limiting, prazos de token, Gate, senhas
    DomainServiceProvider              Port → adapter do kernel + providers dos contextos
src/                                   Núcleo: namespace Core\, livre de framework em Domain/Application
  Shared/                              Kernel: Uuid, Money, Email, AggregateRoot, ports, buses
  <Contexto>/                          Um por bounded context (o template não traz nenhum)
    Domain/{Entity,ValueObject,Enum,Event,Exception,Repository}
    Application/{Command,Query,Dto,Port}
    Infrastructure/{Persistence/Eloquent,Listener,Provider}
routes/api.php                         O mapa: quem pode chamar o quê
config/identity.php                    Prazos dos tokens
config/api.php                         Rate limits e base dos tipos de erro
database/{migrations,factories,seeders}
tests/{Unit,Integration,Feature,Architecture}
docs/{guide,adr,api}
docker/, compose.yaml, Makefile, install.sh
```

## O caminho de uma escrita

O exemplo é o contexto fictício `Billing` do [guia 04](04-criando-um-modulo.md), com
`POST /api/v1/invoices`:

1. **Rota** ([`routes/api.php`](../../routes/api.php)): `auth:api` autentica,
   `permission:invoices.issue` autoriza, `throttle:api` limita.
2. **FormRequest** (`IssueInvoiceRequest`) valida o formato e se converte em um command
   (`toCommand()`).
3. **Controller** (`IssueInvoiceController`) envia o command ao `CommandBus`. Três linhas:
   validar, executar, apresentar.
4. **Handler** (`IssueInvoiceHandler`) constrói os value objects, confere regras, cria o agregado,
   salva pelo port do repositório e publica os eventos, tudo dentro de `TransactionManager::run()`.
5. **Domínio** (`Invoice`) impõe as invariantes e registra `InvoiceIssued`.
6. **Infrastructure**: `EloquentInvoiceRepository` mapeia entidade ↔ model;
   `LaravelEventDispatcher` entrega os eventos aos listeners depois do commit.
7. **Resposta**: o controller lê o resultado pelo `QueryBus` e devolve um Resource com 201 e
   `Location`.

Se algo falha, uma exceção sobe até o renderer central e vira Problem Details. Nenhum try/catch no
caminho.

## O caminho de uma leitura

`GET /api/v1/invoices` não passa por entidades (CQRS-lite):

`ListInvoicesRequest::toQuery()` → `QueryBus` → `ListInvoicesHandler` → port `InvoiceReadModel` →
`EloquentInvoiceReadModel` (query builder) → DTOs `InvoiceView` →
[`PageResource`](../../app/Http/Resources/PageResource.php).

Regra prática: **escrita pelo domínio, leitura direta**.

## Identity é a exceção

Usuários, autenticação, perfis e clientes de serviço vivem em `app/`, sem hexágono: não há regra
de negócio rica, e autenticação é assunto do framework. Os casos de uso são *actions* em
[`app/Auth/`](../../app/Auth/): classes `final readonly` com um método, injetadas direto no
controller (sem bus). Um módulo seu que seja CRUD puro pode seguir o mesmo caminho.

## Convenções que os testes garantem

- `declare(strict_types=1)` em todo arquivo; classes `final` por padrão.
- Value objects, commands, queries, handlers e eventos são `readonly`.
- Controllers são `final`, single-action e terminam em `Controller`.
- `Core\*\Domain` e `Core\*\Application` não importam framework nem outros contextos.
- `App\Http` não toca em `Core\*\Infrastructure` nem em repositórios.
- Passport só aparece no lado de usuários; Sanctum, no lado interno.
- Toda rota de API tem nome, throttle e, se não estiver na lista de públicas, autenticação.

## Próximas leituras

- [01-shared-kernel.md](01-shared-kernel.md): as peças reutilizadas por todo contexto.
- [02-autenticacao.md](02-autenticacao.md) e [03-permissoes.md](03-permissoes.md).
- [04-criando-um-modulo.md](04-criando-um-modulo.md): passo a passo de um contexto novo.
