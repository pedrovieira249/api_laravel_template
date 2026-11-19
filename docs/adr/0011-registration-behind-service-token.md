# ADR 0011: Registro de usuários fechado, feito só por sistemas internos

- **Status:** aceita
- **Data:** 2026-11-19

## Contexto

`POST /api/v1/auth/register` nasceu pública: qualquer pessoa na internet criava uma conta. Uma
rota assim fica exposta a cadastro em massa por robôs, a descoberta de quais e-mails já têm conta
(a validação responde "e-mail em uso") e a consumo de recursos (cada cadastro calcula um hash de
senha e emite tokens). Limitar por IP ajuda pouco, porque quem abusa troca de IP.

Nos projetos que usam este template, quem cadastra usuários é um sistema da casa (o site, um BFF,
outra API), não o navegador falando direto com esta API.

## Decisão

- O registro sai do grupo público e vai para o grupo interno:
  `POST /api/v1/internal/auth/register`. A rota pública deixa de existir (404).
- Ela exige o token de serviço do Sanctum (`auth:internal`, [ADR 0008](0008-dual-authentication-passport-and-sanctum.md))
  **e** a ability `users.register` (`App\Enums\ServiceAbility::UsersRegister`). Um token de
  usuário, mesmo de administrador, não serve.
- Limite próprio, o limiter `register`: **5 requisições por segundo por cliente de serviço**
  (`API_REGISTER_RATE_LIMIT`). Acima disso, 429 com `Retry-After`. Cada sistema tem a sua cota.
- O limite é aplicado pelo middleware `throttle.exact`
  (`App\Http\Middleware\EnforceExactRateLimit`), não pelo `throttle` do framework, porque aqui o
  teto é requisito e o do framework cede sob requisições simultâneas (ver "Por que um middleware
  próprio").
- O caso de uso não muda: o perfil é sempre o padrão (`user`), nunca vem de quem chama, e a
  resposta traz os tokens do usuário criado, para o sistema chamador já deixá-lo logado.

Onde cada parte está:

| O quê | Onde |
| --- | --- |
| O teto (5) | `config/api.php` → `rate_limits.register` |
| A janela (por segundo) e a chave (por cliente) | `AppServiceProvider::configureRateLimiting()` |
| A rota, a ability e o `throttle.exact:register` | `routes/api.php`, grupo `internal` |
| A contagem exata | `app/Http/Middleware/EnforceExactRateLimit.php` |

## Consequências

- O `client_secret` do sistema que cadastra fica no servidor dele. Nunca vai para um navegador ou
  um app: quem o tem cria contas em nome de qualquer pessoa.
- Proteções voltadas a pessoas (CAPTCHA, confirmação de e-mail, termos de uso) são
  responsabilidade do sistema chamador, que é quem fala com a pessoa.
- Dê `users.register` só ao sistema que faz o cadastro. Um cliente com a ability curinga `*`
  também consegue registrar.
- O primeiro usuário de um ambiente novo já não se cadastra sozinho: é preciso um cliente de
  serviço ([docs/guide/06-producao.md](../guide/06-producao.md#o-primeiro-administrador)).
- Reabrir o registro ao público é uma decisão, não um ajuste: devolver a rota ao grupo `auth`,
  incluí-la em `PUBLIC_ROUTES` no `RouteProtectionTest` e registrar uma ADR nova.

## Por que um middleware próprio para o limite

"Não mais que 5 por segundo" foi tratado como requisito: em nenhum intervalo de 1 segundo podem
passar 6 cadastros do mesmo cliente. O `throttle` do Laravel não garante isso, por dois motivos
independentes, ambos medidos neste projeto com requisições simultâneas e o mesmo token:

1. **Corrida entre conferir e contar.** Ele lê o contador e só depois o incrementa. Contra um
   teto de 5, passaram 6 no login (janela de 1 minuto, então não é efeito de virada de janela).
2. **Janela fixa.** Uma rajada na virada da janela é aceita dos dois lados. Com a janela presa ao
   segundo do relógio, 8 cadastros disparados juntos em x,80 s passaram todos.

As alternativas mais simples foram testadas ou lidas no código-fonte e descartadas:

| Alternativa | Por que não serve |
| --- | --- |
| `$middleware->throttleWithRedis()` (`ThrottleRequestsWithRedis`) | Também confere em um passo e adquire em outro, e ignora o resultado da aquisição: a mesma corrida |
| `RateLimiter::hit()` como "incrementa e decide" | No começo da janela ele pode regravar o contador com 1 e apagar o incremento de outra requisição (medido: duas requisições receberam o mesmo número, 7 aceitas) |
| Um contador por segundo de relógio, com `add()` + `increment()` do cache | Resolve a corrida (40 rajadas alinhadas ao segundo, sempre 5), mas continua sendo janela fixa: na virada passam até 10 |

`EnforceExactRateLimit` (alias `throttle.exact`) decide tudo em um script Lua dentro do Redis,
que executa de forma atômica:

1. remove do conjunto as requisições aceitas há mais de uma janela;
2. conta as que sobraram;
3. se já há `maxAttempts`, recusa e informa em quanto tempo abre uma vaga;
4. senão, registra a requisição atual e a aceita.

É uma janela deslizante: só entram na conta as requisições **aceitas**, cada uma por exatamente
uma janela. O relógio é o do Redis (`TIME`), um só para todos os servidores de aplicação.

Medido depois da troca: 40 rajadas de 12 cadastros simultâneos com o mesmo token, disparadas em
cinco pontos do segundo (x,03, x,50, x,80, x,90 e x,97); em todas, exatamente 5 aceitos. A suíte
cobre o mesmo com 25 processos concorrentes disputando 3 vagas
(`tests/Feature/Api/ExactRateLimitTest.php`).

O que isso significa na prática:

- O teto vale com qualquer número de servidores de aplicação e em qualquer nível de concorrência.
- Depende do Redis (7 ou mais recente, cliente phpredis). Se o Redis estiver fora do ar, a rota
  responde erro em vez de aceitar sem limite.
- Os testes do `throttle.exact` usam o Redis e o tempo de verdade: `freezeTime()` não os afeta, e
  dois deles esperam pouco mais de um segundo.
- O `throttle` do framework continua nos outros limites (`api`, `internal`, `login`, `token`),
  onde uma requisição a mais sob rajada não muda nada. Para tornar exato qualquer um deles, basta
  trocar `throttle:<nome>` por `throttle.exact:<nome>` na rota.
