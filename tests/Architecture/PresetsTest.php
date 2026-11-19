<?php

declare(strict_types=1);
use App\Providers\AppServiceProvider;

arch()->preset()->php();
arch()->preset()->security();
// O preset proíbe usar App\Providers em qualquer lugar. O AppServiceProvider
// registra o provider do Telescope pelo nome da classe (só em local), o que conta como uso.
arch()->preset()->laravel()
    ->ignoring(AppServiceProvider::class);
