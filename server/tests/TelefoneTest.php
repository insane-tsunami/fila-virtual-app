<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Support\Telefone;

final class TelefoneTest extends TestCase
{
    /** @return array<string, array{mixed, string}> */
    public static function validos(): array
    {
        return [
            'formatado sem DDI (11 dígitos)' => ['(11) 97177-8203', '5511971778203'],
            'com DDI e sinal de mais' => ['+55 11 97177-8203', '5511971778203'],
            'só dígitos sem DDI' => ['11971778203', '5511971778203'],
            'fixo com 10 dígitos' => ['(11) 3333-4444', '551133334444'],
            'fixo com DDI (12 dígitos)' => ['551133334444', '551133334444'],
            'celular com DDI (13 dígitos)' => ['5511971778203', '5511971778203'],
            'DDD 55 sem DDI não é confundido com o DDI' => ['(55) 99999-1234', '5555999991234'],
            'número inteiro vindo do JSON' => [11971778203, '5511971778203'],
        ];
    }

    #[DataProvider('validos')]
    public function testNormalizaTelefonesValidos(mixed $entrada, string $esperado): void
    {
        $this->assertSame($esperado, Telefone::normalizar($entrada));
    }

    /** @return array<string, array{mixed}> */
    public static function invalidos(): array
    {
        return [
            'curto demais' => ['123'],
            'só texto' => ['abc'],
            'vazio' => [''],
            'nove dígitos' => ['971778203'],
            'doze dígitos sem 55' => ['441133334444'],
            'treze dígitos sem 55' => ['4411971778203'],
            'quatorze dígitos' => ['55119717782030'],
            'nulo' => [null],
            'lista' => [['11971778203']],
            'booleano' => [true],
        ];
    }

    #[DataProvider('invalidos')]
    public function testRecusaTelefonesInvalidos(mixed $entrada): void
    {
        $this->assertNull(Telefone::normalizar($entrada));
    }

    public function testMascaraMostraApenasOsQuatroUltimosDigitos(): void
    {
        $this->assertSame('*****8203', Telefone::mascarar('5511971778203'));
        $this->assertSame('*****4444', Telefone::mascarar('551133334444'));
    }

    public function testMascaraNuncaContemOTelefoneCompleto(): void
    {
        $this->assertStringNotContainsString('5511971', Telefone::mascarar('5511971778203'));
    }
}
