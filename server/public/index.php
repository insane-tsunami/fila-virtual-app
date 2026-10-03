<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Support\Aplicacao;
use Support\Database;

date_default_timezone_set('UTC');

$config = require __DIR__ . '/../api/config.php';
Database::connect($config['db']);

Aplicacao::criar($config)->run();
