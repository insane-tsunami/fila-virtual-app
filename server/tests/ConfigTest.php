<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    private const VARIAVEIS = ['DB_DRIVER', 'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'API_KEY', 'CORS_ORIGIN'];

    /** @var array<string, string|false> */
    private array $original = [];

    protected function setUp(): void
    {
        foreach (self::VARIAVEIS as $nome) {
            $this->original[$nome] = getenv($nome);
            putenv($nome);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->original as $nome => $valor) {
            putenv($valor === false ? $nome : "$nome=$valor");
        }
    }

    /** @return array<string, mixed> */
    private function carregar(): array
    {
        return require __DIR__ . '/../api/config.php';
    }

    public function testAplicaOsPadroesQuandoNadaEstaDefinido(): void
    {
        $config = $this->carregar();

        $this->assertSame('mysql', $config['db']['driver']);
        $this->assertSame('localhost', $config['db']['host']);
        $this->assertSame('zerafilas', $config['db']['database']);
        $this->assertSame('', $config['db']['username']);
        $this->assertSame('', $config['db']['password']);
        $this->assertArrayNotHasKey('api_key', $config, 'a chave provisória foi removida (login por conta)');
        $this->assertSame('', $config['cors_origin']);
    }

    public function testLeOsValoresDoAmbiente(): void
    {
        putenv('DB_DRIVER=sqlite');
        putenv('DB_NAME=:memory:');
        putenv('API_KEY=ignorada');
        putenv('CORS_ORIGIN=https://front.exemplo.com');

        $config = $this->carregar();

        $this->assertSame('sqlite', $config['db']['driver']);
        $this->assertSame(':memory:', $config['db']['database']);
        $this->assertArrayNotHasKey('api_key', $config, 'API_KEY no ambiente é ignorada');
        $this->assertSame('https://front.exemplo.com', $config['cors_origin']);
    }

    public function testCadaChamadaReleOAmbiente(): void
    {
        putenv('CORS_ORIGIN=https://primeira.exemplo.com');
        $primeira = $this->carregar();
        putenv('CORS_ORIGIN=https://segunda.exemplo.com');
        $segunda = $this->carregar();

        $this->assertSame('https://primeira.exemplo.com', $primeira['cors_origin']);
        $this->assertSame('https://segunda.exemplo.com', $segunda['cors_origin']);
    }

    public function testNaoDefineConstantesGlobais(): void
    {
        $this->carregar();

        foreach (['DBDRIVER', 'DBHOST', 'DBNAME', 'DBUSER', 'DBPASS'] as $constante) {
            $this->assertFalse(defined($constante), "$constante não deve existir");
        }
    }
}
