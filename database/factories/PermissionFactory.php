<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Só para testes da própria estrutura de RBAC: permissões reais nascem do enum
 * App\Enums\PermissionName, pelo comando `permissions:sync`.
 *
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'resource-'.fake()->unique()->numerify('######').'.action',
            'description' => fake()->sentence(),
        ];
    }
}
