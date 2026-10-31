<?php

declare(strict_types=1);

namespace Tests;

use PDO;
use PHPUnit\Framework\TestCase;

final class LimparCommandTest extends TestCase
{
    private string $banco;

    protected function setUp(): void
    {
        $this->banco = sys_get_temp_dir() . '/limpar-' . bin2hex(random_bytes(4)) . '.sqlite';
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

    private function migrar(): void
    {
        [$c, $saida] = $this->rodar('migrate', $this->sqlite());
        $this->assertSame(0, $c, $saida);
    }

    private function pdo(): PDO
    {
        return new PDO('sqlite:' . $this->banco);
    }

    private function haHoras(int $horas): string
    {
        return gmdate('Y-m-d H:i:s', time() - $horas * 3600);
    }

    private function haDias(int $dias): string
    {
        return gmdate('Y-m-d H:i:s', time() - $dias * 86400);
    }

    /** @return array<string, int> */
    private function contagensDaSaida(string $saida): array
    {
        preg_match_all('/^(Entradas encerradas|Telefones apagados|Sessões removidas|Tokens removidos|Contadores removidos): (\d+)$/m', $saida, $m);

        return array_combine($m[1], array_map('intval', $m[2]));
    }

    public function testBancoSemMigrarSaiComErroENaoCriaTabelas(): void
    {
        [$c, $saida, $erro] = $this->rodar('limpar', $this->sqlite());

        $this->assertSame(1, $c);
        $this->assertSame('', $saida);
        $this->assertStringContainsString('php bin/migrate', $erro);
        $this->assertSame(
            0,
            (int) $this->pdo()->query("select count(*) from sqlite_master where type = 'table'")->fetchColumn(),
            'não pode criar a tabela migrations'
        );
    }

    public function testBancoMigradoSemDadosZeraTudo(): void
    {
        $this->migrar();

        [$c, $saida] = $this->rodar('limpar', $this->sqlite());

        $this->assertSame(0, $c);
        $this->assertSame([
            'Entradas encerradas' => 0,
            'Telefones apagados' => 0,
            'Sessões removidas' => 0,
            'Tokens removidos' => 0,
            'Contadores removidos' => 0,
        ], $this->contagensDaSaida($saida));
    }

    public function testComDadosVencidosMostraAsContagensEASegundaExecucaoZera(): void
    {
        $this->migrar();
        $db = $this->pdo();
        $db->exec("insert into estabelecimentos (nome, slug) values ('Loja', 'loja')");
        $loja = (int) $db->lastInsertId();
        $insert = $db->prepare(
            'insert into entradas_fila (estabelecimento_id, codigo, telefone, status, entrou_em, finalizou_em)'
            . ' values (?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([$loja, 'esquecida', '5511971778203', 'aguardando', $this->haHoras(25), null]);
        $insert->execute([$loja, 'antiga', '5511971778203', 'finalizado', $this->haDias(40), $this->haDias(31)]);

        [$c, $saida] = $this->rodar('limpar', $this->sqlite());

        $this->assertSame(0, $c);
        $this->assertSame([
            'Entradas encerradas' => 1,
            'Telefones apagados' => 1,
            'Sessões removidas' => 0,
            'Tokens removidos' => 0,
            'Contadores removidos' => 0,
        ], $this->contagensDaSaida($saida));

        [$c, $saida] = $this->rodar('limpar', $this->sqlite());

        $this->assertSame(0, $c);
        $this->assertSame([
            'Entradas encerradas' => 0,
            'Telefones apagados' => 0,
            'Sessões removidas' => 0,
            'Tokens removidos' => 0,
            'Contadores removidos' => 0,
        ], $this->contagensDaSaida($saida));
    }
}
