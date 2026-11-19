# ADR 0007: Entidades de domínio separadas dos models Eloquent, com mappers explícitos

- **Status:** aceita
- **Data:** 2026-11-11

## Contexto

Eloquent é um Active Record: o objeto que guarda os dados também sabe se persistir, tem relações,
casts, scopes e eventos globais. É isso que torna o Laravel produtivo, e também o que acopla o
modelo de negócio ao banco: um model `Invoice` carrega `total_cents` como um atributo que qualquer
um pode definir como negativo, suas "invariantes" vivem onde alguém lembrou de colocá-las, e testar
uma regra significa bater no banco.

Queremos que `Invoice` seja um agregado PHP puro (ADR 0001) com construtor privado, value objects e
métodos que impõem regras (`issue()`, `pay()`), testável em milissegundos.

## Decisão

- Cada agregado tem duas classes: a **entidade** em `Core\<Ctx>\Domain\Entity` (PHP puro, estado
  exposto com propriedades `public private(set)`, comportamento como métodos, eventos registrados)
  e o **model Eloquent** em `Core\<Ctx>\Infrastructure\Persistence\Eloquent\Model` (colunas, casts,
  factory). O model é um detalhe de persistência e nunca sai de Infrastructure.
- Um **mapper** por agregado (`InvoiceMapper`) traduz nas duas direções: `toEntity(Model)` chama o
  construtor nomeado `reconstitute()` da entidade (sem validação, sem eventos: o estado armazenado
  é confiável); `toModel(Entity, Model)` copia o estado para o model com `forceFill`.
- O **repositório** (`EloquentInvoiceRepository`) implementa o port do Domain usando o model e o
  mapper. `save()` é um upsert: encontra a linha ou cria um model novo, mapeia, salva.
- Models do núcleo usam `#[WithoutTimestamps]`: a entidade é dona de `createdAt`/`updatedAt`,
  dirigidos pelo port `Clock`, então os testes podem congelar o tempo e o Eloquent nunca os
  reescreve silenciosamente.
- Listagens nunca passam por entidades (CQRS-lite): read models usam o query builder e devolvem
  DTOs.

## Consequências

- Testes de domínio não precisam de banco: o ciclo de vida inteiro de um agregado roda em poucos
  milissegundos.
- Mais classes por agregado: entidade, model, mapper, interface de repositório, repositório,
  factory. O mapper é código chato, de propósito.
- Conveniências do Eloquent (relações na entidade, lazy loading, accessors) não estão disponíveis
  no domínio. Agregados são carregados inteiros e salvos inteiros.
- Factories miram models, não entidades; testes unitários constroem entidades com um Test Data
  Builder em `tests/Support/<Contexto>`.
- Custom casts (`CastsAttributes`) ainda fazem sentido no model: código que toca models diretamente
  (seeders, factories) recebe um value object validado em vez de uma string crua.
- O architecture test `eloquent models stay in infrastructure (and identity)` lista onde
  `Illuminate\Database\Eloquent\Model` pode aparecer. Um contexto novo com persistência entra
  nessa lista.
