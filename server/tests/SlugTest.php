<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Support\Slug;

final class SlugTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function nomes(): array
    {
        return [
            'exemplo da spec' => ['Moda & Cia São João', 'moda-cia-sao-joao'],
            'nome simples' => ['Veste Bem', 'veste-bem'],
            'acentos do português' => ['Açaí do Zé Pão Ânimo Órfão Vovó Pingüim', 'acai-do-ze-pao-animo-orfao-vovo-pinguim'],
            'espaços e símbolos repetidos' => ['  Loja --- do   João!!!  ', 'loja-do-joao'],
            'números' => ['Loja 24h 2', 'loja-24h-2'],
            'maiúsculas acentuadas' => ['ÀÉÎÕÜ Çã', 'aeiou-ca'],
            'eszett' => ['Straße', 'strasse'],
        ];
    }

    #[DataProvider('nomes')]
    public function testGeraSlugDoNome(string $nome, string $esperado): void
    {
        $this->assertSame($esperado, Slug::deNome($nome));
    }

    /** @return array<string, array{string}> */
    public static function semLetrasNemNumeros(): array
    {
        return [
            'vazio' => [''],
            'só símbolos' => ['!!!'],
            'só espaços' => ['   '],
            'só emoji' => ['😀😀'],
            'só caracteres de outro alfabeto' => ['商店'],
        ];
    }

    #[DataProvider('semLetrasNemNumeros')]
    public function testNomeSemLetrasNemNumerosNaoGeraSlug(string $nome): void
    {
        $this->assertNull(Slug::deNome($nome));
    }

    public function testCortaEm80CaracteresSemHifenNaPonta(): void
    {
        $this->assertSame(str_repeat('a', 80), Slug::deNome(str_repeat('a', 100)));

        // o corte cai logo depois de um hífen: o hífen não pode ficar na ponta
        $nome = str_repeat('a', 79) . ' ' . str_repeat('b', 10);
        $slug = Slug::deNome($nome);
        $this->assertSame(str_repeat('a', 79), $slug);
        $this->assertLessThanOrEqual(80, strlen($slug));
    }
}
