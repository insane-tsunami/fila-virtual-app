<?php

declare(strict_types=1);

namespace Tests;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Services\MailerFalhouException;
use Services\RecuperacaoSenhaService;

/** Cenários do spec password-recovery: pedir o link, concluir a redefinição e não revelar contas. */
final class PasswordRecoveryApiTest extends ApiTestCase
{
    private const EMAIL = 'a@exemplo.com';

    private MailerEmMemoria $mailer;

    /** @var list<string> */
    private array $log = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->mailer = new MailerEmMemoria();
        $this->log = [];
    }

    /**
     * @param array<mixed>|string|null $corpo
     * @param array<string, mixed> $config
     */
    private function api(string $metodo, string $uri, array|string|null $corpo = null, array $config = []): ResponseInterface
    {
        return $this->chamar($metodo, $uri, $corpo, [], $config + [
            'mailer' => $this->mailer,
            'app_url' => 'https://app.exemplo.com',
            'registrar' => function (string $mensagem): void {
                $this->log[] = $mensagem;
            },
        ]);
    }

    private function pedir(string $email = self::EMAIL, array $config = []): ResponseInterface
    {
        return $this->api('POST', '/api/senha/esqueci', ['email' => $email], $config);
    }

    private function redefinir(?string $token, string $senha = 'senha-nova-22'): ResponseInterface
    {
        return $this->api('POST', '/api/senha/redefinir', ['token' => $token, 'nova_senha' => $senha]);
    }

    private function login(string $senha): int
    {
        return $this->chamar('POST', '/api/sessoes', ['email' => self::EMAIL, 'senha' => $senha])->getStatusCode();
    }

    /** Cadastra a conta de teste e devolve o token da sessão aberta no cadastro. */
    private function conta(): string
    {
        $token = $this->cadastrarConta('Moda Azul', self::EMAIL, '93339970000105', 'senha-antiga-1')['token'];
        $this->confirmarEmail();

        return $token;
    }

    /** O link de redefinição só vai para e-mail confirmado. */
    private function confirmarEmail(): void
    {
        $this->db->table('contas')->where('email', self::EMAIL)->update(['email_confirmado_em' => gmdate('Y-m-d H:i:s')]);
    }

    // --- Pedido ----------------------------------------------------------------------------

    public function testPedidoComContaResponde202EEnviaOLink(): void
    {
        $this->conta();

        $resposta = $this->pedir();

        $this->assertSame(202, $resposta->getStatusCode());
        $this->assertStringStartsWith('application/json', $resposta->getHeaderLine('Content-Type'));
        $this->assertSame(['mensagem' => RecuperacaoSenhaService::MENSAGEM_PEDIDO], $this->json($resposta));
        $this->assertCount(1, $this->mailer->enviados);
        $this->assertSame(self::EMAIL, $this->mailer->enviados[0]['para']);
        $this->assertNotNull($this->mailer->ultimoToken());
        $this->assertStringContainsString(
            'https://app.exemplo.com/redefinir-senha#token=',
            $this->mailer->enviados[0]['texto']
        );
        $this->assertStringContainsString('1 hora', $this->mailer->enviados[0]['texto']);
    }

    public function testPedidoSemContaTemExatamenteAMesmaResposta(): void
    {
        $this->conta();
        $com = $this->pedir();
        $sem = $this->pedir('ninguem@exemplo.com');

        $this->assertSame($com->getStatusCode(), $sem->getStatusCode());
        $this->assertSame((string) $com->getBody(), (string) $sem->getBody());
        $this->assertSame($com->getHeaders(), $sem->getHeaders());
        $this->assertCount(1, $this->mailer->enviados, 'só a conta existente recebe e-mail');
    }

    public function testEmailNaoConfirmadoTemExatamenteAMesmaRespostaENaoRecebeNada(): void
    {
        $this->conta();
        $this->db->table('contas')->where('email', self::EMAIL)->update(['email_confirmado_em' => null]);
        $semConta = $this->pedir('ninguem@exemplo.com');

        $naoConfirmado = $this->pedir();

        $this->assertSame(202, $naoConfirmado->getStatusCode());
        $this->assertSame((string) $semConta->getBody(), (string) $naoConfirmado->getBody());
        $this->assertSame($semConta->getHeaders(), $naoConfirmado->getHeaders());
        $this->assertSame([], $this->mailer->enviados);
        $this->assertSame(0, $this->db->table('redefinicoes_senha')->count());
    }

    public function testEmailComMaiusculasEEspacos(): void
    {
        $this->conta();

        $this->pedir('  A@Exemplo.COM ');

        $this->assertCount(1, $this->mailer->enviados);
    }

    public function testEmailAusenteOuInvalidoEh422(): void
    {
        $this->conta();

        foreach ([[], ['email' => 'sem-arroba'], ['email' => ''], ['email' => 42]] as $corpo) {
            $resposta = $this->api('POST', '/api/senha/esqueci', $corpo);
            $this->assertSame(422, $resposta->getStatusCode());
            $this->assertSame('E-mail inválido.', $this->json($resposta)['erro']);
        }
        $this->assertSame([], $this->mailer->enviados);
    }

    public function testCorpoQueNaoEJsonEh400(): void
    {
        $this->assertSame(400, $this->api('POST', '/api/senha/esqueci', '{não é json')->getStatusCode());
        $this->assertSame(400, $this->api('POST', '/api/senha/redefinir', '{não é json')->getStatusCode());
    }

    public function testAsDuasRotasSaoPublicas(): void
    {
        $this->assertNotSame(401, $this->pedir('x@exemplo.com')->getStatusCode());
        $this->assertNotSame(401, $this->redefinir('abc')->getStatusCode());
    }

    public function testFalhaDoEnvioNaoApareceNaRespostaEVaiParaOLog(): void
    {
        $this->conta();
        $this->mailer = new MailerEmMemoria(new MailerFalhouException('Falha ao enviar o e-mail pelo servidor SMTP (X).'));

        $resposta = $this->pedir();

        $this->assertSame(202, $resposta->getStatusCode());
        $this->assertSame(['mensagem' => RecuperacaoSenhaService::MENSAGEM_PEDIDO], $this->json($resposta));
        $this->assertCount(1, $this->log);
        $this->assertStringContainsString('Falha ao enviar', $this->log[0]);
        $this->assertSame([], $this->errosLogados, 'não é um 500');
    }

    public function testExcecaoInesperadaDoEnvioTambemNaoViraErro(): void
    {
        $this->conta();
        $this->mailer = new MailerEmMemoria(new RuntimeException('qualquer coisa'));

        $this->assertSame(202, $this->pedir()->getStatusCode());
    }

    public function testSemAppUrlNaoEnviaEAvisaNoLogMantendoA202(): void
    {
        $this->conta();

        $resposta = $this->pedir(self::EMAIL, ['app_url' => '']);

        $this->assertSame(202, $resposta->getStatusCode());
        $this->assertSame([], $this->mailer->enviados);
        $this->assertStringContainsString('APP_URL', implode("\n", $this->log));
    }

    public function testDriverDeLogEntregaOLinkNoLogEOFluxoCompletoFunciona(): void
    {
        $this->conta();
        $config = ['mailer' => null, 'mail' => ['driver' => 'log']];

        $this->assertSame(202, $this->pedir(self::EMAIL, $config)->getStatusCode());

        preg_match('/#token=([0-9a-f]{64})/', implode("\n", $this->log), $m);
        $this->assertNotEmpty($m, 'o driver log grava o link');
        $this->assertSame(200, $this->redefinir($m[1])->getStatusCode());
    }

    public function testDriverDesligadoPadraoNaoGravaOLink(): void
    {
        $this->conta();

        $resposta = $this->pedir(self::EMAIL, ['mailer' => null]);

        $this->assertSame(202, $resposta->getStatusCode());
        $this->assertStringNotContainsString('#token=', implode("\n", $this->log));
        $this->assertStringContainsString('desligado', implode("\n", $this->log));
    }

    // --- Concluir --------------------------------------------------------------------------

    public function testFluxoCompletoTrocaASenhaEEncerraAsSessoesSemAbrirNenhuma(): void
    {
        $sessao = $this->conta();
        $this->assertSame(200, $this->chamar('GET', '/api/conta', null, $this->comSessao($sessao))->getStatusCode());
        $this->pedir();

        $resposta = $this->redefinir($this->mailer->ultimoToken());

        $this->assertSame(200, $resposta->getStatusCode());
        $this->assertSame(['mensagem' => 'Senha alterada.'], $this->json($resposta));
        $this->assertSame(401, $this->login('senha-antiga-1'));
        $this->assertSame(401, $this->chamar('GET', '/api/conta', null, $this->comSessao($sessao))->getStatusCode());
        $this->assertSame(0, $this->db->table('sessoes')->count(), 'a redefinição não abre sessão');
        $this->assertSame(200, $this->login('senha-nova-22'));
    }

    public function testTokenEDeUsoUnico(): void
    {
        $this->conta();
        $this->pedir();
        $token = $this->mailer->ultimoToken();
        $this->redefinir($token);

        $resposta = $this->redefinir($token, 'outra-senha-33');

        $this->assertSame(422, $resposta->getStatusCode());
        $this->assertSame('Link inválido ou expirado. Peça um novo.', $this->json($resposta)['erro']);
        $this->assertSame(401, $this->login('outra-senha-33'));
        $this->assertSame(200, $this->login('senha-nova-22'));
    }

    public function testTokenInvalidoOuAusenteEh422ENadaMuda(): void
    {
        $this->conta();
        $this->pedir();

        foreach ([str_repeat('f', 64), '', null] as $token) {
            $resposta = $this->redefinir($token);
            $this->assertSame(422, $resposta->getStatusCode());
            $this->assertSame('Link inválido ou expirado. Peça um novo.', $this->json($resposta)['erro']);
        }
        $semCampo = $this->api('POST', '/api/senha/redefinir', ['nova_senha' => 'senha-nova-22']);
        $this->assertSame(422, $semCampo->getStatusCode());
        $this->assertSame(200, $this->login('senha-antiga-1'));
    }

    public function testTokenVencidoEh422(): void
    {
        $this->conta();
        $this->pedir();
        $this->db->table('redefinicoes_senha')->update(['expira_em' => gmdate('Y-m-d H:i:s', time() - 1)]);

        $this->assertSame(422, $this->redefinir($this->mailer->ultimoToken())->getStatusCode());
        $this->assertSame(200, $this->login('senha-antiga-1'));
    }

    public function testPedidoNovoInvalidaOLinkAnterior(): void
    {
        $this->conta();
        $this->pedir();
        $primeiro = $this->mailer->ultimoToken();
        $this->pedir();
        $segundo = $this->mailer->ultimoToken();

        $this->assertSame(422, $this->redefinir($primeiro)->getStatusCode());
        $this->assertSame(200, $this->redefinir($segundo)->getStatusCode());
    }

    public function testNovaSenhaInvalidaEh422EOTokenContinuaValendo(): void
    {
        $this->conta();
        $this->pedir();
        $token = $this->mailer->ultimoToken();

        foreach (['curta', str_repeat('a', 73)] as $senha) {
            $resposta = $this->redefinir($token, $senha);
            $this->assertSame(422, $resposta->getStatusCode());
            $this->assertStringContainsString('Senha inválida', $this->json($resposta)['erro']);
        }

        $this->assertSame(200, $this->redefinir($token)->getStatusCode());
    }

    public function testOBancoNaoTemOTokenEmClaro(): void
    {
        $this->conta();
        $this->pedir();
        $token = $this->mailer->ultimoToken();

        $this->assertStringNotContainsString(
            $token,
            json_encode($this->db->table('redefinicoes_senha')->get()->all(), JSON_THROW_ON_ERROR)
        );
    }
}
