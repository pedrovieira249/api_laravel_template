<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\ServiceAbility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateServiceClientRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_-]*$/', 'unique:service_clients,name'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['distinct', Rule::enum(ServiceAbility::class)],
        ];
    }

    /**
     * @return list<string>
     */
    public function abilities(): array
    {
        return array_values(array_filter((array) $this->validated('abilities'), is_string(...)));
    }
}
