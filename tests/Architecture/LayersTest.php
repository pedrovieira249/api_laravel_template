<?php

declare(strict_types=1);

/*
 * A regra de dependência, executável:
 *
 *   Domain  <--  Application  <--  Infrastructure / Interfaces (app/)
 *
 * Camadas internas nunca importam camadas externas nem o framework. Cada bounded
 * context é verificado separadamente, para que uma violação aponte o contexto.
 */

// Contexto novo em src/: acrescente o nome aqui e ele passa a ser vigiado.
$contexts = [
    'Shared',
];
$framework = ['Illuminate', 'App', 'Laravel', 'Symfony', 'Carbon'];

// Bounded contexts nunca importam o Domain/Application uns dos outros: a única
// ponte é um adapter de Infrastructure (anti-corruption layer, ADR 0001).
foreach ($contexts as $context) {
    $others = array_values(array_map(
        fn (string $other): string => "Core\\{$other}",
        array_filter($contexts, fn (string $other): bool => $other !== $context && $other !== 'Shared'),
    ));

    // Com um contexto só (além de Shared) ainda não há "outros" para proibir.
    if ($others === []) {
        continue;
    }

    arch("{$context} core does not depend on other contexts")
        ->expect(["Core\\{$context}\\Domain", "Core\\{$context}\\Application"])
        ->not->toUse($others);
}

arch('domain is framework-free')
    ->expect('Core\*\Domain')
    ->not->toUse($framework);

arch('application is framework-free')
    ->expect('Core\*\Application')
    ->not->toUse($framework);

foreach ($contexts as $context) {
    arch("{$context} domain does not depend on outer layers")
        ->expect("Core\\{$context}\\Domain")
        ->not->toUse(["Core\\{$context}\\Application", "Core\\{$context}\\Infrastructure"]);

    arch("{$context} application does not depend on infrastructure")
        ->expect("Core\\{$context}\\Application")
        ->not->toUse("Core\\{$context}\\Infrastructure");

    arch("http layer reaches {$context} only through its application layer")
        ->expect('App\\Http')
        ->not->toUse(["Core\\{$context}\\Infrastructure", "Core\\{$context}\\Domain\\Repository"]);
}
