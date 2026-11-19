<?php

declare(strict_types=1);

/*
 * Ajustes do Laravel Boost (guidelines e skills para agentes de IA). Só as chaves que
 * o template muda; as demais vêm do config do próprio pacote.
 *
 * A cada `boost:update`, o Boost recompõe o bloco <laravel-boost-guidelines> do
 * CLAUDE.md e a pasta .claude/skills a partir dos pacotes instalados. Apagar um
 * arquivo à mão não resolve: ele volta na atualização seguinte. O que precisa ficar
 * de fora tem de estar listado aqui.
 *
 * Critério: o template pode virar qualquer API, então só ficam orientações que valem
 * para qualquer projeto derivado e que não contradizem as decisões já tomadas (ADRs).
 */
return [
    'guidelines' => [
        'exclude' => [
            // Recomenda o Laravel Cloud como destino de deploy. O template entrega uma
            // imagem Docker e não escolhe fornecedor (docs/guide/06-producao.md).
            'deployments',
        ],
    ],

    'skills' => [
        'exclude' => [
            // Mesmo motivo da guideline acima.
            'deploying-to-cloud',
            // Orienta o Passport de fábrica (rotas /oauth/*, scopes, prazos definidos no
            // provider, Client Credentials entre sistemas), o contrário do que as ADRs
            // 0008 e 0009 decidiram. Para autenticação, a fonte é a seção 3 do CLAUDE.md.
            'passport-development',
        ],
    ],
];
