#!/usr/bin/env bash
# =============================================================================
# API Laravel Template: instalação local completa, do zero, em uma máquina com Docker.
#
#   ./install.sh                  instala tudo e roda os testes no fim
#   ./install.sh --name=billing   define o nome do projeto ao criar o .env
#                                 (containers, rede, volumes, imagem e APP_NAME)
#   ./install.sh --no-tests       pula os testes
#   ./install.sh --fresh          recria o banco (apaga dados locais) antes de migrar
#
# O script é idempotente: pode ser executado de novo a qualquer momento.
# Cada etapa explica o que faz; leia o README.md para o "porquê".
# =============================================================================
set -Eeuo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")"

RUN_TESTS=1
FRESH=0
PROJECT_NAME=""
for arg in "$@"; do
  case "$arg" in
    --no-tests) RUN_TESTS=0 ;;
    --fresh) FRESH=1 ;;
    --name=*) PROJECT_NAME="${arg#--name=}" ;;
    -h|--help) sed -n '2,13p' "$0"; exit 0 ;;
    *) echo "Opção desconhecida: $arg" >&2; exit 1 ;;
  esac
done

bold()  { printf '\033[1m%s\033[0m\n' "$*"; }
ok()    { printf '  \033[32m✔\033[0m %s\n' "$*"; }
warn()  { printf '  \033[33m!\033[0m %s\n' "$*"; }
fail()  { printf '  \033[31m✖\033[0m %s\n' "$*" >&2; exit 1; }
step()  { printf '\n\033[1;34m[%s/10]\033[0m \033[1m%s\033[0m\n' "$1" "$2"; }

need() {
  command -v "$1" >/dev/null 2>&1 || fail "'$1' não encontrado. $2"
}

# Lê uma variável do .env (sem aspas), ou devolve o default.
env_value() {
  local value
  value="$(grep -E "^$1=" .env 2>/dev/null | tail -n1 | cut -d= -f2- | tr -d '"' || true)"
  printf '%s' "${value:-$2}"
}

# --- 1. pré-requisitos --------------------------------------------------------------
step 1 "Verificando pré-requisitos"
need docker "Instale o Docker Engine ou Docker Desktop: https://docs.docker.com/get-docker/"
docker compose version >/dev/null 2>&1 || fail "Docker Compose v2 não encontrado (comando 'docker compose')."
docker info >/dev/null 2>&1 || fail "O daemon do Docker não está rodando (ou seu usuário não tem permissão)."
ok "Docker $(docker version --format '{{.Server.Version}}' 2>/dev/null || echo '?') · Compose $(docker compose version --short)"
warn "PHP e Composer NÃO precisam estar instalados na sua máquina: tudo roda nos containers."

# --- 2. .env --------------------------------------------------------------------------
step 2 "Arquivo .env"
if [[ ! -f .env ]]; then
  cp .env.example .env
  ok ".env criado a partir de .env.example"

  if [[ -n "$PROJECT_NAME" ]]; then
    # O nome vira o prefixo dos containers (slug) e o APP_NAME (como digitado).
    slug="$(printf '%s' "$PROJECT_NAME" | tr '[:upper:]' '[:lower:]' | sed -E 's/[^a-z0-9]+/-/g; s/^-+|-+$//g')"
    [[ -n "$slug" ]] || fail "--name precisa ter ao menos uma letra ou dígito."
    sed -i.bak -E "s|^COMPOSE_PROJECT_NAME=.*|COMPOSE_PROJECT_NAME=${slug}|; s|^APP_NAME=.*|APP_NAME=\"${PROJECT_NAME}\"|" .env && rm -f .env.bak
    ok "projeto nomeado: APP_NAME=\"${PROJECT_NAME}\" · COMPOSE_PROJECT_NAME=${slug}"
  fi
else
  ok ".env já existe (mantido)"
  [[ -z "$PROJECT_NAME" ]] || warn "--name ignorado: o .env já existe. Edite APP_NAME e COMPOSE_PROJECT_NAME nele."
fi

PROJECT="$(env_value COMPOSE_PROJECT_NAME api-laravel-template)"
PORT="$(env_value APP_PORT 8080)"
MAILPIT_PORT="$(env_value FORWARD_MAILPIT_PORT 8025)"

# UID/GID do host entram no build da imagem: arquivos criados no container pertencem a você.
export UID GID="$(id -g)"

# --- 3. imagem e containers -----------------------------------------------------------
step 3 "Construindo a imagem do PHP (php:8.5-fpm-alpine + extensões)"
docker compose build app
ok "imagem ${PROJECT}/app:dev pronta"

step 4 "Subindo os serviços (app, nginx, mysql, redis, mailpit, queue, scheduler)"
docker compose up -d || fail "Não foi possível subir os containers. Porta ocupada? Ajuste APP_PORT / FORWARD_*_PORT no .env."
ok "containers no ar"

printf '  aguardando MySQL e Redis ficarem saudáveis'
for _ in $(seq 1 60); do
  if [[ "$(docker compose ps --format '{{.Service}} {{.Health}}' mysql redis 2>/dev/null | grep -c healthy)" == "2" ]]; then
    break
  fi
  printf '.'; sleep 2
done
echo
ok "mysql e redis saudáveis"

# --- 5. dependências PHP ------------------------------------------------------------
step 5 "Instalando dependências do Composer (dentro do container)"
docker compose exec -T app composer install --no-interaction --prefer-dist
ok "vendor/ instalado"

# --- 6. chave da aplicação -----------------------------------------------------------
step 6 "Chave da aplicação (APP_KEY)"
if grep -qE '^APP_KEY=$' .env; then
  docker compose exec -T app php artisan key:generate --no-interaction
  ok "APP_KEY gerada"
else
  ok "APP_KEY já definida"
fi

# --- 7. banco de dados -----------------------------------------------------------------
step 7 "Migrations"
if [[ "$FRESH" == "1" ]]; then
  docker compose exec -T app php artisan migrate:fresh --no-interaction
else
  docker compose exec -T app php artisan migrate --no-interaction
fi
ok "schema criado"

# --- 8. autenticação --------------------------------------------------------------------
step 8 "Autenticação (chaves do Passport, client OAuth, permissões e perfis de sistema)"
docker compose exec -T app php artisan auth:install --no-interaction
docker compose exec -T app php artisan db:seed --no-interaction
ok "pronto para login. Usuários de demonstração (senha: password):"
ok "  admin@example.test · manager@example.test · user@example.test"

# --- 9. verificação ---------------------------------------------------------------------
step 9 "Verificando a aplicação"
docker compose exec -T app php artisan about --only=environment | sed 's/^/  /'
HTTP_CODE="$(curl -s -o /dev/null -w '%{http_code}' "http://localhost:${PORT}/up" || true)"
[[ "$HTTP_CODE" == "200" ]] && ok "http://localhost:${PORT}/up respondeu 200" || warn "/up respondeu '$HTTP_CODE' (nginx ainda subindo? tente de novo em alguns segundos)"

# --- 10. testes --------------------------------------------------------------------------
step 10 "Testes"
if [[ "$RUN_TESTS" == "1" ]]; then
  docker compose exec -T app ./vendor/bin/pest --compact
  ok "suíte verde"
else
  warn "testes pulados (--no-tests)"
fi

cat <<EOF

$(bold "Pronto.")

  API ............... http://localhost:${PORT}/api/v1
  OpenAPI (UI) ...... http://localhost:${PORT}/docs/api
  Horizon (filas) ... http://localhost:${PORT}/horizon
  Pulse ............. http://localhost:${PORT}/pulse
  Telescope ......... http://localhost:${PORT}/telescope
  Mailpit (e-mails) . http://localhost:${MAILPIT_PORT}

  Próximo passo: README.md → "Primeiros passos" (login, refresh, rota protegida por perfil).
  Comandos do dia a dia: make help
EOF
