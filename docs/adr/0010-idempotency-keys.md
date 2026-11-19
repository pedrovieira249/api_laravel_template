# ADR 0010: Chaves de idempotência para POSTs que não podem rodar duas vezes

- **Status:** aceita
- **Data:** 2026-11-11

## Contexto

HTTP POST não é idempotente. Um cliente que envia "criar pedido", perde a conexão antes de ler a
resposta e tenta de novo cria dois pedidos. Retentativas não são um bug do cliente: são o
comportamento correto em timeouts. O servidor precisa torná-las seguras nas operações em que a
duplicata custa caro (pedidos, pagamentos, envios, disparos).

## Decisão

O template traz o middleware pronto, com o alias `idempotent`, e não o aplica em nenhuma rota por
padrão. Cada projeto o liga onde importa:

```php
Route::post('orders', PlaceOrderController::class)->middleware('idempotent');
```

- A rota passa a exigir o header `Idempotency-Key`, uma string única gerada pelo cliente (UUID
  recomendado). Header ausente → 400 Problem Details.
- `App\Http\Middleware\EnsureIdempotency` monta a chave da entrada de cache com **principal
  autenticado + SHA-256 da key**, então dois usuários (ou dois serviços) não colidem nem leem as
  respostas um do outro.
- A primeira resposta JSON bem-sucedida (status, corpo, `Location`) é guardada por 24 horas. Toda
  requisição posterior com a mesma key recebe a resposta guardada com o header
  `Idempotency-Replayed: true`, sem tocar no caso de uso.
- Respostas de falha (4xx/5xx) não são guardadas: uma retentativa corrigida precisa poder ter
  sucesso.
- Enquanto a primeira requisição está sendo processada, um lock atômico de cache na key faz uma
  duplicata concorrente falhar com 409 em vez de rodar o caso de uso duas vezes.

## Consequências

- Retentativas seguras para os clientes, verificado por `tests/Feature/Api/IdempotencyTest.php`.
- O store de cache precisa suportar locks atômicos (Redis em dev/prod, array nos testes). Caso
  contrário o middleware se recusa a rodar, e de forma ruidosa.
- A resposta guardada é um snapshot: um replay depois que o recurso mudou ainda devolve o corpo do
  momento da criação. Clientes que querem o estado atual seguem o `Location`.
- O payload não é comparado: reutilizar uma key com payload diferente faz o replay da primeira
  resposta. Fingerprint do payload ao estilo Stripe é um refinamento possível.
