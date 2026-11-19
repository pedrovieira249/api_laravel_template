<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Auth\Access\SyncAccessControl;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Rode depois de acrescentar ou remover um case em App\Enums\PermissionName.
 * (O `auth:install` já faz isto; este comando existe para o dia a dia.)
 */
#[Description('Mirror App\Enums\PermissionName into the permissions table and ensure the system roles exist')]
#[Signature('permissions:sync')]
final class SyncPermissionsCommand extends Command
{
    public function handle(SyncAccessControl $sync): int
    {
        $sync();

        $this->components->info('Permissions and system roles are in sync.');

        return self::SUCCESS;
    }
}
