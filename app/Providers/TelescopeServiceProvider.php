<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Registra os serviços da aplicação.
     */
    public function register(): void
    {
        // Telescope::night();

        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        Telescope::filter(fn (IncomingEntry $entry): bool => $isLocal ||
               $entry->isReportableException() ||
               $entry->isFailedRequest() ||
               $entry->isFailedJob() ||
               $entry->isScheduledTask() ||
               $entry->hasMonitoredTag());
    }

    /**
     * Impede que detalhes sensíveis da requisição sejam registrados pelo Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters(['_token']);

        Telescope::hideRequestHeaders([
            'cookie',
            'set-cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    /**
     * Registra o gate do Telescope.
     *
     * Este gate determina quem pode acessar o Telescope em ambientes que não sejam local.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', fn (User $user): bool => $user->isAdmin());
    }
}
