<?php

declare(strict_types=1);

namespace Tests;

/** Cenários do spec privacy-notice: o aviso público e a configuração que ele expõe. */
final class PrivacyApiTest extends ApiTestCase
{
    public function testAvisoPublicoDevolveOsPadroesProvisoriosEOsPrazos(): void
    {
        $config = require __DIR__ . '/../api/config.php';
        $resposta = $this->chamar('GET', '/api/privacidade', null, [], [
            'privacidade' => $config['privacidade'],
            'retencao' => $config['retencao'],
        ]);
        $corpo = $this->json($resposta);

        $this->assertSame(200, $resposta->getStatusCode());
        $this->assertSame([
            'controlador' => 'ZeraFilas',
            'email' => 'privacidade@zerafilas.example',
            'encarregado' => '',
            'base_legal' => 'legítimo interesse (art. 7º, IX, da LGPD)',
            'retencao_entrada_ativa_horas' => 24,
            'retencao_telefone_dias' => 30,
        ], $corpo);
    }

    public function testAvisoUsaAConfiguracaoEOPrazoRealDaRetencao(): void
    {
        $resposta = $this->chamar('GET', '/api/privacidade', null, [], [
            'privacidade' => [
                'controlador' => 'Loja X Ltda',
                'email' => 'dpo@lojax.com',
                'encarregado' => 'Maria',
            ],
            'retencao' => ['entrada_ativa_horas' => 2, 'telefone_dias' => 7],
        ]);
        $corpo = $this->json($resposta);

        $this->assertSame('Loja X Ltda', $corpo['controlador']);
        $this->assertSame('dpo@lojax.com', $corpo['email']);
        $this->assertSame('Maria', $corpo['encarregado']);
        $this->assertSame(7, $corpo['retencao_telefone_dias']);
        $this->assertSame(2, $corpo['retencao_entrada_ativa_horas']);
    }

    public function testAvisoNaoDevolveDadosDeContasNemDeClientes(): void
    {
        $this->cadastrarConta('Moda Azul', 'a@exemplo.com', '93339970000105');
        $this->entrarPelaApi(1);

        $bruto = (string) $this->chamar('GET', '/api/privacidade')->getBody();

        $this->assertStringNotContainsString('a@exemplo.com', $bruto);
        $this->assertStringNotContainsString('93339970000105', $bruto);
        $this->assertStringNotContainsString('97177', $bruto);
    }
}
