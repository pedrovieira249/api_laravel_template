# syntax=docker/dockerfile:1
# A linha acima é uma "parser directive": diz ao BuildKit para usar a versão mais recente
# do frontend do Dockerfile (libera COPY --chown, ADD --chmod, heredocs etc.).
# REGRA: ela só é reconhecida se for a PRIMEIRA linha do arquivo. Qualquer comentário,
# linha em branco ou instrução antes dela transforma a diretiva em comentário comum.

# Imagem base: PHP 8.5 com php-fpm (FastCGI, porta 9000) sobre Alpine Linux (imagem pequena).
# Multi-stage: "dev" é o estágio padrão (o compose.yaml usa target: dev); "production" fica no fim.
FROM php:8.5-fpm-alpine AS dev

# Argumentos de build (existem só durante o build). Vêm DEPOIS do FROM: um ARG antes do FROM
# só serviria para parametrizar o próprio FROM. O compose.yaml vai passar o UID/GID do host.
ARG UID=1000
ARG GID=1000

# Pacotes do sistema via apk (gerenciador do Alpine).
#   --no-cache: não grava o índice de pacotes na camada (imagem menor).
#   bash: conforto para `make sh`; git e unzip: usados pelo Composer para baixar/extrair pacotes.
RUN apk add --no-cache bash git unzip

# Copia o script "install-php-extensions" de outra imagem, sem baixar nada com curl.
# Ele compila cada extensão, instala as dependências de build do Alpine e as remove no fim.
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
#   pdo_mysql: driver do MySQL para o PDO/Eloquent.   bcmath: aritmética de precisão arbitrária.
#   intl: formatação/validação i18n (Laravel usa).     zip: Composer e exports.
#   opcache: cache de bytecode (configurado em conf.d/opcache.ini).
#   pcntl: sinais de processo (queue:work e Horizon).  redis: cliente do Redis (cache/filas).
#   pcov: cobertura de testes rápida para o Pest.
RUN install-php-extensions pdo_mysql bcmath intl zip opcache pcntl redis pcov

# Composer copiado da imagem oficial: versão verificável pela tag, sem script de instalação.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# A imagem oficial NÃO traz php.ini ativo, só dois modelos. Em dev ativamos o "development"
# (display_errors=On, error_reporting=E_ALL). Em produção (estágio "production", abaixo) é o "production".
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

# Nossas configurações. Todo *.ini em conf.d/ é carregado após o php.ini, em ordem alfabética.
# Caminhos de origem são relativos ao contexto de build (docker/php), por isso "conf.d/".
COPY conf.d/ "$PHP_INI_DIR/conf.d/"

# Substitui o pool padrão do php-fpm (o original define user/group = www-data, que não podemos
# usar rodando como não-root). docker.conf e zz-docker.conf da imagem continuam valendo.
COPY php-fpm.d/www.conf /usr/local/etc/php-fpm.d/www.conf

# Usuário não-root com o mesmo UID/GID do host: arquivos criados no container pertencem a você.
# Sintaxe do BusyBox (Alpine): -g/-u definem o id, -G grupo primário, -D sem senha, -s shell.
RUN addgroup -g ${GID} laravel \
 && adduser -u ${UID} -G laravel -D -s /bin/bash laravel

# Pasta da aplicação (o compose vai montar o código aqui). WORKDIR cria a pasta como root...
WORKDIR /var/www/html
# ...então entregamos a pasta ao usuário antes de trocar para ele.
RUN chown laravel:laravel /var/www/html

# A partir daqui tudo roda como "laravel": Composer, Artisan, php-fpm, testes.
# Precisa ser depois de apk/extensões/chown, que exigem root.
USER laravel

# Documentação: o php-fpm escuta em 9000. Quem publica porta de verdade é o compose.yaml.
EXPOSE 9000

# Processo principal do container. No serviço "queue" vamos sobrescrever por "php artisan horizon".
CMD ["php-fpm"]

# =============================================================================
# Imagem de PRODUÇÃO (multi-stage). Construa a partir da RAIZ do projeto (make build-prod):
#   docker build -f docker/php/Dockerfile --target production -t <projeto>/app:prod .
# Diferenças em relação ao estágio "dev":
#   - o código é COPIADO para a imagem (sem bind mount), com vendor sem dependências de dev;
#   - php.ini-production (display_errors=Off) e opcache sem checar timestamps;
#   - sem pcov, sem Composer, usuário sem shell;
#   - event/view cache feitos no build; config/route cache ficam para o deploy (dependem do .env).
# =============================================================================

# --- estágio "vendor": dependências do Composer numa imagem que já tem o Composer -----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# Sem dev deps, sem scripts (artisan ainda não está aqui), autoloader otimizado.
# --ignore-platform-reqs: a imagem do Composer não tem as extensões; a final tem.
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader --ignore-platform-reqs

# --- estágio "production": a imagem final -----------------------------------------------
FROM php:8.5-fpm-alpine AS production
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql bcmath intl zip opcache pcntl redis \
 && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php/conf.d/ "$PHP_INI_DIR/conf.d/"
COPY docker/php/php-fpm.d/www.conf /usr/local/etc/php-fpm.d/www.conf
# O código não muda em produção: opcache não precisa checar os arquivos a cada requisição.
RUN { echo 'opcache.validate_timestamps=0'; echo 'opcache.max_accelerated_files=20000'; } > "$PHP_INI_DIR/conf.d/zz-production.ini"

RUN addgroup -g 1000 laravel && adduser -u 1000 -G laravel -D -s /sbin/nologin laravel
WORKDIR /var/www/html

COPY --chown=laravel:laravel . .
COPY --chown=laravel:laravel --from=vendor /app/vendor ./vendor

# Só storage e bootstrap/cache são graváveis em runtime.
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && chown -R laravel:laravel storage bootstrap/cache

USER laravel

# Caches que não dependem do .env. No deploy: php artisan config:cache && php artisan route:cache.
RUN php artisan package:discover --ansi && php artisan event:cache && php artisan view:cache

EXPOSE 9000
CMD ["php-fpm"]
