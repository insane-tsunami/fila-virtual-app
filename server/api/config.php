<?php

declare(strict_types=1);

// Configuração lida do ambiente a cada chamada (ver server/.env.example).
// Retorna um array em vez de constantes para poder ser montada de formas
// diferentes nos testes. Nunca commitar valores reais neste arquivo.
$env = static function (string $key, string $default = ''): string {
    $value = getenv($key);

    return $value === false ? $default : $value;
};

// Inteiro positivo do ambiente; vazio, zero, negativo ou texto usam o padrão.
$inteiro = static function (string $key, int $default) use ($env): int {
    $value = $env($key);

    return ctype_digit($value) && (int) $value > 0 ? (int) $value : $default;
};

return [
    'db' => [
        'driver' => $env('DB_DRIVER', 'mysql'),
        'host' => $env('DB_HOST', 'localhost'),
        'database' => $env('DB_NAME', 'zerafilas'),
        'username' => $env('DB_USER'),
        'password' => $env('DB_PASS'),
    ],
    // Origem única permitida para CORS. Vazia = sem cabeçalhos CORS.
    'cors_origin' => $env('CORS_ORIGIN'),
    // Limite de tentativas: erros por e-mail/IP/conta na janela (minutos) e cadastros por IP.
    'rate_limit' => [
        'login_email' => $inteiro('RATE_LIMIT_LOGIN_EMAIL', 5),
        'login_ip' => $inteiro('RATE_LIMIT_LOGIN_IP', 20),
        'senha_conta' => $inteiro('RATE_LIMIT_SENHA_CONTA', 5),
        'janela_minutos' => $inteiro('RATE_LIMIT_JANELA_MIN', 15),
        'cadastro_ip' => $inteiro('RATE_LIMIT_CADASTRO_IP', 5),
        'cadastro_janela_minutos' => $inteiro('RATE_LIMIT_CADASTRO_JANELA_MIN', 60),
    ],
    // IPs ou faixas CIDR de proxies confiáveis, separados por vírgula (vazio = nenhum).
    'trusted_proxies' => array_values(array_filter(
        array_map('trim', explode(',', $env('TRUSTED_PROXIES'))),
        static fn (string $item): bool => $item !== ''
    )),
];
