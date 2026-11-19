<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
 * O serviço scheduler (compose.yaml) roda `schedule:work`, que acorda a cada
 * minuto e executa o que estiver vencido. Sem ele nenhuma tarefa abaixo roda:
 * o Laravel não tem cron embutido.
 */

// Token Sanctum vencido já é recusado pelo guard, mas a linha continua na tabela. Este
// comando apaga os que venceram há mais de 24h, para personal_access_tokens não crescer
// para sempre (tokens de serviço duram 1h: sem limpeza seriam 24 linhas novas por cliente/dia).
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// O mesmo para o Passport: access tokens de 5 minutos geram muitas linhas. Remove tokens
// e refresh tokens revogados ou expirados há mais de 24h.
Schedule::command('passport:purge --hours=24')->daily();

// As métricas do Horizon (throughput, tempos de espera) são amostradas por este snapshot.
Schedule::command('horizon:snapshot')->everyFiveMinutes();

// Telescope só existe em local (AppServiceProvider): mantenha as tabelas dele pequenas.
Schedule::command('telescope:prune --hours=48')->daily()->environments(['local']);
