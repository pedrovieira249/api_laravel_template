# ADR 0003: Dinheiro como inteiro em unidades mínimas com moeda explícita

- **Status:** aceita
- **Data:** 2026-11-11

## Contexto

Quase toda API de negócio lida com valores monetários. Ponto flutuante não consegue representar a
maioria das frações decimais com exatidão (`0.1 + 0.2 !== 0.3`), e somar valores com floats produz
desvio de arredondamento. Um inteiro puro evita isso, mas um inteiro "1500" não diz se é BRL ou
USD, e somar valores de moedas diferentes é um bug silencioso.

## Decisão

- `Core\Shared\Domain\ValueObject\Money` guarda `int $amount` (unidades mínimas, isto é, centavos)
  e um `Core\Shared\Domain\Enum\Currency`.
- Toda operação (`add`, `subtract`, `multiply`, `percentage`) devolve uma instância **nova**,
  construída com o `clone($this, [...])` do PHP 8.5, e é marcada com `#[\NoDiscard]`: descartar o
  resultado é quase certamente um bug, e o PHP avisa.
- Operações entre moedas diferentes lançam `CurrencyMismatch`, uma `BusinessRuleViolation`
  (HTTP 409). `equals()` não lança: dois valores em moedas diferentes simplesmente não são iguais.
- Porcentagens usam aritmética inteira com arredondamento "half away from zero"; nenhum float
  entra em cena.
- Valores negativos são permitidos (estornos, descontos). "O total de uma fatura é positivo"
  é invariante de quem usa o valor (o agregado), não de `Money`.
- Colunas no banco são `*_cents bigint unsigned` mais um `currency char(3)`.
- Na API, dinheiro sai sempre na mesma forma (`App\Http\Resources\MoneyResource`):
  `{ "amount_cents": 4990, "currency": "BRL", "formatted": "R$49.90" }`. Os centavos são o
  contrato; `formatted` é conveniência para humanos e segue o idioma da requisição
  (`Accept-Language`).

## Consequências

- Totais são exatos e reproduzíveis; testes fazem asserções sobre inteiros.
- Para uma moeda nova, acrescente o case em `Currency` (e seus decimais).
- Se o projeto não lida com dinheiro, `Money`, `Currency`, `CurrencyMismatch` e `MoneyResource`
  podem ser apagados. A alternativa de mercado, quando o domínio financeiro é central,
  é `brick/money`.
