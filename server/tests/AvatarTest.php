<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Support\Avatar;

final class AvatarTest extends TestCase
{
    public function testListasFixasDaSpec(): void
    {
        $this->assertSame(['roxo', 'azul', 'verde', 'laranja', 'vermelho', 'cinza'], Avatar::CORES);
        $this->assertSame(['loja', 'mercado', 'restaurante', 'cafe', 'farmacia', 'beleza'], Avatar::ICONES);
    }

    /** @return array<string, array{array<mixed>}> */
    public static function aceitos(): array
    {
        return [
            'cor e ícone da lista' => [['cor' => 'verde', 'icone' => 'restaurante']],
            'última cor e último ícone' => [['cor' => 'cinza', 'icone' => 'beleza']],
            'os dois nulos (volta à inicial)' => [['cor' => null, 'icone' => null]],
        ];
    }

    #[DataProvider('aceitos')]
    public function testCorposAceitos(array $corpo): void
    {
        $this->assertTrue(Avatar::corpoValido($corpo));
    }

    /** @return array<string, array{array<mixed>}> */
    public static function recusados(): array
    {
        return [
            'cor fora da lista' => [['cor' => 'rosa', 'icone' => 'cafe']],
            'ícone fora da lista' => [['cor' => 'azul', 'icone' => 'carro']],
            'maiúsculas' => [['cor' => 'Azul', 'icone' => 'CAFE']],
            'espaço em volta' => [['cor' => ' azul', 'icone' => 'cafe']],
            'só a cor' => [['cor' => 'azul']],
            'só o ícone' => [['icone' => 'cafe']],
            'cor nula e ícone definido' => [['cor' => null, 'icone' => 'cafe']],
            'cor definida e ícone nulo' => [['cor' => 'azul', 'icone' => null]],
            'não é texto' => [['cor' => 1, 'icone' => ['cafe']]],
            'corpo sem campos' => [[]],
        ];
    }

    #[DataProvider('recusados')]
    public function testCorposRecusados(array $corpo): void
    {
        $this->assertFalse(Avatar::corpoValido($corpo));
    }
}
