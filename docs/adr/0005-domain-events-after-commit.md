# ADR 0005: Eventos de domínio são publicados após o commit da transação

- **Status:** aceita
- **Data:** 2026-11-11

## Contexto

Agregados registram o que aconteceu (`InvoiceIssued`, `InvoicePaid`). Algo precisa publicar
esses eventos para listeners (enviar um e-mail, avisar outro contexto, atualizar um read model). Se
a publicação acontece enquanto a transação que produziu o evento ainda está aberta, um listener
pode agir sobre dados que depois sofrem rollback, ou um listener em fila pode rodar antes de a
linha existir.

## Decisão

- Agregados estendem `Core\Shared\Domain\Entity\AggregateRoot` e chamam `record()`.
  `pullDomainEvents()` devolve e limpa os eventos; é `#[\NoDiscard]` porque esquecer de usar o
  resultado perde eventos silenciosamente.
- Handlers publicam pelo port `Core\Shared\Application\Contract\EventDispatcher`.
- O adapter Laravel (`LaravelEventDispatcher`) entrega cada evento ao hook `afterCommit()` da
  conexão com o banco. Dentro de uma transação o evento espera o commit mais externo; em rollback
  é descartado; fora de transação é despachado imediatamente.
- Listeners são listeners Laravel comuns que tipam a classe (ou uma interface) do evento de
  domínio. Eles vivem em `src/<Contexto>/Infrastructure/Listener` e são descobertos
  automaticamente (`bootstrap/app.php` → `withEvents`). Trabalho pesado vai para a fila
  (`ShouldQueue`, processado pelo Horizon).

## Consequências

- Um handler pode despachar dentro de `TransactionManager::run()` sem se preocupar com ordem.
- Eventos são in-process: se o processo morre entre o commit e o dispatch, eles se perdem. Para
  entrega garantida, use o padrão Outbox: gravar os eventos em uma tabela na mesma transação e
  publicá-los por um job. O ponto de encaixe é um segundo adapter de `EventDispatcher`.
- Na suíte de testes, `RefreshDatabase` envolve cada teste em uma transação; o transaction manager
  de teste do Laravel ignora esse nível externo, então `afterCommit` se comporta como em produção.
- O parâmetro de `handle(InvoiceIssued $event)` é a inscrição do listener, mesmo quando não é
  usado. Por isso o Rector não remove parâmetros públicos "sem uso" (`rector.php`).
