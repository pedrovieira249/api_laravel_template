# ADR 0009: Perfis e permissões em tabelas próprias, verificados por middleware de rota

- **Status:** aceita
- **Data:** 2026-11-11

## Contexto

As rotas de usuário precisam ser limitadas por perfil: "este grupo de rotas é do administrador",
"este é de quem gerencia o catálogo". O orderflow, projeto que deu origem a este template, tinha um
enum `Role` com dois cases gravado em uma coluna de `users`. Isso é simples, mas todo perfil novo
ou toda mudança no que um perfil pode fazer exige alterar código e publicar.

As alternativas eram manter o enum, adotar `spatie/laravel-permission` ou modelar as tabelas no
próprio projeto.

## Decisão

RBAC em tabelas próprias, sem pacote externo:

```
users >--< roles >--< permissions
     role_user      permission_role
```

- **Permissões são código.** O catálogo é o enum `App\Enums\PermissionName` (`users.read`,
  `roles.manage`...), porque uma permissão só existe se algum trecho de código a verifica. O
  comando `permissions:sync` espelha o enum na tabela `permissions`, só para que perfis possam
  referenciá-las. A API as lista, mas não as cria.
- **Perfis são dados.** Criados, editados e apagados pela API de administração
  (`/api/v1/admin/roles`), sem deploy. Um usuário pode ter vários perfis; suas permissões são a
  união das permissões deles.
- **Dois perfis de sistema** (`App\Enums\RoleName`), criados por `auth:install`:
  - `admin`: superadministrador. Tem todas as permissões implicitamente (não depende de linhas na
    tabela pivô), então uma permissão nova já vale para ele no deploy que a introduz.
  - `user`: perfil padrão de quem se registra. Suas permissões são editáveis.

  Perfis de sistema não podem ser apagados nem renomeados.
- **A verificação é por middleware de rota**, declarada nos grupos de `routes/api.php`:

  ```php
  Route::middleware(['auth:api', 'role:admin'])->group(...);              // por perfil
  Route::middleware(['auth:api', 'permission:users.read'])->group(...);     // por permissão
  ```

  `role:a,b` e `permission:a,b` aceitam qualquer um dos listados. Sem usuário, 401; sem o
  perfil/permissão, 403.
- **Integração com o Gate:** um `Gate::before` transforma toda permissão do catálogo em ability,
  então `can:users.read`, `#[Authorize('users.read')]` e `$user->can('users.read')` também
  funcionam.
- **As permissões são lidas do banco a cada requisição** (duas consultas, uma vez por requisição),
  nunca do token. Mudar o perfil de alguém vale na chamada seguinte.
- **Travas contra escalada e contra trancar a administração:**
  - só um administrador concede ou retira o perfil `admin` (quem tem apenas `users.manage` não
    consegue se promover);
  - o último administrador não pode perder o perfil;
  - um perfil em uso não pode ser apagado.

## Consequências

- Um perfil novo é uma chamada à API; uma permissão nova é um case no enum, a descrição dele, um
  `permission:` na rota e um `php artisan permissions:sync`.
- Quando usar cada um: `permission:` para "o que pode ser feito" (delegável a qualquer perfil);
  `role:` para "quem a pessoa é" (no template, só as credenciais de sistemas são `role:admin`).
- Sem cache de permissões: são duas consultas por requisição autenticada que verifica acesso.
  É a escolha segura (revogação imediata). Se virar gargalo, o lugar do cache é
  `User::loadedRoles()`, com invalidação em `SaveRole` e `AssignUserRoles`.
- `roles.manage` + `users.manage` juntos permitem montar um perfil com quase tudo e atribuí-lo.
  São permissões de confiança: dê as duas só a quem poderia ser administrador.
- O primeiro administrador de um ambiente nasce pelo terminal:
  `php artisan user:assign-role ana@empresa.com admin`.
- Não usar o spatie significa manter ~10 classes pequenas no projeto. Em troca, o modelo cabe na
  cabeça, é tipado de ponta a ponta (Larastan nível 9) e não traz cache nem convenções de terceiros.
- `tests/Feature/Api/RouteProtectionTest.php` falha se uma rota referenciar uma permissão que não
  está no enum.

## Referências

- [docs/guide/03-permissoes.md](../guide/03-permissoes.md): receitas de uso.
