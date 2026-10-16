<?php

declare(strict_types=1);

namespace Tests;

use Services\ContaService;
use Services\ExclusaoDeConta;
use Services\LimiteDeTentativas;
use Services\SessaoService;

final class ExclusaoDeContaTest extends DatabaseTestCase
{
    private const TABELAS_DA_CONTA = ['estabelecimentos', 'sessoes', 'confirmacoes_email', 'redefinicoes_senha'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrate();
        $this->semearDemonstracao();
    }

    /** Cadastra uma conta com loja, sessão, entrada na fila, links e contadores; devolve o id. */
    private function contaCompleta(string $email, string $cnpj, string $nome, string $sufixo): int
    {
        (new ContaService(new SessaoService()))->cadastrar([
            'email' => $email, 'cnpj' => $cnpj, 'nome' => $nome, 'senha' => 'senha-segura-1',
        ]);
        $id = (int) $this->db->table('contas')->where('email', $email)->value('id');
        $loja = (int) $this->db->table('estabelecimentos')->where('conta_id', $id)->value('id');
        $agora = '2026-10-14 12:00:00';

        $this->db->table('entradas_fila')->insert([
            'estabelecimento_id' => $loja, 'codigo' => 'cod-' . $sufixo, 'telefone' => '5511971778203',
            'status' => 'aguardando', 'entrou_em' => $agora,
        ]);
        $this->db->table('confirmacoes_email')->insert([
            'conta_id' => $id, 'email' => $email, 'token_hash' => str_repeat($sufixo, 64),
            'criado_em' => $agora, 'expira_em' => '2026-10-15 12:00:00',
        ]);
        $this->db->table('redefinicoes_senha')->insert([
            'conta_id' => $id, 'token_hash' => str_repeat($sufixo, 64),
            'criado_em' => $agora, 'expira_em' => '2026-10-14 13:00:00',
        ]);
        foreach ([
            [LimiteDeTentativas::SENHA_CONTA, LimiteDeTentativas::chave($id)],
            [LimiteDeTentativas::CONFIRMACAO_CONTA, LimiteDeTentativas::chave($id)],
            [LimiteDeTentativas::LOGIN_EMAIL, LimiteDeTentativas::chave($email)],
            [LimiteDeTentativas::CADASTRO_IP, LimiteDeTentativas::chave('203.0.113.' . ord($sufixo))],
        ] as [$escopo, $chave]) {
            $this->db->table('limites_tentativas')->insert([
                'escopo' => $escopo, 'chave' => $chave, 'contagem' => 1, 'expira_em' => '2030-01-01 00:00:00',
            ]);
        }

        return $id;
    }

    private function contar(string $tabela, int $contaId): int
    {
        return $this->db->table($tabela)->where('conta_id', $contaId)->count();
    }

    public function testApagaTudoQueEDaConta(): void
    {
        $id = $this->contaCompleta('a@exemplo.com', '93339970000105', 'Moda Azul', 'a');
        $loja = (int) $this->db->table('estabelecimentos')->where('conta_id', $id)->value('id');
        foreach (self::TABELAS_DA_CONTA as $tabela) {
            $this->assertSame(1, $this->contar($tabela, $id), $tabela);
        }

        (new ExclusaoDeConta())->excluir($id);

        $this->assertFalse($this->db->table('contas')->where('id', $id)->exists());
        foreach (self::TABELAS_DA_CONTA as $tabela) {
            $this->assertSame(0, $this->contar($tabela, $id), $tabela);
        }
        $this->assertFalse($this->db->table('estabelecimentos')->where('slug', 'moda-azul')->exists(), 'o slug fica livre');
        $this->assertSame(0, $this->db->table('entradas_fila')->where('estabelecimento_id', $loja)->count());
        $this->assertSame(0, $this->db->table('limites_tentativas')
            ->whereIn('escopo', [LimiteDeTentativas::SENHA_CONTA, LimiteDeTentativas::CONFIRMACAO_CONTA])
            ->count());
    }

    public function testContadoresPorEmailEPorIpFicam(): void
    {
        $id = $this->contaCompleta('a@exemplo.com', '93339970000105', 'Moda Azul', 'a');

        (new ExclusaoDeConta())->excluir($id);

        $this->assertSame(
            [LimiteDeTentativas::CADASTRO_IP, LimiteDeTentativas::LOGIN_EMAIL],
            $this->db->table('limites_tentativas')->orderBy('escopo')->pluck('escopo')->all()
        );
    }

    public function testOutrasContasLojasEntradasEADemonstracaoFicam(): void
    {
        $alvo = $this->contaCompleta('a@exemplo.com', '93339970000105', 'Moda Azul', 'a');
        $outra = $this->contaCompleta('b@exemplo.com', '12ABC34501DE35', 'Loja B', 'b');
        $demonstracao = (int) $this->db->table('estabelecimentos')->where('slug', 'veste-bem')->value('id');
        $this->db->table('entradas_fila')->insert([
            'estabelecimento_id' => $demonstracao, 'codigo' => 'cod-demo', 'telefone' => '5511971778203',
            'status' => 'aguardando', 'entrou_em' => '2026-10-14 12:00:00',
        ]);

        (new ExclusaoDeConta())->excluir($alvo);

        $this->assertTrue($this->db->table('contas')->where('id', $outra)->exists());
        foreach (self::TABELAS_DA_CONTA as $tabela) {
            $this->assertSame(1, $this->contar($tabela, $outra), $tabela);
        }
        $this->assertEqualsCanonicalizing(['loja-b', 'veste-bem'], $this->db->table('estabelecimentos')->pluck('slug')->all());
        $this->assertEqualsCanonicalizing(['cod-b', 'cod-demo'], $this->db->table('entradas_fila')->pluck('codigo')->all());
        $this->assertSame(2, $this->db->table('limites_tentativas')->where('chave', LimiteDeTentativas::chave($outra))->count());
    }

    public function testContaInexistenteNaoMexeEmNada(): void
    {
        $id = $this->contaCompleta('a@exemplo.com', '93339970000105', 'Moda Azul', 'a');

        (new ExclusaoDeConta())->excluir(999999);

        $this->assertTrue($this->db->table('contas')->where('id', $id)->exists());
        $this->assertSame(2, $this->db->table('estabelecimentos')->count(), 'a loja da conta e a demonstração');
    }
}
