<?php

declare(strict_types=1);

namespace Tests;

use Psr\Http\Message\ResponseInterface;

/** Cenários do spec store-address e os cenários novos da chave em queue-management. */
final class StoreAddressApiTest extends ApiTestCase
{
    private const LOJA = '/api/filas/veste-bem';
    private const ENDERECO = '/api/filas/veste-bem/endereco';

    private function definir(mixed $corpo, ?array $cabecalhos = null, array $config = []): ResponseInterface
    {
        return $this->chamar('PUT', self::ENDERECO, $corpo, $cabecalhos ?? $this->comChave(), $config);
    }

    private function enderecoNoBanco(): ?string
    {
        return $this->db->table('estabelecimentos')->where('slug', 'veste-bem')->value('endereco_publico');
    }

    // --- Dados públicos da loja -----------------------------------------------------

    public function testLojaSemEnderecoDevolveEnderecoNulo(): void
    {
        $resposta = $this->chamar('GET', self::LOJA);

        $this->assertSame(200, $resposta->getStatusCode());
        $this->assertSame(
            ['nome' => 'Veste Bem', 'slug' => 'veste-bem', 'endereco_publico' => null],
            $this->json($resposta)
        );
        $this->assertStringContainsString('"endereco_publico":null', (string) $resposta->getBody());
    }

    public function testLojaComEnderecoDevolveOEndereco(): void
    {
        $this->db->table('estabelecimentos')->where('slug', 'veste-bem')
            ->update(['endereco_publico' => 'https://loja.exemplo.com']);

        $corpo = $this->json($this->chamar('GET', self::LOJA));

        $this->assertSame('https://loja.exemplo.com', $corpo['endereco_publico']);
    }

    public function testLojaInexistenteDevolve404(): void
    {
        $this->assertSame(404, $this->chamar('GET', '/api/filas/nao-existe')->getStatusCode());
    }

    public function testConsultaFuncionaSemChaveMesmoComChaveNaoConfigurada(): void
    {
        $resposta = $this->chamar('GET', self::LOJA, null, [], ['api_key' => '']);

        $this->assertSame(200, $resposta->getStatusCode());
    }

    // --- Definir o endereço ---------------------------------------------------------

    public function testDefinirUmEnderecoValidoDevolve200ERefleteNaConsulta(): void
    {
        $resposta = $this->definir(['endereco_publico' => 'https://loja.exemplo.com']);

        $this->assertSame(200, $resposta->getStatusCode());
        $this->assertSame(
            ['nome' => 'Veste Bem', 'slug' => 'veste-bem', 'endereco_publico' => 'https://loja.exemplo.com'],
            $this->json($resposta)
        );
        $this->assertSame(
            'https://loja.exemplo.com',
            $this->json($this->chamar('GET', self::LOJA))['endereco_publico']
        );
    }

    public function testBarraFinalEhRemovidaNoEnderecoGravadoEDevolvido(): void
    {
        $resposta = $this->definir(['endereco_publico' => 'https://loja.exemplo.com/']);

        $this->assertSame('https://loja.exemplo.com', $this->json($resposta)['endereco_publico']);
        $this->assertSame('https://loja.exemplo.com', $this->enderecoNoBanco());
    }

    public function testLimparComNuloOuTextoVazioDevolveNuloEVoltaAoPadrao(): void
    {
        foreach ([null, ''] as $limpar) {
            $this->definir(['endereco_publico' => 'https://loja.exemplo.com']);
            $this->assertNotNull($this->enderecoNoBanco());

            $resposta = $this->definir(['endereco_publico' => $limpar]);

            $this->assertSame(200, $resposta->getStatusCode());
            $this->assertNull($this->json($resposta)['endereco_publico']);
            $this->assertNull($this->enderecoNoBanco(), 'valor ' . var_export($limpar, true));
        }
    }

    public function testDefinirNaLojaInexistenteDevolve404(): void
    {
        $resposta = $this->chamar(
            'PUT', '/api/filas/nao-existe/endereco', ['endereco_publico' => 'https://loja.exemplo.com'], $this->comChave()
        );

        $this->assertSame(404, $resposta->getStatusCode());
    }

    // --- Validação ------------------------------------------------------------------

    public function testOrigensAceitasSaoGravadasComoEnviadas(): void
    {
        foreach (['http://localhost:3000', 'https://loja.exemplo.com:8443'] as $origem) {
            $resposta = $this->definir(['endereco_publico' => $origem]);

            $this->assertSame(200, $resposta->getStatusCode(), $origem);
            $this->assertSame($origem, $this->enderecoNoBanco());
        }
    }

    /** @return array<string, array{mixed}> */
    public static function recusados(): array
    {
        return [
            'ftp' => [['endereco_publico' => 'ftp://loja.exemplo.com']],
            'sem esquema' => [['endereco_publico' => 'loja.exemplo.com']],
            'javascript' => [['endereco_publico' => 'javascript:alert(1)']],
            'credenciais' => [['endereco_publico' => 'https://usuario:senha@loja.exemplo.com']],
            'caminho' => [['endereco_publico' => 'https://loja.exemplo.com/caminho']],
            'query' => [['endereco_publico' => 'https://loja.exemplo.com?a=1']],
            'fragmento' => [['endereco_publico' => 'https://loja.exemplo.com#x']],
            'mais de 255 caracteres' => [['endereco_publico' => 'https://' . str_repeat('a', 260) . '.com']],
            'número' => [['endereco_publico' => 123]],
            'booleano' => [['endereco_publico' => true]],
            'lista' => [['endereco_publico' => ['https://loja.exemplo.com']]],
            'campo ausente' => [['outro' => 'campo']],
            'corpo vazio' => [[]],
        ];
    }

    /** @param array<mixed> $corpo */
    #[\PHPUnit\Framework\Attributes\DataProvider('recusados')]
    public function testValoresRecusadosDevolvem422EMantemOEnderecoAnterior(array $corpo): void
    {
        $this->definir(['endereco_publico' => 'https://anterior.exemplo.com']);

        $resposta = $this->definir($corpo);

        $this->assertSame(422, $resposta->getStatusCode());
        $this->assertNotSame('', $this->json($resposta)['erro']);
        $this->assertSame('https://anterior.exemplo.com', $this->enderecoNoBanco());
    }

    public function testCorpoQueNaoEJsonDevolve400AindaComAChave(): void
    {
        $resposta = $this->definir('{endereco_publico: ');

        $this->assertSame(400, $resposta->getStatusCode());
    }

    // --- Chave provisória (queue-management) ------------------------------------------

    public function testDefinirSemChaveOuComChaveErradaDevolve401SemAlterarOEndereco(): void
    {
        $this->definir(['endereco_publico' => 'https://anterior.exemplo.com']);

        foreach ([[], ['X-API-Key' => 'errada'], ['X-API-Key' => '']] as $cabecalhos) {
            $resposta = $this->definir(['endereco_publico' => 'https://invasor.exemplo.com'], $cabecalhos);

            $this->assertSame(401, $resposta->getStatusCode(), json_encode($cabecalhos));
            $this->assertSame('https://anterior.exemplo.com', $this->enderecoNoBanco());
        }
    }

    public function testDefinirComChaveNaoConfiguradaNoServidorDevolve401(): void
    {
        foreach (['', self::CHAVE, 'qualquer'] as $enviada) {
            $cabecalhos = $enviada === '' ? [] : ['X-API-Key' => $enviada];
            $resposta = $this->definir(['endereco_publico' => 'https://loja.exemplo.com'], $cabecalhos, ['api_key' => '']);

            $this->assertSame(401, $resposta->getStatusCode(), "chave enviada: '$enviada'");
        }
        $this->assertNull($this->enderecoNoBanco());
    }
}
