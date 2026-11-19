<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ServiceAbility;
use App\Models\ServiceClient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceClient>
 */
class ServiceClientFactory extends Factory
{
    /**
     * O segredo em texto puro de todo cliente criado pela factory (o cast
     * "hashed" do model grava o hash).
     */
    public const string SECRET = 'service-secret';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'service-'.fake()->unique()->numerify('######'),
            'secret' => self::SECRET,
            'abilities' => [ServiceAbility::All->value],
        ];
    }

    public function withAbilities(ServiceAbility ...$abilities): static
    {
        return $this->state(fn (): array => [
            'abilities' => array_map(fn (ServiceAbility $ability): string => $ability->value, $abilities),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => ['revoked_at' => now()]);
    }
}
