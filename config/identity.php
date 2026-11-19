<?php

declare(strict_types=1);

/*
 * Parâmetros da autenticação deste template, em um lugar só (ADR 0008).
 *
 * Dois públicos, dois mecanismos:
 *   - users:    pessoas e aplicações externas. Laravel Passport (OAuth2): access
 *               token JWT de vida curta + refresh token. Guard "api".
 *   - services: outras APIs da casa (serviço a serviço). Laravel Sanctum: token
 *               opaco obtido com client_id + client_secret. Guard "internal".
 *
 * Todos os tempos são em minutos.
 */
return [
    'users' => [
        // Vida do access token entregue no login. Curta de propósito: é o prazo que
        // limita a janela de uso de um token vazado.
        'access_token_ttl' => (int) env('AUTH_USER_TOKEN_TTL', 5),

        // Vida do refresh token. É ele que evita pedir a senha a cada 5 minutos;
        // cada uso o troca por um novo (rotação), então um refresh token só serve uma vez.
        'refresh_token_ttl' => (int) env('AUTH_USER_REFRESH_TOKEN_TTL', 10080),

        // Client OAuth "da casa" usado pelos endpoints /auth/login e /auth/refresh.
        // É público (sem segredo) porque nunca sai do servidor: as rotas /oauth/* do
        // Passport ficam desligadas e só o código da própria API o utiliza.
        'client' => [
            'id' => (string) env('AUTH_FIRST_PARTY_CLIENT_ID', '00000000-0000-7000-8000-000000000001'),
            'name' => 'First-party login',
        ],
    ],

    'services' => [
        // Vida do token de serviço. Vencido o prazo, o serviço pede outro com as
        // mesmas credenciais (não existe refresh token aqui).
        'token_ttl' => (int) env('AUTH_SERVICE_TOKEN_TTL', 60),
    ],
];
