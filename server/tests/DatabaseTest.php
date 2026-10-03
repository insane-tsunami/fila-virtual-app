<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Support\Database;

final class DatabaseTest extends TestCase
{
    public function testConfigDeConexaoSqliteUsaMemoriaPorPadraoEAtivaChavesEstrangeiras(): void
    {
        $config = Database::connectionConfig(['driver' => 'sqlite', 'database' => '']);

        $this->assertSame('sqlite', $config['driver']);
        $this->assertSame(':memory:', $config['database']);
        $this->assertTrue($config['foreign_key_constraints']);
    }

    public function testConfigDeConexaoMysqlUsaUtf8mb4(): void
    {
        $config = Database::connectionConfig([
            'driver' => 'mysql', 'host' => 'db.exemplo', 'database' => 'zerafilas',
            'username' => 'u', 'password' => 'p',
        ]);

        $this->assertSame('mysql', $config['driver']);
        $this->assertSame('db.exemplo', $config['host']);
        $this->assertSame('utf8mb4', $config['charset']);
        $this->assertSame('utf8mb4_unicode_ci', $config['collation']);
    }

    public function testDriverInvalidoELancadoComMensagemClara(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DB_DRIVER inválido: 'oracle'");

        Database::connectionConfig(['driver' => 'oracle']);
    }

    public function testConectaEmSqliteEmMemoriaEExecutaUmaConsulta(): void
    {
        $capsule = Database::connect(['driver' => 'sqlite', 'database' => ':memory:']);

        $this->assertSame(1, (int) $capsule->getConnection()->selectOne('select 1 as n')->n);
    }
}
