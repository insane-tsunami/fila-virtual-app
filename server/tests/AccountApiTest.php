<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;

/** Cenários do spec establishment-account: cadastro, validação, unicidade, dados e troca de senha. */
final class AccountApiTest extends ApiTestCase
{
    /** @return array<string, string> */
    private function corpo(array $mudancas = []): array
    {
        return array_merge([
            'email' => 'contato@modaazul.com',
            'cnpj' => '93.339.970/0001-05',
            'nome' => 'Moda Azul',
            'senha' => 'senha-segura-1',
        ], $mudancas);
    }

    private function contas(): int
    {
        return $this->db->table('contas')->count();
    }

    // --- Cadastro -----------------------------------------------------------------------

    public function testCadastroValidoDevolve201ComTokenContaELoja(): void
    {
        $resposta = $this->chamar('POST', '/api/contas', $this->corpo());
        $corpo = $this->json($resposta);

        $this->assertSame(201, $resposta->getStatusCode());
        $this->assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $corpo['token']);
        $this->assertArrayHasKey('expira_em', $corpo);
        $this->assertSame(['email' => 'contato@modaazul.com', 'cnpj' => '93339970000105'], $corpo['conta']);
        $this->assertSame(
            ['nome' => 'Moda Azul', 'slug' => 'moda-azul', 'endereco_publico' => null],
            $corpo['loja']
        );
        $this->assertSame(1, $this->contas());
    }

    public function testOTokenDoCadastroJaAbreAsRotasProtegidas(): void
    {
        $token = $this->cadastrarConta('Moda Azul', 'a@exemplo.com', '93339970000105')['token'];

        $conta = $this->chamar('GET', '/api/conta', null, $this->comSessao($token));
        $fila = $this->chamar('GET', '/api/filas/moda-azul/entradas', null, $this->comSessao($token));

        $this->assertSame(200, $conta->getStatusCode());
        $this->assertSame(200, $fila->getStatusCode());
        $this->assertSame([], $this->json($fila));
    }

    public function testCadastroNaoExigeSessao(): void
    {
        $resposta = $this->chamar('POST', '/api/contas', $this->corpo());

        $this->assertNotSame(401, $resposta->getStatusCode());
    }

    public function testCorpoQueNaoEJsonEh400(): void
    {
        $resposta = $this->chamar('POST', '/api/contas', '{não é json');

        $this->assertSame(400, $resposta->getStatusCode());
        $this->assertSame(0, $this->contas());
    }

    public function testLojaNovaComecaSemEnderecoEAParteDaConsultaPublica(): void
    {
        $this->cadastrarConta('Moda Azul', 'a@exemplo.com', '93339970000105');

        $publico = $this->json($this->chamar('GET', '/api/filas/moda-azul'));

        $this->assertSame(['nome' => 'Moda Azul', 'slug' => 'moda-azul', 'endereco_publico' => null], $publico);
    }

    // --- Validação ------------------------------------------------------------------------

    public function testCnpjComMascaraEEmailEmMaiusculasSaoNormalizados(): void
    {
        $corpo = $this->json($this->chamar('POST', '/api/contas', $this->corpo(['email' => ' Contato@ModaAzul.COM '])));

        $this->assertSame('contato@modaazul.com', $corpo['conta']['email']);
        $this->assertSame('93339970000105', $corpo['conta']['cnpj']);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidos(): array
    {
        return [
            'e-mail inválido' => [['email' => 'sem-arroba'], 'E-mail'],
            'cnpj com 13 dígitos' => [['cnpj' => '9333997000010'], 'CNPJ'],
            'nome curto' => [['nome' => 'A'], 'Nome do estabelecimento'],
            'nome só de símbolos' => [['nome' => '!!!'], 'letras ou números'],
            'senha curta' => [['senha' => '1234567'], 'Senha'],
            'senha com mais de 72 bytes' => [['senha' => str_repeat('a', 73)], 'Senha'],
        ];
    }

    /** @param array<string, mixed> $mudancas */
    #[DataProvider('invalidos')]
    public function testCampoInvalidoEh422ComAMensagemDoCampoENadaEhCriado(array $mudancas, string $trecho): void
    {
        $resposta = $this->chamar('POST', '/api/contas', $this->corpo($mudancas));

        $this->assertSame(422, $resposta->getStatusCode());
        $this->assertStringContainsString($trecho, $this->json($resposta)['erro']);
        $this->assertSame(0, $this->contas());
        $this->assertSame(0, $this->db->table('sessoes')->count());
        $this->assertSame(1, $this->db->table('estabelecimentos')->count());
    }

    /** @return array<string, array{string}> */
    public static function campos(): array
    {
        return ['email' => ['email'], 'cnpj' => ['cnpj'], 'nome' => ['nome'], 'senha' => ['senha']];
    }

    #[DataProvider('campos')]
    public function testCampoAusenteEh422(string $campo): void
    {
        $corpo = $this->corpo();
        unset($corpo[$campo]);

        $resposta = $this->chamar('POST', '/api/contas', $corpo);

        $this->assertSame(422, $resposta->getStatusCode());
        $this->assertSame(0, $this->contas());
    }

    // --- Unicidade ------------------------------------------------------------------------

    public function testEmailJaCadastradoEh409MesmoComOutraCaixa(): void
    {
        $this->chamar('POST', '/api/contas', $this->corpo());

        $resposta = $this->chamar('POST', '/api/contas', $this->corpo(['email' => 'CONTATO@modaazul.com', 'cnpj' => '11.111.111/0001-91']));

        $this->assertSame(409, $resposta->getStatusCode());
        $this->assertSame(1, $this->contas());
    }

    public function testCnpjJaCadastradoEh409ComOuSemMascara(): void
    {
        $this->chamar('POST', '/api/contas', $this->corpo());

        $resposta = $this->chamar('POST', '/api/contas', $this->corpo(['email' => 'outro@exemplo.com', 'cnpj' => '93339970000105']));

        $this->assertSame(409, $resposta->getStatusCode());
        $this->assertSame(1, $this->contas());
    }

    // --- Slug ------------------------------------------------------------------------------

    public function testSlugGeradoDoNomeComAcentosESimbolos(): void
    {
        $corpo = $this->json($this->chamar('POST', '/api/contas', $this->corpo(['nome' => 'Moda & Cia São João'])));

        $this->assertSame('moda-cia-sao-joao', $corpo['loja']['slug']);
    }

    public function testNomesRepetidosGanhamSufixoInclusiveAoDaLojaDePartida(): void
    {
        $a = $this->cadastrarConta('Veste Bem', 'a@exemplo.com', '93339970000105');
        $b = $this->cadastrarConta('Veste Bem', 'b@exemplo.com', '11111111000191');
        $c = $this->cadastrarConta('Veste Bem', 'c@exemplo.com', '22222222000191');

        $this->assertSame('veste-bem-2', $a['loja']['slug']);
        $this->assertSame('veste-bem-3', $b['loja']['slug']);
        $this->assertSame('veste-bem-4', $c['loja']['slug']);
    }

    // --- Senha nunca em claro --------------------------------------------------------------

    public function testSenhaSoExisteComoHashENuncaApareceNasRespostas(): void
    {
        $r = $this->chamar('POST', '/api/contas', $this->corpo(['senha' => 'senha-bem-secreta']));
        $token = $this->json($r)['token'];
        $conta = $this->chamar('GET', '/api/conta', null, $this->comSessao($token));

        $hash = (string) $this->db->table('contas')->value('senha_hash');
        $this->assertStringNotContainsString('senha-bem-secreta', $hash);
        $this->assertTrue(password_verify('senha-bem-secreta', $hash));
        foreach ([$r, $conta] as $resposta) {
            $texto = (string) $resposta->getBody();
            $this->assertStringNotContainsString('senha-bem-secreta', $texto);
            $this->assertStringNotContainsString('$2y$', $texto);
            $this->assertStringNotContainsString('senha_hash', $texto);
        }
    }

    // --- Dados da conta --------------------------------------------------------------------

    public function testContaLogadaConsultaOsProprioDados(): void
    {
        $token = $this->cadastrarConta('Moda Azul', 'a@exemplo.com', '93.339.970/0001-05')['token'];

        $resposta = $this->chamar('GET', '/api/conta', null, $this->comSessao($token));

        $this->assertSame(200, $resposta->getStatusCode());
        $this->assertSame([
            'conta' => ['email' => 'a@exemplo.com', 'cnpj' => '93339970000105'],
            'loja' => ['nome' => 'Moda Azul', 'slug' => 'moda-azul', 'endereco_publico' => null],
        ], $this->json($resposta));
    }

    public function testDadosDaContaSemSessaoEh401(): void
    {
        $this->assertSame(401, $this->chamar('GET', '/api/conta')->getStatusCode());
    }

    public function testEnderecoDefinidoApareceNosDadosDaConta(): void
    {
        $token = $this->cadastrarConta('Moda Azul', 'a@exemplo.com', '93339970000105')['token'];
        $this->chamar('PUT', '/api/filas/moda-azul/endereco', ['endereco_publico' => 'https://loja.exemplo.com'], $this->comSessao($token));

        $corpo = $this->json($this->chamar('GET', '/api/conta', null, $this->comSessao($token)));

        $this->assertSame('https://loja.exemplo.com', $corpo['loja']['endereco_publico']);
    }

    // --- Trocar a senha --------------------------------------------------------------------

    private function trocar(string $token, mixed $corpo): \Psr\Http\Message\ResponseInterface
    {
        return $this->chamar('PUT', '/api/conta/senha', $corpo, $this->comSessao($token));
    }

    private function login(string $email, string $senha): \Psr\Http\Message\ResponseInterface
    {
        return $this->chamar('POST', '/api/sessoes', ['email' => $email, 'senha' => $senha]);
    }

    public function testTrocaValidaFazOLoginSeguinteSoFuncionarComANovaSenha(): void
    {
        $token = $this->cadastrarConta('Moda Azul', 'a@exemplo.com', '93339970000105', 'senha-antiga-1')['token'];

        $resposta = $this->trocar($token, ['senha_atual' => 'senha-antiga-1', 'nova_senha' => 'senha-nova-22']);

        $this->assertSame(200, $resposta->getStatusCode());
        $this->assertSame(200, $this->login('a@exemplo.com', 'senha-nova-22')->getStatusCode());
        $this->assertSame(401, $this->login('a@exemplo.com', 'senha-antiga-1')->getStatusCode());
    }

    public function testSenhaAtualErradaEh422NaoEh401ESenhaNaoMuda(): void
    {
        $token = $this->cadastrarConta('Moda Azul', 'a@exemplo.com', '93339970000105', 'senha-antiga-1')['token'];

        $resposta = $this->trocar($token, ['senha_atual' => 'errada-errada', 'nova_senha' => 'senha-nova-22']);

        $this->assertSame(422, $resposta->getStatusCode());
        $this->assertSame(['erro' => 'Senha atual incorreta.'], $this->json($resposta));
        $this->assertSame(200, $this->login('a@exemplo.com', 'senha-antiga-1')->getStatusCode());
        $this->assertSame(200, $this->chamar('GET', '/api/conta', null, $this->comSessao($token))->getStatusCode(), 'a sessão segue válida');
    }

    public function testNovaSenhaInvalidaEh422(): void
    {
        $token = $this->cadastrarConta('Moda Azul', 'a@exemplo.com', '93339970000105', 'senha-antiga-1')['token'];

        foreach (['curta', str_repeat('a', 73)] as $nova) {
            $resposta = $this->trocar($token, ['senha_atual' => 'senha-antiga-1', 'nova_senha' => $nova]);
            $this->assertSame(422, $resposta->getStatusCode());
        }
        $this->assertSame(200, $this->login('a@exemplo.com', 'senha-antiga-1')->getStatusCode());
    }

    public function testCamposAusentesNaTrocaSao422(): void
    {
        $token = $this->cadastrarConta('Moda Azul', 'a@exemplo.com', '93339970000105')['token'];

        $this->assertSame(422, $this->trocar($token, ['nova_senha' => 'senha-nova-22'])->getStatusCode());
        $this->assertSame(422, $this->trocar($token, [])->getStatusCode());
    }

    public function testTrocaSemSessaoEh401(): void
    {
        $resposta = $this->chamar('PUT', '/api/conta/senha', ['senha_atual' => 'x', 'nova_senha' => 'y']);

        $this->assertSame(401, $resposta->getStatusCode());
    }

    public function testTrocaEncerraAsOutrasSessoesEMantemAQueTrocou(): void
    {
        $token = $this->cadastrarConta('Moda Azul', 'a@exemplo.com', '93339970000105', 'senha-antiga-1')['token'];
        $outra = $this->json($this->login('a@exemplo.com', 'senha-antiga-1'))['token'];
        $this->assertSame(200, $this->chamar('GET', '/api/conta', null, $this->comSessao($outra))->getStatusCode());

        $this->trocar($token, ['senha_atual' => 'senha-antiga-1', 'nova_senha' => 'senha-nova-22']);

        $this->assertSame(200, $this->chamar('GET', '/api/conta', null, $this->comSessao($token))->getStatusCode());
        $this->assertSame(401, $this->chamar('GET', '/api/conta', null, $this->comSessao($outra))->getStatusCode());
    }
}
