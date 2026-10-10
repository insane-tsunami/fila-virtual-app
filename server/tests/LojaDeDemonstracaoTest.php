<?php

declare(strict_types=1);

namespace Tests;

use Support\LojaDeDemonstracao;

final class LojaDeDemonstracaoTest extends DatabaseTestCase
{
    public function testSemeiaALojaSemDonaESemEnderecoEESejaIdempotente(): void
    {
        $this->migrate();

        $this->assertTrue(LojaDeDemonstracao::semear($this->db));
        $this->assertFalse(LojaDeDemonstracao::semear($this->db));

        $lojas = $this->db->table('estabelecimentos')->get();
        $this->assertCount(1, $lojas);
        $this->assertSame('veste-bem', $lojas[0]->slug);
        $this->assertSame('Veste Bem', $lojas[0]->nome);
        $this->assertNull($lojas[0]->conta_id);
        $this->assertNull($lojas[0]->endereco_publico);
    }
}
