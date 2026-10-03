<?php

declare(strict_types=1);

// Configuração lida do ambiente a cada chamada (ver server/.env.example).
// Retorna um array em vez de constantes para poder ser montada de formas
// diferentes nos testes. Nunca commitar valores reais neste arquivo.
$env = static function (string $key, string $default = ''): string {
    $value = getenv($key);

    return $value === false ? $default : $value;
};

return [
    'db' => [
        'driver' => $env('DB_DRIVER', 'mysql'),
        'host' => $env('DB_HOST', 'localhost'),
        'database' => $env('DB_NAME', 'zerafilas'),
        'username' => $env('DB_USER'),
        'password' => $env('DB_PASS'),
    ],
    // Chave compartilhada provisória das rotas do dashboard. Vazia = rotas fechadas.
    'api_key' => $env('API_KEY'),
    // Origem única permitida para CORS. Vazia = sem cabeçalhos CORS.
    'cors_origin' => $env('CORS_ORIGIN'),
];
