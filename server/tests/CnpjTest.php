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
            'com máscara' => ['93.339.970/0001-05', '93339970000105'],
            'só dígitos' => ['93339970000105', '93339970000105'],
            'com espaços nas pontas' => ['  93.339.970/0001-05 ', '93339970000105'],
            'quebra de linha no fim é espaço em branco' => ["93339970000105\n", '93339970000105'],
            'máscara parcial' => ['93339970/0001-05', '93339970000105'],
            'dígito verificador não é conferido' => ['11111111111111', '11111111111111'],
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
            '13 dígitos' => ['9333997000010'],
            '15 dígitos' => ['933399700001055'],
            'com letras' => ['93.339.970/000A-05'],
            'com outro símbolo' => ['93339970_0001-05'],
            'não é texto: número' => [93339970000105],
            'não é texto: nulo' => [null],
        ];
    }

    #[DataProvider('invalidos')]
    public function testRecusaCnpjsInvalidos(mixed $entrada): void
    {
        $this->assertNull(Cnpj::normalizar($entrada));
    }
}
