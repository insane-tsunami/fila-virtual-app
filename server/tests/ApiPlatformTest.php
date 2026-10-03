<?php

declare(strict_types=1);

namespace Tests;

use Models\EntradaFila;

final class ApiPlatformTest extends ApiTestCase
{
    private const ENTRADAS = '/api/filas/veste-bem/entradas';

    // --- Respostas e erros em JSON ---------------------------------------------

    public function testRotaInexistenteResponde404EmJson(): void
    {
        $resposta = $this->chamar('GET', '/api/nada');

        $this->assertSame(404, $resposta->getStatusCode());
        $this->assertStringStartsWith('application/json', $resposta->getHeaderLine('Content-Type'));
        $this->assertIsString($this->json($resposta)['erro']);
    }

    public function testMetodoNaoPermitidoResponde405EmJsonComAllow(): void
    {
        $resposta = $this->chamar('PUT', self::ENTRADAS);

        $this->assertSame(405, $resposta->getStatusCode());
        $this->assertStringStartsWith('application/json', $resposta->getHeaderLine('Content-Type'));
        $this->assertArrayHasKey('erro', $this->json($resposta));
        $this->assertStringContainsString('POST', $resposta->getHeaderLine('Allow'));
    }

    public function testCorpoQueNaoEJsonResponde400ENaoCriaEntrada(): void
    {
        foreach (['{telefone: 11', 'texto solto', '"so-uma-string"', '123'] as $corpo) {
            $resposta = $this->chamar('POST', self::ENTRADAS, $corpo);

            $this->assertSame(400, $resposta->getStatusCode(), "corpo: $corpo");
            $this->assertStringStartsWith('application/json', $resposta->getHeaderLine('Content-Type'));
            $this->assertArrayHasKey('erro', $this->json($resposta));
        }

        $this->assertSame(0, EntradaFila::query()->count());
    }

    public function testErroDeValidacaoResponde422ComMensagem(): void
    {
        $resposta = $this->chamar('POST', self::ENTRADAS, ['telefone' => '123']);

        $this->assertSame(422, $resposta->getStatusCode());
        $this->assertStringStartsWith('application/json', $resposta->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('Telefone', $this->json($resposta)['erro']);
    }

    public function testErroInesperadoResponde500GenericoSemVazarDetalhesELoga(): void
    {
        $this->db->schema()->drop('entradas_fila');

        $resposta = $this->chamar('POST', self::ENTRADAS, ['telefone' => '11971778203']);

        $this->assertSame(500, $resposta->getStatusCode());
        $this->assertStringStartsWith('application/json', $resposta->getHeaderLine('Content-Type'));
        $this->assertSame(['erro' => 'Erro interno.'], $this->json($resposta));
        $this->assertCount(1, $this->errosLogados, 'o erro inesperado deve ir para o log');
        $this->assertStringNotContainsStringIgnoringCase('entradas_fila', (string) $resposta->getBody());
        $this->assertStringNotContainsStringIgnoringCase('sql', (string) $resposta->getBody());
    }

    public function testErrosDeNegocioNaoSaoLogadosComoErroInesperado(): void
    {
        $this->chamar('POST', self::ENTRADAS, ['telefone' => '123']);
        $this->chamar('GET', '/api/nada');

        $this->assertSame([], $this->errosLogados);
    }

    // --- CORS ------------------------------------------------------------------

    public function testComOrigemConfiguradaAsRespostasLevamAOrigemPermitida(): void
    {
        $resposta = $this->chamar('POST', self::ENTRADAS, ['telefone' => '11971778203'], [], [
            'cors_origin' => 'https://front.exemplo.com',
        ]);

        $this->assertSame(201, $resposta->getStatusCode());
        $this->assertSame('https://front.exemplo.com', $resposta->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertSame('Origin', $resposta->getHeaderLine('Vary'));
    }

    public function testAsRespostasDeErroTambemLevamCorsParaOFrontConseguirLerOErro(): void
    {
        $config = ['cors_origin' => 'https://front.exemplo.com'];

        foreach ([
            $this->chamar('GET', '/api/nada', null, [], $config),
            $this->chamar('POST', self::ENTRADAS, ['telefone' => '123'], [], $config),
            $this->chamar('GET', self::ENTRADAS, null, [], $config),
        ] as $resposta) {
            $this->assertContains($resposta->getStatusCode(), [401, 404, 422]);
            $this->assertSame(
                'https://front.exemplo.com',
                $resposta->getHeaderLine('Access-Control-Allow-Origin'),
                'status ' . $resposta->getStatusCode()
            );
        }
    }

    public function testPreflightResponde204ComMetodosECabecalhosPermitidos(): void
    {
        $resposta = $this->chamar('OPTIONS', self::ENTRADAS, null, [
            'Origin' => 'https://front.exemplo.com',
            'Access-Control-Request-Method' => 'POST',
        ], ['cors_origin' => 'https://front.exemplo.com']);

        $this->assertSame(204, $resposta->getStatusCode());
        $this->assertSame('https://front.exemplo.com', $resposta->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertSame('GET, POST, OPTIONS', $resposta->getHeaderLine('Access-Control-Allow-Methods'));
        $this->assertSame('Content-Type, X-API-Key', $resposta->getHeaderLine('Access-Control-Allow-Headers'));
        $this->assertSame('', (string) $resposta->getBody());
    }

    public function testSemOrigemConfiguradaNenhumCabecalhoCors(): void
    {
        $respostas = [
            $this->chamar('POST', self::ENTRADAS, ['telefone' => '11971778203']),
            $this->chamar('GET', '/api/nada'),
            $this->chamar('OPTIONS', self::ENTRADAS, null, ['Access-Control-Request-Method' => 'POST']),
        ];

        foreach ($respostas as $resposta) {
            $this->assertSame([], $this->cabecalhosCors($resposta), 'status ' . $resposta->getStatusCode());
        }
        $this->assertSame(405, $respostas[2]->getStatusCode(), 'sem CORS, OPTIONS não é tratado especialmente');
    }
}
