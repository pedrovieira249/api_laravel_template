# ADR 0001: Arquitetura hexagonal com camadas da Clean Architecture

- **Status:** aceita
- **Data:** 2026-11-11

## Contexto

Este repositório é um template: o ponto de partida de toda API Laravel nova. As escolhas feitas
aqui se repetem em cada projeto criado a partir dele, então precisam servir tanto a uma API pequena
quanto a uma que cresce por anos.

O estilo Laravel padrão (models Active Record, lógica de negócio espalhada por controllers, models e
facades) é produtivo para CRUD, mas acopla as regras ao framework e ao banco de dados:

- Uma regra como "uma fatura paga não pode ser alterada" acaba dentro de um model Eloquent ou
  de um controller, então testá-la exige subir a aplicação e um banco.
- O model Eloquent vira o modelo de domínio. Preocupações de persistência (colunas, relações, casts)
  e de negócio (invariantes, transições de estado) vivem na mesma classe.
- Atualizações do framework e mudanças de infraestrutura (driver de fila, HTTP client, provedor
  externo) respingam nas regras de negócio.

Queremos o oposto: regras de negócio testáveis em milissegundos, sem container nem banco, um
framework tratado como detalhe substituível, fronteiras explícitas entre os contextos de negócio e
fronteiras garantidas por ferramentas, não por disciplina.

## Decisão

Toda API criada a partir do template é um **monólito modular** em **arquitetura hexagonal
(Ports & Adapters)** organizada nas camadas da **Clean Architecture**, um módulo por bounded context.

A regra de dependência é única e absoluta: `Domain <- Application <- Infrastructure / Interfaces`.
Camadas internas nunca dependem de camadas externas.

- **Domain** é PHP puro: entidades e aggregate roots, value objects, enums, eventos de domínio,
  exceções de domínio, interfaces de repositório (driven ports). Nada de `Illuminate\*` ou `App\*`,
  sem facades, sem helpers globais.
- **Application** guarda os casos de uso: commands e queries com seus handlers, DTOs e os driven
  ports que não são repositórios (`Clock`, `IdGenerator`, `EventDispatcher`, `TransactionManager`,
  gateways externos). Depende só de Domain e é igualmente livre de framework.
- **Infrastructure** implementa os ports com Laravel: models Eloquent, repositórios e mappers,
  HTTP clients, jobs, listeners.
- **Interfaces** (driving adapters) vivem em `app/`: controllers, form requests, API resources,
  middleware, comandos de console, mais os service providers que amarram ports a adapters.

Layout: `app/` é o mundo Laravel (namespace `App\`); `src/` guarda o núcleo sob o namespace `Core\`,
mapeado por PSR-4, como `Core\<Contexto>\{Domain,Application,Infrastructure}`, com `Core\Shared`
como um pequeno kernel compartilhado (`Uuid`, `Money`, `DomainEvent`, `AggregateRoot`, ports, buses).

Escritas passam pelo domínio: o controller monta um command, um handler carrega agregados por um
port de repositório, chama o comportamento do domínio, persiste pelo port e publica os eventos de
domínio depois do commit da transação. Leituras não passam por entidades: query handlers leem com o
query builder e devolvem DTOs (CQRS-lite).

**Validação em duas camadas:** o FormRequest valida formato (tipos, presença, faixas); o domínio
valida invariantes (value objects e entidades). Um número de fatura mal formado passa pelo
FormRequest como "string de até 20 caracteres" e é recusado pelo value object que o representa.

**Contextos não se importam:** `Domain` e `Application` de um contexto nunca importam os de outro.
Quando um contexto precisa de outro, declara um port na própria camada Application e um adapter de
Infrastructure faz a ponte (anti-corruption layer).

Uma exceção deliberada: **Identity** (usuários, autenticação, perfis e permissões, clientes de
serviço) fica 100% Laravel em `app/`. Não tem regra de negócio rica, e autenticação é
responsabilidade do framework (Passport, Sanctum, Gate). Os casos de uso de Identity são *actions*
em `App\Auth\*`: classes pequenas, `final readonly`, testáveis sem o kernel HTTP. Não hexagonalizar
tudo faz parte desta decisão.

As regras são executáveis. Os architecture tests do Pest (`tests/Architecture/`) quebram o build
quando Domain ou Application importam `Illuminate` ou `App`, quando um contexto importa outro,
quando uma classe em `Core` não é `final` ou quando um arquivo não tem `strict_types`. O Larastan
(nível 9) analisa `app/` e `src/`.

## Consequências

**Benefícios**

- Domínio e casos de uso são testados sem o framework nem banco; a suíte continua rápida e as
  regras ficam legíveis em um só lugar.
- O framework vira detalhe: Eloquent, filas e integrações externas ficam atrás de ports, então
  trocar ou simular qualquer um deles é um binding no container.
- Fronteiras são visíveis no sistema de arquivos e garantidas por testes, não por convenção.
- Cada bounded context pode evoluir, ou ser extraído, de forma independente.

**Custos**

- Mais classes por feature: entidade, model Eloquent, mapper, interface e implementação de
  repositório, command, handler, DTO.
- Sem as conveniências do Eloquent dentro do domínio (relações, scopes, casts, facades); entidades
  e models são mapeados à mão, e agregados são carregados inteiros.
- Mais decisões por feature (o que é um value object, onde uma regra pertence), com risco real de
  over-engineering em CRUD simples.
- Os geradores do Artisan miram `App\`; classes sob `Core\` são escritas à mão (o guia 04 traz o
  molde de cada uma).

**Mitigações**

- O CQRS-lite mantém listagens simples e rápidas.
- A exceção de Identity evita cerimônia onde não há regras. O mesmo vale para um módulo que seja
  CRUD puro: ele pode viver em `app/` como Identity vive.
- O kernel compartilhado evita duplicar value objects, ports e buses entre contextos.
- Architecture tests e análise estática pegam violações cedo, quando são baratas de corrigir.

## Referências

- [docs/guide/00-arquitetura.md](../guide/00-arquitetura.md) e [04-criando-um-modulo.md](../guide/04-criando-um-modulo.md).
- Alistair Cockburn, *Hexagonal Architecture*: https://alistair.cockburn.us/hexagonal-architecture/
- Robert C. Martin, *The Clean Architecture*:
  https://blog.cleancoder.com/uncle-bob/2012/08/13/the-clean-architecture.html
