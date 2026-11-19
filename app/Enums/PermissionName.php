<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Catálogo de permissões: a fonte da verdade é este enum, não o banco.
 *
 * Uma permissão só faz sentido se algum trecho de código a verifica
 * (middleware `permission:users.read`, `#[Authorize]`, `$user->can()`), então
 * ela nasce aqui. O comando `permissions:sync` espelha os cases na tabela
 * permissions, e é lá que os perfis as agrupam.
 *
 * Convenção de nome: "recurso.ação", em snake_case. Para criar uma permissão:
 * acrescente o case, descreva-o em description() e rode `php artisan permissions:sync`.
 */
enum PermissionName: string
{
    case UsersRead = 'users.read';
    case UsersManage = 'users.manage';
    case RolesRead = 'roles.read';
    case RolesManage = 'roles.manage';

    public function description(): string
    {
        return match ($this) {
            self::UsersRead => 'List and view users.',
            self::UsersManage => 'Change the roles assigned to a user.',
            self::RolesRead => 'List and view roles and permissions.',
            self::RolesManage => 'Create, update and delete roles.',
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
