# API Laravel Template

Ponto de partida para APIs REST em **Laravel 13 / PHP 8.5**. Em vez de começar de um
`laravel new` vazio, um projeto novo já nasce com autenticação, permissões, padrões de arquitetura,
ambiente Docker e portões de qualidade prontos e testados.

**O que vem pronto**

- **Arquitetura hexagonal** (Ports & Adapters) em monólito modular: núcleo em `src/` (`Core\`),
  livre de framework, com as regras garantidas por testes de arquitetura.
- **Duas autenticações, uma para cada público:**
  - **Passport** para usuários externos: access token de **5 minutos** + refresh token;
  - **Sanctum** para comunicação entre APIs internas: token de **1 hora**.
- **Perfis e permissões** (RBAC em tabelas próprias) aplicados por **middleware nos grupos de
  rota**: `role:admin`, `permission:users.read`.
- **Cadastro de usuários fechado:** só um sistema interno, com token e ability próprios, cria
  contas, a no máximo 5 por segundo.
- Erros em **Problem Details** (RFC 9457), UUID v7, request id nos logs, limite de requisições,
  idempotência, mensagens em pt-BR por `Accept-Language`.
- Ambiente **Docker** completo (PHP-FPM, Nginx, MySQL 9.7, Redis 8, Mailpit, Horizon, agendador)
  sem PHP instalado na máquina.
- **Qualidade de fábrica:** Pest 5 (mais de 260 testes), Larastan nível 9, Pint, Rector, cobertura
  ≥ 80%, type coverage 100%, CI no GitHub Actions, OpenAPI gerado do código.

O template não traz módulo de negócio: `src/` começa só com o kernel compartilhado. O primeiro
módulo nasce seguindo [docs/guide/04-criando-um-modulo.md](docs/guide/04-criando-um-modulo.md).

## Sumário

1. [Requisitos](#1-requisitos)
2. [Instalação passo a passo](#2-instalação-passo-a-passo)
3. [Configuração](#3-configuração)
4. [Testando a API de ponta a ponta](#4-testando-a-api-de-ponta-a-ponta)
5. [Testes automatizados e qualidade](#5-testes-automatizados-e-qualidade)
6. [Protegendo as suas rotas](#6-protegendo-as-suas-rotas)
7. [Criando o primeiro módulo](#7-criando-o-primeiro-módulo)
8. [Comandos do dia a dia](#8-comandos-do-dia-a-dia)
9. [Estrutura do projeto](#9-estrutura-do-projeto)
10. [Produção](#10-produção)
11. [Solução de problemas](#11-solução-de-problemas)
12. [Documentação e stack](#12-documentação-e-stack)

## 1. Requisitos

- Docker Engine 24+ (ou Docker Desktop) com Docker Compose v2.
- Git, `make` e `curl`.
- `python3`, só para os exemplos da seção 4 (extrair os tokens das respostas).

PHP, Composer, MySQL e Redis **não** precisam estar instalados: tudo roda nos containers.

## 2. Instalação passo a passo

### 2.1 Obter o código

```bash
git clone https://github.com/pedrovieira249/api_laravel_template.git minha-api
cd minha-api
```

Para começar um projeto novo a partir do template (sem o histórico dele):

```bash
rm -rf .git && git init -b main
```

### 2.2 Escolher o nome e as portas

O instalador cria o `.env` a partir do `.env.example`. Duas coisas valem ser decididas antes:

- **Nome do projeto.** Passe `--name` ao instalador (passo 2.3). Ele define `APP_NAME` e
  `COMPOSE_PROJECT_NAME`, que é o prefixo dos containers, da rede, dos volumes e da imagem.
- **Portas.** As padrão estão na tabela abaixo. Se alguma já estiver em uso na sua máquina (outro
  projeto, um MySQL local), crie o `.env` antes e troque:

  ```bash
  cp .env.example .env
  # edite APP_PORT, FORWARD_*_PORT e deixe APP_URL com a mesma porta de APP_PORT
  ```

| Serviço | Variável no `.env` | Porta padrão |
| --- | --- | --- |
| API (Nginx) | `APP_PORT` (e `APP_URL`) | 8080 |
| MySQL | `FORWARD_DB_PORT` | 3306 |
| Redis | `FORWARD_REDIS_PORT` | 6379 |
| Mailpit (caixa de e-mail de dev) | `FORWARD_MAILPIT_PORT` | 8025 |
| Mailpit (SMTP) | `FORWARD_MAILPIT_SMTP_PORT` | 1025 |

As portas não ficam no `compose.yaml`: ele lê essas variáveis do `.env` (`${APP_PORT:-8080}`).
Depois de mudar uma porta com o ambiente já no ar, rode `make up` de novo.

### 2.3 Rodar o instalador

```bash
./install.sh --name="Minha API"
```

| Opção | Efeito |
| --- | --- |
| `--name="..."` | Nome do projeto, usado só quando o `.env` ainda não existe |
| `--no-tests` | Pula a suíte de testes no final (mais rápido) |
| `--fresh` | Recria o banco local do zero (**apaga os dados locais**) |

O script é idempotente (pode rodar de novo a qualquer momento) e faz, nesta ordem:

1. confere os pré-requisitos (Docker, Compose, daemon no ar);
2. cria o `.env`, se não existir, e aplica o `--name`;
3. constrói a imagem do PHP (`php:8.5-fpm-alpine` + extensões);
4. sobe os serviços: `app`, `nginx`, `mysql`, `redis`, `mailpit`, `queue` (Horizon) e `scheduler`;
5. instala as dependências do Composer, dentro do container;
6. gera a `APP_KEY`;
7. roda as migrations;
8. prepara a autenticação (`php artisan auth:install`: chaves do Passport, client OAuth,
   permissões e perfis de sistema) e carrega os usuários de demonstração;
9. confere que a aplicação responde em `/up`;
10. roda a suíte de testes.

<details>
<summary>Instalação manual, sem o script</summary>

```bash
cp .env.example .env                 # ajuste nome e portas
make build
make up
make composer cmd="install"
make artisan cmd="key:generate"
make artisan cmd="migrate"
make auth-install                    # chaves do Passport, client OAuth, permissões, perfis
make artisan cmd="db:seed"           # usuários de demonstração (nunca em produção)
make test
```

</details>

### 2.4 Conferir que subiu

```bash
curl -s http://localhost:8080/up -o /dev/null -w '%{http_code}\n'   # 200
curl -s http://localhost:8080/                                      # nome, URL da API e da documentação
docker compose ps                                                   # 7 serviços "Up"
```

Se você mudou `APP_PORT`, troque `8080` pela sua porta (aqui e em todos os exemplos).

| O quê | Onde |
| --- | --- |
| API | http://localhost:8080/api/v1 |
| Documentação OpenAPI | http://localhost:8080/docs/api |
| Horizon (filas) | http://localhost:8080/horizon |
| Pulse (métricas) | http://localhost:8080/pulse |
| Telescope (debug, só local) | http://localhost:8080/telescope |
| Mailpit (e-mails de dev) | http://localhost:8025 |

Usuários de demonstração (senha `password`, criados só fora de produção):

| E-mail | Perfil | Pode |
| --- | --- | --- |
| `admin@example.test` | `admin` | Tudo |
| `manager@example.test` | `manager` (perfil criado como um admin criaria) | Ver usuários (`users.read`) |
| `user@example.test` | `user` (padrão de toda conta cadastrada) | Só as rotas de qualquer usuário autenticado (`/auth/me`, `/auth/logout`) |

## 3. Configuração

Tudo é configurado pelo `.env`. As variáveis próprias do template:

| Variável | Padrão | O que controla |
| --- | --- | --- |
| `APP_NAME` | `API Laravel Template` | Nome exibido pela API e pela documentação |
| `COMPOSE_PROJECT_NAME` | `api-laravel-template` | Prefixo dos containers, rede, volumes e imagem |
| `APP_PORT`, `APP_URL` | `8080` | Porta e URL da API na sua máquina |
| `FORWARD_DB_PORT`, `FORWARD_REDIS_PORT`, `FORWARD_MAILPIT_PORT`, `FORWARD_MAILPIT_SMTP_PORT` | `3306`, `6379`, `8025`, `1025` | Portas dos serviços publicadas no host |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | `app`, `app`, `secret` | Banco de desenvolvimento (o de testes é `<DB_DATABASE>_test`) |
| `AUTH_USER_TOKEN_TTL` | `5` | Minutos de vida do access token de usuário (Passport) |
| `AUTH_USER_REFRESH_TOKEN_TTL` | `10080` | Minutos de vida do refresh token (7 dias) |
| `AUTH_SERVICE_TOKEN_TTL` | `60` | Minutos de vida do token de serviço (Sanctum) |
| `API_RATE_LIMIT` | `120` | Requisições por minuto nas rotas de usuário (por usuário ou IP) |
| `API_INTERNAL_RATE_LIMIT` | `600` | Requisições por minuto nas rotas internas (por cliente de serviço) |
| `API_LOGIN_RATE_LIMIT` | `5` | Tentativas de login por minuto (por e-mail + IP) |
| `API_TOKEN_RATE_LIMIT` | `30` | Pedidos de token por minuto (refresh e token de serviço) |
| `API_REGISTER_RATE_LIMIT` | `5` | Cadastros de usuário por **segundo** (por cliente de serviço) |
| `API_PROBLEM_TYPE_BASE` | `https://api.example.com/problems` | Base do campo `type` dos erros |
| `PASSPORT_PRIVATE_KEY`, `PASSPORT_PUBLIC_KEY` | vazio | Chaves do Passport por variável de ambiente (produção) |

Onde cada assunto é configurado no código:

| Assunto | Arquivo |
| --- | --- |
| Quem pode chamar cada rota | [`routes/api.php`](routes/api.php) |
| Prazos dos tokens | [`config/identity.php`](config/identity.php) |
| Guards (`api` = Passport, `internal` = Sanctum) | [`config/auth.php`](config/auth.php) |
| Tetos de requisições | [`config/api.php`](config/api.php) |
| Limiters, Gate, regras de senha | [`app/Providers/AppServiceProvider.php`](app/Providers/AppServiceProvider.php) |
| Catálogo de permissões e de abilities | [`app/Enums/PermissionName.php`](app/Enums/PermissionName.php), [`app/Enums/ServiceAbility.php`](app/Enums/ServiceAbility.php) |

## 4. Testando a API de ponta a ponta

Um roteiro para executar no terminal, na ordem. Ele passa por tudo o que o template oferece e diz
a resposta esperada em cada passo. Prefere o Postman? Veja o [passo 4.10](#410-pelo-postman).

Prepare o terminal (ajuste a porta se mudou `APP_PORT`):

```bash
API=http://localhost:8080/api/v1
json() { python3 -c "import sys, json; print(json.load(sys.stdin)$1)"; }   # extrai um campo do JSON
status() { curl -s -o /dev/null -w '%{http_code}\n' "$@"; }                # mostra só o status HTTP
```

### 4.1 Login de usuário (Passport)

```bash
LOGIN=$(curl -s $API/auth/login -H 'Content-Type: application/json' \
  -d '{"email":"admin@example.test","password":"password"}')

echo "$LOGIN" | json "['expires_in']"          # 300 (5 minutos)
ACCESS_TOKEN=$(echo "$LOGIN" | json "['access_token']")
REFRESH_TOKEN=$(echo "$LOGIN" | json "['refresh_token']")
```

Senha errada responde **422** com `errors.email`; mais de 5 tentativas por minuto, **429**.

### 4.2 Rota autenticada

```bash
curl -s $API/auth/me -H "Authorization: Bearer $ACCESS_TOKEN"     # 200: dados, perfis e permissões
status $API/auth/me                                               # 401: sem token
```

### 4.3 Acesso por perfil e por permissão

A mesma rota, três perfis:

```bash
token_of() { curl -s $API/auth/login -H 'Content-Type: application/json' \
  -d "{\"email\":\"$1\",\"password\":\"password\"}" | json "['access_token']"; }

MANAGER_TOKEN=$(token_of manager@example.test)
USER_TOKEN=$(token_of user@example.test)

status $API/admin/users -H "Authorization: Bearer $ACCESS_TOKEN"    # 200: admin tem tudo
status $API/admin/users -H "Authorization: Bearer $MANAGER_TOKEN"   # 200: manager tem users.read
status $API/admin/users -H "Authorization: Bearer $USER_TOKEN"      # 403: user não tem
status $API/admin/roles -H "Authorization: Bearer $MANAGER_TOKEN"   # 403: manager não tem roles.read
status $API/admin/service-clients -H "Authorization: Bearer $MANAGER_TOKEN"   # 403: só o perfil admin
```

### 4.4 Criar um perfil e dar a um usuário

```bash
ROLE_ID=$(curl -s $API/admin/roles -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"name":"support","description":"Atendimento","permissions":["users.read","roles.read"]}' \
  | json "['data']['id']")                                           # 201

USER_ID=$(curl -s $API/auth/me -H "Authorization: Bearer $USER_TOKEN" | json "['data']['id']")

curl -s -X PUT $API/admin/users/$USER_ID/roles -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H 'Content-Type: application/json' -d '{"roles":["user","support"]}'   # 200

status $API/admin/users -H "Authorization: Bearer $USER_TOKEN"      # 200 agora, com o MESMO token
```

A mudança vale na requisição seguinte, sem novo login: as permissões são lidas do banco, não do
token. Para desfazer:

```bash
curl -s -X PUT $API/admin/users/$USER_ID/roles -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H 'Content-Type: application/json' -d '{"roles":["user"]}'
status -X DELETE $API/admin/roles/$ROLE_ID -H "Authorization: Bearer $ACCESS_TOKEN"   # 204
```

### 4.5 Renovar o token (refresh)

O access token dura 5 minutos. O cliente troca o refresh token por um par novo, sem senha:

```bash
REFRESH=$(curl -s $API/auth/refresh -H 'Content-Type: application/json' \
  -d "{\"refresh_token\":\"$REFRESH_TOKEN\"}")                      # 200

status $API/auth/refresh -H 'Content-Type: application/json' \
  -d "{\"refresh_token\":\"$REFRESH_TOKEN\"}"                       # 401: cada refresh token serve uma vez
status $API/auth/me -H "Authorization: Bearer $ACCESS_TOKEN"        # 401: o access token antigo foi revogado

ACCESS_TOKEN=$(echo "$REFRESH" | json "['access_token']")           # guarde sempre o par mais recente
REFRESH_TOKEN=$(echo "$REFRESH" | json "['refresh_token']")
```

### 4.6 Comunicação entre APIs (Sanctum)

Cadastre o sistema que vai consumir a API. O `client_secret` aparece **uma única vez**:

```bash
make artisan cmd="service-client:create site-cadastro --ability=users.register"
```

```bash
CLIENT_ID=<client_id mostrado>
CLIENT_SECRET=<client_secret mostrado>

SERVICE=$(curl -s $API/internal/auth/token -H 'Content-Type: application/json' \
  -d "{\"client_id\":\"$CLIENT_ID\",\"client_secret\":\"$CLIENT_SECRET\"}")

echo "$SERVICE" | json "['expires_in']"        # 3600 (1 hora)
SERVICE_TOKEN=$(echo "$SERVICE" | json "['access_token']")

status $API/internal/auth/me -H "Authorization: Bearer $SERVICE_TOKEN"   # 200
```

Os dois mundos não se misturam:

```bash
status $API/auth/me -H "Authorization: Bearer $SERVICE_TOKEN"            # 401: token de serviço em rota de usuário
status $API/internal/auth/me -H "Authorization: Bearer $ACCESS_TOKEN"    # 401: token de usuário em rota interna
```

De fábrica existem duas abilities: `*` (acesso total) e `users.register` (criar contas). As
específicas do seu domínio são cases em [`App\Enums\ServiceAbility`](app/Enums/ServiceAbility.php).

### 4.7 Cadastrar um usuário

O cadastro não é público: só um token de serviço com `users.register` cria contas.

```bash
curl -s $API/internal/auth/register -H "Authorization: Bearer $SERVICE_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"name":"Ana","email":"ana@empresa.test","password":"secret-123","password_confirmation":"secret-123"}'
# 201: a conta (perfil "user") e os tokens dela

status $API/auth/login -H 'Content-Type: application/json' \
  -d '{"email":"ana@empresa.test","password":"secret-123"}'              # 200: a conta já faz login
```

| Situação | Resposta |
| --- | --- |
| Sem token, ou com token de usuário (mesmo de admin) | 401 |
| Token de serviço sem a ability `users.register` | 403 |
| `POST /api/v1/auth/register` (rota pública, que não existe) | 404 |

A senha precisa de 8 caracteres em desenvolvimento; em produção, 12, com letras e números.

### 4.8 Limite de 5 cadastros por segundo

Oito cadastros disparados ao mesmo tempo com o mesmo token:

```bash
for i in $(seq 1 8); do
  curl -s -o /dev/null -w '%{http_code}\n' $API/internal/auth/register \
    -H "Authorization: Bearer $SERVICE_TOKEN" -H 'Content-Type: application/json' \
    -d "{\"name\":\"Teste $i\",\"email\":\"limite$i@empresa.test\",\"password\":\"secret-123\",\"password_confirmation\":\"secret-123\"}" &
done; wait
```

O resultado são cinco `201` e três `429`, em qualquer momento em que a rajada for disparada: o
limite usa uma janela deslizante, então em nenhum intervalo de 1 segundo passam mais de 5.

### 4.9 Logout

```bash
status -X POST $API/auth/logout -H "Authorization: Bearer $ACCESS_TOKEN"   # 204
status $API/auth/me -H "Authorization: Bearer $ACCESS_TOKEN"               # 401
status $API/auth/refresh -H 'Content-Type: application/json' \
  -d "{\"refresh_token\":\"$REFRESH_TOKEN\"}"                              # 401: o refresh token foi junto
```

Para limpar o que o roteiro criou: `make fresh` recria o banco local com os dados de
demonstração (**apaga tudo o que houver nele**).

### 4.10 Pelo Postman

Importe [`docs/api/api-template.postman_collection.json`](docs/api/api-template.postman_collection.json)
e [`docs/api/api-template.postman_environment.json`](docs/api/api-template.postman_environment.json).
São 21 requisições em 4 pastas, feitas para rodar em ordem (ou de uma vez, no Runner): os tokens
e os ids são capturados automaticamente de uma requisição para a outra.

- No environment, `base_url` vem como `http://localhost:8080/api/v1`. Se o seu `APP_PORT` for
  outro, ajuste.
- O access token dura 5 minutos: se demorar entre as requisições, rode "Renovar tokens".
- O "Logout" é a última requisição de propósito, porque ele revoga o token usado pelas outras.

## 5. Testes automatizados e qualidade

```bash
make test               # a suíte inteira (mais de 260 testes, cerca de 20 segundos)
make test-unit          # Unit: PHP puro, sem banco
make test-integration   # Integration: container Laravel + MySQL de teste
make test-feature       # Feature: HTTP de ponta a ponta
make test-arch          # Architecture: regras de dependência e convenções
make artisan cmd="test --filter=LoginTest"    # um arquivo ou um teste
```

Os testes usam o banco `<DB_DATABASE>_test` (criado junto com o container do MySQL) e não tocam
nos dados de desenvolvimento.

| Comando | O que garante |
| --- | --- |
| `make pint` / `make pint-test` | Estilo de código (corrige / só confere) |
| `make stan` | Larastan nível 9 |
| `make rector` / `make rector-fix` | Modernização automática (mostra / aplica) |
| `make coverage` | Cobertura de linhas ≥ 80% |
| `make type-coverage` | 100% de parâmetros, retornos e propriedades tipados |
| **`make check`** | `pint-test` + `stan` + `test`: rode antes de cada commit |
| `make ci` | O mesmo que o GitHub Actions roda ([`ci.yml`](.github/workflows/ci.yml)) |

Dois arquivos de teste funcionam como rede de segurança e merecem leitura:

- [`tests/Feature/Api/RouteProtectionTest.php`](tests/Feature/Api/RouteProtectionTest.php): falha se
  uma rota nova ficar sem autenticação, sem limite de requisições, sem nome ou citar uma permissão
  que não existe.
- [`tests/Architecture/`](tests/Architecture/): falha se o núcleo importar o framework, se um
  contexto importar outro ou se Passport e Sanctum se misturarem.

Mais em [docs/guide/05-testes-e-qualidade.md](docs/guide/05-testes-e-qualidade.md).

## 6. Protegendo as suas rotas

Tudo em [`routes/api.php`](routes/api.php): um grupo de rotas = um público + uma regra de acesso.

```php
// Usuários (Passport): por perfil...
Route::middleware(['auth:api', 'role:admin'])->group(function (): void { /* ... */ });
Route::middleware(['auth:api', 'role:admin,manager'])->group(function (): void { /* ... */ });

// ...ou por permissão (qualquer perfil que a receba)
Route::middleware(['auth:api', 'permission:users.manage'])->group(function (): void { /* ... */ });

// APIs internas (Sanctum): pela ability do token
Route::middleware(['auth:internal', 'ability:invoices.read'])->group(function (): void { /* ... */ });
```

- **Perfis** são dados: criados e editados pela API (`/api/v1/admin/roles`), sem deploy.
- **Permissões** são código: um case em [`App\Enums\PermissionName`](app/Enums/PermissionName.php)
  + `make permissions`.
- **Abilities** de serviço também são código: um case em
  [`App\Enums\ServiceAbility`](app/Enums/ServiceAbility.php).
- Sem autenticação a resposta é **401**; sem o perfil, a permissão ou a ability, **403**.

### Limite de requisições por rota

Três lugares, sempre os mesmos:

1. **O número** em [`config/api.php`](config/api.php), dentro de `rate_limits` (com a variável de
   ambiente correspondente).
2. **O limiter** em `configureRateLimiting()` do
   [`AppServiceProvider`](app/Providers/AppServiceProvider.php): a janela (`Limit::perSecond()`,
   `perMinute()`...) e a chave, isto é, quem divide o mesmo teto (IP, usuário, cliente de serviço).
3. **A rota** em [`routes/api.php`](routes/api.php): `->middleware('throttle:<nome>')`, ou
   `->middleware('throttle.exact:<nome>')` quando o teto é um requisito.

A diferença entre os dois: o `throttle` do Laravel pode deixar passar requisições a mais quando
várias chegam no mesmo instante ou na virada da janela; o `throttle.exact`, do template, usa uma
janela deslizante com contagem atômica no Redis e nunca passa do teto
([ADR 0011](docs/adr/0011-registration-behind-service-token.md)).

O cadastro de usuários é o exemplo completo: limiter `register`, 5 por segundo por cliente de
serviço, com `throttle.exact`. Acima do teto a resposta é **429** com o header `Retry-After`.

Detalhes: [autenticação](docs/guide/02-autenticacao.md) e [permissões](docs/guide/03-permissoes.md).

## 7. Criando o primeiro módulo

[docs/guide/04-criando-um-modulo.md](docs/guide/04-criando-um-modulo.md) monta um contexto de
exemplo (`Billing`, com um agregado `Invoice`) de dentro para fora, com o molde de cada peça:
entidade, value object, exceções, command e handler, read model, repositório Eloquent, controller,
rotas com permissão e os quatro níveis de teste. Ao final, `make check` cobra as mesmas regras do
resto do projeto.

## 8. Comandos do dia a dia

```bash
make help                         # todos os alvos
make up / down / logs s=app       # containers
make sh                           # bash no container da aplicação
make artisan cmd="migrate"        # qualquer comando artisan
make composer cmd="require x/y"
make routes                       # rotas da API com os middlewares de cada uma
make fresh                        # migrate:fresh --seed (apaga os dados locais)
make test / test-unit / test-integration / test-feature / test-arch
make check                        # pint + larastan + testes (antes de commitar)
make ci                           # o que o GitHub Actions roda
make auth-install                 # chaves do Passport, client OAuth, permissões e perfis de sistema
make permissions                  # sincroniza o enum de permissões com o banco
make openapi                      # exporta docs/api/openapi.json
make build-prod                   # imagem de produção
```

Comandos Artisan do template:

| Comando | Para quê |
| --- | --- |
| `auth:install` | Chaves do Passport, client OAuth, permissões e perfis de sistema (rode em todo deploy) |
| `permissions:sync` | Espelha `PermissionName` na tabela `permissions` |
| `user:assign-role {email} {role}` | Dá um perfil a um usuário (o primeiro admin nasce assim) |
| `service-client:create {name} --ability=...` | Cadastra uma API interna e mostra as credenciais |
| `service-client:list` / `service-client:revoke {client}` | Lista e revoga clientes de serviço |

## 9. Estrutura do projeto

```
app/                  Mundo Laravel: HTTP, CLI, providers e Identity
  Auth/               Actions de login, tokens e perfis (Users = Passport, Services = Sanctum)
  Console/Commands/   auth:install, permissions:sync, user:assign-role, service-client:*
  Enums/              RoleName, PermissionName, ServiceAbility
  Http/               Controllers single-action, middleware, requests, resources
  Models/             User, Role, Permission, ServiceClient
  Providers/          Rate limiting, Gate, Passport, ligação port → adapter
src/                  Núcleo (namespace Core\), livre de framework em Domain e Application
  Shared/             Kernel: Uuid, Money, AggregateRoot, ports, command/query bus
  <SeuModulo>/        Domain, Application, Infrastructure (docs/guide/04)
routes/api.php        O mapa: quem pode chamar o quê
config/identity.php   Prazos dos tokens
config/api.php        Tetos de requisições e base dos tipos de erro
database/             Migrations, factories, seeders
tests/                Unit, Integration, Feature, Architecture
docs/                 Guia, ADRs, OpenAPI, Postman
docker/, compose.yaml, Makefile, install.sh
```

Visão completa em [docs/guide/00-arquitetura.md](docs/guide/00-arquitetura.md).

## 10. Produção

O essencial, detalhado em [docs/guide/06-producao.md](docs/guide/06-producao.md):

- `make build-prod` gera a imagem (código e `vendor` dentro dela, sem dependências de dev). A
  mesma imagem roda os três processos: `php-fpm`, `php artisan horizon` e
  `php artisan schedule:work`.
- `APP_ENV=production`, `APP_DEBUG=false`, HTTPS na borda.
- As chaves do Passport vêm de `PASSPORT_PRIVATE_KEY` / `PASSPORT_PUBLIC_KEY` (as mesmas em todas
  as instâncias).
- Em todo deploy: `php artisan migrate --force` e `php artisan auth:install`.
- O primeiro administrador: crie um cliente de serviço com `users.register`, cadastre a conta e
  rode `php artisan user:assign-role email admin`.
- Não rode `db:seed` para criar usuários: os de demonstração têm senha conhecida (e o seeder os
  pula em produção).

## 11. Solução de problemas

| Sintoma | Causa provável | O que fazer |
| --- | --- | --- |
| `http://localhost:8080` não responde | O `.env` usa outra porta, ou os containers não subiram | `grep APP_PORT .env` e `docker compose ps`; use a porta do `.env` |
| `make up` falha com "port is already allocated" | Outra aplicação usa a porta | Troque `APP_PORT` / `FORWARD_*_PORT` no `.env` e rode `make up` |
| Mudei a porta no `.env` e nada mudou | Os containers ainda estão com a porta antiga | `make up` de novo (ele recria o que mudou) |
| Toda rota protegida responde 401 depois de alguns minutos | O access token dura 5 minutos | `POST /auth/refresh` com o refresh token, ou login de novo |
| Login responde 500 e o log cita `auth:install` | Falta o client OAuth ou as chaves do Passport | `make auth-install` |
| 403 em uma rota que deveria abrir | O usuário não tem o perfil ou a permissão | `GET /auth/me` mostra `roles` e `permissions`; ajuste em `/admin/users/{id}/roles` |
| 403 em rota interna | O token de serviço não tem a ability | Crie o cliente com `--ability=<ability>` |
| 429 | Limite de requisições atingido | Espere o tempo do header `Retry-After`; os tetos estão em `config/api.php` |
| 406 | O cliente não aceita JSON | Envie `Accept: application/json` (ou nenhum `Accept`) |
| Arquivos criados pelo container pertencem a outro usuário | UID/GID do host diferente de 1000 | `export UID GID=$(id -g)` e `make build` |
| Testes falham com erro de banco | O banco `<DB_DATABASE>_test` não existe (o `DB_DATABASE` mudou depois da criação do volume) | Ajuste `DB_DATABASE` em `phpunit.xml`; `docker compose down -v` recria os volumes (**apaga os dados**) |

Logs: `make logs s=app` (ou `nginx`, `mysql`, `queue`) e `storage/logs/laravel.log`.

## 12. Documentação e stack

- [docs/README.md](docs/README.md): índice do guia e das decisões de arquitetura.
- [Arquitetura](docs/guide/00-arquitetura.md) · [Shared kernel](docs/guide/01-shared-kernel.md) ·
  [Autenticação](docs/guide/02-autenticacao.md) · [Permissões](docs/guide/03-permissoes.md) ·
  [Criando um módulo](docs/guide/04-criando-um-modulo.md) ·
  [Testes e qualidade](docs/guide/05-testes-e-qualidade.md) · [Produção](docs/guide/06-producao.md)
- [ADRs](docs/adr/): o porquê de cada decisão.
- [docs/api/openapi.json](docs/api/openapi.json): referência da API (UI local em `/docs/api`).

| Camada | Tecnologia |
| --- | --- |
| Linguagem / framework | PHP 8.5, Laravel 13 |
| Autenticação | Laravel Passport 13 (usuários), Laravel Sanctum 4 (APIs internas) |
| Banco / cache / filas | MySQL 9.7, Redis 8, Laravel Horizon |
| Observabilidade | Laravel Pulse, Telescope (dev), request id no log |
| Documentação da API | Scramble (OpenAPI 3.1) |
| Testes | Pest 5: Unit, Integration, Feature, Architecture |
| Qualidade | Larastan (PHPStan nível 9), Pint, Rector |
| Ambiente | Docker Compose, Nginx, Mailpit |
