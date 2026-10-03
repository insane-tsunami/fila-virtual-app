<?php

declare(strict_types=1);

namespace Support;

use Illuminate\Database\Capsule\Manager as Capsule;
use InvalidArgumentException;

final class Database
{
    /**
     * Converte a seção `db` da configuração na configuração de conexão do Illuminate.
     *
     * @param array<string, string> $db
     * @return array<string, mixed>
     */
    public static function connectionConfig(array $db): array
    {
        $driver = $db['driver'] ?? '';

        return match ($driver) {
            'sqlite' => [
                'driver' => 'sqlite',
                'database' => $db['database'] ?: ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'mysql' => [
                'driver' => 'mysql',
                'host' => $db['host'],
                'database' => $db['database'],
                'username' => $db['username'],
                'password' => $db['password'],
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
            ],
            default => throw new InvalidArgumentException(
                "DB_DRIVER inválido: '$driver' (use 'mysql' ou 'sqlite')"
            ),
        };
    }

    /** @param array<string, string> $db */
    public static function connect(array $db): Capsule
    {
        $capsule = new Capsule();
        $capsule->addConnection(self::connectionConfig($db));
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        return $capsule;
    }
}
