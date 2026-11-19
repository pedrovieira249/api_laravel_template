<?php

declare(strict_types=1);

namespace App\Auth\Services;

use App\Enums\ServiceAbility;
use App\Models\ServiceClient;
use Illuminate\Support\Str;

/**
 * Action: cadastra uma API interna e gera o segredo dela.
 *
 * Usada pelo comando `service-client:create` e pelo endpoint de administração:
 * o caso de uso é um só, as portas de entrada é que mudam.
 */
final readonly class RegisterServiceClient
{
    private const int SECRET_LENGTH = 48;

    /**
     * @param  list<string>  $abilities  valores de App\Enums\ServiceAbility
     *
     * @throws \ValueError quando alguma ability não existe no catálogo
     */
    public function __invoke(string $name, array $abilities): NewServiceClient
    {
        $abilities = array_values(array_unique(array_map(
            fn (string $ability): string => ServiceAbility::from($ability)->value,
            $abilities,
        )));

        $secret = Str::random(self::SECRET_LENGTH);

        $client = ServiceClient::query()->create([
            'name' => $name,
            'secret' => $secret,
            'abilities' => $abilities,
        ]);

        return new NewServiceClient($client, $secret);
    }
}
