<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Perfis de sistema: os únicos que o código conhece pelo nome.
 *
 * Perfis são dados (tabela roles, editáveis pela API de administração), mas
 * estes dois precisam existir em todo ambiente: o comando `auth:install` os
 * cria e a API se recusa a apagá-los ou renomeá-los. Qualquer outro perfil
 * ("manager", "support"...) é criado em tempo de execução, sem deploy.
 */
enum RoleName: string
{
    /**
     * Superadministrador: tem todas as permissões, implicitamente
     * (ver App\Models\User::permissionNames()).
     */
    case Admin = 'admin';

    /**
     * Perfil atribuído a toda conta criada pelo registro (POST /internal/auth/register,
     * ADR 0011). Suas permissões são editáveis.
     */
    case User = 'user';

    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Full access to every resource.',
            self::User => 'Default profile for self-registered users.',
        };
    }

    /**
     * Permissões concedidas ao perfil quando ele é criado pelo `auth:install`.
     * Depois de criado, a lista é editada pela API de administração e o
     * deploy não a toca mais. Admin não precisa de lista: tem tudo.
     *
     * @return list<PermissionName>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::Admin => [],
            // Troque pelas permissões básicas do seu domínio (as de leitura, em geral).
            self::User => [],
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
