<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Database\QueryException;
use Support\Migrator;

final class MigrationTest extends DatabaseTestCase
{
    private function migrator(): Migrator
    {
        return new Migrator($this->db, __DIR__ . '/../database/migrations');
    }

    public function testExecutaAsMigracoesEmOrdemERegistraCadaUma(): void
    {
        $executadas = $this->migrator()->run();

        $this->assertSame([
            '0001_create_estabelecimentos',
            '0002_create_entradas_fila',
            '0003_seed_estabelecimento_veste_bem',
            '0004_add_endereco_publico_to_estabelecimentos',
        ], $executadas);
        $this->assertSame($executadas, $this->db->table('migrations')->orderBy('id')->pluck('migration')->all());
    }

    public function testRodarDuasVezesEIdempotente(): void
    {
        $this->migrator()->run();

        $this->assertSame([], $this->migrator()->run(), 'a segunda execução não deve fazer nada');
        $this->assertSame(1, $this->db->table('estabelecimentos')->where('slug', 'veste-bem')->count());
    }

    public function testCriaTabelasEColunasPrevistas(): void
    {
        $this->migrate();
        $schema = $this->db->schema();

        $this->assertTrue($schema->hasColumns('estabelecimentos', ['id', 'nome', 'slug', 'endereco_publico']));
        $this->assertTrue($schema->hasColumns('entradas_fila', [
            'id', 'estabelecimento_id', 'codigo', 'telefone', 'status',
            'entrou_em', 'iniciou_em', 'finalizou_em',
        ]));
    }

    public function testInsereOEstabelecimentoDePartida(): void
    {
        $this->migrate();

        $linha = $this->db->table('estabelecimentos')->where('slug', 'veste-bem')->first();

        $this->assertNotNull($linha);
        $this->assertSame('Veste Bem', $linha->nome);
    }

    public function testEnderecoPublicoComecaNuloEAceitaUmaOrigem(): void
    {
        $this->migrate();

        $this->assertNull($this->db->table('estabelecimentos')->where('slug', 'veste-bem')->value('endereco_publico'));

        $this->db->table('estabelecimentos')->where('slug', 'veste-bem')
            ->update(['endereco_publico' => 'https://loja.exemplo.com']);
        $this->assertSame(
            'https://loja.exemplo.com',
            $this->db->table('estabelecimentos')->where('slug', 'veste-bem')->value('endereco_publico')
        );
    }

    public function testMigracaoNovaNaoPerdeDadosDeUmBancoJaMigradoAteATerceira(): void
    {
        // Simula um banco que já estava na versão anterior: roda só as 3 primeiras e depois a 4ª.
        $dir = sys_get_temp_dir() . '/migracoes-' . bin2hex(random_bytes(4));
        mkdir($dir);
        foreach (glob(__DIR__ . '/../database/migrations/000[123]_*.php') as $arquivo) {
            copy($arquivo, $dir . '/' . basename($arquivo));
        }
        (new Migrator($this->db, $dir))->run();
        $this->db->table('estabelecimentos')->insert(['nome' => 'Outra Loja', 'slug' => 'outra-loja']);

        $executadas = $this->migrator()->run();

        $this->assertSame(['0004_add_endereco_publico_to_estabelecimentos'], $executadas);
        $this->assertSame(2, $this->db->table('estabelecimentos')->count(), 'as linhas existentes continuam');
        $this->assertNull($this->db->table('estabelecimentos')->where('slug', 'outra-loja')->value('endereco_publico'));

        array_map('unlink', glob($dir . '/*.php'));
        rmdir($dir);
    }

    public function testSlugDuplicadoERecusadoPeloBanco(): void
    {
        $this->migrate();

        $this->expectException(QueryException::class);
        $this->db->table('estabelecimentos')->insert(['nome' => 'Outra', 'slug' => 'veste-bem']);
    }

    public function testCodigoDuplicadoERecusadoPeloBanco(): void
    {
        $this->migrate();
        $loja = $this->criarEstabelecimento();
        $entrada = [
            'estabelecimento_id' => $loja, 'codigo' => 'abc', 'telefone' => '5511971778203',
            'status' => 'aguardando', 'entrou_em' => '2026-10-03 12:00:00',
        ];
        $this->db->table('entradas_fila')->insert($entrada);

        $this->expectException(QueryException::class);
        $this->db->table('entradas_fila')->insert($entrada);
    }

    public function testEntradaDeEstabelecimentoInexistenteERecusadaPelaChaveEstrangeira(): void
    {
        $this->migrate();

        $this->expectException(QueryException::class);
        $this->db->table('entradas_fila')->insert([
            'estabelecimento_id' => 9999, 'codigo' => 'xyz', 'telefone' => '5511971778203',
            'status' => 'aguardando', 'entrou_em' => '2026-10-03 12:00:00',
        ]);
    }
}
