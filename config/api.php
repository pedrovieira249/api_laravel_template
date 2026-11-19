<?php

declare(strict_types=1);

/*
 * Parâmetros da camada HTTP da API.
 */
return [
    // Base dos identificadores "type" dos erros (RFC 9457 Problem Details). Precisa
    // ser estável entre ambientes: clientes comparam a string, não acessam a URL.
    'problem_type_base' => rtrim((string) env('API_PROBLEM_TYPE_BASE', 'https://api.example.com/problems'), '/'),

    // Teto de cada limiter (declarados no AppServiceProvider e aplicados nas rotas com
    // throttle:<nome>). Todos são por MINUTO, exceto "register", que é por SEGUNDO.
    'rate_limits' => [
        // Rotas de usuário: por usuário autenticado, ou por IP quando anônimo.
        'api' => (int) env('API_RATE_LIMIT', 120),
        // Rotas internas: por cliente de serviço. Sistemas falam mais do que pessoas.
        'internal' => (int) env('API_INTERNAL_RATE_LIMIT', 600),
        // Login: por e-mail + IP (força bruta de senha).
        'login' => (int) env('API_LOGIN_RATE_LIMIT', 5),
        // Renovação de token e emissão de token de serviço: por IP / client_id + IP.
        'token' => (int) env('API_TOKEN_RATE_LIMIT', 30),
        // Registro de usuários: por SEGUNDO, por cliente de serviço. Cadastro em massa é o
        // abuso clássico desta rota; o teto contém um sistema com defeito ou comprometido.
        'register' => (int) env('API_REGISTER_RATE_LIMIT', 5),
    ],
];
