# Perfis e permissões: limitando rotas por perfil de usuário

O modelo está descrito na [ADR 0009](../adr/0009-rbac-in-database-tables.md). Em uma frase: um
usuário tem perfis, um perfil agrupa permissões, e os grupos de rota exigem um perfil ou uma
permissão por middleware.

```
users >--< roles >--< permissions
```

| Conceito | Onde vive | Quem cria |
| --- | --- | --- |
| Permissão (`users.read`) | enum [`PermissionName`](../../app/Enums/PermissionName.php) + tabela `permissions` | o desenvolvedor, no código |
| Perfil (`manager`) | tabela `roles` | um administrador, pela API |
| Perfil de sistema (`admin`, `user`) | enum [`RoleName`](../../app/Enums/RoleName.php) + tabela `roles` | o `auth:install` |

## Protegendo rotas

Tudo é declarado em [`routes/api.php`](../../routes/api.php). Um grupo de rotas = um público + uma
regra de acesso.

```php
// Só administradores
Route::middleware(['auth:api', 'role:admin'])->prefix('reports')->group(function (): void {
    Route::get('financial', FinancialReportController::class)->name('reports.financial');
});

// Administradores OU gerentes
Route::middleware(['auth:api', 'role:admin,manager'])->group(...);

// Quem tiver a permissão, seja qual for o perfil
Route::middleware(['auth:api', 'permission:roles.manage'])->group(...);

// Uma permissão OU outra
Route::middleware(['auth:api', 'permission:users.read,users.manage'])->group(...);
```

- `auth:api` vem sempre antes: ele identifica o usuário; `role:`/`permission:` decidem o acesso.
- Sem usuário autenticado → **401**. Usuário sem o perfil ou a permissão → **403**.
- Vários valores separados por vírgula significam "qualquer um deles".

Para não digitar nomes, os middlewares aceitam os enums:

```php
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\EnsureUserHasRole;

Route::middleware(['auth:api', EnsureUserHasRole::using(RoleName::Admin)])->group(...);
Route::middleware(['auth:api', EnsureUserHasPermission::using(PermissionName::RolesManage)])->group(...);
```

### `role:` ou `permission:`?

- **`permission:`** quando a regra é sobre **o que pode ser feito**. É o padrão: a mesma rota
  passa a servir a qualquer perfil que receba a permissão, sem mexer no código.
- **`role:`** quando a regra é sobre **quem a pessoa é** e não deve ser delegável. No template,
  só o cadastro de credenciais de outros sistemas (`/admin/service-clients`) é `role:admin`.

Se você se pegar escrevendo `role:admin,manager,support,...` em várias rotas, isso é uma permissão
pedindo para existir.

## Criando uma permissão

1. Acrescente o case e a descrição em [`PermissionName`](../../app/Enums/PermissionName.php):

   ```php
   case InvoicesIssue = 'invoices.issue';
   // em description():
   self::InvoicesIssue => 'Issue invoices.',
   ```

   Convenção: `recurso.ação`, em snake_case.
2. Use-a na rota: `->middleware('permission:invoices.issue')`.
3. Sincronize o banco: `make permissions` (o `auth:install` do deploy também faz isso).
4. Dê a permissão a um perfil pela API (abaixo). Administradores já a têm.

O teste [`RouteProtectionTest`](../../tests/Feature/Api/RouteProtectionTest.php) falha se uma rota
citar uma permissão que não está no enum.

## Gerenciando perfis (API de administração)

| Método | Rota | Exige | O que faz |
| --- | --- | --- | --- |
| GET | `/api/v1/admin/permissions` | `roles.read` | Catálogo de permissões |
| GET | `/api/v1/admin/roles` | `roles.read` | Perfis, suas permissões e quantos usuários têm cada um |
| GET | `/api/v1/admin/roles/{id}` | `roles.read` | Um perfil |
| POST | `/api/v1/admin/roles` | `roles.manage` | Cria um perfil |
| PUT | `/api/v1/admin/roles/{id}` | `roles.manage` | Altera nome, descrição e permissões |
| DELETE | `/api/v1/admin/roles/{id}` | `roles.manage` | Apaga um perfil sem usuários |
| GET | `/api/v1/admin/users` | `users.read` | Lista usuários (paginado) |
| GET | `/api/v1/admin/users/{id}` | `users.read` | Um usuário |
| PUT | `/api/v1/admin/users/{id}/roles` | `users.manage` | Substitui os perfis do usuário |

Criar um perfil:

```bash
curl -s -X POST http://localhost:8080/api/v1/admin/roles \
  -H "Authorization: Bearer $ACCESS_TOKEN" -H 'Content-Type: application/json' \
  -d '{"name":"support","description":"Atendimento","permissions":["users.read","roles.read"]}'
```

Dar o perfil a alguém (a lista substitui os perfis atuais):

```bash
curl -s -X PUT http://localhost:8080/api/v1/admin/users/$USER_ID/roles \
  -H "Authorization: Bearer $ACCESS_TOKEN" -H 'Content-Type: application/json' \
  -d '{"roles":["support"]}'
```

A mudança vale na **próxima requisição** da pessoa, sem novo login: as permissões são lidas do
banco a cada requisição, não do token.

### Regras que a API impõe

| Situação | Resposta |
| --- | --- |
| Apagar ou renomear `admin` ou `user` | 409 |
| Editar as permissões do `admin` (ele sempre tem todas) | 409 |
| Apagar um perfil que algum usuário tem | 409 |
| Tirar o perfil `admin` do último administrador | 409 |
| Conceder ou retirar o perfil `admin` sem ser administrador | 403 |

`roles.manage` e `users.manage` juntas permitem montar um perfil com quase tudo e atribuí-lo. Dê
as duas só a quem poderia ser administrador.

## O perfil admin e o primeiro administrador

- `admin` tem todas as permissões implicitamente: não há linhas dele na tabela `permission_role`.
  Uma permissão nova já vale para administradores.
- Em um ambiente novo ninguém é administrador. O primeiro nasce pelo terminal, depois de a conta
  ser criada ([guia 06](06-producao.md#o-primeiro-administrador)):

  ```bash
  make artisan cmd="user:assign-role ana@empresa.com admin"
  ```

- O perfil `user` é dado a toda conta criada em `POST /internal/auth/register`
  ([guia 02](02-autenticacao.md#registrando-usuários)). As permissões iniciais dele estão
  em `RoleName::defaultPermissions()` e depois são editáveis pela API.

## No código

```php
$user->hasRole('manager');                       // ou RoleName::Admin; vários = qualquer um
$user->hasPermission(PermissionName::UsersRead); // vários = qualquer uma
$user->isAdmin();
$user->roleNames();        // ['manager']
$user->permissionNames();  // ['roles.read', 'users.read']
```

As formas nativas do Laravel também funcionam, porque toda permissão do catálogo é uma ability do
Gate:

```php
Route::get(...)->middleware('can:roles.manage');
#[Authorize('roles.manage')]              // atributo no controller
$user->can('roles.manage');
Gate::authorize('roles.manage');
```

Para regras que dependem do registro ("só o dono edita"), use um gate ou uma policy comuns: o
RBAC responde "pode editar pedidos?", a policy responde "pode editar **este** pedido?".

## APIs internas não têm perfil

Um `ServiceClient` não é um usuário: `role:` e `permission:` respondem **403** para ele. O que
autoriza um sistema é a ability do token (`ability:invoices.read`), descrita no
[guia 02](02-autenticacao.md#2-apis-internas-sanctum).

## Nos testes

```php
actingAsUser(User::factory()->admin()->create());
actingAsUser(User::factory()->withRole('manager')->create());

// Exatamente estas permissões, sem depender de como os perfis reais estão montados:
actingAsUser(User::factory()->withPermissions(PermissionName::UsersRead)->create());
```

Exemplos completos: [`AccessControlTest`](../../tests/Feature/Api/V1/AccessControlTest.php)
(os middlewares) e [`RolesTest`](../../tests/Feature/Api/V1/Admin/RolesTest.php) (a API).
