# Criando um módulo (bounded context)

O template não traz nenhum módulo de negócio: `src/` tem só o kernel (`Shared`). Este guia é o
molde. Ele monta, de dentro (domínio) para fora (HTTP), um contexto fictício `Billing` com um
agregado `Invoice` (fatura). Os trechos de código seguem as convenções que os testes de arquitetura
cobram; copie, renomeie e adapte. Classes citadas e não mostradas (`InvoicePaid`,
`InvalidInvoiceTotal`, `InvoiceNumberAlreadyExists`, `FindInvoiceQuery`, `ShowInvoiceController`,
`ListInvoicesRequest`) seguem o mesmo molde das vizinhas.

> O módulo é CRUD puro, sem regra de negócio? Considere não usar o hexágono: models em
> `app/Models`, actions em `app/` e controllers, como Identity faz ([ADR 0001](../adr/0001-architecture.md)).

```
src/Billing/
  Domain/{Entity,ValueObject,Enum,Event,Exception,Repository}/
  Application/{Command,Query,Dto,Port}/
  Infrastructure/{Persistence/Eloquent/{Model,Mapper,Repository,ReadModel},Listener,Provider}/
```

## 1. Domain: PHP puro

Nada de `Illuminate\*` nem `App\*` aqui. Tudo `final`; value objects e eventos `readonly`.

**Value object**: construtor privado, `fromString()` valida e lança.

```php
// src/Billing/Domain/ValueObject/InvoiceNumber.php
final readonly class InvoiceNumber implements \Stringable
{
    private const string PATTERN = '/^INV-\d{4}-\d{6}$/';

    private function __construct(public string $value) {}

    /**
     * @throws InvalidInvoiceNumber
     */
    public static function fromString(string $value): self
    {
        $normalized = $value |> mb_trim(...) |> mb_strtoupper(...);

        if (preg_match(self::PATTERN, $normalized) !== 1) {
            throw InvalidInvoiceNumber::fromString($value);
        }

        return new self($normalized);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
```

**Enum**: cases em PascalCase, valores em snake_case.

```php
// src/Billing/Domain/Enum/InvoiceStatus.php
enum InvoiceStatus: string
{
    case Open = 'open';
    case Paid = 'paid';
}
```

**Exceções**: a classe base define o status HTTP ([ADR 0004](../adr/0004-domain-exceptions-to-problem-details.md)).

```php
// src/Billing/Domain/Exception/*.php
final class InvoiceNotFound extends EntityNotFound            // 404
{
    public static function withId(Uuid $id): self
    {
        return new self(sprintf('Invoice %s was not found', $id));
    }
}

final class InvoiceAlreadyPaid extends BusinessRuleViolation  // 409
{
    public static function withId(Uuid $id): self
    {
        return new self(sprintf('Invoice %s is already paid', $id));
    }
}

final class InvalidInvoiceNumber extends DomainException      // 422
{
    public static function fromString(string $value): self
    {
        return new self(sprintf('Invalid invoice number: "%s" (expected INV-YYYY-NNNNNN)', $value));
    }
}
```

**Evento**:

```php
// src/Billing/Domain/Event/InvoiceIssued.php
final readonly class InvoiceIssued implements DomainEvent
{
    public function __construct(
        public Uuid $invoiceId,
        public InvoiceNumber $number,
        public \DateTimeImmutable $occurredOn,
    ) {}

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function aggregateId(): Uuid
    {
        return $this->invoiceId;
    }
}
```

**Agregado**: estado somente leitura por fora (`public private(set)`), invariantes nos métodos,
eventos registrados com `record()`. `reconstitute()` existe só para o mapper.

```php
// src/Billing/Domain/Entity/Invoice.php
final class Invoice extends AggregateRoot
{
    private function __construct(
        public readonly Uuid $id,
        public readonly InvoiceNumber $number,
        public readonly Money $total,
        public private(set) InvoiceStatus $status,
        public readonly \DateTimeImmutable $issuedAt,
        public private(set) \DateTimeImmutable $updatedAt,
    ) {}

    /**
     * @throws InvalidInvoiceTotal
     */
    public static function issue(Uuid $id, InvoiceNumber $number, Money $total, \DateTimeImmutable $now): self
    {
        if ($total->isNegative() || $total->isZero()) {
            throw InvalidInvoiceTotal::notPositive($total);
        }

        $invoice = new self($id, $number, $total, InvoiceStatus::Open, $now, $now);
        $invoice->record(new InvoiceIssued($id, $number, $now));

        return $invoice;
    }

    /**
     * Sem validação, sem eventos: o que foi persistido é confiável.
     */
    public static function reconstitute(
        Uuid $id,
        InvoiceNumber $number,
        Money $total,
        InvoiceStatus $status,
        \DateTimeImmutable $issuedAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self($id, $number, $total, $status, $issuedAt, $updatedAt);
    }

    /**
     * @throws InvoiceAlreadyPaid
     */
    public function pay(\DateTimeImmutable $now): void
    {
        if ($this->status === InvoiceStatus::Paid) {
            throw InvoiceAlreadyPaid::withId($this->id);
        }

        $this->status = InvoiceStatus::Paid;
        $this->updatedAt = $now;

        $this->record(new InvoicePaid($this->id, $now));
    }
}
```

**Port do repositório**: só o que os casos de uso precisam. Listagens não passam por ele.

```php
// src/Billing/Domain/Repository/InvoiceRepository.php
interface InvoiceRepository
{
    /**
     * @throws InvoiceNotFound
     */
    public function get(Uuid $id): Invoice;

    public function existsWithNumber(InvoiceNumber $number): bool;

    public function save(Invoice $invoice): void;
}
```

## 2. Application: casos de uso

Um caso de uso = duas classes na mesma pasta. O bus encontra `FooHandler` a partir de `FooCommand`
ou `FooQuery` ([ADR 0006](../adr/0006-command-bus-by-convention.md)); nenhum registro.

**Command**: só primitivos; o tipo do resultado vai no docblock.

```php
// src/Billing/Application/Command/IssueInvoice/IssueInvoiceCommand.php
/**
 * @implements Command<Uuid>
 */
final readonly class IssueInvoiceCommand implements Command
{
    public function __construct(
        public string $number,
        public int $totalCents,
        public string $currency,
    ) {}
}
```

**Handler de escrita**: sempre a mesma forma. Constrói os value objects, confere regras, cria o
agregado, salva e publica os eventos, dentro de uma transação. Tempo, ids, transação e eventos vêm
dos ports do kernel, nunca de facades.

```php
// src/Billing/Application/Command/IssueInvoice/IssueInvoiceHandler.php
final readonly class IssueInvoiceHandler
{
    public function __construct(
        private InvoiceRepository $invoices,
        private IdGenerator $ids,
        private Clock $clock,
        private TransactionManager $transaction,
        private EventDispatcher $events,
    ) {}

    public function __invoke(IssueInvoiceCommand $command): Uuid
    {
        $number = InvoiceNumber::fromString($command->number);
        $total = Money::fromCents($command->totalCents, Currency::from($command->currency));

        return $this->transaction->run(function () use ($number, $total): Uuid {
            if ($this->invoices->existsWithNumber($number)) {
                throw InvoiceNumberAlreadyExists::for($number);
            }

            $invoice = Invoice::issue($this->ids->generate(), $number, $total, $this->clock->now());

            $this->invoices->save($invoice);
            $this->events->dispatch(...$invoice->pullDomainEvents());

            return $invoice->id;
        });
    }
}
```

**Leitura (CQRS-lite)**: um DTO de primitivos, um port de read model, uma query e seu handler.

```php
// src/Billing/Application/Dto/InvoiceView.php
final readonly class InvoiceView
{
    public function __construct(
        public string $id,
        public string $number,
        public int $totalCents,
        public string $currency,
        public string $status,
        public \DateTimeImmutable $issuedAt,
    ) {}
}

// src/Billing/Application/Port/InvoiceReadModel.php
interface InvoiceReadModel
{
    /**
     * @return Page<InvoiceView>
     */
    public function search(ListInvoicesQuery $query): Page;

    /**
     * @throws InvoiceNotFound
     */
    public function find(Uuid $id): InvoiceView;
}

// src/Billing/Application/Query/ListInvoices/ListInvoicesQuery.php
/**
 * @implements Query<Page<InvoiceView>>
 */
final readonly class ListInvoicesQuery implements Query
{
    public function __construct(
        public ?string $status = null,
        public int $page = 1,
        public int $perPage = 15,
    ) {}
}

// src/Billing/Application/Query/ListInvoices/ListInvoicesHandler.php
final readonly class ListInvoicesHandler
{
    public function __construct(private InvoiceReadModel $invoices) {}

    /**
     * @return Page<InvoiceView>
     */
    public function __invoke(ListInvoicesQuery $query): Page
    {
        return $this->invoices->search($query);
    }
}
```

Precisa de outro contexto ou de um serviço externo? Declare um port em `Application/Port`
(`CustomerDirectory`, `PaymentGateway`) e implemente o adapter na Infrastructure. `Domain` e
`Application` nunca importam outro contexto. Falha de dependência externa: lance uma subclasse de
`Core\Shared\Application\Exception\ServiceUnavailable` (vira 503).

## 3. Infrastructure: Laravel

**Model**: detalhe de persistência ([ADR 0007](../adr/0007-separate-entities-from-eloquent-models.md)).
`#[WithoutTimestamps]` porque as datas são estado do agregado, vindas do `Clock`.

```php
// src/Billing/Infrastructure/Persistence/Eloquent/Model/InvoiceModel.php
/**
 * @property string $id
 * @property string $number
 * @property int $total_cents
 * @property string $currency
 * @property string $status
 * @property CarbonImmutable $issued_at
 * @property CarbonImmutable $updated_at
 */
#[Table('invoices')]
#[UseFactory(InvoiceModelFactory::class)]
#[Fillable(['id', 'number', 'total_cents', 'currency', 'status', 'issued_at', 'updated_at'])]
#[WithoutTimestamps]
final class InvoiceModel extends Model
{
    /** @use HasFactory<InvoiceModelFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_cents' => 'integer',
            'issued_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
```

**Mapper**: duas direções, nenhuma lógica.

```php
// src/Billing/Infrastructure/Persistence/Eloquent/Mapper/InvoiceMapper.php
final class InvoiceMapper
{
    public function toEntity(InvoiceModel $model): Invoice
    {
        return Invoice::reconstitute(
            id: Uuid::fromString($model->id),
            number: InvoiceNumber::fromString($model->number),
            total: Money::fromCents($model->total_cents, Currency::from($model->currency)),
            status: InvoiceStatus::from($model->status),
            issuedAt: \DateTimeImmutable::createFromInterface($model->issued_at),
            updatedAt: \DateTimeImmutable::createFromInterface($model->updated_at),
        );
    }

    public function toModel(Invoice $invoice, InvoiceModel $model): InvoiceModel
    {
        return $model->forceFill([
            'id' => $invoice->id->value,
            'number' => $invoice->number->value,
            'total_cents' => $invoice->total->amount,
            'currency' => $invoice->total->currency->value,
            'status' => $invoice->status->value,
            'issued_at' => $invoice->issuedAt,
            'updated_at' => $invoice->updatedAt,
        ]);
    }
}
```

**Repositório**: implementa o port do domínio; `save()` é um upsert.

```php
// src/Billing/Infrastructure/Persistence/Eloquent/Repository/EloquentInvoiceRepository.php
final readonly class EloquentInvoiceRepository implements InvoiceRepository
{
    public function __construct(private InvoiceMapper $mapper) {}

    public function get(Uuid $id): Invoice
    {
        $model = InvoiceModel::query()->find($id->value) ?? throw InvoiceNotFound::withId($id);

        return $this->mapper->toEntity($model);
    }

    public function existsWithNumber(InvoiceNumber $number): bool
    {
        return InvoiceModel::query()->where('number', $number->value)->exists();
    }

    public function save(Invoice $invoice): void
    {
        $model = InvoiceModel::query()->find($invoice->id->value) ?? new InvoiceModel;

        $this->mapper->toModel($invoice, $model)->save();
    }
}
```

**Read model**: query builder puro, linhas viram DTOs.

```php
// src/Billing/Infrastructure/Persistence/Eloquent/ReadModel/EloquentInvoiceReadModel.php
final readonly class EloquentInvoiceReadModel implements InvoiceReadModel
{
    public function __construct(private ConnectionResolverInterface $connections) {}

    public function search(ListInvoicesQuery $query): Page
    {
        $paginator = $this->connections->connection()->table('invoices')
            ->when($query->status !== null, fn (Builder $builder): Builder => $builder->where('status', $query->status))
            ->orderByDesc('issued_at')
            ->orderBy('id')
            ->paginate(perPage: $query->perPage, page: $query->page);

        $rows = array_values(array_filter($paginator->items(), fn (mixed $row): bool => $row instanceof \stdClass));

        return new Page(
            items: array_map($this->toView(...), $rows),
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage(),
        );
    }

    public function find(Uuid $id): InvoiceView
    {
        $row = $this->connections->connection()->table('invoices')->where('id', $id->value)->first();

        return $row instanceof \stdClass ? $this->toView($row) : throw InvoiceNotFound::withId($id);
    }

    private function toView(\stdClass $row): InvoiceView
    {
        return new InvoiceView(
            id: $row->id,
            number: $row->number,
            totalCents: (int) $row->total_cents,
            currency: $row->currency,
            status: $row->status,
            issuedAt: new \DateTimeImmutable($row->issued_at),
        );
    }
}
```

Muitos filtros? Extraia cada um para uma classe e encadeie com `Illuminate\Pipeline\Pipeline`.

**Provider**: liga port a adapter.

```php
// src/Billing/Infrastructure/Provider/BillingServiceProvider.php
final class BillingServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        InvoiceRepository::class => EloquentInvoiceRepository::class,
        InvoiceReadModel::class => EloquentInvoiceReadModel::class,
    ];
}
```

**Listener** (opcional): basta existir em `Infrastructure/Listener`; o type-hint do evento é a
inscrição. Roda depois do commit ([ADR 0005](../adr/0005-domain-events-after-commit.md)); com
`ShouldQueue` vai para a fila (Horizon).

```php
// src/Billing/Infrastructure/Listener/SendInvoiceByEmail.php
final readonly class SendInvoiceByEmail implements ShouldQueue
{
    public function handle(InvoiceIssued $event): void
    {
        // ...
    }
}
```

**Banco**, fora de `src/`:

```php
// database/migrations/..._create_invoices_table.php
Schema::create('invoices', function (Blueprint $table): void {
    $table->uuid('id')->primary();                 // UUID v7 gerado pela aplicação (ADR 0002)
    $table->string('number', 20)->unique();
    $table->unsignedBigInteger('total_cents');     // dinheiro em centavos (ADR 0003)
    $table->char('currency', 3);
    $table->string('status', 20);                  // enum como string + enum PHP
    $table->timestamp('issued_at');
    $table->timestamp('updated_at');

    $table->index(['status', 'issued_at']);
});
```

E uma factory em `database/factories/InvoiceModelFactory.php` (com `protected $model =
InvoiceModel::class`), para os testes de integração e de feature.

## 4. Registrar o contexto (3 linhas)

1. [`app/Providers/DomainServiceProvider.php`](../../app/Providers/DomainServiceProvider.php):
   acrescente `BillingServiceProvider::class` em `contexts()`.
2. [`tests/Architecture/LayersTest.php`](../../tests/Architecture/LayersTest.php): acrescente
   `'Billing'` em `$contexts`.
3. [`tests/Architecture/ConventionsTest.php`](../../tests/Architecture/ConventionsTest.php):
   acrescente `'Core\Billing\Infrastructure'` na regra `eloquent models stay in infrastructure`.

O alvo `make mutate` já inclui `src/Billing/Domain` sozinho.

## 5. HTTP (`app/Http`)

**FormRequest**: valida formato e se converte na mensagem da Application.

```php
// app/Http/Requests/Billing/IssueInvoiceRequest.php
final class IssueInvoiceRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'number' => ['required', 'string', 'max:20'],
            'total_cents' => ['required', 'integer', 'min:1'],
            'currency' => ['required', Rule::enum(Currency::class)],
        ];
    }

    public function toCommand(): IssueInvoiceCommand
    {
        return new IssueInvoiceCommand(
            number: $this->string('number')->toString(),
            totalCents: $this->integer('total_cents'),
            currency: $this->string('currency')->toString(),
        );
    }
}
```

**Resource**: embrulha o DTO de leitura, nunca a entidade nem o model.

```php
// app/Http/Resources/Billing/InvoiceResource.php
/**
 * @mixin InvoiceView
 */
final class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'total' => MoneyResource::make($this->totalCents, $this->currency),
            'status' => $this->status,
            'issued_at' => $this->issuedAt->format(DATE_ATOM),
        ];
    }
}
```

**Controllers**: `final`, um `__invoke`, três passos (validar, executar, apresentar). Nunca
importam `Core\Billing\Infrastructure` nem o repositório.

```php
// app/Http/Controllers/Api/V1/Billing/IssueInvoiceController.php
final class IssueInvoiceController extends Controller
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {}

    public function __invoke(IssueInvoiceRequest $request): JsonResponse
    {
        $id = $this->commands->dispatch($request->toCommand());

        // Escreve e depois lê: a resposta é a mesma projeção que o GET devolve.
        return new InvoiceResource($this->queries->ask(new FindInvoiceQuery($id->value)))
            ->response()
            ->setStatusCode(201)
            ->header('Location', route('api.v1.invoices.show', $id->value));
    }
}

// app/Http/Controllers/Api/V1/Billing/ListInvoicesController.php
final class ListInvoicesController extends Controller
{
    public function __construct(private readonly QueryBus $queries) {}

    /**
     * @return PageResource<InvoiceView>
     */
    public function __invoke(ListInvoicesRequest $request): PageResource
    {
        return new PageResource($this->queries->ask($request->toQuery()), InvoiceResource::class);
    }
}
```

Precisa de quem está chamando? `#[CurrentUser('api')] User $user` no `__invoke`.

## 6. Permissões e rotas

1. Permissões novas em [`PermissionName`](../../app/Enums/PermissionName.php) (case + descrição) e
   `make permissions`:

   ```php
   case InvoicesRead = 'invoices.read';
   case InvoicesIssue = 'invoices.issue';
   ```

2. Um grupo em [`routes/api.php`](../../routes/api.php), dentro do grupo `throttle:api`:

   ```php
   Route::prefix('invoices')->name('invoices.')->middleware('auth:api')->whereUuid('invoice')->group(function (): void {
       Route::middleware('permission:invoices.read')->group(function (): void {
           Route::get('/', ListInvoicesController::class)->name('index');
           Route::get('{invoice}', ShowInvoiceController::class)->name('show');
       });

       Route::post('/', IssueInvoiceController::class)
           ->middleware(['permission:invoices.issue', 'idempotent'])
           ->name('store');
   });
   ```

   `{invoice}` chega ao controller como `string` (UUID), não como model: o contexto não expõe
   Eloquent para a camada HTTP. `idempotent` é opcional ([ADR 0010](../adr/0010-idempotency-keys.md)).

3. Outro sistema precisa consumir? Acrescente a ability em
   [`ServiceAbility`](../../app/Enums/ServiceAbility.php) (`case InvoicesRead = 'invoices.read';`)
   e uma rota no grupo `auth:internal`, reaproveitando o mesmo controller:

   ```php
   Route::get('invoices', ListInvoicesController::class)->middleware('ability:invoices.read')->name('invoices.index');
   ```

## 7. Testes

Cada caso de uso tem os quatro níveis ([guia 05](05-testes-e-qualidade.md)).

**Unitário do domínio** (`tests/Unit/Billing/Domain`), sem Laravel:

```php
it('refuses to pay an invoice twice', function (): void {
    $invoice = Invoice::issue(
        Uuid::fromString('019a5c6e-7b3a-7c1d-8f2e-3a4b5c6d7e10'),
        InvoiceNumber::fromString('INV-2026-000001'),
        Money::fromCents(15000, Currency::Brl),
        new DateTimeImmutable('2026-11-09 12:00:00'),
    );

    $invoice->pay(new DateTimeImmutable);
    $invoice->pay(new DateTimeImmutable);
})->throws(InvoiceAlreadyPaid::class);
```

**Unitário do handler** (`tests/Unit/Billing/Application`), com os fakes de
[`tests/Support/Fakes`](../../tests/Support/Fakes/) e um repositório em memória:

```php
// tests/Support/Billing/InMemoryInvoiceRepository.php
final class InMemoryInvoiceRepository implements InvoiceRepository
{
    /** @var array<string, Invoice> */
    public array $invoices = [];

    public function get(Uuid $id): Invoice
    {
        return $this->invoices[$id->value] ?? throw InvoiceNotFound::withId($id);
    }

    public function existsWithNumber(InvoiceNumber $number): bool
    {
        return array_any($this->invoices, fn (Invoice $invoice): bool => $invoice->number->equals($number));
    }

    public function save(Invoice $invoice): void
    {
        $this->invoices[$invoice->id->value] = $invoice;
    }
}
```

```php
it('issues the invoice inside a transaction and publishes InvoiceIssued', function (): void {
    $invoices = new InMemoryInvoiceRepository;
    $events = new InMemoryEventDispatcher;
    $transaction = new NullTransactionManager;

    $handler = new IssueInvoiceHandler($invoices, new SequentialIdGenerator, FrozenClock::at('2026-11-09 12:00:00'), $transaction, $events);

    $id = $handler(new IssueInvoiceCommand('inv-2026-000001', 15000, 'BRL'));

    expect($invoices->get($id)->number->value)->toBe('INV-2026-000001')
        ->and($transaction->runs)->toBe(1)
        ->and($events->has(InvoiceIssued::class))->toBeTrue();
});
```

**Integração do adapter** (`tests/Integration/Billing`), contra o MySQL de teste:

```php
it('persists an invoice and reads it back identical', function (): void {
    $repository = $this->app->make(InvoiceRepository::class);
    $invoice = Invoice::issue(/* ... */);

    $repository->save($invoice);

    expect($repository->get($invoice->id)->total->equals($invoice->total))->toBeTrue();
    $this->assertDatabaseHas('invoices', ['id' => $invoice->id->value]);
});
```

**Feature HTTP** (`tests/Feature/Api/V1/Billing`): status, corpo e permissões.

```php
it('requires invoices.issue to issue an invoice', function (): void {
    $payload = ['number' => 'INV-2026-000001', 'total_cents' => 15000, 'currency' => 'BRL'];

    $this->postJson('/api/v1/invoices', $payload)->assertUnauthorized();

    actingAsUser(User::factory()->withPermissions(PermissionName::InvoicesRead)->create());
    $this->postJson('/api/v1/invoices', $payload)->assertForbidden();

    actingAsUser(User::factory()->withPermissions(PermissionName::InvoicesIssue)->create());
    $this->postJson('/api/v1/invoices', $payload)
        ->assertCreated()
        ->assertJsonPath('data.number', 'INV-2026-000001')
        ->assertJsonPath('data.total.amount_cents', 15000);
});
```

## 8. Conferir

```bash
make check       # pint + larastan + testes (inclui os de arquitetura e o RouteProtectionTest)
make routes      # as rotas novas com seus middlewares
make openapi     # atualiza docs/api/openapi.json
```

O que os testes de arquitetura vão cobrar do módulo novo:

- `Domain` e `Application` sem `Illuminate`, sem `App` e sem outro contexto;
- classes `final`; value objects, commands, queries, handlers e eventos `readonly`;
- exceções de domínio estendendo `DomainException`;
- `Illuminate\Database\Eloquent\Model` só em `Infrastructure`;
- toda rota com nome, throttle, autenticação e permissões que existem no enum.

Se o projeto não lida com dinheiro, `Money`, `Currency`, `CurrencyMismatch` e `MoneyResource`
podem ser apagados ([ADR 0003](../adr/0003-money-as-integer-cents.md)).
