<?php

declare(strict_types=1);

use App\Http\Controllers\Controller;
use Core\Shared\Application\Exception\ServiceUnavailable;
use Core\Shared\Domain\Entity\AggregateRoot;
use Core\Shared\Domain\Exception\BusinessRuleViolation;
use Core\Shared\Domain\Exception\DomainException;
use Core\Shared\Domain\Exception\EntityNotFound;
use Illuminate\Database\Eloquent\Model;

/**
 * As regras com curinga (Core\*\Domain\...) miram pastas dos bounded contexts. Em um
 * projeto recém-criado, sem nenhum contexto ainda, a pasta não existe e o Pest falharia
 * ao procurá-la: a regra só é registrada quando há o que vigiar.
 */
$exists = fn (string $pattern): bool => glob(dirname(__DIR__, 2).'/src/'.$pattern, GLOB_ONLYDIR) !== [];

arch('debugging functions prohibited throughout the project')
    ->expect(['dd', 'dump', 'var_dump', 'ray'])->not->toBeUsed();

arch('core uses strict types')
    ->expect(['Core'])
    ->toUseStrictTypes();

// Classes base abstratas não podem ser final: listá-las explicitamente.
arch('core classes are final')
    ->expect(['Core'])
    ->classes()
    ->toBeFinal()
    ->ignoring([
        AggregateRoot::class,
        DomainException::class,
        EntityNotFound::class,
        BusinessRuleViolation::class,
        ServiceUnavailable::class,
    ]);

arch('domain exceptions extend the domain root')
    ->expect('Core\*\Domain\Exception')
    ->classes()
    ->toExtend(DomainException::class);

arch('value objects are immutable')
    ->expect('Core\*\Domain\ValueObject')
    ->toBeReadonly();

if ($exists('*/Application/Command')) {
    arch('commands and their handlers are immutable')
        ->expect('Core\*\Application\Command')
        ->classes()
        ->toBeReadonly();
}

if ($exists('*/Application/Query')) {
    arch('queries and their handlers are immutable')
        ->expect('Core\*\Application\Query')
        ->classes()
        ->toBeReadonly();
}

arch('domain events are immutable')
    ->expect('Core\*\Domain\Event')
    ->classes()
    ->toBeReadonly();

// Contexto novo com persistência Eloquent: acrescente 'Core\<Contexto>\Infrastructure' aqui.
arch('eloquent models stay in infrastructure (and identity)')
    ->expect(Model::class)
    ->toOnlyBeUsedIn([
        'App\Models',
        'App\Providers',
    ]);

arch('app uses strict types')
    ->expect(['App'])
    ->toUseStrictTypes();

arch('controllers have the Controller suffix')
    ->expect(['App\Http\Controllers'])
    ->toHaveSuffix('Controller');

// Controller é a base abstrata do framework: a única exceção às duas regras abaixo.
arch('controllers are final')
    ->expect('App\Http\Controllers')
    ->classes()
    ->toBeFinal()
    ->ignoring(Controller::class);

arch('controllers are single-action')
    ->expect('App\Http\Controllers')
    ->classes()
    ->toBeInvokable()
    ->ignoring(Controller::class);

arch('identity actions are final and immutable')
    ->expect('App\Auth')
    ->classes()
    ->toBeFinal()
    ->toBeReadonly();

arch('application exceptions are final')
    ->expect('App\Exceptions')
    ->classes()
    ->toBeFinal();
