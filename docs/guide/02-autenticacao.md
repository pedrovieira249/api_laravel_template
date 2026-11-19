# Autenticação: Passport para usuários, Sanctum para APIs internas

A API tem dois públicos e um mecanismo para cada um. A decisão e os motivos estão na
[ADR 0008](../adr/0008-dual-authentication-passport-and-sanctum.md); este guia mostra como usar.

| | Usuários externos | APIs internas |
| --- | --- | --- |
| Pacote | Laravel Passport | Laravel Sanctum |
| Model | [`App\Models\User`](../../app/Models/User.php) | [`App\Models\ServiceClient`](../../app/Models/ServiceClient.php) |
| Middleware | `auth:api` | `auth:internal` |
| Credencial | e-mail + senha | `client_id` + `client_secret` |
| Validade do token | 5 minutos + refresh token (7 dias) | 1 hora, sem refresh |
| Autorização | `role:` e `permission:` ([guia 03](03-permissoes.md)) | `ability:` |
| Rotas | `/api/v1/...` | `/api/v1/internal/...` |

Os prazos ficam em [`config/identity.php`](../../config/identity.php):

```dotenv
AUTH_USER_TOKEN_TTL=5              # minutos, access token de usuário
AUTH_USER_REFRESH_TOKEN_TTL=10080  # minutos, refresh token (7 dias)
AUTH_SERVICE_TOKEN_TTL=60          # minutos, token de serviço
```

## 1. Usuários externos (Passport)

### Endpoints

| Método | Rota | Auth | O que faz |
| --- | --- | --- | --- |
| POST | `/api/v1/auth/login` | pública | E-mail + senha → access token + refresh token |
| POST | `/api/v1/auth/refresh` | pública | Refresh token → par novo (o antigo deixa de valer) |
| POST | `/api/v1/auth/logout` | `auth:api` | Revoga o access token da requisição e o refresh token dele |
| GET | `/api/v1/auth/me` | `auth:api` | Dados, perfis e permissões efetivas do usuário |

O cadastro de usuários não está nesta lista: ele é fechado e fica do lado das APIs internas
([seção 2](#registrando-usuários)).

### Login

```bash
curl -s http://localhost:8080/api/v1/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@example.test","password":"password"}'
```

```json
{
  "data": {
    "id": "01a1cbe9-b3b6-710b-ac41-5a38bf2934e7",
    "name": "Admin",
    "email": "admin@example.test",
    "roles": ["admin"],
    "permissions": ["users.read", "users.manage", "roles.read", "roles.manage"],
    "created_at": "2026-11-11T18:40:53+00:00"
  },
  "token_type": "Bearer",
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
  "refresh_token": "def50200a7b200f87feabc01dc3f8bbd...",
  "expires_in": 300
}
```

Credenciais erradas respondem **422** com `errors.email` (a mesma mensagem para e-mail inexistente
e senha errada). Mais de 5 tentativas por minuto para o mesmo e-mail + IP respondem **429**.

### Usando o token

```bash
curl -s http://localhost:8080/api/v1/auth/me -H "Authorization: Bearer $ACCESS_TOKEN"
```

Sem token, com token inválido ou com token expirado a resposta é **401**.

### Renovando (o fluxo que o cliente precisa implementar)

O access token dura 5 minutos. O cliente não pede a senha de novo: troca o refresh token.

```bash
curl -s http://localhost:8080/api/v1/auth/refresh \
  -H 'Content-Type: application/json' \
  -d "{\"refresh_token\":\"$REFRESH_TOKEN\"}"
```

```json
{ "token_type": "Bearer", "access_token": "eyJ...", "refresh_token": "def502...", "expires_in": 300 }
```

Regras:

- O refresh token **rotaciona**: a resposta traz um refresh token novo e o antigo deixa de valer.
  Guarde sempre o mais recente.
- O access token anterior também é revogado no refresh.
- Refresh token inválido, expirado ou já usado responde **401**. Aí só resta o login.

Receita para o cliente (interceptor HTTP):

1. Mande toda requisição com `Authorization: Bearer <access_token>`.
2. Se vier **401**, chame `/auth/refresh` uma vez, guarde o par novo e repita a requisição.
3. Se o refresh também der **401**, descarte os tokens e leve a pessoa ao login.
4. Evite duas renovações simultâneas (várias abas, várias requisições): serialize o passo 2. A
   segunda chamada com o mesmo refresh token falharia, porque ele já foi usado.

### Como funciona por dentro

```
POST /auth/login ─▶ LoginController ─▶ AuthenticateUser ─▶ UserTokenIssuer
                                                                │  monta, em memória, um pedido OAuth
                                                                │  (password grant, client "da casa")
                                                                ▼
                                                  AuthorizationServer do Passport
                                                  valida a senha, grava os tokens, assina o JWT
```

- [`UserTokenIssuer`](../../app/Auth/Users/UserTokenIssuer.php) é o único ponto que fala com o
  servidor OAuth. Nenhuma chamada HTTP acontece: é uma chamada de método.
- As rotas `/oauth/*` do Passport estão desligadas (`Passport::ignoreRoutes()` no
  [`AppServiceProvider`](../../app/Providers/AppServiceProvider.php)).
- [`FirstPartyClient`](../../app/Auth/Users/FirstPartyClient.php) é o client OAuth usado nesses
  pedidos. É criado pelo `php artisan auth:install`.
- Os prazos são configurados em `AppServiceProvider::configureUserTokens()`.
- O JWT carrega só o id do usuário (`sub`). Perfis e permissões são lidos do banco a cada
  requisição.

### Em um controller

```php
use Illuminate\Container\Attributes\CurrentUser;

public function __invoke(#[CurrentUser('api')] User $user): UserResource
```

`#[CurrentUser('api')]` injeta o usuário do guard `api` já tipado. A rota precisa estar sob
`auth:api`.

## 2. APIs internas (Sanctum)

### Cadastrando um sistema

```bash
make artisan cmd="service-client:create billing-api --ability='*'"
```

```
client_id ............ 01a1cbea-ebb1-72e4-986a-47f43f7eaa60
client_secret ........ m3Zq...   (48 caracteres)
abilities ............ *
```

O `client_secret` aparece **uma única vez**: o banco guarda só o hash. Outros comandos:

```bash
make artisan cmd="service-client:list"
make artisan cmd="service-client:revoke billing-api"   # por nome ou client_id
```

Um administrador também pode fazer o mesmo pela API (`/api/v1/admin/service-clients`).

As abilities disponíveis são os cases de [`App\Enums\ServiceAbility`](../../app/Enums/ServiceAbility.php).
De fábrica existem duas: `*` (acesso total) e `users.register` (criar contas de usuário). Para
restringir um sistema ao que ele precisa, acrescente um case por capacidade
(`case InvoicesRead = 'invoices.read';`), exija-o nas rotas com `ability:` e cadastre o cliente com
`--ability=invoices.read`.

### Endpoints

| Método | Rota | Auth | O que faz |
| --- | --- | --- | --- |
| POST | `/api/v1/internal/auth/token` | pública | `client_id` + `client_secret` → token de 1 hora |
| GET | `/api/v1/internal/auth/me` | `auth:internal` | Identidade e abilities do cliente |
| POST | `/api/v1/internal/auth/register` | `auth:internal` + `ability:users.register` | Cria uma conta de usuário (máx. 5 por segundo por cliente) |
| DELETE | `/api/v1/internal/auth/token` | `auth:internal` | Descarta o token da requisição |

### Obtendo e usando o token

```bash
curl -s http://localhost:8080/api/v1/internal/auth/token \
  -H 'Content-Type: application/json' \
  -d '{"client_id":"01a1cbea-...","client_secret":"m3Zq..."}'
```

```json
{ "token_type": "Bearer", "access_token": "1|Jx8...", "expires_in": 3600, "abilities": ["*"] }
```

```bash
curl -s http://localhost:8080/api/v1/internal/auth/me -H "Authorization: Bearer $SERVICE_TOKEN"
```

Receita para o serviço consumidor:

1. Peça um token na inicialização e guarde-o em memória com o horário de expiração.
2. Renove um pouco antes de `expires_in` (ou ao receber **401**), repetindo a mesma chamada. Não
   há refresh token.
3. Cada instância do serviço pede o seu: tokens são independentes.

Credenciais erradas, cliente inexistente e cliente revogado respondem o mesmo **401**.

### Registrando usuários

O cadastro não é público ([ADR 0011](../adr/0011-registration-behind-service-token.md)): quem cria
contas é um sistema da casa (site, BFF), com um token de serviço que carregue `users.register`.

```bash
make artisan cmd="service-client:create site-cadastro --ability=users.register"
```

```bash
curl -s http://localhost:8080/api/v1/internal/auth/register \
  -H "Authorization: Bearer $SERVICE_TOKEN" -H 'Content-Type: application/json' \
  -d '{"name":"Ana","email":"ana@empresa.com","password":"secret-123","password_confirmation":"secret-123"}'
```

A resposta (201) tem a mesma forma do login: os dados do usuário, com o perfil padrão `user`, e o
par de tokens dele, para o sistema chamador já deixá-lo logado.

| Situação | Resposta |
| --- | --- |
| Sem token, ou com token de usuário (mesmo de admin) | 401 |
| Token de serviço sem `users.register` | 403 |
| Mais de 5 cadastros no mesmo segundo pelo mesmo cliente | 429, com `Retry-After` |
| `POST /api/v1/auth/register` (a rota pública antiga) | 404 |

O limite é do limiter `register`: o teto em [`config/api.php`](../../config/api.php)
(`API_REGISTER_RATE_LIMIT`), a janela e a chave em
[`AppServiceProvider`](../../app/Providers/AppServiceProvider.php) e o `throttle.exact:register` na
rota. O `throttle.exact` é um middleware do template com janela deslizante e contagem atômica no
Redis: em nenhum intervalo de 1 segundo passam mais de 5 cadastros do mesmo cliente, cheguem eles
juntos ou na virada do segundo. O `throttle` do framework não garante isso (ADR 0011).

### Protegendo rotas internas

```php
Route::prefix('internal')->group(function (): void {
    Route::middleware(['auth:internal', 'throttle:internal'])->group(function (): void {
        Route::get('invoices', ListInvoicesController::class)->middleware('ability:invoices.read');
    });
});
```

- `ability:a,b` exige pelo menos uma; `abilities:a,b` exige todas.
- Token sem a ability responde **403**.
- No controller: `#[CurrentUser('internal')] ServiceClient $client`.
- O mesmo controller pode atender uma rota de usuário (com `permission:`) e uma rota interna
  (com `ability:`): quem pode chamar é assunto da rota, não do controller.

## 3. O que um token não abre

| Token | Rota | Resultado |
| --- | --- | --- |
| JWT de usuário | `/api/v1/internal/...` | 401 |
| Token de serviço | `/api/v1/auth/me`, `/api/v1/admin/...` | 401 |
| Qualquer um, expirado ou revogado | qualquer rota protegida | 401 |

Isso é verificado por testes
([`InternalRoutesTest`](../../tests/Feature/Api/V1/Internal/InternalRoutesTest.php)) e por regras
de arquitetura ([`AuthenticationTest`](../../tests/Architecture/AuthenticationTest.php)).

## 4. Instalação e operação

`php artisan auth:install` (idempotente; rode na instalação e em todo deploy, depois do `migrate`):

1. gera as chaves RSA do Passport em `storage/oauth-*.key`, se não existirem e não vierem do ambiente;
2. cria o client OAuth da casa;
3. sincroniza o catálogo de permissões e cria os perfis de sistema.

Limpeza automática (serviço `scheduler`, [`routes/console.php`](../../routes/console.php)):
`passport:purge` e `sanctum:prune-expired` rodam todo dia e apagam tokens expirados ou revogados
há mais de 24 horas.

Produção: veja [06-producao.md](06-producao.md) (chaves por variável de ambiente, primeiro
administrador).

## 5. Nos testes

Helpers em [`tests/Pest.php`](../../tests/Pest.php):

```php
actingAsUser(User::factory()->admin()->create());                         // guard api, sem token real
actingAsUser(User::factory()->withPermissions(PermissionName::UsersRead)->create());
actingAsService(ServiceClient::factory()->create(), ['invoices.read']);   // guard internal
$tokens = loginAs($user);                                                 // login real, devolve os tokens
freshRequest();                                                           // entre duas requisições com tokens diferentes
```

Use `actingAs*` na maioria dos testes e `loginAs` só quando o assunto do teste for o próprio token.
