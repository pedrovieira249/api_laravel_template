<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPublicMethodParameterRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/bootstrap/app.php',
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/routes',
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withPhpSets()
    ->withComposerBased(laravel: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
    )
    ->withSkip([
        // Parâmetros de handlers e listeners são contratos, não código morto: o bus
        // passa a mensagem para __invoke(), e a descoberta de eventos inscreve um
        // listener pelo type-hint de handle(). Removê-los quebra os dois em silêncio.
        RemoveUnusedPublicMethodParameterRector::class,
    ]);
