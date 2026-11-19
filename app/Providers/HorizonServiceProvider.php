<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

/**
 * Dashboard do Horizon em /horizon. Em "local" qualquer um pode abrir; em qualquer
 * outro ambiente só admins (o gate abaixo).
 */
class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn (?User $user): bool => $user?->isAdmin() ?? false);
    }
}
