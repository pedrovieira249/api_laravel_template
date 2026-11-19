<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateRoleRequest extends FormRequest
{
    /**
     * O nome é o identificador usado nos middlewares (role:manager), então segue
     * o formato de um slug: minúsculas, dígitos, "-" e "_".
     */
    public const string NAME_PATTERN = 'regex:/^[a-z][a-z0-9_-]*$/';

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60', self::NAME_PATTERN, 'unique:roles,name'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['distinct', Rule::enum(PermissionName::class)],
        ];
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return array_values(array_filter((array) $this->validated('permissions', []), is_string(...)));
    }
}
