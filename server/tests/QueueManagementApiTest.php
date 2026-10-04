<?php

declare(strict_types=1);

namespace Tests;

use Models\EntradaFila;

/** Cenários do spec queue-management, ponta a ponta pela API. */
final class QueueManagementApiTest extends ApiTestCase
{
    private const ENTRADAS = '/api/filas/veste-bem/entradas';

    /** @return list<array<string, mixed>> */
    private function listar(): array
    {
        $resposta = $this->chamar('GET', self::ENTRADAS, null, $this->comSessao());
        $this->assertSame(200, $resposta->getStatusCode());

        return $this->json($resposta);
    }

    private function finalizar(string $codigo, array $cabecalhos = null): \Psr\Http\Message\ResponseInterface
    {
        return $this->chamar('POST', self::ENTRADAS . "/$codigo/finalizar", null, $cabecalhos ?? $this->comSessao());
    }

    /** @return list<string> */
    private function statusNoBanco(): array
    {
        return EntradaFila::query()->orderBy('id')->pluck('status')->all();
    }

    // --- Chave de acesso provisória ------------------------------------------------

    public function testChaveCorretaListaAFila(): void
    {
        $this->entrarPelaApi(1);

        $resposta = $this->chamar('GET', self::ENTRADAS, null, $this->comSessao());

        $this->assertSame(200, $resposta->getStatusCode());
        $this->assertCount(1, $this->json($resposta));
    }

    public function testSessaoAusenteOuInvalidaDevolve401SemDadosENaoAlteraNada(): void
    {
        $entrada = $this->entrarPelaApi(1);
        $this->entrarPelaApi(2);
        $antes = $this->statusNoBanco();

        foreach ([
            [],
            ['Authorization' => 'Bearer token-que-nao-existe'],
            ['Authorization' => 'Bearer '],
            ['Authorization' => 'Basic abc'],
            ['Authorization' => $this->comSessao()['Authorization'] . ' x'],
            ['X-API-Key' => 'a-chave-antiga-nao-vale-mais'],
        ] as $cabecalhos) {
            $lista = $this->chamar('GET', self::ENTRADAS, null, $cabecalhos);
            $this->assertSame(401, $lista->getStatusCode(), json_encode($cabecalhos));
            $this->assertSame(['erro' => 'Autenticação ausente ou inválida.'], $this->json($lista));
            $this->assertStringNotContainsString('*****', (string) $lista->getBody());

            $final = $this->finalizar($entrada['codigo'], $cabecalhos);
            $this->assertSame(401, $final->getStatusCode(), json_encode($cabecalhos));
        }

        $this->assertSame($antes, $this->statusNoBanco(), 'nada pode mudar sem sessão');
    }

    public function testChamadasDoClienteNaoExigemSessao(): void
    {
        $entrada = $this->chamar('POST', self::ENTRADAS, ['telefone' => '11971778203']);
        $codigo = $this->json($entrada)['codigo'];
        $consulta = $this->chamar('GET', self::ENTRADAS . "/$codigo");

        $this->assertSame(201, $entrada->getStatusCode());
        $this->assertSame(200, $consulta->getStatusCode());
    }

    // --- Listagem ---------------------------------------------------------------------

    public function testListagemTraTresEntradasEmOrdemComTelefoneMascarado(): void
    {
        foreach (['(11) 97177-8203', '(11) 98888-7777', '(11) 95555-1234'] as $telefone) {
            $this->chamar('POST', self::ENTRADAS, ['telefone' => $telefone]);
        }

        $resposta = $this->chamar('GET', self::ENTRADAS, null, $this->comSessao());
        $lista = $this->json($resposta);

        $this->assertSame([1, 2, 3], array_column($lista, 'posicao'));
        $this->assertSame(['em_atendimento', 'aguardando', 'aguardando'], array_column($lista, 'status'));
        $this->assertSame(['*****8203', '*****7777', '*****1234'], array_column($lista, 'telefone'));
        $this->assertSame(['codigo', 'posicao', 'status', 'telefone'], array_keys($lista[0]));
        $this->assertStringNotContainsString('5511971', (string) $resposta->getBody());
        $this->assertStringNotContainsString('97177', (string) $resposta->getBody());
    }

    public function testListagemDeFilaVaziaDevolveListaVaziaEm200(): void
    {
        $resposta = $this->chamar('GET', self::ENTRADAS, null, $this->comSessao());

        $this->assertSame(200, $resposta->getStatusCode());
        $this->assertSame('[]', (string) $resposta->getBody());
    }

    public function testEntradasFinalizadasNaoAparecemNaListagem(): void
    {
        $primeiro = $this->entrarPelaApi(1);
        $segundo = $this->entrarPelaApi(2);
        $this->finalizar($primeiro['codigo']);

        $this->assertSame([$segundo['codigo']], array_column($this->listar(), 'codigo'));
    }

    public function testListagemDeEstabelecimentoInexistenteDevolve404(): void
    {
        $resposta = $this->chamar('GET', '/api/filas/nao-existe/entradas', null, $this->comSessao());

        $this->assertSame(404, $resposta->getStatusCode());
    }

    // --- Finalizar ----------------------------------------------------------------------

    public function testFinalizarComAguardandoPromoveOProximoEMantemAOrdem(): void
    {
        $a = $this->entrarPelaApi(1);
        $b = $this->entrarPelaApi(2);
        $c = $this->entrarPelaApi(3);

        $resposta = $this->finalizar($a['codigo']);
        $corpo = $this->json($resposta);

        $this->assertSame(200, $resposta->getStatusCode());
        $this->assertSame('finalizado', $corpo['finalizada']['status']);
        $this->assertNull($corpo['finalizada']['posicao']);
        $this->assertSame($b['codigo'], $corpo['atual']['codigo']);
        $this->assertSame('em_atendimento', $corpo['atual']['status']);
        $this->assertSame(1, $corpo['atual']['posicao']);
        $this->assertSame([$b['codigo'], $c['codigo']], array_column($this->listar(), 'codigo'));
        $this->assertSame([1, 2], array_column($this->listar(), 'posicao'));
    }

    public function testFinalizarOUltimoDevolveAtualNuloEFilaVazia(): void
    {
        $unico = $this->entrarPelaApi(1);

        $resposta = $this->finalizar($unico['codigo']);

        $this->assertSame(200, $resposta->getStatusCode());
        $this->assertNull($this->json($resposta)['atual']);
        $this->assertArrayHasKey('atual', $this->json($resposta), 'a chave existe e vale null');
        $this->assertSame([], $this->listar());
    }

    public function testFinalizarEntradaAguardandoDevolve409ENadaMuda(): void
    {
        $this->entrarPelaApi(1);
        $segundo = $this->entrarPelaApi(2);
        $antes = $this->statusNoBanco();

        $resposta = $this->finalizar($segundo['codigo']);

        $this->assertSame(409, $resposta->getStatusCode());
        $this->assertArrayHasKey('erro', $this->json($resposta));
        $this->assertSame($antes, $this->statusNoBanco());
    }

    public function testFinalizacaoRepetidaComoNoDuploCliqueFinalizaSoUmAtendimento(): void
    {
        $a = $this->entrarPelaApi(1);
        $b = $this->entrarPelaApi(2);
        $this->entrarPelaApi(3);

        $primeira = $this->finalizar($a['codigo']);
        $segunda = $this->finalizar($a['codigo']);

        $this->assertSame(200, $primeira->getStatusCode());
        $this->assertSame(409, $segunda->getStatusCode());
        $this->assertSame(['finalizado', 'em_atendimento', 'aguardando'], $this->statusNoBanco());
        $this->assertSame(
            'em_atendimento',
            $this->json($this->chamar('GET', self::ENTRADAS . '/' . $b['codigo']))['status'],
            'o próximo cliente não pode ser finalizado por engano'
        );
    }

    public function testFinalizarCodigoInexistenteDevolve404(): void
    {
        $this->entrarPelaApi(1);

        $resposta = $this->finalizar('inexistente');

        $this->assertSame(404, $resposta->getStatusCode());
        $this->assertArrayHasKey('erro', $this->json($resposta));
    }
}
