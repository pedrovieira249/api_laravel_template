# ADR 0008: Dois mecanismos de autenticação: Passport para usuários, Sanctum para APIs internas

- **Status:** aceita
- **Data:** 2026-11-11

## Contexto

Uma API deste template atende dois públicos com necessidades diferentes:

1. **Usuários externos**: pessoas, por meio de aplicações web e mobile. O token trafega por redes
   e dispositivos que não controlamos, então precisa ter vida curta (5 minutos) e ainda assim não
   obrigar a pessoa a digitar a senha a cada 5 minutos.
2. **APIs internas**: outros sistemas da casa, em comunicação serviço a serviço. Não há pessoa,
   não há senha, o tráfego é de rede interna e o volume é maior. O token pode viver mais (1 hora)
   e o "login" é a apresentação de uma credencial de máquina.

Um mecanismo só atenderia mal a um dos dois. E misturá-los traz um risco concreto: um token emitido
para um sistema ser aceito em uma rota de usuário (ou o contrário).

## Decisão

Cada público tem o seu pacote, o seu model, o seu guard e o seu grupo de rotas.

| | Usuários externos | APIs internas |
| --- | --- | --- |
| Pacote | Laravel **Passport** (OAuth2) | Laravel **Sanctum** |
| Quem autentica | `App\Models\User` | `App\Models\ServiceClient` |
| Guard (`config/auth.php`) | `api` | `internal` |
| Middleware de rota | `auth:api` | `auth:internal` |
| Credencial | e-mail + senha | `client_id` + `client_secret` |
| Token | JWT assinado (RS256) | token opaco (hash no banco) |
| Validade | **5 min** (`AUTH_USER_TOKEN_TTL`) | **1 h** (`AUTH_SERVICE_TOKEN_TTL`) |
| Renovação | refresh token (7 dias, `AUTH_USER_REFRESH_TOKEN_TTL`) | pedir outro com as mesmas credenciais |
| Autorização | perfis e permissões (ADR 0009) | abilities do token |
| Rotas | `/api/v1/...` | `/api/v1/internal/...` |

**Usuários (Passport)**

- O cliente não fala OAuth. A API expõe `POST /auth/login` e `POST /auth/refresh`, e
  `App\Auth\Users\UserTokenIssuer` faz a requisição OAuth por dentro, em memória (password grant e
  refresh token grant), contra o servidor de autorização do Passport. As rotas `/oauth/*` do
  Passport ficam desligadas (`Passport::ignoreRoutes()`).
- Existe um único client OAuth, "da casa" (`App\Auth\Users\FirstPartyClient`), com id fixo em
  `config/identity.php`. É público (sem segredo) porque nunca sai do servidor.
- O refresh token **rotaciona**: cada uso devolve um par novo e revoga o anterior (access e
  refresh). Um refresh token roubado e já usado pelo dono não serve mais.
- Logout revoga o access token da requisição **e** o refresh token emitido com ele.
- O token não carrega perfis nem permissões (sem scopes). Eles são lidos do banco a cada
  requisição, então mudar o acesso de alguém vale na chamada seguinte.

**APIs internas (Sanctum)**

- Cada sistema é um `ServiceClient`: um nome, o hash de um segredo e uma lista de abilities
  (`App\Enums\ServiceAbility`). É criado por `php artisan service-client:create` ou pelo endpoint
  de administração; o segredo aparece uma única vez.
- `POST /internal/auth/token` troca `client_id` + `client_secret` por um token com `expires_at` em
  1 hora e com as abilities do cliente. Não há refresh token: expirou, pede outro.
- Cada chamada emite um token novo sem invalidar os anteriores (um serviço roda em várias
  instâncias). Revogar o cliente apaga todos os tokens dele na hora.
- `config/sanctum.php`: `guard => []` (só Bearer token, sem sessão de SPA), `routes => false` e
  `expiration` igual ao TTL, como segunda trava aplicada pelo guard.

**Isolamento**

- Os dois pacotes têm um trait `HasApiTokens` com os mesmos métodos. O do Passport fica em `User`,
  o do Sanctum em `ServiceClient`: sem conflito e sem ambiguidade.
- O guard `internal` usa o provider `service_clients`, então recusa um token cujo dono não seja um
  `ServiceClient`. O guard `api` só entende JWT do Passport.
- Architecture tests (`tests/Architecture/AuthenticationTest.php`) confinam `Laravel\Passport` ao
  lado de usuários e `Laravel\Sanctum` ao lado interno. `tests/Feature/Api/RouteProtectionTest.php`
  garante que nenhuma rota mistura `role`/`permission` com `auth:internal` nem `ability` com
  `auth:api`, e que toda rota fora de uma lista explícita de públicas exige autenticação.

## Consequências

- Um token vazado de usuário vale no máximo 5 minutos; o que mantém a sessão é o refresh token,
  que é de uso único e revogável.
- O cliente precisa tratar o 401: chamar `/auth/refresh`, repetir a requisição e, se o refresh
  também falhar, mandar a pessoa para o login. É o custo do token curto.
- Access tokens de 5 minutos geram muitas linhas em `oauth_access_tokens`; o agendador roda
  `passport:purge` e `sanctum:prune-expired` todo dia (`routes/console.php`).
- O Passport exige um par de chaves RSA. `php artisan auth:install` as gera em `storage/`; em
  produção com mais de um servidor, injete-as por `PASSPORT_PRIVATE_KEY` / `PASSPORT_PUBLIC_KEY`.
- O password grant é desaconselhado pelo OAuth 2.1 para clients de terceiros. Aqui ele é usado só
  pela própria API, a favor do próprio usuário, e nunca fica exposto. Se um dia terceiros
  precisarem de OAuth de verdade (authorization code + PKCE), reative as rotas do Passport
  removendo `Passport::ignoreRoutes()` e forneça a tela de consentimento; o resto continua igual.
- O relógio que valida o JWT é o do sistema, não o do Carbon: nos testes, `travelTo()` não faz um
  token de usuário vencer (ver `tests/Feature/Api/V1/Auth/TokenLifetimeTest.php`).

## Referências

- [docs/guide/02-autenticacao.md](../guide/02-autenticacao.md): os fluxos passo a passo, com exemplos.
- Laravel Passport: https://laravel.com/docs/13.x/passport · Sanctum: https://laravel.com/docs/13.x/sanctum
