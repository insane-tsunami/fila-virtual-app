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

        $this->assertTrue($schema->hasColumns('estabelecimentos', ['id', 'nome', 'slug']));
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
