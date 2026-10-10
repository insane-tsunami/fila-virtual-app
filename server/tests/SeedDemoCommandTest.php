<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class SeedDemoCommandTest extends TestCase
{
    private string $banco;

    protected function setUp(): void
    {
        $this->banco = sys_get_temp_dir() . '/seed-' . bin2hex(random_bytes(4)) . '.sqlite';
        touch($this->banco);
    }

    protected function tearDown(): void
    {
        @unlink($this->banco);
    }

    /** @param array<string, string> $env @param list<string> $args @return array{int, string, string} */
    private function rodar(string $comando, array $env, array $args = []): array
    {
        $proc = proc_open(
            array_merge([PHP_BINARY, __DIR__ . '/../bin/' . $comando], $args),
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            $env + ['PATH' => (string) getenv('PATH')]
        );
        $saida = (string) stream_get_contents($pipes[1]);
        $erro = (string) stream_get_contents($pipes[2]);

        return [proc_close($proc), $saida, $erro];
    }

    /** @return array<string, string> */
    private function sqlite(): array
    {
        return ['DB_DRIVER' => 'sqlite', 'DB_NAME' => $this->banco];
    }

    public function testCriaARodaDeNovoEDizQueJaExiste(): void
    {
        [$c, $saida] = $this->rodar('migrate', $this->sqlite());
        $this->assertSame(0, $c, $saida);

        [$c, $saida] = $this->rodar('seed-demo', $this->sqlite());
        $this->assertSame(0, $c);
        $this->assertStringContainsString('Loja de demonstração criada: veste-bem', $saida);

        [$c, $saida] = $this->rodar('seed-demo', $this->sqlite());
        $this->assertSame(0, $c);
        $this->assertStringContainsString('já existe', $saida);
    }

    public function testBancoSemMigrarOrientaRodarAMigracao(): void
    {
        [$c, , $erro] = $this->rodar('seed-demo', $this->sqlite());

        $this->assertSame(1, $c);
        $this->assertStringContainsString('php bin/migrate', $erro);
    }

    public function testMysqlSemForcarRecusaAntesDeConectar(): void
    {
        [$c, $saida, $erro] = $this->rodar('seed-demo', [
            'DB_DRIVER' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_NAME' => 'inexistente',
        ]);

        $this->assertSame(1, $c);
        $this->assertSame('', $saida);
        $this->assertStringContainsString('--forcar', $erro);
        $this->assertStringNotContainsString('SQLSTATE', $erro, 'recusou antes de tentar conectar');
    }
}
