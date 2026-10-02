<?php

// Credenciais do banco vêm de variáveis de ambiente (ver server/.env.example).
// Nunca commitar valores reais neste arquivo.
$env = function ($key, $default = null) {
    $value = getenv($key);
    return $value === false ? $default : $value;
};

defined('DBDRIVER') or define('DBDRIVER', $env('DB_DRIVER', 'mysql'));
defined('DBHOST') or define('DBHOST', $env('DB_HOST', 'localhost'));
defined('DBNAME') or define('DBNAME', $env('DB_NAME', 'zerafilas'));
defined('DBUSER') or define('DBUSER', $env('DB_USER', ''));
defined('DBPASS') or define('DBPASS', $env('DB_PASS', ''));
