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
            '0005_create_contas_e_sessoes',
            '0006_create_limites_tentativas',
            '0007_create_redefinicoes_senha',
            '0008_add_email_confirmado_e_confirmacoes_email',
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

    public function testCriaATabelaDeLimitesDeTentativasComChaveComposta(): void
    {
        $this->migrate();
        $tabela = $this->db->table('limites_tentativas');

        $this->assertTrue($this->db->schema()->hasColumns('limites_tentativas', ['escopo', 'chave', 'contagem', 'expira_em']));

        $linha = ['escopo' => 'login_email', 'chave' => str_repeat('a', 64), 'contagem' => 1, 'expira_em' => '2030-01-01 00:00:00'];
        $tabela->insert($linha);
        $tabela->insert(['escopo' => 'login_ip'] + $linha);

        $this->expectException(QueryException::class);
        $tabela->insert($linha);
    }

    public function testCriaATabelaDeRedefinicoesDeSenhaComUmTokenPorConta(): void
    {
        $this->migrate();
        $this->assertTrue($this->db->schema()->hasColumns(
            'redefinicoes_senha',
            ['id', 'conta_id', 'token_hash', 'criado_em', 'expira_em']
        ));
        $contaId = $this->db->table('contas')->insertGetId([
            'email' => 'a@exemplo.com', 'cnpj' => '93339970000105', 'senha_hash' => 'x', 'criado_em' => '2030-01-01 00:00:00',
        ]);
        $linha = ['conta_id' => $contaId, 'token_hash' => str_repeat('a', 64), 'criado_em' => '2030-01-01 00:00:00', 'expira_em' => '2030-01-01 01:00:00'];
        $this->db->table('redefinicoes_senha')->insert($linha);

        $this->expectException(QueryException::class);
        $this->db->table('redefinicoes_senha')->insert(['token_hash' => str_repeat('b', 64)] + $linha);
    }

    public function testCriaAColunaDeEmailConfirmadoEATabelaDeConfirmacoes(): void
    {
        $this->migrate();
        $this->assertTrue($this->db->schema()->hasColumn('contas', 'email_confirmado_em'));
        $this->assertTrue($this->db->schema()->hasColumns(
            'confirmacoes_email',
            ['id', 'conta_id', 'email', 'token_hash', 'criado_em', 'expira_em']
        ));
        $contaId = $this->db->table('contas')->insertGetId([
            'email' => 'a@exemplo.com', 'cnpj' => '93339970000105', 'senha_hash' => 'x', 'criado_em' => '2030-01-01 00:00:00',
        ]);
        $linha = [
            'conta_id' => $contaId, 'email' => 'a@exemplo.com', 'token_hash' => str_repeat('a', 64),
            'criado_em' => '2030-01-01 00:00:00', 'expira_em' => '2030-01-02 00:00:00',
        ];
        $this->db->table('confirmacoes_email')->insert($linha);

        $this->expectException(QueryException::class);
        $this->db->table('confirmacoes_email')->insert(['token_hash' => str_repeat('b', 64)] + $linha);
    }

    public function testContasAnterioresAMigracaoFicamComEmailNaoConfirmado(): void
    {
        $dir = sys_get_temp_dir() . '/migracoes-' . bin2hex(random_bytes(4));
        mkdir($dir);
        foreach (glob(__DIR__ . '/../database/migrations/000[1234567]_*.php') as $arquivo) {
            copy($arquivo, $dir . '/' . basename($arquivo));
        }
        (new Migrator($this->db, $dir))->run();
        $this->db->table('contas')->insert([
            'email' => 'antiga@exemplo.com', 'cnpj' => '93339970000105', 'senha_hash' => 'x', 'criado_em' => '2030-01-01 00:00:00',
        ]);

        $executadas = $this->migrator()->run();

        $this->assertSame(['0008_add_email_confirmado_e_confirmacoes_email'], $executadas);
        $conta = $this->db->table('contas')->where('email', 'antiga@exemplo.com')->first();
        $this->assertNull($conta->email_confirmado_em, 'sem anistia: a conta antiga não está confirmada');

        array_map('unlink', glob($dir . '/*.php'));
        rmdir($dir);
    }

    public function testCriaAsTabelasDeContasESessoes(): void
    {
        $this->migrate();
        $schema = $this->db->schema();

        $this->assertTrue($schema->hasColumns('contas', ['id', 'email', 'cnpj', 'senha_hash', 'criado_em']));
        $this->assertTrue($schema->hasColumns('sessoes', ['id', 'conta_id', 'token_hash', 'criado_em', 'expira_em']));
        $this->assertTrue($schema->hasColumn('estabelecimentos', 'conta_id'));
    }

    public function testLojaDePartidaNaoTemDono(): void
    {
        $this->migrate();

        $this->assertNull($this->db->table('estabelecimentos')->where('slug', 'veste-bem')->value('conta_id'));
    }

    /** @return array<string, mixed> */
    private function conta(string $email = 'a@exemplo.com', string $cnpj = '93339970000105'): array
    {
        return ['email' => $email, 'cnpj' => $cnpj, 'senha_hash' => 'hash', 'criado_em' => '2026-10-04 10:00:00'];
    }

    public function testEmailDuplicadoERecusadoPeloBanco(): void
    {
        $this->migrate();
        $this->db->table('contas')->insert($this->conta());

        $this->expectException(QueryException::class);
        $this->db->table('contas')->insert($this->conta('a@exemplo.com', '11111111000191'));
    }

    public function testCnpjDuplicadoERecusadoPeloBanco(): void
    {
        $this->migrate();
        $this->db->table('contas')->insert($this->conta());

        $this->expectException(QueryException::class);
        $this->db->table('contas')->insert($this->conta('b@exemplo.com'));
    }

    public function testTokenDeSessaoDuplicadoERecusadoPeloBanco(): void
    {
        $this->migrate();
        $conta = $this->db->table('contas')->insertGetId($this->conta());
        $sessao = [
            'conta_id' => $conta, 'token_hash' => str_repeat('a', 64),
            'criado_em' => '2026-10-04 10:00:00', 'expira_em' => '2026-10-11 10:00:00',
        ];
        $this->db->table('sessoes')->insert($sessao);

        $this->expectException(QueryException::class);
        $this->db->table('sessoes')->insert($sessao);
    }

    public function testSessaoDeContaInexistenteERecusadaPelaChaveEstrangeira(): void
    {
        $this->migrate();

        $this->expectException(QueryException::class);
        $this->db->table('sessoes')->insert([
            'conta_id' => 9999, 'token_hash' => str_repeat('b', 64),
            'criado_em' => '2026-10-04 10:00:00', 'expira_em' => '2026-10-11 10:00:00',
        ]);
    }

    public function testUmaContaNaoPodeTerDuasLojas(): void
    {
        $this->migrate();
        $conta = $this->db->table('contas')->insertGetId($this->conta());
        $this->db->table('estabelecimentos')->insert(['nome' => 'A', 'slug' => 'a', 'conta_id' => $conta]);

        $this->expectException(QueryException::class);
        $this->db->table('estabelecimentos')->insert(['nome' => 'B', 'slug' => 'b', 'conta_id' => $conta]);
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

    public function testAtualizarDaQuartaParaAQuintaMantemAsLojasSemDono(): void
    {
        $dir = sys_get_temp_dir() . '/migracoes-' . bin2hex(random_bytes(4));
        mkdir($dir);
        foreach (glob(__DIR__ . '/../database/migrations/000[1234]_*.php') as $arquivo) {
            copy($arquivo, $dir . '/' . basename($arquivo));
        }
        (new Migrator($this->db, $dir))->run();
        $this->db->table('estabelecimentos')->where('slug', 'veste-bem')
            ->update(['endereco_publico' => 'https://loja.exemplo.com']);

        $executadas = $this->migrator()->run();

        $this->assertSame([
            '0005_create_contas_e_sessoes',
            '0006_create_limites_tentativas',
            '0007_create_redefinicoes_senha',
            '0008_add_email_confirmado_e_confirmacoes_email',
        ], $executadas);
        $loja = $this->db->table('estabelecimentos')->where('slug', 'veste-bem')->first();
        $this->assertNull($loja->conta_id);
        $this->assertSame('https://loja.exemplo.com', $loja->endereco_publico);

        array_map('unlink', glob($dir . '/*.php'));
        rmdir($dir);
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

        $this->assertSame([
            '0004_add_endereco_publico_to_estabelecimentos',
            '0005_create_contas_e_sessoes',
            '0006_create_limites_tentativas',
            '0007_create_redefinicoes_senha',
            '0008_add_email_confirmado_e_confirmacoes_email',
        ], $executadas);
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
