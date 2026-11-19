# ADR 0006: Command bus e query bus resolvem handlers por convenção de nome

- **Status:** aceita
- **Data:** 2026-11-11

## Contexto

Controllers não devem instanciar casos de uso com `new`: o handler precisa de seus ports
injetados, e o controller deveria depender de uma abstração, não de uma classe concreta por caso de
uso. Um bus dá um único ponto de entrada para escritas (`CommandBus::dispatch`) e um para leituras
(`QueryBus::ask`). Buses normalmente exigem um registro mapeando mensagem → handler, que é
boilerplate que fica defasado.

## Decisão

- Mensagens são DTOs imutáveis que implementam as interfaces marcadoras `Command` ou `Query`
  (`Core\Shared\Application\Bus`). Elas declaram seu tipo de resultado por generics
  (`@implements Command<Uuid>`), então a análise estática sabe o que `dispatch()` devolve.
- Handlers são classes invocáveis simples nomeadas por convenção: `IssueInvoiceCommand` →
  `IssueInvoiceHandler`, `ListInvoicesQuery` → `ListInvoicesHandler`, no mesmo namespace.
  `HandlerResolver` deriva o nome da classe do handler e falha com `HandlerNotFound` (uma
  `LogicException`, isto é, um erro de programação) quando ela não existe.
- `ContainerCommandBus` / `ContainerQueryBus` resolvem o handler a partir do service container do
  Laravel, então os ports injetados pelo construtor são satisfeitos, e o invocam de forma síncrona.
- Sem pipeline de middleware, sem enfileiramento automático: trabalho assíncrono é explícito (um
  Job que despacha um command).

## Consequências

- Adicionar um caso de uso = adicionar duas classes lado a lado. Nenhum registro em lugar nenhum.
- A convenção é uma regra que o time precisa conhecer; está documentada aqui e verificada por
  testes.
- `mixed` aparece nas assinaturas do bus. É o único lugar em que o projeto o permite, porque os
  docblocks genéricos dão o tipo preciso ao Larastan e às IDEs.
- As actions de Identity (`App\Auth\*`) não passam pelo bus: são injetadas direto no controller.
  O bus é a porta do núcleo (`src/`), não de `app/`.
