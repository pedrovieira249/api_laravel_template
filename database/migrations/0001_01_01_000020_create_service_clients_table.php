<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Identidade das APIs internas (comunicação serviço a serviço, ADR 0008).
 * Cada linha é um sistema que pode pedir um token Sanctum de curta duração
 * apresentando client_id (o id) + client_secret.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_clients', function (Blueprint $table): void {
            // O id é o client_id entregue ao outro sistema.
            $table->uuid('id')->primary();
            // Nome legível e único: "billing-api", "reports-worker".
            $table->string('name', 100)->unique();
            // Só o hash é guardado; o segredo em texto puro aparece uma única vez, na criação.
            $table->string('secret');
            // Lista JSON do que o serviço pode fazer; copiada para cada token emitido.
            $table->json('abilities');
            // Revogar não apaga a linha: o histórico de quem já teve acesso continua auditável.
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_clients');
    }
};
