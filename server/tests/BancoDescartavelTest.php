<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BancoDescartavelTest extends TestCase
{
    public function testSqliteSempreEPermitido(): void
    {
        DatabaseTestCase::garantirBancoDescartavel(['driver' => 'sqlite', 'database' => ':memory:']);
        DatabaseTestCase::garantirBancoDescartavel(['driver' => 'sqlite', 'database' => '/var/dados/zerafilas.sqlite']);

        $this->addToAssertionCount(2);
    }

    public function testMysqlComNomeDeTesteEPermitido(): void
    {
        DatabaseTestCase::garantirBancoDescartavel(['driver' => 'mysql', 'database' => 'zerafilas_test']);
        DatabaseTestCase::garantirBancoDescartavel(['driver' => 'mysql', 'database' => 'TESTES']);

        $this->addToAssertionCount(2);
    }

    public function testMysqlDeProducaoERecusadoAntesDeApagarQualquerCoisa(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Recusando rodar os testes no banco "zerafilas"');

        DatabaseTestCase::garantirBancoDescartavel(['driver' => 'mysql', 'database' => 'zerafilas']);
    }
}
