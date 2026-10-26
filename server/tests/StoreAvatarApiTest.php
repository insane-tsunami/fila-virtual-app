<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;

/** Cenários do spec store-avatar: definir, voltar à inicial, validação, posse e o avatar nas respostas. */
final class StoreAvatarApiTest extends ApiTestCase
{
    private const MENSAGEM = 'Escolha uma cor e um ícone da lista.';

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = $this->cadastrarConta('Moda Azul', 'a@exemplo.com', '93339970000105')['token'];
    }

    private function definir(mixed $corpo, ?array $cabecalhos = null, string $slug = 'moda-azul'): ResponseInterface
    {
        return $this->chamar('PUT', "/api/filas/{$slug}/avatar", $corpo, $cabecalhos ?? $this->comSessao($this->token));
    }

    /** @return array{cor: string|null, icone: string|null} */
    private function avatarNoBanco(string $slug = 'moda-azul'): array
    {
        $loja = $this->db->table('estabelecimentos')->where('slug', $slug)->first();

        return ['cor' => $loja->avatar_cor, 'icone' => $loja->avatar_icone];
    }

    // --- Definir e voltar à inicial ---------------------------------------------------------

    public function testEscolherUmAvatarDevolve200EApareceNoLoginNaContaENaConsulta(): void
    {
        $resposta = $this->definir(['cor' => 'verde', 'icone' => 'restaurante']);

        $esperado = ['cor' => 'verde', 'icone' => 'restaurante'];
        $this->assertSame(200, $resposta->getStatusCode());
        $this->assertSame(
            ['nome' => 'Moda Azul', 'slug' => 'moda-azul', 'endereco_publico' => null, 'avatar' => $esperado],
            $this->json($resposta)
        );
        $login = $this->json($this->chamar('POST', '/api/sessoes', ['email' => 'a@exemplo.com', 'senha' => 'senha-segura-1']));
        $this->assertSame($esperado, $login['loja']['avatar']);
        $conta = $this->json($this->chamar('GET', '/api/conta', null, $this->comSessao($this->token)));
        $this->assertSame($esperado, $conta['loja']['avatar']);
        $this->assertSame($esperado, $this->json($this->chamar('GET', '/api/filas/moda-azul'))['avatar']);
    }

    public function testVoltarAInicialComAsDuasPartesNulas(): void
    {
        $this->definir(['cor' => 'azul', 'icone' => 'cafe']);

        $resposta = $this->definir(['cor' => null, 'icone' => null]);

        $this->assertSame(200, $resposta->getStatusCode());
        $this->assertNull($this->json($resposta)['avatar']);
        $this->assertSame(['cor' => null, 'icone' => null], $this->avatarNoBanco());
    }

    public function testDefinirOEnderecoDevolveOAvatarDaLoja(): void
    {
        $this->definir(['cor' => 'azul', 'icone' => 'cafe']);

        $resposta = $this->chamar(
            'PUT', '/api/filas/moda-azul/endereco', ['endereco_publico' => 'https://loja.exemplo.com'], $this->comSessao($this->token)
        );

        $this->assertSame(['cor' => 'azul', 'icone' => 'cafe'], $this->json($resposta)['avatar']);
    }

    public function testOutrasLojasNaoMudam(): void
    {
        $this->semearDemonstracao();

        $this->definir(['cor' => 'roxo', 'icone' => 'loja']);

        $this->assertSame(['cor' => null, 'icone' => null], $this->avatarNoBanco('veste-bem'));
    }

    // --- Validação ---------------------------------------------------------------------------

    /** @return array<string, array{mixed}> */
    public static function recusados(): array
    {
        return [
            'cor fora da lista' => [['cor' => 'rosa', 'icone' => 'cafe']],
            'ícone fora da lista' => [['cor' => 'azul', 'icone' => 'carro']],
            'maiúsculas' => [['cor' => 'Azul', 'icone' => 'CAFE']],
            'só a cor' => [['cor' => 'azul']],
            'só o ícone' => [['icone' => 'cafe']],
            'cor nula e ícone definido' => [['cor' => null, 'icone' => 'cafe']],
            'cor definida e ícone nulo' => [['cor' => 'azul', 'icone' => null]],
            'não é texto' => [['cor' => 1, 'icone' => true]],
            'corpo sem campos' => [[]],
            'corpo vazio' => [null],
        ];
    }

    #[DataProvider('recusados')]
    public function testCorposRecusadosDevolvem422EMantemOAvatarAnterior(mixed $corpo): void
    {
        $this->definir(['cor' => 'laranja', 'icone' => 'mercado']);

        $resposta = $this->definir($corpo);

        $this->assertSame(422, $resposta->getStatusCode());
        $this->assertSame(['erro' => self::MENSAGEM], $this->json($resposta));
        $this->assertSame(['cor' => 'laranja', 'icone' => 'mercado'], $this->avatarNoBanco());
    }

    // --- Sessão e posse da loja -----------------------------------------------------------------

    public function testSemSessaoOuComSessaoInvalidaDevolve401(): void
    {
        foreach ([[], ['Authorization' => 'Bearer errado']] as $cabecalhos) {
            $resposta = $this->definir(['cor' => 'azul', 'icone' => 'cafe'], $cabecalhos);

            $this->assertSame(401, $resposta->getStatusCode(), json_encode($cabecalhos));
        }
        $this->assertSame(['cor' => null, 'icone' => null], $this->avatarNoBanco());
    }

    public function testLojaDeOutraContaDevolve403SemMudarNada(): void
    {
        $outra = $this->cadastrarConta('Casa Verde', 'b@exemplo.com', '11111111000191')['token'];
        $this->definir(['cor' => 'verde', 'icone' => 'beleza'], $this->comSessao($outra), 'casa-verde');

        $resposta = $this->definir(['cor' => 'vermelho', 'icone' => 'farmacia'], null, 'casa-verde');

        $this->assertSame(403, $resposta->getStatusCode());
        $this->assertSame(['cor' => 'verde', 'icone' => 'beleza'], $this->avatarNoBanco('casa-verde'));
    }

    public function testLojaInexistenteDevolve404(): void
    {
        $resposta = $this->definir(['cor' => 'azul', 'icone' => 'cafe'], null, 'nao-existe');

        $this->assertSame(404, $resposta->getStatusCode());
    }
}
