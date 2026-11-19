<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Só validação de formato (ADR 0001, validação em duas camadas). O perfil nunca
 * é aceito de quem chama: toda conta nasce com o perfil padrão. Quem pode chamar
 * (um sistema interno com a ability users.register) é assunto da rota (ADR 0011).
 */
final class RegisterRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ];
    }

    /**
     * E-mails são gravados em minúsculas; normalizar antes de validar faz a regra
     * unique enxergar "Ana@Example.com" e "ana@example.com" como o mesmo endereço
     * em qualquer collation.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(mb_trim($this->input('email')))]);
        }
    }
}
