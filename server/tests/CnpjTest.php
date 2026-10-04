<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Support\Cnpj;

final class CnpjTest extends TestCase
{
    /** @return array<string, array{mixed, string}> */
    public static function validos(): array
    {
        return [
            // numéricos
            'numérico com máscara' => ['11.222.333/0001-81', '11222333000181'],
            'numérico só dígitos' => ['11222333000181', '11222333000181'],
            'numérico 93.339.970/0001-05' => ['93.339.970/0001-05', '93339970000105'],
            'numérico 11.111.111/0001-91' => ['11.111.111/0001-91', '11111111000191'],
            'numérico 22.222.222/0001-91' => ['22.222.222/0001-91', '22222222000191'],
            'DV repetido que não é 14 iguais' => ['11111111111180', '11111111111180'],
            // alfanuméricos
            'exemplo oficial da Receita' => ['12.ABC.345/01DE-35', '12ABC34501DE35'],
            'oficial sem máscara' => ['12ABC34501DE35', '12ABC34501DE35'],
            'só letras na base' => ['ZZZZZZZZ000191', 'ZZZZZZZZ000191'],
            'letras e números intercalados' => ['A1B2C3D4E5F668', 'A1B2C3D4E5F668'],
            'outro alfanumérico' => ['AB12CD34EF5602', 'AB12CD34EF5602'],
            // caixa, máscara e espaços
            'minúsculas viram maiúsculas' => ['12.abc.345/01de-35', '12ABC34501DE35'],
            'caixa mista' => ['12aBc34501De35', '12ABC34501DE35'],
            'espaços nas pontas' => ['  11.222.333/0001-81 ', '11222333000181'],
            'quebra de linha no fim é espaço em branco' => ["11222333000181\n", '11222333000181'],
            'máscara parcial' => ['11222333/0001-81', '11222333000181'],
        ];
    }

    #[DataProvider('validos')]
    public function testNormalizaCnpjsValidos(mixed $entrada, string $esperado): void
    {
        $this->assertSame($esperado, Cnpj::normalizar($entrada));
    }

    /** @return array<string, array{mixed}> */
    public static function invalidos(): array
    {
        return [
            'vazio' => [''],
            'só espaços' => ['   '],
            'DV errado (alfanumérico)' => ['12ABC34501DE36'],
            'DV errado no primeiro dígito' => ['12ABC34501DE45'],
            'DV errado (numérico)' => ['11222333000180'],
            'DV errado com máscara' => ['11.222.333/0001-82'],
            'DV errado em 93.339.970/0001-0x' => ['93339970000106'],
            '14 caracteres iguais com DV correto' => ['00000000000000'],
            '13 caracteres' => ['12ABC34501DE3'],
            '15 caracteres' => ['12ABC34501DE355'],
            'letra no primeiro dígito verificador' => ['12ABC34501DEA5'],
            'letras nos dois dígitos verificadores' => ['12ABC34501DEAB'],
            'outro símbolo' => ['12ABC345_01DE-35'],
            'acento' => ['12ÁBC34501DE35'],
            'não é texto: número' => [11222333000181],
            'não é texto: nulo' => [null],
            'não é texto: lista' => [['11222333000181']],
        ];
    }

    #[DataProvider('invalidos')]
    public function testRecusaCnpjsInvalidos(mixed $entrada): void
    {
        $this->assertNull(Cnpj::normalizar($entrada));
    }

    public function testDigitosVerificadoresDoExemploOficial(): void
    {
        $this->assertSame('35', Cnpj::digitosVerificadores('12ABC34501DE'));
        $this->assertSame('81', Cnpj::digitosVerificadores('112223330001'));
        $this->assertSame('91', Cnpj::digitosVerificadores('111111110001'));
    }

    public function testCadaCaractereValeOCodigoAsciiMenos48(): void
    {
        // 'A' (17) no lugar de um dígito muda o DV: a base só de A's não dá o DV da base só de 1's
        $this->assertNotSame(
            Cnpj::digitosVerificadores('AAAAAAAAAAAA'),
            Cnpj::digitosVerificadores('111111111111')
        );
        $this->assertSame('68', Cnpj::digitosVerificadores('A1B2C3D4E5F6'));
    }

    public function testBaseDeZerosTemDigitoZeroMasNaoEUmCnpj(): void
    {
        $this->assertSame('00', Cnpj::digitosVerificadores('000000000000'));
        $this->assertNull(Cnpj::normalizar('00.000.000/0000-00'));
    }
}
