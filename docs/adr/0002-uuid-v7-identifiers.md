# ADR 0002: Identificadores UUID v7 gerados pela aplicação

- **Status:** aceita
- **Data:** 2026-11-11

## Contexto

Todo agregado precisa de uma identidade. Inteiros auto-incrementais são o padrão do MySQL, mas são
atribuídos pelo banco no momento do INSERT, o que obriga a aplicação a persistir antes de poder
referenciar a entidade (eventos, itens apontando para o pai) e vaza ids sequenciais e adivinháveis
pela API. UUIDs aleatórios (v4) resolvem os dois problemas, mas espalham os inserts pelo índice
clusterizado do InnoDB, prejudicando a performance de escrita em tabelas grandes.

## Decisão

- Identificadores são **UUID versão 7** (RFC 9562): os primeiros 48 bits são um timestamp Unix em
  milissegundos, então os ids são ordenados no tempo e o InnoDB faz append em vez de dividir páginas.
- São gerados **pela aplicação**, antes do INSERT, pelo port
  `Core\Shared\Application\Contract\IdGenerator`. O adapter Laravel usa `Str::uuid7()`.
- O domínio só conhece o value object `Core\Shared\Domain\ValueObject\Uuid`, que valida a forma
  textual canônica (versão e variante incluídas) e nunca gera nada.
- Colunas são `char(36)` com a representação canônica com hifens, pela legibilidade em logs e
  clientes SQL.
- Os models de Identity (`User`, `Role`, `Permission`, `ServiceClient`) usam a trait `HasUuids` do
  Laravel, que também produz v7. As migrations publicadas do Passport e do Sanctum foram ajustadas
  para colunas UUID (`foreignUuid('user_id')`, `uuidMorphs('tokenable')`).

## Consequências

- Entidades nascem completas: `Invoice::issue(...)` recebe seu id, registra `InvoiceIssued` com
  esse id e é persistida depois. Nada de "salva e depois lê o id de volta".
- Testes unitários usam `Tests\Support\Fakes\SequentialIdGenerator` e obtêm ids determinísticos.
- Rotas recebem UUIDs: use `->whereUuid('invoice')` no grupo para que um id mal formado vire 404
  antes de chegar ao caso de uso.
- Ids têm 36 caracteres: chaves estrangeiras e índices são maiores do que com inteiros. Aceito.
