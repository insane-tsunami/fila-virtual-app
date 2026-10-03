<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Support\EnderecoPublico;

final class EnderecoPublicoTest extends TestCase
{
    /** @return array<string, array{mixed, string}> */
    public static function validos(): array
    {
        return [
            'localhost com porta' => ['http://localhost:3000', 'http://localhost:3000'],
            'https com porta' => ['https://loja.exemplo.com:8443', 'https://loja.exemplo.com:8443'],
            'https simples' => ['https://loja.exemplo.com', 'https://loja.exemplo.com'],
            'barra final removida' => ['https://loja.exemplo.com/', 'https://loja.exemplo.com'],
            'barra final com porta' => ['http://localhost:3000/', 'http://localhost:3000'],
            'esquema e host em minúsculas' => ['HTTPS://Loja.Exemplo.COM', 'https://loja.exemplo.com'],
            'ipv4' => ['http://192.168.0.10:8080', 'http://192.168.0.10:8080'],
            'host de uma letra' => ['http://a', 'http://a'],
            'porta com zero à esquerda' => ['http://localhost:0080', 'http://localhost:80'],
            'domínio com hífen' => ['https://minha-loja.com.br', 'https://minha-loja.com.br'],
        ];
    }

    #[DataProvider('validos')]
    public function testNormalizaOrigensValidas(mixed $entrada, string $esperado): void
    {
        $this->assertSame($esperado, EnderecoPublico::normalizar($entrada));
    }

    /** @return array<string, array{mixed}> */
    public static function invalidos(): array
    {
        return [
            'esquema ftp' => ['ftp://loja.exemplo.com'],
            'sem esquema' => ['loja.exemplo.com'],
            'javascript' => ['javascript:alert(1)'],
            'credenciais' => ['https://usuario:senha@loja.exemplo.com'],
            'só usuário' => ['https://usuario@loja.exemplo.com'],
            'com caminho' => ['https://loja.exemplo.com/caminho'],
            'caminho e barra' => ['https://loja.exemplo.com/a/'],
            'com query' => ['https://loja.exemplo.com?a=1'],
            'com query após barra' => ['https://loja.exemplo.com/?a=1'],
            'com fragmento' => ['https://loja.exemplo.com#topo'],
            'vazio' => [''],
            'só espaços' => ['   '],
            'espaço no meio' => ['https://loja .exemplo.com'],
            'quebra de linha no fim' => ["https://loja.exemplo.com\n"],
            'porta zero' => ['http://localhost:0'],
            'porta acima do limite' => ['http://localhost:65536'],
            'porta vazia' => ['http://localhost:'],
            'host com unicode' => ['https://lójá.exemplo.com'],
            'host começando com hífen' => ['https://-loja.com'],
            'host terminando com hífen' => ['https://loja-.com'],
            'ponto duplo' => ['https://loja..com'],
            'sem host' => ['https://'],
            'ipv6 entre colchetes' => ['http://[::1]:3000'],
            'texto longo demais' => ['https://' . str_repeat('a', 250) . '.com'],
            'nulo' => [null],
            'número' => [123],
            'lista' => [['https://loja.exemplo.com']],
            'booleano' => [true],
        ];
    }

    #[DataProvider('invalidos')]
    public function testRecusaValoresQueNaoSaoUmaOrigemHttp(mixed $entrada): void
    {
        $this->assertNull(EnderecoPublico::normalizar($entrada));
    }

    /** Monta `https://` + 4 rótulos separados por ponto, ajustando o tamanho total da URL. */
    private static function urlComTamanho(int $total): string
    {
        $host = $total - strlen('https://');
        $rotulos = 4;
        $letras = $host - ($rotulos - 1);
        $base = intdiv($letras, $rotulos);
        $sobra = $letras % $rotulos;
        $partes = [];
        for ($i = 0; $i < $rotulos; $i++) {
            $partes[] = str_repeat('a', $base + ($i < $sobra ? 1 : 0));
        }

        return 'https://' . implode('.', $partes);
    }

    public function testAceitaExatamente255CaracteresERecusa256(): void
    {
        $com255 = self::urlComTamanho(255);
        $com256 = self::urlComTamanho(256);

        $this->assertSame(255, strlen($com255));
        $this->assertSame(256, strlen($com256));
        $this->assertSame($com255, EnderecoPublico::normalizar($com255));
        $this->assertNull(EnderecoPublico::normalizar($com256));
    }

    public function testRotuloDnsTemNoMaximo63Caracteres(): void
    {
        $this->assertNotNull(EnderecoPublico::normalizar('https://' . str_repeat('a', 63) . '.com'));
        $this->assertNull(EnderecoPublico::normalizar('https://' . str_repeat('a', 64) . '.com'));
    }
}
