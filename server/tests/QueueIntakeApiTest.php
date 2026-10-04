<?php

declare(strict_types=1);

namespace Tests;

use Models\EntradaFila;

/** Cenários do spec queue-intake, ponta a ponta pela API. */
final class QueueIntakeApiTest extends ApiTestCase
{
    private const ENTRADAS = '/api/filas/veste-bem/entradas';

    private function finalizarPelaApi(string $codigo): void
    {
        $resposta = $this->chamar('POST', self::ENTRADAS . "/$codigo/finalizar", null, $this->comSessao());
        $this->assertSame(200, $resposta->getStatusCode());
    }

    // --- Entrada na fila ---------------------------------------------------------

    public function testEntrarEmFilaComClientesDevolve201NaTerceiraPosicao(): void
    {
        $this->entrarPelaApi(1);
        $this->entrarPelaApi(2);

        $resposta = $this->chamar('POST', self::ENTRADAS, ['telefone' => '(11) 95555-1234']);
        $corpo = $this->json($resposta);

        $this->assertSame(201, $resposta->getStatusCode());
        $this->assertSame(3, $corpo['posicao']);
        $this->assertSame('aguardando', $corpo['status']);
        $this->assertNotEmpty($corpo['codigo']);
        $this->assertSame(['codigo', 'posicao', 'status'], array_keys($corpo));
    }

    public function testEntrarEmFilaVaziaDevolve201EmAtendimento(): void
    {
        $resposta = $this->chamar('POST', self::ENTRADAS, ['telefone' => '(11) 97177-8203']);
        $corpo = $this->json($resposta);

        $this->assertSame(201, $resposta->getStatusCode());
        $this->assertSame(1, $corpo['posicao']);
        $this->assertSame('em_atendimento', $corpo['status']);
    }

    public function testEstabelecimentoInexistenteDevolve404ENaoCriaEntrada(): void
    {
        $resposta = $this->chamar('POST', '/api/filas/nao-existe/entradas', ['telefone' => '11971778203']);

        $this->assertSame(404, $resposta->getStatusCode());
        $this->assertArrayHasKey('erro', $this->json($resposta));
        $this->assertSame(0, EntradaFila::query()->count());
    }

    // --- Telefone ------------------------------------------------------------------

    public function testTelefoneFormatadoSemDdiEGravadoComDdi(): void
    {
        $this->chamar('POST', self::ENTRADAS, ['telefone' => '(11) 97177-8203']);

        $this->assertSame('5511971778203', EntradaFila::query()->first()->telefone);
    }

    public function testTelefoneComDdiEGravadoSemDuplicarODdi(): void
    {
        $this->chamar('POST', self::ENTRADAS, ['telefone' => '+55 11 97177-8203']);

        $this->assertSame('5511971778203', EntradaFila::query()->first()->telefone);
    }

    public function testTelefoneInvalidoOuAusenteDevolve422ENaoCriaEntrada(): void
    {
        foreach ([['telefone' => '123'], ['telefone' => 'sem digitos'], [], ['outro' => 'campo']] as $corpo) {
            $resposta = $this->chamar('POST', self::ENTRADAS, $corpo);

            $this->assertSame(422, $resposta->getStatusCode(), json_encode($corpo));
            $this->assertNotSame('', $this->json($resposta)['erro']);
        }

        $this->assertSame(0, EntradaFila::query()->count());
    }

    // --- Duplicidade ------------------------------------------------------------------

    public function testMesmoTelefoneDevolve200ComAMesmaEntradaSemDuplicar(): void
    {
        $primeira = $this->json($this->chamar('POST', self::ENTRADAS, ['telefone' => '(11) 97177-8203']));
        $this->entrarPelaApi(2);

        $resposta = $this->chamar('POST', self::ENTRADAS, ['telefone' => '+55 11 97177-8203']);
        $repetida = $this->json($resposta);

        $this->assertSame(200, $resposta->getStatusCode());
        $this->assertSame($primeira['codigo'], $repetida['codigo']);
        $this->assertSame($primeira['posicao'], $repetida['posicao']);
        $this->assertSame(1, EntradaFila::query()->where('telefone', '5511971778203')->count());
    }

    public function testTelefoneDeAtendimentoFinalizadoCriaNovaEntradaCom201(): void
    {
        $antiga = $this->entrarPelaApi(1);
        $this->finalizarPelaApi($antiga['codigo']);

        $resposta = $this->chamar('POST', self::ENTRADAS, ['telefone' => '(11) 90001-0001']);

        $this->assertSame(201, $resposta->getStatusCode());
        $this->assertNotSame($antiga['codigo'], $this->json($resposta)['codigo']);
    }

    // --- Consulta da própria posição ----------------------------------------------------

    public function testPosicaoAvancaQuandoOPrimeiroEFinalizado(): void
    {
        $primeiro = $this->entrarPelaApi(1);
        $this->entrarPelaApi(2);
        $terceiro = $this->entrarPelaApi(3);
        $this->assertSame(3, $this->json($this->chamar('GET', self::ENTRADAS . '/' . $terceiro['codigo']))['posicao']);

        $this->finalizarPelaApi($primeiro['codigo']);

        $resposta = $this->chamar('GET', self::ENTRADAS . '/' . $terceiro['codigo']);
        $this->assertSame(200, $resposta->getStatusCode());
        $this->assertSame(2, $this->json($resposta)['posicao']);
    }

    public function testEntradaFinalizadaTemStatusFinalizadoEPosicaoNula(): void
    {
        $primeiro = $this->entrarPelaApi(1);
        $this->finalizarPelaApi($primeiro['codigo']);

        $corpo = $this->json($this->chamar('GET', self::ENTRADAS . '/' . $primeiro['codigo']));

        $this->assertSame('finalizado', $corpo['status']);
        $this->assertNull($corpo['posicao']);
        $this->assertArrayHasKey('posicao', $corpo, 'a chave existe e vale null');
    }

    public function testCodigoInexistenteOuDeOutroEstabelecimentoDevolve404(): void
    {
        $entrada = $this->entrarPelaApi(1);
        $this->criarEstabelecimento('outra-loja');

        $this->assertSame(404, $this->chamar('GET', self::ENTRADAS . '/inexistente')->getStatusCode());
        $this->assertSame(
            404,
            $this->chamar('GET', '/api/filas/outra-loja/entradas/' . $entrada['codigo'])->getStatusCode()
        );
    }

    public function testConsultaNaoTrazOTelefoneNemInteiroNemMascarado(): void
    {
        $entrada = $this->chamar('POST', self::ENTRADAS, ['telefone' => '(11) 97177-8203']);
        $codigo = $this->json($entrada)['codigo'];

        $resposta = $this->chamar('GET', self::ENTRADAS . '/' . $codigo);
        $bruto = (string) $resposta->getBody();

        $this->assertArrayNotHasKey('telefone', $this->json($resposta));
        $this->assertStringNotContainsString('8203', $bruto);
        $this->assertStringNotContainsString('*****', $bruto);
    }

    public function testConsultaFuncionaSemAChaveDeApi(): void
    {
        $entrada = $this->entrarPelaApi(1);

        $resposta = $this->chamar('GET', self::ENTRADAS . '/' . $entrada['codigo']);

        $this->assertSame(200, $resposta->getStatusCode());
    }

    // --- Código imprevisível -----------------------------------------------------------

    public function testCodigosDeEntradasConsecutivasSaoLongosAleatoriosENaoDedutiveis(): void
    {
        $a = $this->entrarPelaApi(1)['codigo'];
        $b = $this->entrarPelaApi(2)['codigo'];

        foreach ([$a, $b] as $codigo) {
            $this->assertGreaterThanOrEqual(16, strlen($codigo));
            $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $codigo);
        }
        $this->assertNotSame($a, $b);
        $this->assertNotSame(hexdec(substr($a, 0, 8)) + 1, hexdec(substr($b, 0, 8)), 'não pode ser sequencial');
    }
}
