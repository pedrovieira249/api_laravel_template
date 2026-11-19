<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * RBAC em tabelas próprias, sem pacote externo (ADR 0009):
 *
 *   users >--< roles >--< permissions
 *
 * Um usuário tem vários perfis (roles); um perfil agrega várias permissões.
 * Perfis são dados (criados e editados pela API de administração). Permissões
 * são definidas no código (App\Enums\PermissionName) e só espelhadas aqui pelo
 * comando `permissions:sync`, para que perfis possam referenciá-las.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // Identificador estável usado nos middlewares: role:admin.
            $table->string('name', 60)->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // "recurso.ação": users.read, roles.manage. Usado em permission:users.read.
            $table->string('name', 100)->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Pivôs: PK composta impede duplicatas e já indexa a primeira coluna; a FK da
        // segunda coluna ganha o próprio índice. cascadeOnDelete limpa os vínculos
        // quando um perfil, uma permissão ou um usuário deixa de existir.
        Schema::create('permission_role', function (Blueprint $table): void {
            $table->foreignUuid('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignUuid('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('role_user', function (Blueprint $table): void {
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('role_id')->constrained('roles')->cascadeOnDelete();
            $table->primary(['user_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
