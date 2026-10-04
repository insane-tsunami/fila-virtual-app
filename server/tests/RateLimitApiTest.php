<?php

declare(strict_types=1);

namespace Tests;

use Psr\Http\Message\ResponseInterface;

/** Cenários do spec rate-limiting: login, cadastro, troca de senha, formato do 429 e IP do cliente. */
final class RateLimitApiTest extends ApiTestCase
{
    private const EMAIL = 'a@exemplo.com';
    private const SENHA = 'senha-segura-1';

    /** @var array<string, int> */
    private array $limites = [
        'login_email' => 5, 'login_ip' => 20, 'senha_conta' => 5, 'janela_minutos' => 15,
        'cadastro_ip' => 5, 'cadastro_janela_minutos' => 60,
        'esqueci_email' => 3, 'esqueci_ip' => 10, 'esqueci_janela_minutos' => 60, 'redefinir_ip' => 20,
        'confirmacao_conta' => 3, 'confirmacao_janela_minutos' => 60, 'confirmar_ip' => 20,
    ];

    /** @var list<string> */
    private array $proxies = [];

    private MailerEmMemoria $mailer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enderecoRemoto = '198.51.100.7';
        $this->proxies = [];
        $this->mailer = new MailerEmMemoria();
    }

    /**
     * @param array<mixed>|string|null $corpo
     * @param array<string, string> $cabecalhos
     */
    private function api(string $metodo, string $uri, array|string|null $corpo = null, array $cabecalhos = []): ResponseInterface
    {
        return $this->chamar($metodo, $uri, $corpo, $cabecalhos, [
            'rate_limit' => $this->limites,
            'trusted_proxies' => $this->proxies,
            'mailer' => $this->mailer,
            'app_url' => 'https://app.exemplo.com',
        ]);
    }

    private function login(string $email, string $senha): ResponseInterface
    {
        return $this->api('POST', '/api/sessoes', ['email' => $email, 'senha' => $senha]);
    }

    private function erroNoLogin(string $email = self::EMAIL): int
    {
        return $this->login($email, 'senha-errada-99')->getStatusCode();
    }

    private function conta(): void
    {
        $this->cadastrarConta('Moda Azul', self::EMAIL, '93339970000105');
        $this->db->table('contas')->update(['email_confirmado_em' => gmdate('Y-m-d H:i:s')]);
        $this->db->table('limites_tentativas')->delete();
        $this->db->table('sessoes')->delete();
    }

    private function cadastro(string $email = 'novo@exemplo.com', string $cnpj = '12ABC34501DE35'): ResponseInterface
    {
        return $this->api('POST', '/api/contas', [
            'email' => $email, 'cnpj' => $cnpj, 'nome' => 'Loja Nova', 'senha' => self::SENHA,
        ]);
    }

    private function trocar(string $token, string $atual, string $nova = 'outra-senha-22'): ResponseInterface
    {
        return $this->api('PUT', '/api/conta/senha', ['senha_atual' => $atual, 'nova_senha' => $nova], $this->comSessao($token));
    }

    // --- Login ------------------------------------------------------------------------------

    public function testQuintoErroNoMesmoEmailBloqueiaMesmoComASenhaCerta(): void
    {
        $this->conta();
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(401, $this->erroNoLogin());
        }

        $resposta = $this->login(self::EMAIL, self::SENHA);

        $this->assertSame(429, $resposta->getStatusCode());
        $this->assertSame(0, $this->db->table('sessoes')->count(), 'nenhuma sessão aberta');
        $this->assertSame([], $this->errosLogados);
    }

    public function testEmailDesconhecidoBloqueiaNoMesmoPontoQueUmExistente(): void
    {
        $this->conta();
        $existente = $desconhecido = [];
        for ($i = 0; $i < 6; $i++) {
            $existente[] = $this->erroNoLogin(self::EMAIL);
            $desconhecido[] = $this->erroNoLogin('ninguem@exemplo.com');
        }

        $this->assertSame([401, 401, 401, 401, 401, 429], $existente);
        $this->assertSame($existente, $desconhecido);
    }

    public function testRespostasDeBloqueioSaoEquivalentesComEESemConta(): void
    {
        $this->conta();
        for ($i = 0; $i < 5; $i++) {
            $this->erroNoLogin(self::EMAIL);
            $this->erroNoLogin('ninguem@exemplo.com');
        }

        $a = $this->login(self::EMAIL, 'x');
        $b = $this->login('ninguem@exemplo.com', 'x');

        $this->assertSame($this->json($a), $this->json($b));
        $this->assertSame($a->getHeaderLine('Retry-After') === '', $b->getHeaderLine('Retry-After') === '');
    }

    public function testLoginCorretoZeraOContadorDoEmail(): void
    {
        $this->conta();
        for ($i = 0; $i < 4; $i++) {
            $this->erroNoLogin();
        }

        $this->assertSame(200, $this->login(self::EMAIL, self::SENHA)->getStatusCode());

        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(401, $this->erroNoLogin(), 'volta a poder errar sem bloqueio');
        }
    }

    public function testVinteEUmErrosDoMesmoIpBloqueiamOIp(): void
    {
        $this->conta();
        $this->limites['login_ip'] = 3;
        for ($i = 0; $i < 3; $i++) {
            $this->assertSame(401, $this->erroNoLogin("pessoa$i@exemplo.com"));
        }

        $this->assertSame(429, $this->erroNoLogin('outra@exemplo.com'));
        $this->assertSame(429, $this->login(self::EMAIL, self::SENHA)->getStatusCode());
    }

    public function testBloqueioPorIpNaoAfetaOutroIp(): void
    {
        $this->conta();
        $this->limites['login_ip'] = 2;
        $this->erroNoLogin('p1@exemplo.com');
        $this->erroNoLogin('p2@exemplo.com');
        $this->assertSame(429, $this->erroNoLogin('p3@exemplo.com'));

        $this->enderecoRemoto = '203.0.113.50';

        $this->assertSame(401, $this->erroNoLogin('p3@exemplo.com'));
        $this->assertSame(200, $this->login(self::EMAIL, self::SENHA)->getStatusCode());
    }

    public function testBloqueioPorEmailVaiComOEmailMesmoTrocandoDeIp(): void
    {
        $this->conta();
        for ($i = 0; $i < 5; $i++) {
            $this->erroNoLogin();
        }
        $this->enderecoRemoto = '203.0.113.50';

        $this->assertSame(429, $this->login(self::EMAIL, self::SENHA)->getStatusCode());
    }

    public function testDadosFaltandoNaoContam(): void
    {
        $this->conta();
        for ($i = 0; $i < 6; $i++) {
            $this->assertSame(422, $this->api('POST', '/api/sessoes', ['email' => self::EMAIL])->getStatusCode());
        }

        $this->assertSame(0, $this->db->table('limites_tentativas')->count());
        $this->assertSame(401, $this->erroNoLogin());
    }

    public function testEmailComFormatoInvalidoContaSoNoIp(): void
    {
        $this->conta();
        $this->limites['login_ip'] = 2;

        $this->assertSame(401, $this->erroNoLogin('isto-nao-e-email'));
        $this->assertSame(401, $this->erroNoLogin('isto-nao-e-email'));

        $this->assertSame(['login_ip'], $this->db->table('limites_tentativas')->pluck('escopo')->all());
        $this->assertSame(429, $this->erroNoLogin('isto-nao-e-email'));
    }

    public function testJanelaVencidaLiberaDeNovo(): void
    {
        $this->conta();
        for ($i = 0; $i < 5; $i++) {
            $this->erroNoLogin();
        }
        $this->assertSame(429, $this->login(self::EMAIL, self::SENHA)->getStatusCode());

        $this->db->table('limites_tentativas')->update(['expira_em' => gmdate('Y-m-d H:i:s', time() - 1)]);

        $this->assertSame(200, $this->login(self::EMAIL, self::SENHA)->getStatusCode());
    }

    public function testBancoNaoGuardaOEmailNemOIpEmClaro(): void
    {
        $this->conta();
        $this->erroNoLogin('Segredo@Exemplo.com');

        $texto = json_encode($this->db->table('limites_tentativas')->get()->all(), JSON_THROW_ON_ERROR);

        $this->assertNotSame('[]', $texto);
        $this->assertStringNotContainsString('segredo', strtolower($texto));
        $this->assertStringNotContainsString('198.51.100.7', $texto);
    }

    public function testFalhaNosContadoresNaoImpedeOLoginEVaiParaOLog(): void
    {
        $this->conta();
        $this->db->schema()->drop('limites_tentativas');

        $resposta = $this->login(self::EMAIL, self::SENHA);

        $this->assertSame(200, $resposta->getStatusCode());
        $this->assertNotSame([], $this->errosLogados);
    }

    // --- Formato do 429 ---------------------------------------------------------------------

    public function testFormatoDoBloqueio(): void
    {
        $this->conta();
        for ($i = 0; $i < 5; $i++) {
            $this->erroNoLogin();
        }

        $resposta = $this->login(self::EMAIL, self::SENHA);
        $corpo = $this->json($resposta);

        $this->assertSame(429, $resposta->getStatusCode());
        $this->assertStringStartsWith('application/json', $resposta->getHeaderLine('Content-Type'));
        $this->assertMatchesRegularExpression('/\A\d+\z/', $resposta->getHeaderLine('Retry-After'));
        $segundos = (int) $resposta->getHeaderLine('Retry-After');
        $this->assertGreaterThan(0, $segundos);
        $this->assertLessThanOrEqual(900, $segundos);
        $this->assertSame('Muitas tentativas. Tente de novo em 15 minutos.', $corpo['erro']);
    }

    public function testRetryAfterExpostoAoNavegadorSoComCorsConfigurado(): void
    {
        $this->conta();
        for ($i = 0; $i < 5; $i++) {
            $this->erroNoLogin();
        }
        $corpo = ['email' => self::EMAIL, 'senha' => self::SENHA];
        $config = ['rate_limit' => $this->limites];

        $com = $this->chamar('POST', '/api/sessoes', $corpo, [], $config + ['cors_origin' => 'https://front.exemplo.com']);
        $sem = $this->chamar('POST', '/api/sessoes', $corpo, [], $config);

        $this->assertSame(429, $com->getStatusCode());
        $this->assertSame('Retry-After', $com->getHeaderLine('Access-Control-Expose-Headers'));
        $this->assertSame([], $this->cabecalhosCors($sem));
    }

    // --- Cadastro ---------------------------------------------------------------------------

    public function testSextaChamadaDeCadastroNaHoraEBloqueadaSemCriarNada(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(422, $this->api('POST', '/api/contas', [])->getStatusCode());
        }

        $resposta = $this->cadastro();

        $this->assertSame(429, $resposta->getStatusCode());
        $this->assertSame(0, $this->db->table('contas')->count());
        $this->assertSame(0, $this->db->table('estabelecimentos')->whereNotNull('conta_id')->count());
    }

    public function testCadastrosRecusadosPor409TambemContam(): void
    {
        $this->cadastrarConta('Moda Azul', self::EMAIL, '93339970000105');
        $this->db->table('limites_tentativas')->delete();

        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(409, $this->cadastro(self::EMAIL, '93339970000105')->getStatusCode());
        }

        $this->assertSame(429, $this->cadastro(self::EMAIL, '93339970000105')->getStatusCode());
    }

    public function testCadastroValidoDentroDoLimiteFunciona(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->api('POST', '/api/contas', []);
        }

        $this->assertSame(201, $this->cadastro()->getStatusCode());
    }

    public function testOutroIpPodeCadastrarComOPrimeiroBloqueado(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->api('POST', '/api/contas', []);
        }
        $this->assertSame(429, $this->cadastro()->getStatusCode());

        $this->enderecoRemoto = '203.0.113.50';

        $this->assertSame(201, $this->cadastro()->getStatusCode());
    }

    // --- Troca de senha ---------------------------------------------------------------------

    public function testQuintoErroNaSenhaAtualBloqueiaMesmoComASenhaCerta(): void
    {
        $token = $this->cadastrarConta('Moda Azul', self::EMAIL, '93339970000105')['token'];
        $this->db->table('limites_tentativas')->delete();
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(422, $this->trocar($token, 'errada-errada')->getStatusCode());
        }

        $this->assertSame(429, $this->trocar($token, self::SENHA)->getStatusCode());
        $this->assertSame(200, $this->login(self::EMAIL, self::SENHA)->getStatusCode(), 'senha não mudou');
    }

    public function testTrocaBemSucedidaZeraOContadorDaConta(): void
    {
        $token = $this->cadastrarConta('Moda Azul', self::EMAIL, '93339970000105')['token'];
        $this->db->table('limites_tentativas')->delete();
        for ($i = 0; $i < 4; $i++) {
            $this->trocar($token, 'errada-errada');
        }

        $this->assertSame(200, $this->trocar($token, self::SENHA, 'outra-senha-22')->getStatusCode());

        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(422, $this->trocar($token, 'errada-errada')->getStatusCode());
        }
    }

    public function testOutraContaNaoEAfetadaPeloBloqueioDaTroca(): void
    {
        $a = $this->cadastrarConta('Moda Azul', self::EMAIL, '93339970000105')['token'];
        $b = $this->cadastrarConta('Loja Nova', 'b@exemplo.com', '12ABC34501DE35')['token'];
        $this->db->table('limites_tentativas')->delete();
        for ($i = 0; $i < 5; $i++) {
            $this->trocar($a, 'errada-errada');
        }

        $this->assertSame(429, $this->trocar($a, self::SENHA)->getStatusCode());
        $this->assertSame(200, $this->trocar($b, self::SENHA)->getStatusCode());
    }

    public function testNovaSenhaInvalidaNaoContaComoErro(): void
    {
        $token = $this->cadastrarConta('Moda Azul', self::EMAIL, '93339970000105')['token'];
        $this->db->table('limites_tentativas')->delete();

        for ($i = 0; $i < 6; $i++) {
            $this->assertSame(422, $this->trocar($token, self::SENHA, 'curta')->getStatusCode());
        }

        $this->assertSame(200, $this->trocar($token, self::SENHA)->getStatusCode());
    }

    // --- Configuração e IP do cliente -------------------------------------------------------

    public function testLimiteConfiguradoValeNoTerceiroErro(): void
    {
        $this->conta();
        $this->limites['login_email'] = 3;

        $this->assertSame([401, 401, 401, 429], [
            $this->erroNoLogin(), $this->erroNoLogin(), $this->erroNoLogin(), $this->erroNoLogin(),
        ]);
    }

    public function testSemProxyConfiavelOCabecalhoEncaminhadoEhIgnorado(): void
    {
        $this->conta();
        $this->limites['login_ip'] = 2;
        $falsos = fn (int $n) => ['X-Forwarded-For' => "1.2.3.$n"];

        $this->api('POST', '/api/sessoes', ['email' => 'x@exemplo.com', 'senha' => 'y'], $falsos(1));
        $this->api('POST', '/api/sessoes', ['email' => 'x@exemplo.com', 'senha' => 'y'], $falsos(2));
        $resposta = $this->api('POST', '/api/sessoes', ['email' => 'x@exemplo.com', 'senha' => 'y'], $falsos(3));

        $this->assertSame(429, $resposta->getStatusCode());
    }

    public function testAtrasDeProxyConfiavelCadaClienteTemOSeuLimite(): void
    {
        $this->conta();
        $this->limites['login_ip'] = 2;
        $this->proxies = ['198.51.100.0/24'];
        $de = fn (string $ip) => $this->api(
            'POST',
            '/api/sessoes',
            ['email' => 'x@exemplo.com', 'senha' => 'y'],
            ['X-Forwarded-For' => "1.2.3.4, $ip"]
        )->getStatusCode();

        $this->assertSame([401, 401, 429], [$de('203.0.113.1'), $de('203.0.113.1'), $de('203.0.113.1')]);
        $this->assertSame(401, $de('203.0.113.2'), 'outro cliente atrás do mesmo proxy');
    }

    // --- "Esqueci a senha" ------------------------------------------------------------------

    private function pedirRedefinicao(string $email): ResponseInterface
    {
        return $this->api('POST', '/api/senha/esqueci', ['email' => $email]);
    }

    private function redefinir(?string $token, string $senha = 'senha-nova-22'): ResponseInterface
    {
        return $this->api('POST', '/api/senha/redefinir', ['token' => $token, 'nova_senha' => $senha]);
    }

    public function testQuartoPedidoDoMesmoEmailNaHoraEBloqueadoSemEnviarEmail(): void
    {
        $this->conta();
        $status = [];
        for ($i = 0; $i < 4; $i++) {
            $status[] = $this->pedirRedefinicao(self::EMAIL)->getStatusCode();
        }

        $this->assertSame([202, 202, 202, 429], $status);
        $this->assertCount(3, $this->mailer->enviados, 'o quarto pedido não envia e-mail');
    }

    public function testEmailSemContaBloqueiaNoMesmoPontoQueUmExistente(): void
    {
        $this->conta();
        $existente = $desconhecido = [];
        for ($i = 0; $i < 4; $i++) {
            $existente[] = $this->pedirRedefinicao(self::EMAIL);
            $desconhecido[] = $this->pedirRedefinicao('ninguem@exemplo.com');
        }

        $this->assertSame([202, 202, 202, 429], array_map(fn ($r) => $r->getStatusCode(), $existente));
        $this->assertSame([202, 202, 202, 429], array_map(fn ($r) => $r->getStatusCode(), $desconhecido));
        $this->assertSame((string) $existente[3]->getBody(), (string) $desconhecido[3]->getBody());
        $this->assertSame($existente[3]->getHeaders(), $desconhecido[3]->getHeaders());
    }

    public function testMuitosPedidosDoMesmoIpBloqueiamOIp(): void
    {
        $this->limites['esqueci_ip'] = 10;
        for ($i = 0; $i < 10; $i++) {
            $this->assertSame(202, $this->pedirRedefinicao("pessoa$i@exemplo.com")->getStatusCode());
        }

        $this->assertSame(429, $this->pedirRedefinicao('outra@exemplo.com')->getStatusCode());

        $this->enderecoRemoto = '203.0.113.50';
        $this->assertSame(202, $this->pedirRedefinicao('outra@exemplo.com')->getStatusCode(), 'outro IP segue livre');
    }

    public function testFormatoDoBloqueioDoPedido(): void
    {
        $this->limites['esqueci_email'] = 1;
        $this->pedirRedefinicao(self::EMAIL);

        $resposta = $this->pedirRedefinicao(self::EMAIL);
        $segundos = (int) $resposta->getHeaderLine('Retry-After');

        $this->assertSame(429, $resposta->getStatusCode());
        $this->assertGreaterThan(0, $segundos);
        $this->assertLessThanOrEqual(3600, $segundos);
        $this->assertSame('Muitas tentativas. Tente de novo em 60 minutos.', $this->json($resposta)['erro']);
    }

    public function testEmailInvalidoNaoContaNoLimiteDoPedido(): void
    {
        $this->limites['esqueci_ip'] = 2;
        for ($i = 0; $i < 4; $i++) {
            $this->assertSame(422, $this->api('POST', '/api/senha/esqueci', ['email' => 'sem-arroba'])->getStatusCode());
        }

        $this->assertSame(0, $this->db->table('limites_tentativas')->count());
        $this->assertSame(202, $this->pedirRedefinicao(self::EMAIL)->getStatusCode());
    }

    public function testLimiteDoPedidoConfiguradoValeNoSegundoPedido(): void
    {
        $this->limites['esqueci_email'] = 1;

        $this->assertSame(202, $this->pedirRedefinicao(self::EMAIL)->getStatusCode());
        $this->assertSame(429, $this->pedirRedefinicao(self::EMAIL)->getStatusCode());
    }

    public function testVigesimoPrimeiroTokenInvalidoBloqueiaMesmoComTokenValido(): void
    {
        $this->conta();
        $this->limites['redefinir_ip'] = 3;
        $this->pedirRedefinicao(self::EMAIL);
        $valido = $this->mailer->ultimoToken();
        for ($i = 0; $i < 3; $i++) {
            $this->assertSame(422, $this->redefinir(str_repeat('f', 64))->getStatusCode());
        }

        $this->assertSame(429, $this->redefinir($valido)->getStatusCode());
        $this->assertSame(200, $this->login(self::EMAIL, self::SENHA)->getStatusCode(), 'a senha não mudou');
    }

    public function testSenhaInvalidaNaoContaNoLimiteDaRedefinicao(): void
    {
        $this->conta();
        $this->limites['redefinir_ip'] = 2;
        $this->pedirRedefinicao(self::EMAIL);
        $token = $this->mailer->ultimoToken();

        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(422, $this->redefinir($token, 'curta')->getStatusCode());
        }

        $this->assertSame(200, $this->redefinir($token)->getStatusCode());
    }

    public function testRedefinicaoConcluidaNaoContaNoLimite(): void
    {
        $this->conta();
        $this->limites['redefinir_ip'] = 1;
        $this->pedirRedefinicao(self::EMAIL);

        $this->assertSame(200, $this->redefinir($this->mailer->ultimoToken())->getStatusCode());
        $this->assertSame(0, $this->db->table('limites_tentativas')->where('escopo', 'redefinir_ip')->count());
    }

    public function testBloqueioDaRedefinicaoNaoAfetaOutroIp(): void
    {
        $this->limites['redefinir_ip'] = 1;
        $this->redefinir(str_repeat('f', 64));
        $this->assertSame(429, $this->redefinir(str_repeat('f', 64))->getStatusCode());

        $this->enderecoRemoto = '203.0.113.50';

        $this->assertSame(422, $this->redefinir(str_repeat('f', 64))->getStatusCode());
    }

    // --- Confirmação do e-mail --------------------------------------------------------------

    /** Cadastra pela API (com limites e mailer em memória) e devolve o token da sessão. */
    private function cadastrarComConfirmacao(string $email = self::EMAIL, string $cnpj = '93339970000105'): string
    {
        $resposta = $this->api('POST', '/api/contas', [
            'email' => $email, 'cnpj' => $cnpj, 'nome' => 'Moda Azul', 'senha' => self::SENHA,
        ]);
        $this->assertSame(201, $resposta->getStatusCode());

        return $this->json($resposta)['token'];
    }

    private function reenviarConfirmacao(string $sessao): ResponseInterface
    {
        return $this->api('POST', '/api/conta/email/reenviar', null, $this->comSessao($sessao));
    }

    private function trocarEmail(string $sessao, string $email, string $senha = self::SENHA): ResponseInterface
    {
        return $this->api('PUT', '/api/conta/email', ['email' => $email, 'senha' => $senha], $this->comSessao($sessao));
    }

    private function confirmarEmail(?string $token): ResponseInterface
    {
        return $this->api('POST', '/api/email/confirmar', ['token' => $token]);
    }

    public function testQuartoEnvioDeConfirmacaoNaHoraEBloqueadoSemEnviarEmail(): void
    {
        $sessao = $this->cadastrarComConfirmacao();
        $this->db->table('limites_tentativas')->delete();
        $this->mailer->enviados = [];
        $status = [];
        for ($i = 0; $i < 4; $i++) {
            $status[] = $this->reenviarConfirmacao($sessao)->getStatusCode();
        }

        $this->assertSame([202, 202, 202, 429], $status);
        $this->assertCount(3, $this->mailer->enviados, 'o quarto envio não manda e-mail');
    }

    public function testReenviarETrocarOEmailContamNoMesmoLimite(): void
    {
        $sessao = $this->cadastrarComConfirmacao();
        $this->db->table('limites_tentativas')->delete();

        $this->assertSame(202, $this->reenviarConfirmacao($sessao)->getStatusCode());
        $this->assertSame(200, $this->trocarEmail($sessao, 'novo1@exemplo.com')->getStatusCode());
        $this->assertSame(200, $this->trocarEmail($sessao, 'novo2@exemplo.com')->getStatusCode());

        $this->assertSame(429, $this->reenviarConfirmacao($sessao)->getStatusCode());
        $quarta = $this->trocarEmail($sessao, 'novo3@exemplo.com');
        $this->assertSame(429, $quarta->getStatusCode());
        $this->assertSame('novo2@exemplo.com', $this->json(
            $this->api('GET', '/api/conta', null, $this->comSessao($sessao))
        )['conta']['email'], 'a troca bloqueada não muda o e-mail');
    }

    public function testFormatoDoBloqueioDaConfirmacao(): void
    {
        $this->limites['confirmacao_conta'] = 1;
        $sessao = $this->cadastrarComConfirmacao();
        $this->db->table('limites_tentativas')->delete();
        $this->reenviarConfirmacao($sessao);

        $resposta = $this->reenviarConfirmacao($sessao);
        $segundos = (int) $resposta->getHeaderLine('Retry-After');

        $this->assertSame(429, $resposta->getStatusCode());
        $this->assertGreaterThan(0, $segundos);
        $this->assertLessThanOrEqual(3600, $segundos);
        $this->assertSame('Muitas tentativas. Tente de novo em 60 minutos.', $this->json($resposta)['erro']);
    }

    public function testOutraContaNaoEAfetadaPeloBloqueioDoReenvio(): void
    {
        $a = $this->cadastrarComConfirmacao();
        $b = $this->cadastrarComConfirmacao('b@exemplo.com', '12ABC34501DE35');
        $this->db->table('limites_tentativas')->delete();
        for ($i = 0; $i < 3; $i++) {
            $this->reenviarConfirmacao($a);
        }

        $this->assertSame(429, $this->reenviarConfirmacao($a)->getStatusCode());
        $this->assertSame(202, $this->reenviarConfirmacao($b)->getStatusCode());
    }

    public function testSenhaErradaNaTrocaDeEmailEDeSenhaCompartilhamOLimite(): void
    {
        $sessao = $this->cadastrarComConfirmacao();
        $this->db->table('limites_tentativas')->delete();
        $erroSenha = fn () => $this->api(
            'PUT',
            '/api/conta/senha',
            ['senha_atual' => 'errada-errada', 'nova_senha' => 'outra-senha-22'],
            $this->comSessao($sessao)
        )->getStatusCode();

        $this->assertSame([422, 422, 422], [$erroSenha(), $erroSenha(), $erroSenha()]);
        $this->assertSame(422, $this->trocarEmail($sessao, 'novo@exemplo.com', 'errada-errada')->getStatusCode());
        $this->assertSame(422, $this->trocarEmail($sessao, 'novo@exemplo.com', 'errada-errada')->getStatusCode());

        $this->assertSame(429, $this->trocarEmail($sessao, 'novo@exemplo.com')->getStatusCode(), 'mesmo com a senha certa');
    }

    public function testVigesimoPrimeiroTokenInvalidoNaConfirmacaoBloqueiaMesmoComTokenValido(): void
    {
        $this->limites['confirmar_ip'] = 3;
        $sessao = $this->cadastrarComConfirmacao();
        $valido = $this->mailer->ultimoToken();
        for ($i = 0; $i < 3; $i++) {
            $this->assertSame(422, $this->confirmarEmail(str_repeat('f', 64))->getStatusCode());
        }

        $this->assertSame(429, $this->confirmarEmail($valido)->getStatusCode());
        $this->assertFalse($this->json(
            $this->api('GET', '/api/conta', null, $this->comSessao($sessao))
        )['conta']['email_confirmado']);
    }

    public function testConfirmacaoConcluidaNaoContaNoLimite(): void
    {
        $this->limites['confirmar_ip'] = 1;
        $this->cadastrarComConfirmacao();

        $this->assertSame(200, $this->confirmarEmail($this->mailer->ultimoToken())->getStatusCode());
        $this->assertSame(0, $this->db->table('limites_tentativas')->where('escopo', 'confirmar_ip')->count());
    }

    public function testBloqueioDaConfirmacaoNaoAfetaOutroIp(): void
    {
        $this->limites['confirmar_ip'] = 1;
        $this->confirmarEmail(str_repeat('f', 64));
        $this->assertSame(429, $this->confirmarEmail(str_repeat('f', 64))->getStatusCode());

        $this->enderecoRemoto = '203.0.113.50';

        $this->assertSame(422, $this->confirmarEmail(str_repeat('f', 64))->getStatusCode());
    }

    public function testLimiteDeConfirmacaoConfiguradoValeNoSegundoEnvio(): void
    {
        $this->limites['confirmacao_conta'] = 1;
        $sessao = $this->cadastrarComConfirmacao();
        $this->db->table('limites_tentativas')->delete();

        $this->assertSame(202, $this->reenviarConfirmacao($sessao)->getStatusCode());
        $this->assertSame(429, $this->reenviarConfirmacao($sessao)->getStatusCode());
    }

    public function testTrocaDeEmailBemSucedidaZeraOContadorDeSenhaDaConta(): void
    {
        $sessao = $this->cadastrarComConfirmacao();
        $this->db->table('limites_tentativas')->delete();
        $erroSenha = fn () => $this->api(
            'PUT',
            '/api/conta/senha',
            ['senha_atual' => 'errada-errada', 'nova_senha' => 'outra-senha-22'],
            $this->comSessao($sessao)
        )->getStatusCode();
        for ($i = 0; $i < 4; $i++) {
            $this->assertSame(422, $erroSenha());
        }

        $this->assertSame(200, $this->trocarEmail($sessao, 'novo@exemplo.com')->getStatusCode());

        for ($i = 0; $i < 4; $i++) {
            $this->assertSame(422, $erroSenha(), 'volta a poder errar sem bloqueio');
        }
    }
}
