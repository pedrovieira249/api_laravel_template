<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * O que uma API interna pode fazer com o token Sanctum que recebe.
 *
 * É o equivalente, para sistemas, das permissões de usuário: cada
 * ServiceClient guarda uma lista destas abilities, elas são copiadas para o
 * token emitido e os grupos de rota as exigem com `ability:<nome>`.
 * Validar contra um enum impede cadastrar um cliente com uma ability digitada errado.
 */
enum ServiceAbility: string
{
    /**
     * Curinga do Sanctum: o token pode tudo. Use só para serviços de confiança total.
     */
    case All = '*';

    /**
     * Criar contas de usuário em POST /internal/auth/register (ADR 0011). Dê só ao
     * sistema que faz o cadastro (site, BFF): quem a tem cria usuários em nome de qualquer um.
     */
    case UsersRegister = 'users.register';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
