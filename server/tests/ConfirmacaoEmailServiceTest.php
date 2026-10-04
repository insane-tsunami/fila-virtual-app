<?php

declare(strict_types=1);

namespace Tests;

use Models\Conta;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Services\ConfirmacaoEmailService;
use Services\ConflitoException;
use Services\ContaService;
use Services\DadosInvalidosException;
use Services\Mailer;
use Services\MailerFalhouException;
use Services\NaoAutenticadoException;
use Services\NaoEncontradoException;
use Services\SessaoService;

final class ConfirmacaoEmailServiceTest extends DatabaseTestCase
{
    private const EMAIL = 'dona@exemplo.com';
    private const APP_URL = 'https://app.exemplo.com';

    private SessaoService $sessoes;
    private MailerEmMemoria $mailer;
    private ConfirmacaoEmailService $servico;
    private Conta $conta;

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
        $this->conta = Conta::query()->where('email', self::EMAIL)->firstOrFail();
        $this->mailer = new MailerEmMemoria();
        $this->servico = $this->servico($this->mailer);
    }

    private function servico(Mailer $mailer, string $appUrl = self::APP_URL): ConfirmacaoEmailService
    {
        return new ConfirmacaoEmailService($mailer, $appUrl, null, function (string $mensagem): void {
            $this->log[] = $mensagem;
        });
    }

    private function enviarEPegarToken(): string
    {
        $this->servico->enviar($this->conta->fresh());
        $token = $this->mailer->ultimoToken();
        $this->assertNotNull($token);

        return $token;
    }

    private function fresca(): Conta
    {
        return Conta::query()->findOrFail($this->conta->id);
    }

    // --- Enviar ----------------------------------------------------------------------------

    public function testEnviaOLinkComOTokenNoFragmentoEOPrazo(): void
    {
        $this->servico->enviar($this->conta);

        $this->assertCount(1, $this->mailer->enviados);
        $mensagem = $this->mailer->enviados[0];
        $this->assertSame(self::EMAIL, $mensagem['para']);
        $this->assertSame(ConfirmacaoEmailService::ASSUNTO, $mensagem['assunto']);
        $this->assertMatchesRegularExpression(
            '~https://app\.exemplo\.com/confirmar-email#token=[0-9a-f]{64}\b~',
            $mensagem['texto']
        );
        $this->assertStringContainsString('24 horas', $mensagem['texto']);
        $this->assertStringNotContainsString('?token=', $mensagem['texto']);
    }

    public function testOBancoGuardaSoOHashEOEmailDoEnvioComValidadeDe24Horas(): void
    {
        $antes = time();
        $token = $this->enviarEPegarToken();

        $linha = $this->db->table('confirmacoes_email')->first();
        $this->assertSame(hash('sha256', $token), $linha->token_hash);
        $this->assertSame(self::EMAIL, $linha->email);
        $this->assertStringNotContainsString($token, json_encode($linha, JSON_THROW_ON_ERROR));
        $expira = strtotime($linha->expira_em . ' UTC');
        $this->assertGreaterThanOrEqual($antes + 86400, $expira);
        $this->assertLessThanOrEqual(time() + 86400, $expira);
    }

    public function testReenvioInvalidaOTokenAnterior(): void
    {
        $primeiro = $this->enviarEPegarToken();
        $segundo = $this->enviarEPegarToken();

        $this->assertNotSame($primeiro, $segundo);
        $this->assertSame(1, $this->db->table('confirmacoes_email')->count());
        try {
            $this->servico->confirmar($primeiro);
            $this->fail('o primeiro token devia estar invalidado');
        } catch (DadosInvalidosException) {
        }
        $this->servico->confirmar($segundo);
        $this->assertNotNull($this->fresca()->email_confirmado_em);
    }

    public function testEnviarApagaTokensVencidosDeOutrasContas(): void
    {
        $outra = Conta::create([
            'email' => 'outra@exemplo.com', 'cnpj' => '12ABC34501DE35', 'senha_hash' => 'x', 'criado_em' => gmdate('Y-m-d H:i:s'),
        ]);
        $this->servico->enviar($outra);
        $this->db->table('confirmacoes_email')->update(['expira_em' => gmdate('Y-m-d H:i:s', time() - 5)]);

        $this->servico->enviar($this->conta);

        $this->assertSame(1, $this->db->table('confirmacoes_email')->count());
    }

    public function testSemAppUrlNaoEnviaNemGuardaTokenEAvisaNoLog(): void
    {
        $this->servico($this->mailer, '')->enviar($this->conta);

        $this->assertSame([], $this->mailer->enviados);
        $this->assertSame(0, $this->db->table('confirmacoes_email')->count());
        $this->assertCount(1, $this->log);
        $this->assertStringContainsString('APP_URL', $this->log[0]);
    }

    public function testFalhaDoEnvioNaoLancaEVaiParaOLogSemVazar(): void
    {
        $falha = new MailerEmMemoria(new MailerFalhouException('Falha ao enviar o e-mail pelo servidor SMTP (X).'));

        $this->servico($falha)->enviar($this->conta);

        $this->assertCount(1, $this->log);
        $this->assertStringContainsString('Falha ao enviar o e-mail pelo servidor SMTP (X).', $this->log[0]);
    }

    public function testExcecaoDesconhecidaLogaSoOTipo(): void
    {
        $falha = new MailerEmMemoria(new RuntimeException('smtp://u:SENHA@host #token=' . str_repeat('a', 64)));

        $this->servico($falha)->enviar($this->conta);

        $this->assertStringContainsString(RuntimeException::class, $this->log[0]);
        $this->assertStringNotContainsString('SENHA', $this->log[0]);
        $this->assertStringNotContainsString('#token=', $this->log[0]);
    }

    // --- Confirmar -------------------------------------------------------------------------

    public function testConfirmarMarcaAContaEApagaOToken(): void
    {
        $token = $this->enviarEPegarToken();
        $this->assertFalse($this->fresca()->paraResposta()['email_confirmado']);

        $this->servico->confirmar($token);

        $this->assertTrue($this->fresca()->paraResposta()['email_confirmado']);
        $this->assertSame(0, $this->db->table('confirmacoes_email')->count());
    }

    public function testTokenEDeUsoUnico(): void
    {
        $token = $this->enviarEPegarToken();
        $this->servico->confirmar($token);
        $quando = $this->fresca()->email_confirmado_em;

        try {
            $this->servico->confirmar($token);
            $this->fail('devia recusar o segundo uso');
        } catch (DadosInvalidosException $e) {
            $this->assertSame(ConfirmacaoEmailService::MENSAGEM_LINK_INVALIDO, $e->getMessage());
        }
        $this->assertSame($quando, $this->fresca()->email_confirmado_em);
    }

    public function testTokenVencidoEhRecusado(): void
    {
        $token = $this->enviarEPegarToken();
        $this->db->table('confirmacoes_email')->update(['expira_em' => gmdate('Y-m-d H:i:s', time() - 1)]);

        $this->expectException(DadosInvalidosException::class);
        $this->expectExceptionMessage(ConfirmacaoEmailService::MENSAGEM_LINK_INVALIDO);
        $this->servico->confirmar($token);
    }

    /** @return array<string, array{mixed}> */
    public static function tokensInvalidos(): array
    {
        return ['desconhecido' => [str_repeat('f', 64)], 'vazio' => [''], 'nulo' => [null], 'número' => [123], 'curto' => ['abc']];
    }

    #[DataProvider('tokensInvalidos')]
    public function testTokenInvalidoEh422ENadaMuda(mixed $token): void
    {
        $this->enviarEPegarToken();

        try {
            $this->servico->confirmar($token);
            $this->fail('devia recusar');
        } catch (DadosInvalidosException $e) {
            $this->assertSame(ConfirmacaoEmailService::MENSAGEM_LINK_INVALIDO, $e->getMessage());
        }
        $this->assertNull($this->fresca()->email_confirmado_em);
        $this->assertSame(1, $this->db->table('confirmacoes_email')->count());
    }

    public function testTokenPresoAoEmailDoEnvio(): void
    {
        $token = $this->enviarEPegarToken();
        // o e-mail da conta mudou depois do envio (sem passar pela troca, que apagaria o token)
        $this->db->table('contas')->where('id', $this->conta->id)->update(['email' => 'novo@exemplo.com']);

        $this->expectException(DadosInvalidosException::class);
        try {
            $this->servico->confirmar($token);
        } finally {
            $this->assertNull($this->fresca()->email_confirmado_em);
        }
    }

    // --- Reenviar --------------------------------------------------------------------------

    public function testReenviarMandaOLinkEDevolveAMensagem(): void
    {
        $mensagem = $this->servico->reenviar($this->conta->id);

        $this->assertSame(ConfirmacaoEmailService::MENSAGEM_REENVIADO, $mensagem);
        $this->assertCount(1, $this->mailer->enviados);
        $this->assertNotNull($this->mailer->ultimoToken());
    }

    public function testReenviarComEmailConfirmadoEhConflitoESemEnvio(): void
    {
        $this->servico->confirmar($this->enviarEPegarToken());
        $enviados = count($this->mailer->enviados);

        try {
            $this->servico->reenviar($this->conta->id);
            $this->fail('devia recusar');
        } catch (ConflitoException) {
        }
        $this->assertCount($enviados, $this->mailer->enviados);
    }

    public function testReenviarContaInexistente(): void
    {
        $this->expectException(NaoEncontradoException::class);
        $this->servico->reenviar(999999);
    }

    // --- Trocar o e-mail -------------------------------------------------------------------

    public function testTrocarEmailAtualizaEnviaAoNovoEnderecoEInvalidaOAntigo(): void
    {
        $antigo = $this->enviarEPegarToken();

        $conta = $this->servico->trocarEmail($this->conta->id, ['email' => '  NOVO@Exemplo.com ', 'senha' => 'senha-antiga-1']);

        $this->assertSame(['email' => 'novo@exemplo.com', 'cnpj' => '93339970000105', 'email_confirmado' => false], $conta);
        $ultima = end($this->mailer->enviados);
        $this->assertSame('novo@exemplo.com', $ultima['para']);
        $novo = $this->mailer->ultimoToken();
        $this->assertNotSame($antigo, $novo);
        $this->assertSame(1, $this->db->table('confirmacoes_email')->count());
        try {
            $this->servico->confirmar($antigo);
            $this->fail('o token do endereço antigo devia ser recusado');
        } catch (DadosInvalidosException) {
        }
        $this->servico->confirmar($novo);
        $this->assertTrue($this->fresca()->paraResposta()['email_confirmado']);
        $this->assertSame('novo@exemplo.com', $this->fresca()->email);
    }

    public function testDepoisDaTrocaOLoginValeComOEmailNovoEnaoComOAntigo(): void
    {
        $this->servico->trocarEmail($this->conta->id, ['email' => 'novo@exemplo.com', 'senha' => 'senha-antiga-1']);

        $this->assertSame('novo@exemplo.com', $this->sessoes->entrar('novo@exemplo.com', 'senha-antiga-1')['conta']['email']);
        $this->expectException(NaoAutenticadoException::class);
        $this->sessoes->entrar(self::EMAIL, 'senha-antiga-1');
    }

    public function testTrocarEmailComSenhaErradaEh422ENadaMuda(): void
    {
        try {
            $this->servico->trocarEmail($this->conta->id, ['email' => 'novo@exemplo.com', 'senha' => 'errada-errada']);
            $this->fail('devia recusar');
        } catch (DadosInvalidosException $e) {
            $this->assertSame('Senha atual incorreta.', $e->getMessage());
        }
        $this->assertSame(self::EMAIL, $this->fresca()->email);
        $this->assertSame([], $this->mailer->enviados);
    }

    public function testTrocarParaOEmailDeOutraContaEhConflito(): void
    {
        Conta::create([
            'email' => 'outra@exemplo.com', 'cnpj' => '12ABC34501DE35', 'senha_hash' => 'x', 'criado_em' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->expectException(ConflitoException::class);
        try {
            $this->servico->trocarEmail($this->conta->id, ['email' => 'OUTRA@exemplo.com', 'senha' => 'senha-antiga-1']);
        } finally {
            $this->assertSame(self::EMAIL, $this->fresca()->email);
        }
    }

    public function testTrocarParaOMesmoEmailEh422(): void
    {
        $this->expectException(DadosInvalidosException::class);
        $this->expectExceptionMessage('Informe um e-mail diferente do atual.');
        $this->servico->trocarEmail($this->conta->id, ['email' => ' DONA@exemplo.com ', 'senha' => 'senha-antiga-1']);
    }

    /** @return array<string, array{array<mixed>}> */
    public static function corposInvalidos(): array
    {
        return [
            'sem e-mail' => [['senha' => 'senha-antiga-1']],
            'e-mail inválido' => [['email' => 'sem-arroba', 'senha' => 'senha-antiga-1']],
            'sem senha' => [['email' => 'novo@exemplo.com']],
            'senha vazia' => [['email' => 'novo@exemplo.com', 'senha' => '']],
            'senha não é texto' => [['email' => 'novo@exemplo.com', 'senha' => 123]],
        ];
    }

    /** @param array<mixed> $corpo */
    #[DataProvider('corposInvalidos')]
    public function testTrocarComDadosInvalidosEh422(array $corpo): void
    {
        $this->expectException(DadosInvalidosException::class);
        try {
            $this->servico->trocarEmail($this->conta->id, $corpo);
        } finally {
            $this->assertSame(self::EMAIL, $this->fresca()->email);
        }
    }

    public function testTrocarComEmailJaConfirmadoEhConflitoENadaMuda(): void
    {
        $this->servico->confirmar($this->enviarEPegarToken());

        try {
            $this->servico->trocarEmail($this->conta->id, ['email' => 'novo@exemplo.com', 'senha' => 'senha-antiga-1']);
            $this->fail('devia recusar');
        } catch (ConflitoException $e) {
            $this->assertSame(ConfirmacaoEmailService::MENSAGEM_JA_CONFIRMADO, $e->getMessage());
        }
        $this->assertSame(self::EMAIL, $this->fresca()->email);
    }

    public function testTrocarSemAppUrlTrocaOEmailMasNaoEnviaNada(): void
    {
        $this->servico($this->mailer, '')->trocarEmail($this->conta->id, ['email' => 'novo@exemplo.com', 'senha' => 'senha-antiga-1']);

        $this->assertSame('novo@exemplo.com', $this->fresca()->email);
        $this->assertSame([], $this->mailer->enviados);
        $this->assertSame(0, $this->db->table('confirmacoes_email')->count());
    }
}
