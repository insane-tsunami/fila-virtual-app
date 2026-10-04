<?php

declare(strict_types=1);

namespace Tests;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Services\ConfirmacaoEmailService;
use Services\MailerFalhouException;

/** Cenários do spec email-confirmation: confirmar, reenviar, trocar o e-mail e o estado nas respostas. */
final class EmailConfirmationApiTest extends ApiTestCase
{
    private const EMAIL = 'a@exemplo.com';
    private const SENHA = 'senha-segura-1';

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
     * @param array<string, string> $cabecalhos
     * @param array<string, mixed> $config
     */
    private function api(string $metodo, string $uri, array|string|null $corpo = null, array $cabecalhos = [], array $config = []): ResponseInterface
    {
        return $this->chamar($metodo, $uri, $corpo, $cabecalhos, $config + [
            'mailer' => $this->mailer,
            'app_url' => 'https://app.exemplo.com',
            'registrar' => function (string $mensagem): void {
                $this->log[] = $mensagem;
            },
        ]);
    }

    /**
     * Cadastra pela API (com o mailer em memória) e devolve a resposta decodificada.
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function cadastrar(string $email = self::EMAIL, string $cnpj = '93339970000105', array $config = []): array
    {
        $resposta = $this->api('POST', '/api/contas', [
            'email' => $email, 'cnpj' => $cnpj, 'nome' => 'Moda Azul', 'senha' => self::SENHA,
        ], [], $config);
        $this->assertSame(201, $resposta->getStatusCode(), (string) $resposta->getBody());

        return $this->json($resposta);
    }

    private function confirmar(?string $token): ResponseInterface
    {
        return $this->api('POST', '/api/email/confirmar', ['token' => $token]);
    }

    private function reenviar(string $sessao): ResponseInterface
    {
        return $this->api('POST', '/api/conta/email/reenviar', null, $this->comSessao($sessao));
    }

    private function trocar(string $sessao, string $email, string $senha = self::SENHA): ResponseInterface
    {
        return $this->api('PUT', '/api/conta/email', ['email' => $email, 'senha' => $senha], $this->comSessao($sessao));
    }

    private function conta(string $sessao): array
    {
        return $this->json($this->api('GET', '/api/conta', null, $this->comSessao($sessao)))['conta'];
    }

    // --- Cadastro e estado -----------------------------------------------------------------

    public function testCadastroEnviaAConfirmacaoEDevolveOEmailNaoConfirmado(): void
    {
        $corpo = $this->cadastrar();

        $this->assertFalse($corpo['conta']['email_confirmado']);
        $this->assertCount(1, $this->mailer->enviados);
        $this->assertSame(self::EMAIL, $this->mailer->enviados[0]['para']);
        $this->assertSame(ConfirmacaoEmailService::ASSUNTO, $this->mailer->enviados[0]['assunto']);
        $this->assertNotNull($this->mailer->ultimoToken());
        $this->assertStringContainsString('24 horas', $this->mailer->enviados[0]['texto']);
        $this->assertStringContainsString(
            'https://app.exemplo.com/confirmar-email#token=',
            $this->mailer->enviados[0]['texto']
        );
    }

    public function testFalhaDoEnvioNoCadastroNaoDesfazOCadastro(): void
    {
        $this->mailer = new MailerEmMemoria(new MailerFalhouException('Falha ao enviar o e-mail pelo servidor SMTP (X).'));

        $corpo = $this->cadastrar();

        $this->assertSame(self::EMAIL, $corpo['conta']['email']);
        $this->assertSame(1, $this->db->table('contas')->count());
        $this->assertStringContainsString('Falha ao enviar', implode("\n", $this->log));
        $this->assertSame([], $this->errosLogados);
    }

    public function testExcecaoInesperadaDoEnvioNoCadastroTambemNaoDesfaz(): void
    {
        $this->mailer = new MailerEmMemoria(new RuntimeException('qualquer coisa'));

        $this->cadastrar();

        $this->assertSame(1, $this->db->table('contas')->count());
    }

    public function testSemAppUrlOCadastroFuncionaSemEnviar(): void
    {
        $this->cadastrar(self::EMAIL, '93339970000105', ['app_url' => '']);

        $this->assertSame([], $this->mailer->enviados);
        $this->assertStringContainsString('APP_URL', implode("\n", $this->log));
    }

    public function testLoginDeContaNaoConfirmadaFuncionaETrazOEstado(): void
    {
        $this->cadastrar();

        $resposta = $this->api('POST', '/api/sessoes', ['email' => self::EMAIL, 'senha' => self::SENHA]);

        $this->assertSame(200, $resposta->getStatusCode());
        $this->assertFalse($this->json($resposta)['conta']['email_confirmado']);
    }

    public function testContaNaoConfirmadaUsaODashboardNormalmente(): void
    {
        $sessao = $this->cadastrar()['token'];

        $fila = $this->api('GET', '/api/filas/moda-azul/entradas', null, $this->comSessao($sessao));

        $this->assertSame(200, $fila->getStatusCode());
    }

    public function testGetContaTrazOEstadoDeConfirmacao(): void
    {
        $sessao = $this->cadastrar()['token'];
        $this->assertFalse($this->conta($sessao)['email_confirmado']);

        $this->confirmar($this->mailer->ultimoToken());

        $this->assertTrue($this->conta($sessao)['email_confirmado']);
    }

    public function testContaAnteriorAMigracaoFicaNaoConfirmada(): void
    {
        $sessao = $this->cadastrar()['token'];
        $this->db->table('contas')->update(['email_confirmado_em' => null]);

        $this->assertFalse($this->conta($sessao)['email_confirmado']);
    }

    // --- Confirmar -------------------------------------------------------------------------

    public function testConfirmarSemSessaoResponde200EConfirma(): void
    {
        $sessao = $this->cadastrar()['token'];

        $resposta = $this->confirmar($this->mailer->ultimoToken());

        $this->assertSame(200, $resposta->getStatusCode());
        $this->assertSame(['mensagem' => 'E-mail confirmado.'], $this->json($resposta));
        $this->assertTrue($this->conta($sessao)['email_confirmado']);
    }

    public function testTokenDeConfirmacaoEDeUsoUnico(): void
    {
        $this->cadastrar();
        $token = $this->mailer->ultimoToken();
        $this->confirmar($token);

        $resposta = $this->confirmar($token);

        $this->assertSame(422, $resposta->getStatusCode());
        $this->assertSame(ConfirmacaoEmailService::MENSAGEM_LINK_INVALIDO, $this->json($resposta)['erro']);
    }

    public function testTokenInvalidoOuAusenteEh422ENadaMuda(): void
    {
        $sessao = $this->cadastrar()['token'];

        foreach ([str_repeat('f', 64), '', null] as $token) {
            $resposta = $this->confirmar($token);
            $this->assertSame(422, $resposta->getStatusCode());
            $this->assertSame(ConfirmacaoEmailService::MENSAGEM_LINK_INVALIDO, $this->json($resposta)['erro']);
        }
        $this->assertSame(422, $this->api('POST', '/api/email/confirmar', [])->getStatusCode());
        $this->assertFalse($this->conta($sessao)['email_confirmado']);
    }

    public function testTokenVencidoEh422(): void
    {
        $this->cadastrar();
        $this->db->table('confirmacoes_email')->update(['expira_em' => gmdate('Y-m-d H:i:s', time() - 1)]);

        $this->assertSame(422, $this->confirmar($this->mailer->ultimoToken())->getStatusCode());
    }

    public function testCorpoQueNaoEJsonEh400(): void
    {
        $this->assertSame(400, $this->api('POST', '/api/email/confirmar', '{não é json')->getStatusCode());
    }

    public function testBancoNaoGuardaOTokenEmClaro(): void
    {
        $this->cadastrar();
        $token = $this->mailer->ultimoToken();

        $this->assertStringNotContainsString(
            $token,
            json_encode($this->db->table('confirmacoes_email')->get()->all(), JSON_THROW_ON_ERROR)
        );
    }

    // --- Reenviar --------------------------------------------------------------------------

    public function testReenviarEnviaUmNovoLinkEInvalidaOAnterior(): void
    {
        $sessao = $this->cadastrar()['token'];
        $primeiro = $this->mailer->ultimoToken();

        $resposta = $this->reenviar($sessao);

        $this->assertSame(202, $resposta->getStatusCode());
        $this->assertSame(['mensagem' => 'Enviamos um novo link de confirmação.'], $this->json($resposta));
        $this->assertCount(2, $this->mailer->enviados);
        $this->assertNotSame($primeiro, $this->mailer->ultimoToken());
        $this->assertSame(422, $this->confirmar($primeiro)->getStatusCode());
        $this->assertSame(200, $this->confirmar($this->mailer->ultimoToken())->getStatusCode());
    }

    public function testReenviarComEmailConfirmadoEh409SemEnviar(): void
    {
        $sessao = $this->cadastrar()['token'];
        $this->confirmar($this->mailer->ultimoToken());
        $enviados = count($this->mailer->enviados);

        $resposta = $this->reenviar($sessao);

        $this->assertSame(409, $resposta->getStatusCode());
        $this->assertCount($enviados, $this->mailer->enviados);
    }

    public function testReenviarSemSessaoEh401(): void
    {
        $this->assertSame(401, $this->api('POST', '/api/conta/email/reenviar')->getStatusCode());
        $this->assertSame(401, $this->api('POST', '/api/conta/email/reenviar', null, ['Authorization' => 'Bearer x'])->getStatusCode());
    }

    // --- Trocar o e-mail -------------------------------------------------------------------

    public function testTrocarOEmailDaContaNaoConfirmada(): void
    {
        $sessao = $this->cadastrar()['token'];
        $antigo = $this->mailer->ultimoToken();

        $resposta = $this->trocar($sessao, '  Novo@Exemplo.com ');
        $corpo = $this->json($resposta);

        $this->assertSame(200, $resposta->getStatusCode());
        $this->assertSame(
            ['email' => 'novo@exemplo.com', 'cnpj' => '93339970000105', 'email_confirmado' => false],
            $corpo['conta']
        );
        $this->assertSame('novo@exemplo.com', end($this->mailer->enviados)['para']);
        $this->assertSame(422, $this->confirmar($antigo)->getStatusCode(), 'o token do endereço antigo deixa de valer');
        $this->assertSame(200, $this->confirmar($this->mailer->ultimoToken())->getStatusCode());
        $this->assertSame(
            200,
            $this->api('POST', '/api/sessoes', ['email' => 'novo@exemplo.com', 'senha' => self::SENHA])->getStatusCode()
        );
        $this->assertSame(
            401,
            $this->api('POST', '/api/sessoes', ['email' => self::EMAIL, 'senha' => self::SENHA])->getStatusCode()
        );
    }

    public function testTrocarComSenhaErradaEh422(): void
    {
        $sessao = $this->cadastrar()['token'];

        $resposta = $this->trocar($sessao, 'novo@exemplo.com', 'errada-errada');

        $this->assertSame(422, $resposta->getStatusCode());
        $this->assertSame(self::EMAIL, $this->conta($sessao)['email']);
    }

    public function testTrocarParaOEmailDeOutraContaEh409(): void
    {
        $this->cadastrar('outra@exemplo.com', '12ABC34501DE35');
        $sessao = $this->cadastrar()['token'];

        $resposta = $this->trocar($sessao, 'outra@exemplo.com');

        $this->assertSame(409, $resposta->getStatusCode());
        $this->assertSame(self::EMAIL, $this->conta($sessao)['email']);
    }

    public function testTrocarComEmailJaConfirmadoEh409(): void
    {
        $sessao = $this->cadastrar()['token'];
        $this->confirmar($this->mailer->ultimoToken());

        $resposta = $this->trocar($sessao, 'novo@exemplo.com');

        $this->assertSame(409, $resposta->getStatusCode());
        $this->assertSame(self::EMAIL, $this->conta($sessao)['email']);
    }

    public function testTrocarComDadosInvalidosEh422(): void
    {
        $sessao = $this->cadastrar()['token'];
        $cabecalhos = $this->comSessao($sessao);

        foreach ([[], ['email' => 'sem-arroba', 'senha' => self::SENHA], ['email' => 'novo@exemplo.com'], ['email' => self::EMAIL, 'senha' => self::SENHA]] as $corpo) {
            $this->assertSame(422, $this->api('PUT', '/api/conta/email', $corpo, $cabecalhos)->getStatusCode());
        }
        $this->assertSame(self::EMAIL, $this->conta($sessao)['email']);
    }

    public function testTrocarSemSessaoEh401(): void
    {
        $this->assertSame(401, $this->api('PUT', '/api/conta/email', ['email' => 'x@exemplo.com', 'senha' => 'y'])->getStatusCode());
    }

    public function testASessaoContinuaValidaDepoisDeTrocarOEmail(): void
    {
        $sessao = $this->cadastrar()['token'];

        $this->trocar($sessao, 'novo@exemplo.com');

        $this->assertSame('novo@exemplo.com', $this->conta($sessao)['email']);
    }
}
