<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;

/** Cenários do spec account-session: login, token, rotas protegidas, posse da loja e sair. */
final class SessionApiTest extends ApiTestCase
{
    private function login(string $email, string $senha): ResponseInterface
    {
        return $this->chamar('POST', '/api/sessoes', ['email' => $email, 'senha' => $senha]);
    }

    /** @return array{token: string, slug: string} */
    private function loja(string $nome, string $email, string $cnpj): array
    {
        $corpo = $this->cadastrarConta($nome, $email, $cnpj);

        return ['token' => $corpo['token'], 'slug' => $corpo['loja']['slug']];
    }

    // --- Login ------------------------------------------------------------------------------

    public function testLoginCorretoDevolve200ComTokenContaELoja(): void
    {
        $this->cadastrarConta('Moda Azul', 'a@exemplo.com', '93339970000105');

        $resposta = $this->login('a@exemplo.com', 'senha-segura-1');
        $corpo = $this->json($resposta);

        $this->assertSame(200, $resposta->getStatusCode());
        $this->assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $corpo['token']);
        $this->assertSame('a@exemplo.com', $corpo['conta']['email']);
        $this->assertSame('moda-azul', $corpo['loja']['slug']);
    }

    public function testEmailDoLoginNaoDiferenciaMaiusculas(): void
    {
        $this->cadastrarConta('Moda Azul', 'a@exemplo.com', '93339970000105');

        $this->assertSame(200, $this->login('A@Exemplo.COM', 'senha-segura-1')->getStatusCode());
    }

    public function testSenhaErradaEEmailDesconhecidoDao401ComAMesmaMensagemESemSessao(): void
    {
        $this->cadastrarConta('Moda Azul', 'a@exemplo.com', '93339970000105');
        $antes = $this->db->table('sessoes')->count();

        $erradas = [
            $this->login('a@exemplo.com', 'senha-errada-9'),
            $this->login('ninguem@exemplo.com', 'senha-segura-1'),
            $this->login('nao-e-um-email', 'senha-segura-1'),
        ];

        foreach ($erradas as $resposta) {
            $this->assertSame(401, $resposta->getStatusCode());
            $this->assertSame(['erro' => 'E-mail ou senha incorretos.'], $this->json($resposta));
        }
        $this->assertSame($antes, $this->db->table('sessoes')->count());
    }

    public function testLoginSemCamposEh422(): void
    {
        $this->assertSame(422, $this->chamar('POST', '/api/sessoes', [])->getStatusCode());
        $this->assertSame(422, $this->login('', 'x')->getStatusCode());
    }

    public function testLoginNaoExigeSessao(): void
    {
        $this->assertNotSame(401, $this->chamar('POST', '/api/sessoes', [])->getStatusCode());
    }

    public function testVariosAparelhosTemTokensDiferentesEAmbosValem(): void
    {
        $this->cadastrarConta('Moda Azul', 'a@exemplo.com', '93339970000105');

        $a = $this->json($this->login('a@exemplo.com', 'senha-segura-1'))['token'];
        $b = $this->json($this->login('a@exemplo.com', 'senha-segura-1'))['token'];

        $this->assertNotSame($a, $b);
        $this->assertSame(200, $this->chamar('GET', '/api/conta', null, $this->comSessao($a))->getStatusCode());
        $this->assertSame(200, $this->chamar('GET', '/api/conta', null, $this->comSessao($b))->getStatusCode());
    }

    // --- Token --------------------------------------------------------------------------------

    public function testBancoNaoGuardaOTokenSoOHash(): void
    {
        $token = $this->cadastrarConta('Moda Azul', 'a@exemplo.com', '93339970000105')['token'];

        $linhas = json_encode($this->db->table('sessoes')->get(), JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString($token, $linhas);
        $this->assertSame(1, $this->db->table('sessoes')->where('token_hash', hash('sha256', $token))->count());
    }

    public function testSessaoVence(): void
    {
        $token = $this->cadastrarConta('Moda Azul', 'a@exemplo.com', '93339970000105')['token'];
        $this->assertSame(200, $this->chamar('GET', '/api/conta', null, $this->comSessao($token))->getStatusCode());

        $this->db->table('sessoes')->update(['expira_em' => gmdate('Y-m-d H:i:s', time() - 1)]);

        $this->assertSame(401, $this->chamar('GET', '/api/conta', null, $this->comSessao($token))->getStatusCode());
    }

    public function testValidadeDeSeteDias(): void
    {
        $corpo = $this->cadastrarConta('Moda Azul', 'a@exemplo.com', '93339970000105');

        $this->assertEqualsWithDelta(time() + 7 * 86400, strtotime($corpo['expira_em'] . ' UTC'), 5);
    }

    // --- Rotas protegidas -----------------------------------------------------------------------

    /** @return array<string, array{string, string, mixed}> */
    public static function protegidas(): array
    {
        return [
            'dados da conta' => ['GET', '/api/conta', null],
            'trocar a senha' => ['PUT', '/api/conta/senha', ['senha_atual' => 'x', 'nova_senha' => 'y']],
            'sair' => ['DELETE', '/api/sessao', null],
            'listar a fila' => ['GET', '/api/filas/veste-bem/entradas', null],
            'finalizar' => ['POST', '/api/filas/veste-bem/entradas/abc/finalizar', null],
            'definir o endereço' => ['PUT', '/api/filas/veste-bem/endereco', ['endereco_publico' => 'https://x.exemplo.com']],
        ];
    }

    #[DataProvider('protegidas')]
    public function testRotaProtegidaSemSessaoOuComChaveAntigaEh401(string $metodo, string $uri, mixed $corpo): void
    {
        foreach ([[], ['X-API-Key' => 'chave-antiga'], ['Authorization' => 'Bearer desconhecido']] as $cabecalhos) {
            $resposta = $this->chamar($metodo, $uri, $corpo, $cabecalhos);

            $this->assertSame(401, $resposta->getStatusCode(), $uri . ' ' . json_encode($cabecalhos));
        }
    }

    public function testRotasPublicasFuncionamSemSessao(): void
    {
        $entrada = $this->chamar('POST', '/api/filas/veste-bem/entradas', ['telefone' => '11971778203']);
        $codigo = $this->json($entrada)['codigo'];

        $this->assertSame(201, $entrada->getStatusCode());
        $this->assertSame(200, $this->chamar('GET', "/api/filas/veste-bem/entradas/$codigo")->getStatusCode());
        $this->assertSame(200, $this->chamar('GET', '/api/filas/veste-bem')->getStatusCode());
    }

    // --- Só a loja da própria conta ----------------------------------------------------------------

    public function testDonaUsaAsRotasDaPropriaLoja(): void
    {
        $a = $this->loja('Moda Azul', 'a@exemplo.com', '93339970000105');
        $this->chamar('POST', "/api/filas/{$a['slug']}/entradas", ['telefone' => '11971778203']);

        $lista = $this->chamar('GET', "/api/filas/{$a['slug']}/entradas", null, $this->comSessao($a['token']));
        $endereco = $this->chamar('PUT', "/api/filas/{$a['slug']}/endereco", ['endereco_publico' => 'https://a.exemplo.com'], $this->comSessao($a['token']));

        $this->assertSame(200, $lista->getStatusCode());
        $this->assertCount(1, $this->json($lista));
        $this->assertSame(200, $endereco->getStatusCode());
    }

    public function testContaNaoListaNemFinalizaNemDefineEnderecoDaLojaDeOutra(): void
    {
        $a = $this->loja('Moda Azul', 'a@exemplo.com', '93339970000105');
        $b = $this->loja('Casa Verde', 'b@exemplo.com', '11111111000191');
        $entrada = $this->json($this->chamar('POST', "/api/filas/{$b['slug']}/entradas", ['telefone' => '11971778203']));
        $this->chamar('PUT', "/api/filas/{$b['slug']}/endereco", ['endereco_publico' => 'https://b.exemplo.com'], $this->comSessao($b['token']));

        $lista = $this->chamar('GET', "/api/filas/{$b['slug']}/entradas", null, $this->comSessao($a['token']));
        $final = $this->chamar('POST', "/api/filas/{$b['slug']}/entradas/{$entrada['codigo']}/finalizar", null, $this->comSessao($a['token']));
        $endereco = $this->chamar('PUT', "/api/filas/{$b['slug']}/endereco", ['endereco_publico' => 'https://invasor.exemplo.com'], $this->comSessao($a['token']));

        foreach ([$lista, $final, $endereco] as $resposta) {
            $this->assertSame(403, $resposta->getStatusCode());
            $this->assertSame(['erro' => 'Esta loja não pertence à sua conta.'], $this->json($resposta));
        }
        $this->assertStringNotContainsString('*****', (string) $lista->getBody());
        $this->assertSame('em_atendimento', $this->db->table('entradas_fila')->where('codigo', $entrada['codigo'])->value('status'), 'nada foi finalizado');
        $this->assertSame('https://b.exemplo.com', $this->db->table('estabelecimentos')->where('slug', $b['slug'])->value('endereco_publico'));
    }

    public function testLojaSemDonoEhNegadaATodasAsContas(): void
    {
        $a = $this->loja('Moda Azul', 'a@exemplo.com', '93339970000105');

        $resposta = $this->chamar('GET', '/api/filas/veste-bem/entradas', null, $this->comSessao($a['token']));

        $this->assertSame(403, $resposta->getStatusCode());
    }

    public function testSlugInexistenteComSessaoEh404(): void
    {
        $a = $this->loja('Moda Azul', 'a@exemplo.com', '93339970000105');

        foreach ([
            $this->chamar('GET', '/api/filas/nao-existe/entradas', null, $this->comSessao($a['token'])),
            $this->chamar('PUT', '/api/filas/nao-existe/endereco', ['endereco_publico' => null], $this->comSessao($a['token'])),
        ] as $resposta) {
            $this->assertSame(404, $resposta->getStatusCode());
        }
    }

    public function testCadaContaVeSoAPropriaFila(): void
    {
        $a = $this->loja('Moda Azul', 'a@exemplo.com', '93339970000105');
        $b = $this->loja('Casa Verde', 'b@exemplo.com', '11111111000191');
        $this->chamar('POST', "/api/filas/{$a['slug']}/entradas", ['telefone' => '11971778203']);
        $this->chamar('POST', "/api/filas/{$b['slug']}/entradas", ['telefone' => '11988887777']);

        $daA = $this->json($this->chamar('GET', "/api/filas/{$a['slug']}/entradas", null, $this->comSessao($a['token'])));
        $daB = $this->json($this->chamar('GET', "/api/filas/{$b['slug']}/entradas", null, $this->comSessao($b['token'])));

        $this->assertSame('*****8203', $daA[0]['telefone']);
        $this->assertSame('*****7777', $daB[0]['telefone']);
    }

    // --- Sair --------------------------------------------------------------------------------------------

    public function testSairEncerraSoAquelaSessao(): void
    {
        $this->cadastrarConta('Moda Azul', 'a@exemplo.com', '93339970000105');
        $a = $this->json($this->login('a@exemplo.com', 'senha-segura-1'))['token'];
        $b = $this->json($this->login('a@exemplo.com', 'senha-segura-1'))['token'];

        $resposta = $this->chamar('DELETE', '/api/sessao', null, $this->comSessao($a));

        $this->assertSame(204, $resposta->getStatusCode());
        $this->assertSame('', (string) $resposta->getBody());
        $this->assertSame(401, $this->chamar('GET', '/api/conta', null, $this->comSessao($a))->getStatusCode());
        $this->assertSame(200, $this->chamar('GET', '/api/conta', null, $this->comSessao($b))->getStatusCode());
    }
}
