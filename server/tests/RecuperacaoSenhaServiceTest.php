<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Services\ContaService;
use Services\DadosInvalidosException;
use Services\Mailer;
use Services\MailerFalhouException;
use Services\NaoAutenticadoException;
use Services\RecuperacaoSenhaService;
use Services\SessaoService;
use Support\Senha;

final class RecuperacaoSenhaServiceTest extends DatabaseTestCase
{
    private const EMAIL = 'dona@exemplo.com';
    private const APP_URL = 'https://app.exemplo.com';

    private SessaoService $sessoes;
    private MailerEmMemoria $mailer;
    private RecuperacaoSenhaService $servico;

    /** @var list<string> */
    private array $log = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrate();
        $this->log = [];
        $this->sessoes = new SessaoService();
        (new ContaService($this->sessoes))->cadastrar([
            'email' => self::EMAIL, 'cnpj' => '93339970000105', 'nome' => 'Moda Azul', 'senha' => 'senha-antiga-1',
        ]);
        $this->confirmarEmail(self::EMAIL);
        $this->mailer = new MailerEmMemoria();
        $this->servico = $this->servico($this->mailer);
    }

    private function servico(Mailer $mailer, string $appUrl = self::APP_URL): RecuperacaoSenhaService
    {
        return new RecuperacaoSenhaService($this->sessoes, $mailer, $appUrl, null, function (string $mensagem): void {
            $this->log[] = $mensagem;
        });
    }

    /** O link de redefinição só vai para e-mail confirmado. */
    private function confirmarEmail(string $email): void
    {
        $this->db->table('contas')->where('email', $email)->update(['email_confirmado_em' => gmdate('Y-m-d H:i:s')]);
    }

    private function pedirEPegarToken(string $email = self::EMAIL): string
    {
        $this->servico->pedir($email);
        $token = $this->mailer->ultimoToken();
        $this->assertNotNull($token);

        return $token;
    }

    private function loginComSucesso(string $senha): bool
    {
        try {
            $this->sessoes->entrar(self::EMAIL, $senha);

            return true;
        } catch (NaoAutenticadoException) {
            return false;
        }
    }

    // --- Pedido ----------------------------------------------------------------------------

    public function testContaExistenteRecebeUmEmailComOLinkEOPrazo(): void
    {
        $resposta = $this->servico->pedir(self::EMAIL);

        $this->assertSame(RecuperacaoSenhaService::MENSAGEM_PEDIDO, $resposta);
        $this->assertCount(1, $this->mailer->enviados);
        $mensagem = $this->mailer->enviados[0];
        $this->assertSame(self::EMAIL, $mensagem['para']);
        $this->assertSame(RecuperacaoSenhaService::ASSUNTO, $mensagem['assunto']);
        $this->assertMatchesRegularExpression(
            '~https://app\.exemplo\.com/redefinir-senha#token=[0-9a-f]{64}\b~',
            $mensagem['texto']
        );
        $this->assertStringContainsString('1 hora', $mensagem['texto']);
        $this->assertStringNotContainsString('?token=', $mensagem['texto'], 'o token vai no fragmento, não na query');
    }

    public function testEmailSemContaTemAMesmaRespostaENaoEnviaNada(): void
    {
        $resposta = $this->servico->pedir('ninguem@exemplo.com');

        $this->assertSame(RecuperacaoSenhaService::MENSAGEM_PEDIDO, $resposta);
        $this->assertSame([], $this->mailer->enviados);
        $this->assertSame(0, $this->db->table('redefinicoes_senha')->count());
        $this->assertSame([], $this->log);
    }

    public function testEmailNaoConfirmadoTemAMesmaRespostaENaoRecebeNada(): void
    {
        $this->db->table('contas')->where('email', self::EMAIL)->update(['email_confirmado_em' => null]);

        $resposta = $this->servico->pedir(self::EMAIL);

        $this->assertSame(RecuperacaoSenhaService::MENSAGEM_PEDIDO, $resposta);
        $this->assertSame([], $this->mailer->enviados);
        $this->assertSame(0, $this->db->table('redefinicoes_senha')->count());
        $this->assertSame([], $this->log);
    }

    public function testEmailComMaiusculasEEspacosEEncontrado(): void
    {
        $this->servico->pedir('  DONA@Exemplo.COM ');

        $this->assertCount(1, $this->mailer->enviados);
        $this->assertSame(self::EMAIL, $this->mailer->enviados[0]['para']);
    }

    /** @return array<string, array{mixed}> */
    public static function emailsInvalidos(): array
    {
        return ['sem arroba' => ['dona'], 'vazio' => [''], 'nulo' => [null], 'número' => [123], 'lista' => [['a@b.com']]];
    }

    #[DataProvider('emailsInvalidos')]
    public function testEmailInvalidoEh422SemEnviarNada(mixed $email): void
    {
        try {
            $this->servico->pedir($email);
            $this->fail('devia recusar');
        } catch (DadosInvalidosException $e) {
            $this->assertSame('E-mail inválido.', $e->getMessage());
        }
        $this->assertSame([], $this->mailer->enviados);
    }

    public function testOBancoGuardaSoOHashDoToken(): void
    {
        $token = $this->pedirEPegarToken();

        $linhas = $this->db->table('redefinicoes_senha')->get();
        $this->assertCount(1, $linhas);
        $this->assertSame(hash('sha256', $token), $linhas[0]->token_hash);
        $this->assertStringNotContainsString($token, json_encode($linhas->all(), JSON_THROW_ON_ERROR));
    }

    public function testPedidoNovoInvalidaOTokenAnterior(): void
    {
        $primeiro = $this->pedirEPegarToken();
        $segundo = $this->pedirEPegarToken();

        $this->assertNotSame($primeiro, $segundo);
        $this->assertSame(1, $this->db->table('redefinicoes_senha')->count());
        try {
            $this->servico->redefinir($primeiro, 'senha-nova-22');
            $this->fail('o primeiro token devia estar invalidado');
        } catch (DadosInvalidosException) {
        }
        $this->servico->redefinir($segundo, 'senha-nova-22');
        $this->assertTrue($this->loginComSucesso('senha-nova-22'));
    }

    public function testPedidoApagaTokensVencidosDeOutrasContas(): void
    {
        $outra = (new ContaService($this->sessoes))->cadastrar([
            'email' => 'outra@exemplo.com', 'cnpj' => '12ABC34501DE35', 'nome' => 'Loja Nova', 'senha' => 'senha-segura-1',
        ]);
        $this->confirmarEmail('outra@exemplo.com');
        $this->servico->pedir('outra@exemplo.com');
        $this->db->table('redefinicoes_senha')->update(['expira_em' => gmdate('Y-m-d H:i:s', time() - 5)]);

        $this->servico->pedir(self::EMAIL);

        $this->assertSame(1, $this->db->table('redefinicoes_senha')->count());
        $this->assertSame('outra@exemplo.com', $outra['conta']['email']);
    }

    public function testSemAppUrlNaoEnviaNemGuardaTokenEAvisaNoLog(): void
    {
        $resposta = $this->servico($this->mailer, '')->pedir(self::EMAIL);

        $this->assertSame(RecuperacaoSenhaService::MENSAGEM_PEDIDO, $resposta);
        $this->assertSame([], $this->mailer->enviados);
        $this->assertSame(0, $this->db->table('redefinicoes_senha')->count());
        $this->assertCount(1, $this->log);
        $this->assertStringContainsString('APP_URL', $this->log[0]);
    }

    public function testFalhaDoEnvioNaoApareceNaRespostaEVaiParaOLogSemVazar(): void
    {
        $falha = new MailerEmMemoria(new MailerFalhouException('Falha ao enviar o e-mail pelo servidor SMTP (X).'));

        $resposta = $this->servico($falha)->pedir(self::EMAIL);

        $this->assertSame(RecuperacaoSenhaService::MENSAGEM_PEDIDO, $resposta);
        $this->assertCount(1, $this->log);
        $this->assertStringContainsString('Falha ao enviar o e-mail pelo servidor SMTP (X).', $this->log[0]);
    }

    public function testExcecaoDesconhecidaDoEnvioLogaSoOTipo(): void
    {
        $falha = new MailerEmMemoria(new RuntimeException('smtp://u:SENHA@host #token=' . str_repeat('a', 64)));

        $this->servico($falha)->pedir(self::EMAIL);

        $this->assertCount(1, $this->log);
        $this->assertStringContainsString(RuntimeException::class, $this->log[0]);
        $this->assertStringNotContainsString('SENHA', $this->log[0]);
        $this->assertStringNotContainsString('#token=', $this->log[0]);
    }

    // --- Concluir --------------------------------------------------------------------------

    public function testRedefinirTrocaASenhaEAAntigaDeixaDeValer(): void
    {
        $token = $this->pedirEPegarToken();

        $this->servico->redefinir($token, 'senha-nova-22');

        $this->assertFalse($this->loginComSucesso('senha-antiga-1'));
        $this->assertTrue($this->loginComSucesso('senha-nova-22'));
        $this->assertSame(0, $this->db->table('redefinicoes_senha')->count());
    }

    public function testRedefinirEncerraTodasAsSessoesENaoAbreNenhuma(): void
    {
        $a = $this->sessoes->entrar(self::EMAIL, 'senha-antiga-1')['token'];
        $b = $this->sessoes->entrar(self::EMAIL, 'senha-antiga-1')['token'];
        $token = $this->pedirEPegarToken();

        $this->servico->redefinir($token, 'senha-nova-22');

        $this->assertNull($this->sessoes->resolver($a));
        $this->assertNull($this->sessoes->resolver($b));
        $this->assertSame(0, $this->db->table('sessoes')->count());
    }

    public function testTokenEDeUsoUnico(): void
    {
        $token = $this->pedirEPegarToken();
        $this->servico->redefinir($token, 'senha-nova-22');

        try {
            $this->servico->redefinir($token, 'outra-senha-33');
            $this->fail('devia recusar o segundo uso');
        } catch (DadosInvalidosException $e) {
            $this->assertSame(RecuperacaoSenhaService::MENSAGEM_LINK_INVALIDO, $e->getMessage());
        }
        $this->assertTrue($this->loginComSucesso('senha-nova-22'));
        $this->assertFalse($this->loginComSucesso('outra-senha-33'));
    }

    public function testTokenVencidoEhRecusado(): void
    {
        $token = $this->pedirEPegarToken();
        $this->db->table('redefinicoes_senha')->update(['expira_em' => gmdate('Y-m-d H:i:s', time() - 1)]);

        $this->expectException(DadosInvalidosException::class);
        $this->expectExceptionMessage(RecuperacaoSenhaService::MENSAGEM_LINK_INVALIDO);
        $this->servico->redefinir($token, 'senha-nova-22');
    }

    public function testTokenValeUmaHoraDePedido(): void
    {
        $antes = time();
        $this->pedirEPegarToken();

        $expira = strtotime((string) $this->db->table('redefinicoes_senha')->value('expira_em') . ' UTC');
        $this->assertGreaterThanOrEqual($antes + 3600, $expira);
        $this->assertLessThanOrEqual(time() + 3600, $expira);
    }

    /** @return array<string, array{mixed}> */
    public static function tokensInvalidos(): array
    {
        return ['desconhecido' => [str_repeat('a', 64)], 'vazio' => [''], 'nulo' => [null], 'número' => [123], 'curto' => ['abc']];
    }

    #[DataProvider('tokensInvalidos')]
    public function testTokenInvalidoEh422ENadaMuda(mixed $token): void
    {
        $sessao = $this->sessoes->entrar(self::EMAIL, 'senha-antiga-1')['token'];

        try {
            $this->servico->redefinir($token, 'senha-nova-22');
            $this->fail('devia recusar');
        } catch (DadosInvalidosException $e) {
            $this->assertSame(RecuperacaoSenhaService::MENSAGEM_LINK_INVALIDO, $e->getMessage());
        }
        $this->assertTrue($this->loginComSucesso('senha-antiga-1'));
        $this->assertNotNull($this->sessoes->resolver($sessao));
    }

    #[DataProvider('senhasInvalidas')]
    public function testSenhaInvalidaNaoQueimaOToken(mixed $senha): void
    {
        $token = $this->pedirEPegarToken();

        try {
            $this->servico->redefinir($token, $senha);
            $this->fail('devia recusar');
        } catch (DadosInvalidosException $e) {
            $this->assertSame(Senha::MENSAGEM_INVALIDA, $e->getMessage());
        }
        $this->assertSame(1, $this->db->table('redefinicoes_senha')->count());
        $this->assertTrue($this->loginComSucesso('senha-antiga-1'));

        $this->servico->redefinir($token, 'senha-nova-22');
        $this->assertTrue($this->loginComSucesso('senha-nova-22'));
    }

    /** @return array<string, array{mixed}> */
    public static function senhasInvalidas(): array
    {
        return ['curta' => ['curta'], 'longa' => [str_repeat('a', 73)], 'nula' => [null], 'número' => [12345678]];
    }
}
