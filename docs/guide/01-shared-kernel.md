# Kernel compartilhado (`src/Shared`)

O que todo contexto reutiliza. É pequeno de propósito: só entra aqui o que é usado por mais de um
contexto e não pertence a nenhum.

## Domain

| Peça | Arquivo | Para que serve |
| --- | --- | --- |
| `AggregateRoot` | [Entity/AggregateRoot.php](../../src/Shared/Domain/Entity/AggregateRoot.php) | Base dos agregados: `record()` guarda eventos, `pullDomainEvents()` os entrega (`#[\NoDiscard]`) |
| `DomainEvent` | [Event/DomainEvent.php](../../src/Shared/Domain/Event/DomainEvent.php) | Contrato de um evento: `occurredOn()` e `aggregateId()` |
| `Uuid` | [ValueObject/Uuid.php](../../src/Shared/Domain/ValueObject/Uuid.php) | Identidade validada ([ADR 0002](../adr/0002-uuid-v7-identifiers.md)) |
| `Email` | [ValueObject/Email.php](../../src/Shared/Domain/ValueObject/Email.php) | E-mail normalizado (trim + minúsculas, com o operador pipe do PHP 8.5) |
| `Money` + `Currency` | [ValueObject/Money.php](../../src/Shared/Domain/ValueObject/Money.php) | Dinheiro em centavos inteiros ([ADR 0003](../adr/0003-money-as-integer-cents.md)) |

### A hierarquia de exceções

```
DomainException (abstrata)            → 422
├── EntityNotFound (abstrata)         → 404
└── BusinessRuleViolation (abstrata)  → 409
```

Cada erro de negócio é uma classe `final` que estende uma dessas três e tem construtores estáticos
nomeados. A base escolhida define o status HTTP ([ADR 0004](../adr/0004-domain-exceptions-to-problem-details.md)):

```php
final class InvoiceAlreadyPaid extends BusinessRuleViolation   // 409
{
    public static function withId(Uuid $id): self
    {
        return new self(sprintf('Invoice %s is already paid', $id));
    }
}
```

## Application

### Ports (interfaces que o núcleo usa e a Infrastructure implementa)

| Port | Adapter Laravel | Fake para testes |
| --- | --- | --- |
| [`Clock`](../../src/Shared/Application/Contract/Clock.php) | `SystemClock` | `FrozenClock` |
| [`IdGenerator`](../../src/Shared/Application/Contract/IdGenerator.php) | `UuidV7Generator` | `SequentialIdGenerator` |
| [`EventDispatcher`](../../src/Shared/Application/Contract/EventDispatcher.php) | `LaravelEventDispatcher` (após o commit, [ADR 0005](../adr/0005-domain-events-after-commit.md)) | `InMemoryEventDispatcher` |
| [`TransactionManager`](../../src/Shared/Application/Contract/TransactionManager.php) | `DbTransactionManager` (3 tentativas em deadlock) | `NullTransactionManager` |

Handlers nunca chamam `new \DateTimeImmutable()`, `Str::uuid()` ou `DB::transaction()`: pedem ao
port. É isso que permite testá-los sem Laravel. Os fakes estão em
[`tests/Support/Fakes/`](../../tests/Support/Fakes/).

### Buses ([ADR 0006](../adr/0006-command-bus-by-convention.md))

```php
$id = $this->commands->dispatch(new IssueInvoiceCommand(...));    // escrita
$view = $this->queries->ask(new FindInvoiceQuery($id->value));    // leitura
```

O handler é encontrado pelo nome: `IssueInvoiceCommand` → `IssueInvoiceHandler`, na mesma pasta.
Nenhum registro. A mensagem declara o tipo do resultado (`@implements Command<Uuid>`), então o
Larastan e a IDE sabem o que volta.

### `Page`

[`Page<T>`](../../src/Shared/Application/Dto/Page.php) é o resultado paginado sem framework:
`items`, `total`, `perPage`, `currentPage`. Read models devolvem `Page`; na borda HTTP,
[`PageResource`](../../app/Http/Resources/PageResource.php) o transforma em `{ data, meta }`.

### `ServiceUnavailable`

[`ServiceUnavailable`](../../src/Shared/Application/Exception/ServiceUnavailable.php) é a base
para "uma dependência externa não respondeu". O adapter de um gateway lança uma subclasse; a
camada HTTP responde 503 e um job em fila tenta de novo.

## Infrastructure

Os adapters acima e os buses ([`src/Shared/Infrastructure/`](../../src/Shared/Infrastructure/)).
A ligação port → adapter é feita em um lugar só,
[`DomainServiceProvider`](../../app/Providers/DomainServiceProvider.php):

```php
public array $singletons = [
    Clock::class => SystemClock::class,
    IdGenerator::class => UuidV7Generator::class,
    EventDispatcher::class => LaravelEventDispatcher::class,
    TransactionManager::class => DbTransactionManager::class,
    CommandBus::class => ContainerCommandBus::class,
    QueryBus::class => ContainerQueryBus::class,
];
```

O mesmo provider registra o provider de cada contexto (método `contexts()`) e ensina a descoberta
de eventos a encontrar listeners em `src/*/Infrastructure/Listener`.

## Na borda HTTP, para todo contexto

| Peça | Arquivo | O que faz |
| --- | --- | --- |
| `ProblemDetailsRenderer` | [app/Http/Responses](../../app/Http/Responses/ProblemDetailsRenderer.php) | Todo erro da API em `application/problem+json` |
| `AssignRequestId` | [app/Http/Middleware](../../app/Http/Middleware/AssignRequestId.php) | `X-Request-Id` na resposta e em todas as linhas de log da requisição (e dos jobs que ela despacha) |
| `RequireJsonAccept` | [app/Http/Middleware](../../app/Http/Middleware/RequireJsonAccept.php) | A API só fala JSON: `Accept: text/html` recebe 406 |
| `SetLocale` | [app/Http/Middleware](../../app/Http/Middleware/SetLocale.php) | `Accept-Language: pt-BR` troca as mensagens de validação e de auth |
| `EnsureIdempotency` | [app/Http/Middleware](../../app/Http/Middleware/EnsureIdempotency.php) | Alias `idempotent`, para POSTs que não podem rodar duas vezes ([ADR 0010](../adr/0010-idempotency-keys.md)) |
| `EnforceExactRateLimit` | [app/Http/Middleware](../../app/Http/Middleware/EnforceExactRateLimit.php) | Alias `throttle.exact`: limite de requisições com teto exato sob concorrência ([ADR 0011](../adr/0011-registration-behind-service-token.md)) |
| `MoneyResource` | [app/Http/Resources](../../app/Http/Resources/MoneyResource.php) | Forma JSON única para dinheiro |

## Recursos de PHP moderno que aparecem aqui

| Recurso | Onde |
| --- | --- |
| `final readonly class` (8.2) | Value objects, commands, DTOs, handlers |
| Constantes tipadas (8.3) | `private const string PATTERN` em `Uuid` |
| Visibilidade assimétrica `public private(set)` (8.4) | Entidades (molde no [guia 04](04-criando-um-modulo.md)) |
| `new` sem parênteses, `array_any` (8.4) | Fakes de teste, renderer |
| Operador pipe `\|>` (8.5) | `Email::fromString` |
| `clone($obj, [...])` (8.5) | `Money` |
| `#[\NoDiscard]` (8.5) | `Money::add`, `AggregateRoot::pullDomainEvents` |
| `#[\SensitiveParameter]` (8.2) | Senhas, segredos e tokens nas actions de `app/Auth` |
