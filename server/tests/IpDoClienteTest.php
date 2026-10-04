<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Support\IpDoCliente;

final class IpDoClienteTest extends TestCase
{
    /** @param list<string> $proxies */
    private function ip(?string $conexao, ?string $encaminhado = null, array $proxies = []): string
    {
        $params = $conexao === null ? [] : ['REMOTE_ADDR' => $conexao];
        $requisicao = (new ServerRequestFactory())->createServerRequest('POST', '/api/sessoes', $params);
        if ($encaminhado !== null) {
            $requisicao = $requisicao->withHeader('X-Forwarded-For', $encaminhado);
        }

        return IpDoCliente::de($requisicao, $proxies);
    }

    public function testUsaOEnderecoDaConexao(): void
    {
        $this->assertSame('198.51.100.7', $this->ip('198.51.100.7'));
    }

    public function testIgnoraOCabecalhoSemProxyConfiavel(): void
    {
        $this->assertSame('198.51.100.7', $this->ip('198.51.100.7', '1.2.3.4'));
        $this->assertSame('198.51.100.7', $this->ip('198.51.100.7', '1.2.3.4', ['10.0.0.1']));
    }

    public function testSemEnderecoOuComEnderecoInvalidoVaiParaUmaChaveFixa(): void
    {
        $this->assertSame('desconhecido', $this->ip(null));
        $this->assertSame('desconhecido', $this->ip('não é ip'));
    }

    public function testAtrasDeProxyConfiavelUsaOCabecalho(): void
    {
        $this->assertSame('203.0.113.9', $this->ip('10.0.0.1', '203.0.113.9', ['10.0.0.1']));
    }

    public function testAceitaFaixaCidr(): void
    {
        $this->assertSame('203.0.113.9', $this->ip('192.168.45.3', '203.0.113.9', ['192.168.0.0/16']));
        $this->assertSame('192.169.0.1', $this->ip('192.169.0.1', '203.0.113.9', ['192.168.0.0/16']));
        $this->assertSame('203.0.113.9', $this->ip('172.17.5.5', '203.0.113.9', ['172.16.0.0/12']));
    }

    public function testCadeiaDeProxiesEmpilhadosPegaOMaisADireitaNaoConfiavel(): void
    {
        $this->assertSame(
            '203.0.113.9',
            $this->ip('10.0.0.2', '1.2.3.4, 203.0.113.9, 10.0.0.1', ['10.0.0.0/24'])
        );
    }

    public function testValorForjadoANoEsquerdaNaoVale(): void
    {
        $this->assertSame('203.0.113.9', $this->ip('10.0.0.1', '1.2.3.4, 203.0.113.9', ['10.0.0.1']));
    }

    public function testCabecalhoInvalidoOuAusenteCaiNaConexao(): void
    {
        $this->assertSame('10.0.0.1', $this->ip('10.0.0.1', 'lixo', ['10.0.0.1']));
        $this->assertSame('10.0.0.1', $this->ip('10.0.0.1', '203.0.113.9, lixo', ['10.0.0.1']));
        $this->assertSame('10.0.0.1', $this->ip('10.0.0.1', null, ['10.0.0.1']));
        $this->assertSame('10.0.0.1', $this->ip('10.0.0.1', '10.0.0.1', ['10.0.0.1']));
    }

    public function testIpv6EAgrupadoPeloSlash64(): void
    {
        $a = $this->ip('2001:db8:1:2:aaaa:bbbb:cccc:dddd');
        $b = $this->ip('2001:db8:1:2::1');

        $this->assertSame('2001:db8:1:2::/64', $a);
        $this->assertSame($a, $b);
        $this->assertNotSame($a, $this->ip('2001:db8:1:3::1'));
    }

    public function testIpv6AtrasDeProxyConfiavelEFaixaIpv6(): void
    {
        $this->assertSame(
            '2001:db8:9:9::/64',
            $this->ip('fd00::1', '2001:db8:9:9::5', ['fd00::/8'])
        );
    }

    public function testIpv4EscritoComoIpv6EhTratadoComoIpv4(): void
    {
        $this->assertSame('198.51.100.7', $this->ip('::ffff:198.51.100.7'));
        $this->assertSame('203.0.113.9', $this->ip('::ffff:10.0.0.1', '203.0.113.9', ['10.0.0.1']));
    }

    public function testEntradaInvalidaNaListaDeProxiesEIgnorada(): void
    {
        $this->assertSame('10.0.0.1', $this->ip('10.0.0.1', '203.0.113.9', ['lixo', '10.0.0.1/99', '10.0.0.1/x']));
    }
}
