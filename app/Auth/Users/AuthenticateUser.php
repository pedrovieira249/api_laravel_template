<?php

declare(strict_types=1);

namespace App\Auth\Users;

use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Action: confere credenciais e emite o par de tokens (access + refresh).
 *
 * A mesma mensagem para e-mail desconhecido e senha errada: nunca revelar
 * quais contas existem. Convenção do Laravel (Breeze/Fortify): credenciais
 * inválidas são 422 com a linha auth.failed, não 401.
 */
final readonly class AuthenticateUser
{
    public function __construct(private UserTokenIssuer $tokens) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(string $email, #[\SensitiveParameter] string $password): AuthenticatedUser
    {
        $email = mb_strtolower(mb_trim($email));

        $tokens = $this->tokens->forCredentials($email, $password)
            ?? throw ValidationException::withMessages(['email' => [__('auth.failed')]]);

        // O servidor OAuth já validou a senha; aqui só buscamos o model para a resposta.
        return new AuthenticatedUser(User::query()->where('email', $email)->sole(), $tokens);
    }
}
