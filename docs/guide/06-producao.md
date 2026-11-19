# Levando para produção

## Imagem

```bash
make build-prod     # docker build --target production -t <projeto>/app:prod .
```

O estágio `production` de [`docker/php/Dockerfile`](../../docker/php/Dockerfile) copia o código
para a imagem, instala o `vendor` sem dependências de desenvolvimento, usa `php.ini-production`,
desliga a checagem de timestamps do OPcache e roda como usuário sem shell. Chaves e `.env` **não**
entram na imagem ([`.dockerignore`](../../.dockerignore)).

A mesma imagem atende três papéis, mudando o comando: `php-fpm` (web), `php artisan horizon`
(filas) e `php artisan schedule:work` (agendador). Sem o agendador, tokens expirados nunca são
apagados.

## Variáveis de ambiente que importam

| Variável | Valor em produção |
| --- | --- |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` (com `true`, erros 500 expõem a mensagem da exceção) |
| `APP_KEY` | Gerada uma vez (`php artisan key:generate --show`) e guardada como segredo |
| `APP_URL` | A URL pública |
| `API_PROBLEM_TYPE_BASE` | A base dos tipos de erro; a mesma em todos os ambientes |
| `PASSPORT_PRIVATE_KEY`, `PASSPORT_PUBLIC_KEY` | O par RSA que assina os JWT (ver abaixo) |
| `AUTH_USER_TOKEN_TTL`, `AUTH_USER_REFRESH_TOKEN_TTL`, `AUTH_SERVICE_TOKEN_TTL` | Prazos dos tokens, em minutos (5, 10080, 60) |
| `API_RATE_LIMIT`, `API_INTERNAL_RATE_LIMIT`, `API_LOGIN_RATE_LIMIT`, `API_TOKEN_RATE_LIMIT` | Requisições por minuto |
| `API_REGISTER_RATE_LIMIT` | Cadastros de usuário por **segundo**, por cliente de serviço (5) |
| `DB_*`, `REDIS_*`, `MAIL_*` | Os serviços reais |
| `LOG_LEVEL` | `info` ou `warning` |

### Chaves do Passport

Em desenvolvimento, `auth:install` grava o par em `storage/oauth-private.key` e
`storage/oauth-public.key`. Em produção, com mais de uma instância, todas precisam do **mesmo**
par, e ele não pode se perder em um redeploy (todos os tokens emitidos deixariam de valer). Gere
uma vez e injete como segredo:

```bash
php artisan passport:keys            # em um ambiente seguro; copie o conteúdo dos dois arquivos
```

```dotenv
PASSPORT_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----"
PASSPORT_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----"
```

Com as variáveis definidas, `auth:install` não gera arquivos.

## Sequência de deploy

```bash
php artisan migrate --force
php artisan auth:install            # client OAuth + permissões + perfis de sistema (idempotente)
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan horizon:terminate       # os workers reiniciam com o código novo
```

`auth:install` roda em **todo** deploy: é ele que leva ao banco as permissões novas do enum.

Não rode `db:seed` em produção para criar usuários: os de demonstração têm senha conhecida (o
`DatabaseSeeder` os pula quando `APP_ENV=production`).

## O primeiro administrador

Em um banco novo não há usuários, e o cadastro não é público
([ADR 0011](../adr/0011-registration-behind-service-token.md)). O primeiro administrador nasce em
três passos:

```bash
# 1. Um cliente de serviço que pode cadastrar (guarde client_id e client_secret)
php artisan service-client:create onboarding --ability=users.register

# 2. Com o token desse cliente, crie a conta (ver docs/guide/02-autenticacao.md#registrando-usuários)
#    POST /api/v1/internal/auth/token  ->  POST /api/v1/internal/auth/register

# 3. Promova a conta
php artisan user:assign-role ana@empresa.com admin
```

A partir daí ela gerencia perfis e usuários pela API. Se o cliente `onboarding` foi criado só para
isso, revogue-o: `php artisan service-client:revoke onboarding`.

## Credenciais de APIs internas

```bash
php artisan service-client:create billing-api --ability=invoices.read
```

A ability precisa ser um case de `App\Enums\ServiceAbility` (de fábrica existem `*` e
`users.register`). Entregue
`client_id` e `client_secret` ao outro time por um canal de segredos. Para trocar um
segredo: crie um cliente novo, migre o consumidor e revogue o antigo
(`service-client:revoke`).

## Dashboards

Horizon (`/horizon`), Pulse (`/pulse`), Telescope (`/telescope`, só em `local`) e a documentação
OpenAPI (`/docs/api`) usam o middleware `web`. Fora de `local` só abrem para um usuário
administrador autenticado, e a API não tem tela de login: na prática ficam fechados. Para usá-los
em produção, proteja-os na borda (VPN, IP allowlist, autenticação do proxy) e ajuste os gates
`viewHorizon`, `viewPulse` e `viewApiDocs` nos providers.

## Lista de conferência

- [ ] `APP_DEBUG=false` e `APP_ENV=production`.
- [ ] `APP_KEY` e as chaves do Passport guardadas como segredo, fora da imagem e do repositório.
- [ ] HTTPS na borda; tokens nunca trafegam em HTTP puro.
- [ ] Os três processos no ar: web, `horizon` e `schedule:work`.
- [ ] `auth:install` no pipeline de deploy.
- [ ] Um administrador criado com `user:assign-role`.
- [ ] `GET /up` no health check do orquestrador.
- [ ] Backups do banco: perder `oauth_clients`, `roles` ou `service_clients` derruba a autenticação.
