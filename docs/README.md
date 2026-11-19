# Documentação do template

O código é a fonte da verdade; os textos aqui explicam **por que** ele tem a forma que tem e como
usá-lo. Para instalar e dar os primeiros passos, comece pelo [README](../README.md) da raiz.

## Guia

| # | Tema | Arquivo |
| - | ---- | ------- |
| 0 | A arquitetura em uma página | [guide/00-arquitetura.md](guide/00-arquitetura.md) |
| 1 | Kernel compartilhado: value objects, exceções, eventos, ports, buses | [guide/01-shared-kernel.md](guide/01-shared-kernel.md) |
| 2 | Autenticação: Passport para usuários, Sanctum para APIs internas | [guide/02-autenticacao.md](guide/02-autenticacao.md) |
| 3 | Perfis e permissões: limitando rotas por perfil | [guide/03-permissoes.md](guide/03-permissoes.md) |
| 4 | Criando um módulo: o molde de cada peça | [guide/04-criando-um-modulo.md](guide/04-criando-um-modulo.md) |
| 5 | Testes e qualidade | [guide/05-testes-e-qualidade.md](guide/05-testes-e-qualidade.md) |
| 6 | Levando para produção | [guide/06-producao.md](guide/06-producao.md) |

## Referência da API

- [api/openapi.json](api/openapi.json): OpenAPI gerado a partir do código pelo Scramble
  (`make openapi`). UI local em `/docs/api`.
- [api/api-template.postman_collection.json](api/api-template.postman_collection.json) e
  [api/api-template.postman_environment.json](api/api-template.postman_environment.json):
  collection do Postman com 21 requisições em 4 pastas, para rodar em ordem: autenticação de
  usuários, administração de perfis e clientes de serviço, APIs internas e logout. Tokens e ids
  são capturados automaticamente; ajuste `base_url` no environment para a porta do seu `APP_PORT`.
  Ao criar ou mudar rotas, atualize a collection junto.

## Decisões de arquitetura (ADRs)

Cada decisão que não é óbvia pelo código tem um registro em [adr/](adr/), no formato
Contexto → Decisão → Consequências. Leia-as quando se perguntar "por que não fizeram X?".

| ADR | Decisão |
| --- | ------- |
| [0001](adr/0001-architecture.md) | Hexagonal + camadas da Clean Architecture, monólito modular; Identity fora do hexágono |
| [0002](adr/0002-uuid-v7-identifiers.md) | UUID v7 gerado pela aplicação |
| [0003](adr/0003-money-as-integer-cents.md) | Dinheiro em centavos inteiros com moeda |
| [0004](adr/0004-domain-exceptions-to-problem-details.md) | Erros → Problem Details (RFC 9457) em um único lugar |
| [0005](adr/0005-domain-events-after-commit.md) | Eventos de domínio publicados após o commit |
| [0006](adr/0006-command-bus-by-convention.md) | Bus resolve handlers por convenção de nome |
| [0007](adr/0007-separate-entities-from-eloquent-models.md) | Entidades separadas dos models Eloquent, com mappers |
| [0008](adr/0008-dual-authentication-passport-and-sanctum.md) | Passport (5 min + refresh) para usuários, Sanctum (1 h) para APIs internas |
| [0009](adr/0009-rbac-in-database-tables.md) | Perfis e permissões em tabelas próprias, verificados por middleware de rota |
| [0010](adr/0010-idempotency-keys.md) | `Idempotency-Key` para POSTs que não podem rodar duas vezes |
| [0011](adr/0011-registration-behind-service-token.md) | Registro de usuários fechado: só sistemas internos, com ability própria e limite de 5/s |

Ao tomar uma decisão nova no seu projeto, acrescente uma ADR (`NNNN-titulo.md`) em vez de editar
as existentes.
