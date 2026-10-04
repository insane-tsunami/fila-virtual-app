<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Support\Email;

final class EmailTest extends TestCase
{
    /** @return array<string, array{mixed, string}> */
    public static function validos(): array
    {
        return [
            'simples' => ['contato@vestebem.com', 'contato@vestebem.com'],
            'maiúsculas viram minúsculas' => ['Contato@VesteBem.COM', 'contato@vestebem.com'],
            'espaços nas pontas' => ['  contato@vestebem.com  ', 'contato@vestebem.com'],
            'com sinal de mais e subdomínio' => ['a+b@loja.sp.exemplo.com.br', 'a+b@loja.sp.exemplo.com.br'],
        ];
    }

    #[DataProvider('validos')]
    public function testNormalizaEmailsValidos(mixed $entrada, string $esperado): void
    {
        $this->assertSame($esperado, Email::normalizar($entrada));
    }

    /** @return array<string, array{mixed}> */
    public static function invalidos(): array
    {
        return [
            'vazio' => [''],
            'só espaços' => ['   '],
            'sem arroba' => ['contato.vestebem.com'],
            'sem domínio' => ['contato@'],
            'sem usuário' => ['@vestebem.com'],
            'espaço no meio' => ['con tato@vestebem.com'],
            'dois arrobas' => ['a@b@vestebem.com'],
            'não é texto: número' => [123],
            'não é texto: nulo' => [null],
            'não é texto: lista' => [['a@b.com']],
        ];
    }

    #[DataProvider('invalidos')]
    public function testRecusaEmailsInvalidos(mixed $entrada): void
    {
        $this->assertNull(Email::normalizar($entrada));
    }

    public function testLimiteDe254Caracteres(): void
    {
        $dominio = '@exemplo.com';
        $de254 = str_repeat('a', 64) . '@' . str_repeat('b', 63) . '.' . str_repeat('c', 63) . '.' . str_repeat('d', 57) . '.com';
        $this->assertSame(254, strlen($de254));

        $this->assertSame($de254, Email::normalizar($de254));
        $this->assertNull(Email::normalizar('x' . $de254), 'com 255 caracteres é recusado');
        $this->assertNotNull(Email::normalizar('ok' . $dominio));
    }
}
