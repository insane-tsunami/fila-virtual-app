<?php

declare(strict_types=1);

namespace Tests;

use Services\ContaService;
use Services\DadosInvalidosException;
use Services\NaoAutenticadoException;
use Services\SessaoService;

final class SessaoServiceTest extends DatabaseTestCase
{
    private SessaoService $sessoes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrate();
        $this->sessoes = new SessaoService();
        (new ContaService($this->sessoes))->cadastrar([
            'email' => 'contato@vestebem.com', 'cnpj' => '93339970000105',
            'nome' => 'Moda Azul', 'senha' => 'senha-segura-1',
        ]);
        $this->db->table('sessoes')->delete();
    }

    public function testLoginCorretoAbreSessaoComTokenContaELoja(): void
    {
        $r = $this->sessoes->entrar('contato@vestebem.com', 'senha-segura-1');

        $this->assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $r['token']);
        $this->assertSame('contato@vestebem.com', $r['conta']['email']);
        $this->assertSame('moda-azul', $r['loja']['slug']);
        $this->assertNotNull($this->sessoes->resolver($r['token']));
    }

    public function testEmailNaoDiferenciaMaiusculasENemEspacos(): void
    {
        $r = $this->sessoes->entrar('  CONTATO@VesteBem.com ', 'senha-segura-1');

        $this->assertSame('contato@vestebem.com', $r['conta']['email']);
    }

    public function testBancoGuardaSoOHashDoToken(): void
    {
        $r = $this->sessoes->entrar('contato@vestebem.com', 'senha-segura-1');

        $guardado = (string) $this->db->table('sessoes')->value('token_hash');
        $this->assertNotSame($r['token'], $guardado);
        $this->assertSame(hash('sha256', $r['token']), $guardado);
        $this->assertStringNotContainsString($r['token'], json_encode($this->db->table('sessoes')->get(), JSON_THROW_ON_ERROR));
    }

    public function testSenhaErradaEEmailDesconhecidoDaoAMesmaMensagemESemSessao(): void
    {
        $mensagens = [];
        foreach ([['contato@vestebem.com', 'errada-errada'], ['ninguem@exemplo.com', 'senha-segura-1'], ['nao-e-email', 'senha-segura-1']] as [$e, $s]) {
            try {
                $this->sessoes->entrar($e, $s);
                $this->fail('devia recusar');
            } catch (NaoAutenticadoException $ex) {
                $mensagens[] = $ex->getMessage();
            }
        }

        $this->assertSame(['E-mail ou senha incorretos.'], array_values(array_unique($mensagens)));
        $this->assertSame(0, $this->db->table('sessoes')->count());
    }

    public function testCamposEmBrancoSao422(): void
    {
        foreach ([['', 'x'], ['a@b.com', ''], [null, 'x'], ['a@b.com', null], [123, 'x']] as [$e, $s]) {
            try {
                $this->sessoes->entrar($e, $s);
                $this->fail('devia recusar');
            } catch (DadosInvalidosException) {
            }
        }
        $this->assertSame(0, $this->db->table('sessoes')->count());
    }

    public function testDoisLoginsDaMesmaContaGeramTokensDiferentesEAmbosValem(): void
    {
        $a = $this->sessoes->entrar('contato@vestebem.com', 'senha-segura-1');
        $b = $this->sessoes->entrar('contato@vestebem.com', 'senha-segura-1');

        $this->assertNotSame($a['token'], $b['token']);
        $this->assertNotNull($this->sessoes->resolver($a['token']));
        $this->assertNotNull($this->sessoes->resolver($b['token']));
    }

    public function testSessaoValeSeteDiasEExpiraDepois(): void
    {
        $r = $this->sessoes->entrar('contato@vestebem.com', 'senha-segura-1');

        $esperado = time() + 7 * 86400;
        $this->assertEqualsWithDelta($esperado, strtotime($r['expira_em'] . ' UTC'), 5);

        $this->db->table('sessoes')->update(['expira_em' => gmdate('Y-m-d H:i:s', time() - 1)]);
        $this->assertNull($this->sessoes->resolver($r['token']));
    }

    public function testTokenDesconhecidoOuVazioNaoResolve(): void
    {
        $this->assertNull($this->sessoes->resolver(''));
        $this->assertNull($this->sessoes->resolver(str_repeat('0', 64)));
        $this->assertNull($this->sessoes->resolver('qualquer coisa'));
    }

    public function testSairEncerraSoASessaoDoToken(): void
    {
        $a = $this->sessoes->entrar('contato@vestebem.com', 'senha-segura-1');
        $b = $this->sessoes->entrar('contato@vestebem.com', 'senha-segura-1');

        $this->sessoes->sair((int) $this->sessoes->resolver($a['token'])->id);

        $this->assertNull($this->sessoes->resolver($a['token']));
        $this->assertNotNull($this->sessoes->resolver($b['token']));
    }

    public function testLoginApagaAsSessoesVencidas(): void
    {
        $velha = $this->sessoes->entrar('contato@vestebem.com', 'senha-segura-1');
        $this->db->table('sessoes')->update(['expira_em' => gmdate('Y-m-d H:i:s', time() - 60)]);

        $nova = $this->sessoes->entrar('contato@vestebem.com', 'senha-segura-1');

        $this->assertSame(1, $this->db->table('sessoes')->count());
        $this->assertNull($this->sessoes->resolver($velha['token']));
        $this->assertNotNull($this->sessoes->resolver($nova['token']));
    }
}
