# ADR 0004: Erros mapeados centralmente para Problem Details (RFC 9457)

- **Status:** aceita
- **Data:** 2026-11-11

## Contexto

Casos de uso falham por motivos de negócio (fatura já paga, número duplicado, registro
desconhecido) e por motivos técnicos (validação, autenticação, bugs). Tratar cada um em todo
controller com try/catch duplica código e deixa os corpos de erro inconsistentes. A API também
precisa de um formato de erro documentado e legível por máquina.

## Decisão

- O domínio lança exceções de uma hierarquia pequena com raiz em
  `Core\Shared\Domain\Exception\DomainException`:
  - `EntityNotFound` → **404**
  - `BusinessRuleViolation` → **409** (requisição bem formada, conflita com o estado atual)
  - qualquer outra `DomainException` → **422** (valor inválido: e-mail, UUID, número de fatura...)
- Exceções são **final** e construídas por construtores estáticos nomeados
  (`InvoiceNotFound::withId($id)`), então as mensagens são compostas em um só lugar.
- Regras de Identity (que vive em `app/`) usam exceções em `App\Exceptions` que estendem as
  exceções HTTP do Symfony: `ConflictHttpException` (409) e `AccessDeniedHttpException` (403).
- `bootstrap/app.php` registra um único callback `render`. Para requisições de API ele delega a
  `App\Http\Responses\ProblemDetailsRenderer`, que produz corpos `application/problem+json`:

  ```json
  {
    "type": "https://api.example.com/problems/invoice-already-paid",
    "title": "Invoice already paid",
    "status": 409,
    "detail": "Invoice 019a... is already paid",
    "instance": "/api/v1/invoices/019a.../pay"
  }
  ```

  Para validação há também `errors` (campo → mensagens).
- `type` é um identificador estável por tipo de erro: `<base>/<nome-da-classe-em-kebab-case>` para
  erros com nome próprio (domínio e `App\Exceptions`), `<base>/validation` para validação e
  `about:blank` quando o status já diz tudo. A base vem de `config('api.problem_type_base')`
  (`API_PROBLEM_TYPE_BASE`) e precisa ser a mesma em todos os ambientes.
- Exceções do framework também são mapeadas: `ValidationException` (422), `AuthenticationException`
  (401), `AuthorizationException` (403), `ModelNotFoundException` (404, com texto neutro: a
  mensagem original cita a classe do model), `ThrottleRequestsException` (429), outras exceções
  HTTP mantêm seu status, qualquer outra coisa é 500 com detalhes ocultos a menos que
  `APP_DEBUG=true`.

## Consequências

- Controllers não têm try/catch; um caso de uso ou retorna ou lança.
- Adicionar um erro de negócio significa adicionar uma classe final sob `Domain\Exception` e
  escolher sua base. Nenhuma mudança no renderer.
- Clientes tratam erros pelo `type` (estável) e pelo `status`, nunca pelo texto de `detail`.
- A hierarquia é garantida por um architecture test (`domain exceptions extend the domain root`).
- Credenciais de login inválidas respondem 422 com `errors.email` (convenção do Laravel), não 401;
  401 fica para "token ausente, inválido ou expirado".
