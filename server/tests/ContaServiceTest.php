<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Models\Conta;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use Services\ConflitoException;
use Services\ContaService;
use Services\DadosInvalidosException;
use Services\LimiteDeTentativas;
use Services\LimiteExcedidoException;
use Services\SessaoService;

final class ContaServiceTest extends DatabaseTestCase
{
    private ContaService $contas;
    private SessaoService $sessoes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrate();
        $this->semearDemonstracao();
        $this->sessoes = new SessaoService();
        $this->contas = new ContaService($this->sessoes);
    }

    /** @return array<string, string> */
    private function corpo(array $mudancas = []): array
    {
        return array_merge([
            'email' => 'contato@vestebem.com',
            'cnpj' => '93.339.970/0001-05',
            'nome' => 'Moda Azul',
            'senha' => 'senha-segura-1',
        ], $mudancas);
    }

    private function tabelasVazias(): void
    {
        $this->assertSame(0, $this->db->table('contas')->count(), 'nenhuma conta');
        $this->assertSame(0, $this->db->table('sessoes')->count(), 'nenhuma sessão');
        $this->assertSame(1, $this->db->table('estabelecimentos')->count(), 'só a loja de partida');
    }

    // --- Cadastro ---------------------------------------------------------------------

    public function testCadastroCriaContaLojaESessaoEDevolveOToken(): void
    {
        $resposta = $this->contas->cadastrar($this->corpo());

        $this->assertSame(64, strlen($resposta['token']));
        $this->assertSame([
            'email' => 'contato@vestebem.com', 'cnpj' => '93339970000105', 'email_confirmado' => false,
            'confirmar_ate' => $this->db->table('contas')->value('confirmar_ate'),
        ], $resposta['conta']);
        $this->assertSame(
            ['nome' => 'Moda Azul', 'slug' => 'moda-azul', 'endereco_publico' => null],
            $resposta['loja']
        );
        $conta = Conta::query()->firstOrFail();
        $this->assertSame(
            $conta->id,
            (int) $this->db->table('estabelecimentos')->where('slug', 'moda-azul')->value('conta_id')
        );
        $this->assertSame(1, $this->db->table('sessoes')->where('conta_id', $conta->id)->count());
        $this->assertNotNull($this->sessoes->resolver($resposta['token']));
    }

    public function testCadastroGravaOPrazoDeConfirmacaoQuatorzeDiasDepoisDoCadastro(): void
    {
        $resposta = $this->contas->cadastrar($this->corpo());

        $conta = Conta::query()->firstOrFail();
        $this->assertSame($conta->confirmar_ate, $resposta['conta']['confirmar_ate']);
        $this->assertMatchesRegularExpression('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/', $conta->confirmar_ate);
        $this->assertSame(
            ContaService::PRAZO_CONFIRMACAO_DIAS * 86400,
            strtotime($conta->confirmar_ate . ' UTC') - strtotime($conta->criado_em . ' UTC')
        );
    }

    public function testCadastroGuardaEmailECnpjNormalizados(): void
    {
        $this->contas->cadastrar($this->corpo(['email' => ' Contato@VesteBem.com ', 'cnpj' => '93.339.970/0001-05']));

        $conta = Conta::query()->firstOrFail();
        $this->assertSame('contato@vestebem.com', $conta->email);
        $this->assertSame('93339970000105', $conta->cnpj);
    }

    public function testSenhaFicaApenasComoHash(): void
    {
        $this->contas->cadastrar($this->corpo());

        $hash = (string) $this->db->table('contas')->value('senha_hash');
        $this->assertStringNotContainsString('senha-segura-1', $hash);
        $this->assertTrue(password_verify('senha-segura-1', $hash));
        $this->assertStringStartsWith('$2y$', $hash);
    }

    public function testCadastroEhTudoOuNada(): void
    {
        // todos os slugs candidatos já ocupados: a loja falha DEPOIS de a conta ser inserida
        $this->db->table('estabelecimentos')->insert(['nome' => 'x', 'slug' => 'loja']);
        for ($n = 2; $n <= 50; $n++) {
            $this->db->table('estabelecimentos')->insert(['nome' => 'x', 'slug' => 'loja-' . $n]);
        }
        $antes = $this->db->table('estabelecimentos')->count();

        try {
            $this->contas->cadastrar($this->corpo(['nome' => 'Loja']));
            $this->fail('devia recusar');
        } catch (ConflitoException) {
        }

        $this->assertSame(0, $this->db->table('contas')->count(), 'a conta não pode ficar');
        $this->assertSame(0, $this->db->table('sessoes')->count());
        $this->assertSame($antes, $this->db->table('estabelecimentos')->count());
    }

    // --- Unicidade ----------------------------------------------------------------------

    public function testEmailRepetidoComOutraCaixaEh409ESemEfeito(): void
    {
        $this->contas->cadastrar($this->corpo());
        $hash = $this->db->table('contas')->value('senha_hash');

        try {
            $this->contas->cadastrar($this->corpo(['email' => 'CONTATO@vestebem.COM', 'cnpj' => '11.111.111/0001-91', 'senha' => 'outra-senha-9']));
            $this->fail('devia recusar');
        } catch (ConflitoException $e) {
            $this->assertStringContainsString('e-mail', $e->getMessage());
        }

        $this->assertSame(1, $this->db->table('contas')->count());
        $this->assertSame(2, $this->db->table('estabelecimentos')->count());
        $this->assertSame($hash, $this->db->table('contas')->value('senha_hash'), 'a conta existente não muda');
    }

    public function testCnpjAlfanumericoEAceitoEGuardadoEmMaiusculas(): void
    {
        $resposta = $this->contas->cadastrar($this->corpo(['cnpj' => '12.abc.345/01de-35']));

        $this->assertSame('12ABC34501DE35', $resposta['conta']['cnpj']);
        $this->assertSame('12ABC34501DE35', Conta::query()->firstOrFail()->cnpj);
    }

    public function testCnpjRepetidoComOutraCaixaEh409(): void
    {
        $this->contas->cadastrar($this->corpo(['cnpj' => '12ABC34501DE35']));

        $this->expectException(ConflitoException::class);
        $this->contas->cadastrar($this->corpo(['email' => 'outra@exemplo.com', 'cnpj' => '12.abc.345/01de-35']));
    }

    public function testCnpjRepetidoComEmSemMascaraEh409(): void
    {
        $this->contas->cadastrar($this->corpo());

        $this->expectException(ConflitoException::class);
        $this->expectExceptionMessage('CNPJ');
        $this->contas->cadastrar($this->corpo(['email' => 'outra@exemplo.com', 'cnpj' => '93339970000105']));
    }

    // --- Slug ---------------------------------------------------------------------------

    public function testSlugRepetidoRecebeSufixos(): void
    {
        $a = $this->contas->cadastrar($this->corpo(['nome' => 'Moda Azul']));
        $b = $this->contas->cadastrar($this->corpo(['email' => 'b@exemplo.com', 'cnpj' => '11111111000191', 'nome' => 'Moda  Azul!']));
        $c = $this->contas->cadastrar($this->corpo(['email' => 'c@exemplo.com', 'cnpj' => '22222222000191', 'nome' => 'moda azul']));

        $this->assertSame('moda-azul', $a['loja']['slug']);
        $this->assertSame('moda-azul-2', $b['loja']['slug']);
        $this->assertSame('moda-azul-3', $c['loja']['slug']);
    }

    public function testSlugDaDemonstracaoSemeadaContinuaOcupado(): void
    {
        $resposta = $this->contas->cadastrar($this->corpo(['nome' => 'Veste Bem']));

        $this->assertSame('veste-bem-2', $resposta['loja']['slug']);
        $this->assertNull($this->db->table('estabelecimentos')->where('slug', 'veste-bem')->value('conta_id'));
    }

    public function testPrimeiraLojaChamadaVesteBemEmBancoSemADemonstracaoFicaComOSlugLivre(): void
    {
        $this->db->table('estabelecimentos')->where('slug', 'veste-bem')->delete();

        $resposta = $this->contas->cadastrar($this->corpo(['nome' => 'Veste Bem']));

        $this->assertSame('veste-bem', $resposta['loja']['slug']);
    }

    public function testSufixoCabeNosOitentaCaracteres(): void
    {
        $nome = str_repeat('a', 100);
        $a = $this->contas->cadastrar($this->corpo(['nome' => $nome]));
        $b = $this->contas->cadastrar($this->corpo(['email' => 'b@exemplo.com', 'cnpj' => '11111111000191', 'nome' => $nome]));

        $this->assertSame(str_repeat('a', 80), $a['loja']['slug']);
        $this->assertSame(str_repeat('a', 78) . '-2', $b['loja']['slug']);
    }

    // --- Validação ----------------------------------------------------------------------

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidos(): array
    {
        return [
            'e-mail inválido' => [['email' => 'sem-arroba'], 'E-mail'],
            'e-mail não é texto' => [['email' => 123], 'E-mail'],
            'cnpj com 13 dígitos' => [['cnpj' => '9333997000010'], 'CNPJ'],
            'cnpj com letra nos dígitos verificadores' => [['cnpj' => '12ABC34501DEAB'], 'CNPJ'],
            'cnpj com dígito verificador errado' => [['cnpj' => '93339970000106'], 'dígitos verificadores'],
            'cnpj alfanumérico com dígito verificador errado' => [['cnpj' => '12ABC34501DE36'], 'CNPJ'],
            'cnpj com 14 caracteres iguais' => [['cnpj' => '00000000000000'], 'CNPJ'],
            'nome de 1 caractere' => [['nome' => 'A'], 'Nome do estabelecimento'],
            'nome só com espaços' => [['nome' => '    '], 'Nome do estabelecimento'],
            'nome com 121 caracteres' => [['nome' => str_repeat('a', 121)], 'Nome do estabelecimento'],
            'nome sem letras nem números' => [['nome' => '!!!'], 'letras ou números'],
            'senha com 7 bytes' => [['senha' => '1234567'], 'Senha'],
            'senha com 73 bytes' => [['senha' => str_repeat('a', 73)], 'Senha'],
            'senha não é texto' => [['senha' => 12345678], 'Senha'],
        ];
    }

    /** @param array<string, mixed> $mudancas */
    #[DataProvider('invalidos')]
    public function testDadoInvalidoEh422ENadaEhCriado(array $mudancas, string $trecho): void
    {
        try {
            $this->contas->cadastrar($this->corpo($mudancas));
            $this->fail('devia recusar');
        } catch (DadosInvalidosException $e) {
            $this->assertStringContainsString($trecho, $e->getMessage());
        }

        $this->tabelasVazias();
    }

    /** @return array<string, array{string}> */
    public static function camposDoCadastro(): array
    {
        return ['email' => ['email'], 'cnpj' => ['cnpj'], 'nome' => ['nome'], 'senha' => ['senha']];
    }

    #[DataProvider('camposDoCadastro')]
    public function testCampoAusenteEh422(string $campo): void
    {
        $corpo = $this->corpo();
        unset($corpo[$campo]);

        $this->expectException(DadosInvalidosException::class);
        try {
            $this->contas->cadastrar($corpo);
        } finally {
            $this->tabelasVazias();
        }
    }

    public function testSenhaDeOitoEDe72BytesSaoAceitas(): void
    {
        $a = $this->contas->cadastrar($this->corpo(['senha' => '12345678']));
        $b = $this->contas->cadastrar($this->corpo(['email' => 'b@exemplo.com', 'cnpj' => '11111111000191', 'nome' => 'Loja B', 'senha' => str_repeat('a', 72)]));

        $this->assertNotSame('', $a['token']);
        $this->assertNotSame('', $b['token']);
    }

    // --- Dados da conta -----------------------------------------------------------------

    public function testDadosDaContaTrazContaELoja(): void
    {
        $this->contas->cadastrar($this->corpo());
        $id = (int) $this->db->table('contas')->value('id');

        $this->assertSame([
            'conta' => [
                'email' => 'contato@vestebem.com', 'cnpj' => '93339970000105', 'email_confirmado' => false,
                'confirmar_ate' => $this->db->table('contas')->value('confirmar_ate'),
            ],
            'loja' => ['nome' => 'Moda Azul', 'slug' => 'moda-azul', 'endereco_publico' => null],
        ], $this->contas->dados($id));
    }

    public function testDadosDeContaConfirmadaTrazemOPrazoNulo(): void
    {
        $this->contas->cadastrar($this->corpo());
        $this->db->table('contas')->update(['email_confirmado_em' => gmdate('Y-m-d H:i:s')]);
        $id = (int) $this->db->table('contas')->value('id');

        $conta = $this->contas->dados($id)['conta'];

        $this->assertTrue($conta['email_confirmado']);
        $this->assertNull($conta['confirmar_ate']);
    }

    public function testDadosNaoVazamSenhaNemHash(): void
    {
        $r = $this->contas->cadastrar($this->corpo());
        $id = (int) $this->db->table('contas')->value('id');
        $texto = json_encode([$r, $this->contas->dados($id)], JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('senha', strtolower($texto));
        $this->assertStringNotContainsString('$2y$', $texto);
    }

    // --- Trocar a senha -----------------------------------------------------------------

    /** @return array{0: int, 1: int, 2: string} id da conta, id da sessão e token */
    private function contaComSessao(): array
    {
        $r = $this->contas->cadastrar($this->corpo());
        $sessao = $this->sessoes->resolver($r['token']);

        return [(int) $sessao->conta_id, (int) $sessao->id, $r['token']];
    }

    public function testTrocaDeSenhaValida(): void
    {
        [$conta, $sessao] = $this->contaComSessao();

        $this->contas->trocarSenha($conta, $sessao, ['senha_atual' => 'senha-segura-1', 'nova_senha' => 'nova-senha-2']);

        $hash = (string) $this->db->table('contas')->value('senha_hash');
        $this->assertTrue(password_verify('nova-senha-2', $hash));
        $this->assertFalse(password_verify('senha-segura-1', $hash));
    }

    public function testSenhaAtualErradaEh422ESenhaNaoMuda(): void
    {
        [$conta, $sessao] = $this->contaComSessao();
        $antes = $this->db->table('contas')->value('senha_hash');

        try {
            $this->contas->trocarSenha($conta, $sessao, ['senha_atual' => 'errada-errada', 'nova_senha' => 'nova-senha-2']);
            $this->fail('devia recusar');
        } catch (DadosInvalidosException $e) {
            $this->assertSame('Senha atual incorreta.', $e->getMessage());
        }

        $this->assertSame($antes, $this->db->table('contas')->value('senha_hash'));
    }

    public function testNovaSenhaInvalidaEh422ESenhaNaoMuda(): void
    {
        [$conta, $sessao] = $this->contaComSessao();
        $antes = $this->db->table('contas')->value('senha_hash');

        foreach (['curta', str_repeat('a', 73)] as $nova) {
            try {
                $this->contas->trocarSenha($conta, $sessao, ['senha_atual' => 'senha-segura-1', 'nova_senha' => $nova]);
                $this->fail('devia recusar');
            } catch (DadosInvalidosException) {
            }
        }

        $this->assertSame($antes, $this->db->table('contas')->value('senha_hash'));
    }

    public function testCamposAusentesNaTrocaSao422(): void
    {
        [$conta, $sessao] = $this->contaComSessao();

        $this->expectException(DadosInvalidosException::class);
        $this->contas->trocarSenha($conta, $sessao, ['nova_senha' => 'nova-senha-2']);
    }

    public function testTrocaEncerraAsOutrasSessoesEMantemAAtual(): void
    {
        [$conta, $sessao, $token] = $this->contaComSessao();
        $outra = $this->sessoes->abrir(Conta::query()->findOrFail($conta))['token'];
        $this->assertNotNull($this->sessoes->resolver($outra));

        $this->contas->trocarSenha($conta, $sessao, ['senha_atual' => 'senha-segura-1', 'nova_senha' => 'nova-senha-2']);

        $this->assertNotNull($this->sessoes->resolver($token), 'a sessão que trocou continua');
        $this->assertNull($this->sessoes->resolver($outra), 'a outra foi encerrada');
    }

    public function testTrocaNaoMexeNasSessoesDeOutraConta(): void
    {
        [$conta, $sessao] = $this->contaComSessao();
        $b = $this->contas->cadastrar($this->corpo(['email' => 'b@exemplo.com', 'cnpj' => '11111111000191', 'nome' => 'Loja B']));

        $this->contas->trocarSenha($conta, $sessao, ['senha_atual' => 'senha-segura-1', 'nova_senha' => 'nova-senha-2']);

        $this->assertNotNull($this->sessoes->resolver($b['token']));
    }

    public function testComLimiteOCadastroDoMesmoIpEhBloqueadoSemCriarConta(): void
    {
        $contas = new ContaService($this->sessoes, new LimiteDeTentativas([
            LimiteDeTentativas::CADASTRO_IP => ['max' => 1, 'janela' => 3600],
        ]));
        try {
            $contas->cadastrar([], '198.51.100.7');
        } catch (DadosInvalidosException) {
        }

        try {
            $contas->cadastrar([
                'email' => 'novo@exemplo.com', 'cnpj' => '93339970000105', 'nome' => 'Loja Nova', 'senha' => 'senha-segura-1',
            ], '198.51.100.7');
            $this->fail('o segundo cadastro devia ser bloqueado');
        } catch (LimiteExcedidoException) {
            $this->assertSame(0, $this->db->table('contas')->count());
        }
    }

    // --- Cadastro sobre conta não confirmada abandonada -----------------------------------

    private const PRAZO_VENCIDO = '2026-01-01 00:00:00';

    /** Cadastra e devolve o id da conta; `abandonada` põe o prazo no passado. */
    private function contaExistente(string $email, string $cnpj, string $nome, bool $abandonada = true): int
    {
        $this->contas->cadastrar($this->corpo(['email' => $email, 'cnpj' => $cnpj, 'nome' => $nome]));
        $id = (int) $this->db->table('contas')->where('email', $email)->value('id');
        if ($abandonada) {
            $this->db->table('contas')->where('id', $id)->update(['confirmar_ate' => self::PRAZO_VENCIDO]);
        }

        return $id;
    }

    private function entradaNaFila(int $contaId, int $diasAtras): void
    {
        $loja = (int) $this->db->table('estabelecimentos')->where('conta_id', $contaId)->value('id');
        $this->db->table('entradas_fila')->insert([
            'estabelecimento_id' => $loja, 'codigo' => 'c' . $contaId . '-' . $diasAtras, 'telefone' => '5511971778203',
            'status' => 'finalizado', 'entrou_em' => gmdate('Y-m-d H:i:s', time() - $diasAtras * 86400),
        ]);
    }

    private function assertConflito(array $corpo, string $trecho): void
    {
        try {
            $this->contas->cadastrar($corpo);
            $this->fail('devia recusar com 409');
        } catch (ConflitoException $e) {
            $this->assertStringContainsString($trecho, $e->getMessage());
        }
    }

    private function existe(int $contaId): bool
    {
        return $this->db->table('contas')->where('id', $contaId)->exists();
    }

    public function testEmailDeContaAbandonadaFicaComOCadastroNovo(): void
    {
        $antiga = $this->contaExistente('contato@vestebem.com', '93339970000105', 'Moda Azul');

        $resposta = $this->contas->cadastrar($this->corpo(['cnpj' => '11.111.111/0001-91', 'nome' => 'Moda Azul']));

        $this->assertFalse($this->existe($antiga));
        $this->assertSame('11111111000191', $resposta['conta']['cnpj']);
        $this->assertSame('moda-azul', $resposta['loja']['slug'], 'o slug da loja apagada fica livre');
        $this->assertSame(1, $this->db->table('contas')->count());
        $this->assertTrue($this->db->table('estabelecimentos')->where('slug', 'veste-bem')->exists());
    }

    public function testCnpjDeContaAbandonadaComOutroEmailFicaComOCadastroNovo(): void
    {
        $antiga = $this->contaExistente('antiga@exemplo.com', '93339970000105', 'Loja Antiga');

        $resposta = $this->contas->cadastrar($this->corpo());

        $this->assertFalse($this->existe($antiga));
        $this->assertSame('contato@vestebem.com', $resposta['conta']['email']);
        $this->assertFalse($this->db->table('estabelecimentos')->where('slug', 'loja-antiga')->exists());
    }

    public function testDentroDoPrazoEh409(): void
    {
        $antiga = $this->contaExistente('contato@vestebem.com', '93339970000105', 'Moda Azul', false);
        $this->db->table('contas')->update(['confirmar_ate' => gmdate('Y-m-d H:i:s', time() + 60)]);

        $this->assertConflito($this->corpo(['cnpj' => '11111111000191']), 'e-mail');

        $this->assertTrue($this->existe($antiga));
    }

    public function testLojaComEntradaNosUltimos14DiasEh409(): void
    {
        $antiga = $this->contaExistente('contato@vestebem.com', '93339970000105', 'Moda Azul');
        $this->entradaNaFila($antiga, 13);

        $this->assertConflito($this->corpo(['cnpj' => '11111111000191']), 'e-mail');

        $this->assertTrue($this->existe($antiga));
        $this->assertSame(1, $this->db->table('entradas_fila')->count());
    }

    public function testEntradaMaisAntigaQue14DiasNaoProtegeEAEntradaSaiComALoja(): void
    {
        $antiga = $this->contaExistente('contato@vestebem.com', '93339970000105', 'Moda Azul');
        $this->entradaNaFila($antiga, 15);

        $this->contas->cadastrar($this->corpo(['cnpj' => '11111111000191']));

        $this->assertFalse($this->existe($antiga));
        $this->assertSame(0, $this->db->table('entradas_fila')->count());
    }

    public function testEmailAbandonadoECnpjDeContaProtegidaEh409ENadaMuda(): void
    {
        $doEmail = $this->contaExistente('contato@vestebem.com', '93339970000105', 'Moda Azul');
        $doCnpj = $this->contaExistente('outra@exemplo.com', '11111111000191', 'Loja B', false);

        $this->assertConflito($this->corpo(['cnpj' => '11111111000191']), 'CNPJ');

        $this->assertTrue($this->existe($doEmail));
        $this->assertTrue($this->existe($doCnpj));
    }

    public function testEmailProtegidoECnpjAbandonadoEh409ComAMensagemDoEmail(): void
    {
        $doEmail = $this->contaExistente('contato@vestebem.com', '93339970000105', 'Moda Azul', false);
        $doCnpj = $this->contaExistente('outra@exemplo.com', '11111111000191', 'Loja B');

        $this->assertConflito($this->corpo(['cnpj' => '11111111000191']), 'e-mail');

        $this->assertTrue($this->existe($doEmail));
        $this->assertTrue($this->existe($doCnpj));
    }

    public function testEmailECnpjDeDuasContasAbandonadasSaoApagadas(): void
    {
        $doEmail = $this->contaExistente('contato@vestebem.com', '93339970000105', 'Moda Azul');
        $doCnpj = $this->contaExistente('outra@exemplo.com', '11111111000191', 'Loja B');

        $this->contas->cadastrar($this->corpo(['cnpj' => '11111111000191', 'nome' => 'Loja Nova']));

        $this->assertFalse($this->existe($doEmail));
        $this->assertFalse($this->existe($doCnpj));
        $this->assertSame(1, $this->db->table('contas')->count());
        $this->assertEqualsCanonicalizing(['loja-nova', 'veste-bem'], $this->db->table('estabelecimentos')->pluck('slug')->all());
    }

    public function testContaConfirmadaAntigaESemClientesNuncaESubstituida(): void
    {
        $antiga = $this->contaExistente('contato@vestebem.com', '93339970000105', 'Moda Azul');
        $this->db->table('contas')->update(['email_confirmado_em' => '2026-01-01 00:00:00']);
        $sessoes = $this->db->table('sessoes')->count();

        $this->assertConflito($this->corpo(['cnpj' => '11111111000191']), 'e-mail');
        $this->assertConflito($this->corpo(['email' => 'nova@exemplo.com']), 'CNPJ');

        $this->assertTrue($this->existe($antiga));
        $this->assertTrue($this->db->table('estabelecimentos')->where('slug', 'moda-azul')->exists());
        $this->assertSame($sessoes, $this->db->table('sessoes')->count());
    }

    public function testContaNaoConfirmadaSemPrazoNuncaESubstituida(): void
    {
        $antiga = $this->contaExistente('contato@vestebem.com', '93339970000105', 'Moda Azul');
        $this->db->table('contas')->update(['confirmar_ate' => null]);

        $this->assertConflito($this->corpo(['cnpj' => '11111111000191']), 'e-mail');

        $this->assertTrue($this->existe($antiga));
    }

    // --- Corrida e erros do banco no cadastro --------------------------------------------

    /** PDOException com o SQLSTATE em texto, como o PDO lança (o construtor só aceita código inteiro). */
    private static function erroDoBanco(string $sqlstate, string $mensagem): PDOException
    {
        return new class ($sqlstate, $mensagem) extends PDOException {
            public function __construct(string $sqlstate, string $mensagem)
            {
                parent::__construct($mensagem);
                $this->code = $sqlstate;
                $this->errorInfo = [$sqlstate, 0, $mensagem];
            }
        };
    }

    /**
     * Faz o próximo INSERT na tabela falhar com o erro dado. Com `$rival`, essa conta aparece no banco
     * logo depois (fora da transação), como se outro cadastro a tivesse gravado entre a busca e o INSERT.
     *
     * @param array<string, string>|null $rival
     */
    private function falharProximoInsert(string $tabela, string $sqlstate, string $mensagem, ?array $rival = null): void
    {
        $estado = ['falhou' => false, 'rival' => $rival];
        $this->db->getConnection()->beforeExecuting(
            function (string $sql, array $bindings, Connection $conexao) use (&$estado, $tabela, $sqlstate, $mensagem): void {
                if (!$estado['falhou'] && preg_match('/\Ainsert into [`"]' . $tabela . '[`"]/', $sql) === 1) {
                    $estado['falhou'] = true;

                    throw new QueryException($conexao->getName(), $sql, $bindings, self::erroDoBanco($sqlstate, $mensagem));
                }
                if ($estado['falhou'] && $estado['rival'] !== null && $conexao->transactionLevel() === 0) {
                    $rival = $estado['rival'];
                    $estado['rival'] = null;
                    $conexao->table('contas')->insert($rival);
                }
            }
        );
    }

    public function testContaCriadaEntreABuscaEOInsertEh409EmVezDe500(): void
    {
        $this->falharProximoInsert('contas', '23000', 'UNIQUE constraint failed: contas.email', [
            'email' => 'contato@vestebem.com', 'cnpj' => '11111111000191', 'senha_hash' => 'x', 'criado_em' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->assertConflito($this->corpo(), 'e-mail');

        $this->assertSame(['11111111000191'], $this->db->table('contas')->pluck('cnpj')->all(), 'só a conta do outro cadastro');
        $this->assertSame(1, $this->db->table('estabelecimentos')->count(), 'nenhuma loja nova');
        $this->assertSame(0, $this->db->table('sessoes')->count());
    }

    public function testErroDoBancoSemConflitoContinuaSubindo(): void
    {
        $this->falharProximoInsert('contas', 'HY000', 'disk I/O error');

        try {
            $this->contas->cadastrar($this->corpo());
            $this->fail('o erro do banco devia subir');
        } catch (QueryException $e) {
            $this->assertStringContainsString('disk I/O error', $e->getMessage());
        }
        $this->assertSame(0, $this->db->table('contas')->count());
    }

    public function testErroDoBancoNaCriacaoDaLojaNaoViraTentativaDeOutroSlug(): void
    {
        $this->falharProximoInsert('estabelecimentos', 'HY000', 'disk I/O error');

        $this->expectException(QueryException::class);
        $this->contas->cadastrar($this->corpo());
    }

    public function testDeadlockRefazATransacao(): void
    {
        $this->falharProximoInsert('contas', '40001', 'Deadlock found when trying to get lock; try restarting transaction');

        $resposta = $this->contas->cadastrar($this->corpo());

        $this->assertSame('contato@vestebem.com', $resposta['conta']['email']);
        $this->assertSame(1, $this->db->table('contas')->count());
    }
}
