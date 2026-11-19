<?php

declare(strict_types=1);

namespace App\Providers;

use Core\Shared\Application\Bus\CommandBus;
use Core\Shared\Application\Bus\QueryBus;
use Core\Shared\Application\Contract\Clock;
use Core\Shared\Application\Contract\EventDispatcher;
use Core\Shared\Application\Contract\IdGenerator;
use Core\Shared\Application\Contract\TransactionManager;
use Core\Shared\Infrastructure\Bus\ContainerCommandBus;
use Core\Shared\Infrastructure\Bus\ContainerQueryBus;
use Core\Shared\Infrastructure\Clock\SystemClock;
use Core\Shared\Infrastructure\Event\LaravelEventDispatcher;
use Core\Shared\Infrastructure\Id\UuidV7Generator;
use Core\Shared\Infrastructure\Persistence\DbTransactionManager;
use Illuminate\Foundation\Events\DiscoverEvents;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use SplFileInfo;

/**
 * O único lugar onde ports (interfaces em Core\*\Application e
 * Core\*\Domain) encontram adapters (Core\*\Infrastructure).
 *
 * O Laravel 13 também oferece #[Bind] na própria interface, mas esse atributo
 * vive em Illuminate\Container, e a camada Application precisa continuar
 * livre de framework, então o wiring é feito aqui, explicitamente.
 */
final class DomainServiceProvider extends ServiceProvider
{
    /**
     * Adapters sem estado: uma instância para o processo inteiro.
     *
     * @var array<class-string, class-string>
     */
    public array $singletons = [
        Clock::class => SystemClock::class,
        IdGenerator::class => UuidV7Generator::class,
        EventDispatcher::class => LaravelEventDispatcher::class,
        TransactionManager::class => DbTransactionManager::class,
        CommandBus::class => ContainerCommandBus::class,
        QueryBus::class => ContainerQueryBus::class,
    ];

    public function register(): void
    {
        // A descoberta de eventos deriva nomes de classe dos caminhos de arquivo e assume o
        // layout app/ -> App\; ensinamos a ela src/ -> Core\ (ver bootstrap/app.php).
        DiscoverEvents::guessClassNamesUsing(function (SplFileInfo $file, string $basePath): string {
            $relative = trim(Str::replaceFirst($basePath, '', $file->getRealPath()), DIRECTORY_SEPARATOR);
            $class = str_replace(DIRECTORY_SEPARATOR, '\\', Str::replaceLast('.php', '', $relative));

            /** @var class-string $resolved */
            $resolved = Str::replaceStart('src\\', 'Core\\', Str::replaceStart('app\\', 'App\\', $class));

            return $resolved;
        });

        foreach ($this->contexts() as $provider) {
            $this->app->register($provider);
        }
    }

    /**
     * Providers de cada bounded context, mantidos ao lado dos adapters que conectam.
     * Contexto novo em src/ = uma linha nova aqui (e em tests/Architecture/LayersTest.php).
     *
     * @return list<class-string<ServiceProvider>>
     */
    private function contexts(): array
    {
        return [
        ];
    }
}
