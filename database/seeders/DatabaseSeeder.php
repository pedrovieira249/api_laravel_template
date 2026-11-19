<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Sempre: o que a autenticação precisa para funcionar (idempotente, seguro em produção).
        $this->call(IdentitySeeder::class);

        // Dados de demonstração têm senha conhecida: nunca em produção.
        if (app()->isProduction()) {
            return;
        }

        $this->call([
            DemoUsersSeeder::class,
        ]);
    }
}
