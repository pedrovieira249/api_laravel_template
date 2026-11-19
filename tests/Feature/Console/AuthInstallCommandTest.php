<?php

declare(strict_types=1);

use App\Enums\PermissionName;
use App\Models\Permission;
use App\Models\Role;
use Laravel\Passport\Passport;

beforeEach(function (): void {
    // Diretório descartável: o teste nunca toca nas chaves reais de storage/.
    $this->keys = sys_get_temp_dir().'/passport-keys-'.uniqid();
    mkdir($this->keys);
    Passport::loadKeysFrom($this->keys);
});

afterEach(function (): void {
    array_map(unlink(...), glob($this->keys.'/*') ?: []);
    rmdir($this->keys);
    Passport::$keyPath = null;
});

it('prepares the keys, the first-party client, the permissions and the system roles', function (): void {
    config()->set('passport.private_key');
    DB::table('oauth_clients')->delete();

    $this->artisan('auth:install')
        ->expectsOutputToContain('First-party OAuth client ready')
        ->expectsOutputToContain('Permissions and system roles are in sync.')
        ->assertSuccessful();

    expect(file_exists($this->keys.'/oauth-private.key'))->toBeTrue()
        ->and(file_exists($this->keys.'/oauth-public.key'))->toBeTrue();

    $this->assertDatabaseHas('oauth_clients', ['id' => config('identity.users.client.id'), 'secret' => null, 'provider' => 'users', 'revoked' => false]);
    expect(Permission::query()->pluck('name')->all())->toEqualCanonicalizing(PermissionName::values())
        ->and(Role::query()->orderBy('name')->pluck('name')->all())->toBe(['admin', 'user']);
});

it('can run on every deploy without duplicating or overwriting anything', function (): void {
    config()->set('passport.private_key');

    $this->artisan('auth:install')->assertSuccessful();
    $firstKey = file_get_contents($this->keys.'/oauth-private.key');

    $this->artisan('auth:install')
        ->expectsOutputToContain('Passport keys already exist.')
        ->assertSuccessful();

    expect(file_get_contents($this->keys.'/oauth-private.key'))->toBe($firstKey);
    $this->assertDatabaseCount('oauth_clients', 1);
    $this->assertDatabaseCount('roles', 2);
});

it('does not write key files when the keys come from the environment', function (): void {
    // TestCase já injeta um par de chaves pela config, como PASSPORT_PRIVATE_KEY faria.
    $this->artisan('auth:install')
        ->expectsOutputToContain('Passport keys come from the environment.')
        ->assertSuccessful();

    expect(glob($this->keys.'/*'))->toBe([]);
});
