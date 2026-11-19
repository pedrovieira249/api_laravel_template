<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A lista substitui os perfis atuais do usuário. Lista vazia é válida: tira
 * todos os perfis (o usuário continua existindo, sem acesso a rotas protegidas).
 */
final class SyncUserRolesRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'roles' => ['present', 'array'],
            'roles.*' => ['string', 'distinct', 'exists:roles,name'],
        ];
    }

    /**
     * @return list<string>
     */
    public function roles(): array
    {
        return array_values(array_filter((array) $this->validated('roles'), is_string(...)));
    }
}
