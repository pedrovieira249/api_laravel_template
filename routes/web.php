<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

/*
 * Este projeto é uma API: não há páginas. A raiz só diz o que existe aqui e onde
 * está a documentação. As rotas "web" restantes vêm dos pacotes (dashboards do
 * Horizon, Pulse e Telescope, e a documentação OpenAPI em /docs/api).
 */
Route::get('/', fn (): JsonResponse => new JsonResponse([
    'name' => config('app.name'),
    'api' => url('/api/v1'),
    'docs' => url('/docs/api'),
    'health' => url('/up'),
]))->name('home');
