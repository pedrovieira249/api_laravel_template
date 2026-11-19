COMPOSE = docker compose
EXEC    = $(COMPOSE) exec app
# Mesmo nome que o compose.yaml usa (COMPOSE_PROJECT_NAME do .env, se existir).
PROJECT = $(or $(shell grep -s '^COMPOSE_PROJECT_NAME=' .env | cut -d= -f2),api-laravel-template)

.PHONY: help install up down build logs sh composer artisan routes test test-unit test-integration test-feature pint pint-test test-arch stan check fresh auth-install permissions rector rector-fix coverage type-coverage mutate ci build-prod openapi

help: ## Lista os alvos deste Makefile
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'

# Bind-mounted files: if missing at "up", Docker creates a directory with that name.
CONFIG_FILES = docker/nginx/default.conf docker/mysql/my.cnf

$(CONFIG_FILES):
	@mkdir -p $(@D)
	@touch $@
	@echo "Created missing placeholder: $@"

install: ## Instalação completa (./install.sh)
	./install.sh

up: $(CONFIG_FILES) ## Sobe os containers
	@echo "Configuration files checked."
	$(COMPOSE) up -d

sh: ## Bash dentro do container app
	$(EXEC) bash

artisan: ## php artisan cmd="..."
	$(EXEC) php artisan $(cmd)

down: ## Para os containers
	$(COMPOSE) down

build: ## Reconstrói a imagem do app
	$(COMPOSE) build

logs: ## Logs (s=serviço)
	$(COMPOSE) logs -f $(s)

composer: ## composer cmd="..."
	$(EXEC) composer $(cmd)

test: ## Todos os testes
	$(EXEC) ./vendor/bin/pest

pint-test: ## Pint em modo verificação
	$(EXEC) ./vendor/bin/pint --test

test-unit: ## Só a suíte Unit
	$(EXEC) ./vendor/bin/pest --testsuite=Unit

test-integration: ## Só a suíte Integration
	$(EXEC) ./vendor/bin/pest --testsuite=Integration

test-feature: ## Só a suíte Feature
	$(EXEC) ./vendor/bin/pest --testsuite=Feature

test-arch: ## Só os Architecture tests
	$(EXEC) ./vendor/bin/pest --testsuite=Architecture

pint: ## Pint corrigindo
	$(EXEC) ./vendor/bin/pint

stan: ## Larastan nível 9
	$(EXEC) ./vendor/bin/phpstan analyze $(cmd)

check: pint-test stan test ## Obrigatório antes de commitar: pint + larastan + testes
	@echo "All checks completed."

fresh: ## migrate:fresh --seed (apaga os dados locais)
	$(EXEC) php artisan migrate:fresh --seed

routes: ## Lista as rotas da API com os middlewares de cada uma
	$(EXEC) php artisan route:list --path=api -v

# ---- autenticação e permissões --------------------------------------------------------
auth-install: ## Chaves do Passport + client OAuth + permissões + perfis de sistema
	$(EXEC) php artisan auth:install

permissions: ## Espelha App\Enums\PermissionName na tabela permissions
	$(EXEC) php artisan permissions:sync

rector: ## Rector dry-run
	$(EXEC) ./vendor/bin/rector process --dry-run

rector-fix: ## Rector aplicando
	$(EXEC) ./vendor/bin/rector process

# ---- qualidade e entrega ------------------------------------------------------------
coverage: ## Cobertura (mín. 80%)
	$(EXEC) php -d pcov.directory=/var/www/html ./vendor/bin/pest --coverage --min=80 --compact

type-coverage: ## Type coverage (100%)
	$(EXEC) ./vendor/bin/pest --type-coverage --min=100 --compact

# Mutation testing no domínio (lento): cobre só src/*/Domain com os testes unitários.
# Todo contexto novo entra sozinho: a lista é montada a partir das pastas existentes.
MUTATE_PATHS = $(shell ls -d src/*/Domain | paste -sd, -)
mutate: ## Mutation testing no domínio
	$(EXEC) ./vendor/bin/pest --mutate --path=$(MUTATE_PATHS) --testsuite=Unit --min=70 --compact

# O que o GitHub Actions roda (ver .github/workflows/ci.yml).
ci: pint-test stan test-arch coverage type-coverage ## O mesmo que o GitHub Actions roda

# Imagem de produção (multi-stage), construída a partir da raiz do projeto.
build-prod: ## Imagem de produção
	docker build -f docker/php/Dockerfile --target production -t $(PROJECT)/app:prod .

# Exporta o OpenAPI gerado pelo Scramble para docs/api/openapi.json.
openapi: ## Exporta docs/api/openapi.json
	$(EXEC) php artisan scramble:export --path=docs/api/openapi.json
