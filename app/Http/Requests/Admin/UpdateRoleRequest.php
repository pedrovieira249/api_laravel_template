<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\PermissionName;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateRoleRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $role = $this->route('role');

        return [
            'name' => [
                'required', 'string', 'max:60', CreateRoleRequest::NAME_PATTERN,
                Rule::unique('roles', 'name')->ignore($role instanceof Role ? $role->id : null),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['distinct', Rule::enum(PermissionName::class)],
        ];
    }

    /**
     * null quando o campo não veio: as permissões do perfil ficam como estão.
     *
     * @return list<string>|null
     */
    public function permissions(): ?array
    {
        if (! $this->has('permissions')) {
            return null;
        }

        return array_values(array_filter((array) $this->validated('permissions'), is_string(...)));
    }
}
