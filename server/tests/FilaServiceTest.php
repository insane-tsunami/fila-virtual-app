<?php

declare(strict_types=1);

namespace Tests;

use Models\EntradaFila;
use Services\ConflitoException;
use Services\DadosInvalidosException;
use Services\FilaService;
use Services\NaoEncontradoException;

final class FilaServiceTest extends DatabaseTestCase
{
    private FilaService $fila;
    private const SLUG = 'veste-bem';

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrate();
        $this->fila = new FilaService();
    }

    /** Entra na fila com o telefone de número `$n` e devolve o resultado do serviço. */
    private function entrar(int $n): array
    {
        return $this->fila->entrar(self::SLUG, sprintf('(11) 9%04d-%04d', $n, $n));
    }

    private function codigo(int $n): string
    {
        return $this->entrar($n)['entrada']['codigo'];
    }

    /** @return list<string> status na ordem de chegada */
    private function statusDaFila(): array
    {
        return array_column($this->fila->listar(self::SLUG), 'status');
    }

    // --- queue-intake: entrada --------------------------------------------

    public function testEntrarEmFilaComClientesFicaAguardandoNaTerceiraPosicao(): void
    {
        $this->entrar(1);
        $this->entrar(2);

        $resultado = $this->entrar(3);

        $this->assertTrue($resultado['criada']);
        $this->assertSame(3, $resultado['entrada']['posicao']);
        $this->assertSame('aguardando', $resultado['entrada']['status']);
        $this->assertNotSame('', $resultado['entrada']['codigo']);
    }

    public function testEntrarEmFilaVaziaJaFicaEmAtendimento(): void
    {
        $resultado = $this->entrar(1);

        $this->assertSame(1, $resultado['entrada']['posicao']);
        $this->assertSame('em_atendimento', $resultado['entrada']['status']);
        $this->assertNotNull(EntradaFila::query()->first()->iniciou_em);
    }

    public function testEntrarEmEstabelecimentoInexistenteLancaNaoEncontrado(): void
    {
        $this->expectException(NaoEncontradoException::class);

        try {
            $this->fila->entrar('nao-existe', '11971778203');
        } finally {
            $this->assertSame(0, EntradaFila::query()->count());
        }
    }

    public function testTelefoneInvalidoLancaDadosInvalidosENaoCriaEntrada(): void
    {
        foreach (['123', 'abc', '', null] as $invalido) {
            try {
                $this->fila->entrar(self::SLUG, $invalido);
                $this->fail('deveria recusar o telefone ' . var_export($invalido, true));
            } catch (DadosInvalidosException) {
                // esperado
            }
        }

        $this->assertSame(0, EntradaFila::query()->count());
    }

    public function testGravaOTelefoneNormalizadoComDdi(): void
    {
        $this->fila->entrar(self::SLUG, '(11) 97177-8203');

        $this->assertSame('5511971778203', EntradaFila::query()->first()->telefone);
    }

    // --- queue-intake: duplicidade ------------------------------------------

    public function testMesmoTelefoneNaoDuplicaMesmoEscritoDeOutraForma(): void
    {
        $primeira = $this->fila->entrar(self::SLUG, '(11) 97177-8203');
        $this->fila->entrar(self::SLUG, '(11) 98888-7777');

        $repetida = $this->fila->entrar(self::SLUG, '+55 11 97177-8203');

        $this->assertFalse($repetida['criada']);
        $this->assertSame($primeira['entrada']['codigo'], $repetida['entrada']['codigo']);
        $this->assertSame($primeira['entrada']['posicao'], $repetida['entrada']['posicao']);
        $this->assertSame(2, EntradaFila::query()->count());
    }

    public function testTelefoneDeEntradaFinalizadaPodeEntrarDeNovo(): void
    {
        $primeiro = $this->entrar(1);
        $this->fila->finalizar(self::SLUG, $primeiro['entrada']['codigo']);

        $novo = $this->entrar(1);

        $this->assertTrue($novo['criada']);
        $this->assertNotSame($primeiro['entrada']['codigo'], $novo['entrada']['codigo']);
    }

    // --- queue-intake: código imprevisível ----------------------------------

    public function testCodigosSaoAleatoriosLongosEIndependentesDoId(): void
    {
        $a = $this->codigo(1);
        $b = $this->codigo(2);

        foreach ([$a, $b] as $codigo) {
            $this->assertGreaterThanOrEqual(16, strlen($codigo));
            $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $codigo);
        }
        $this->assertNotSame($a, $b);
        $this->assertStringNotContainsString('9000', $a, 'o código não deve embutir o telefone');
    }

    // --- queue-intake: consulta da posição -----------------------------------

    public function testPosicaoAvancaQuandoAFilaAnda(): void
    {
        $primeiro = $this->codigo(1);
        $this->codigo(2);
        $terceiro = $this->codigo(3);
        $this->assertSame(3, $this->fila->consultar(self::SLUG, $terceiro)['posicao']);

        $this->fila->finalizar(self::SLUG, $primeiro);

        $this->assertSame(2, $this->fila->consultar(self::SLUG, $terceiro)['posicao']);
    }

    public function testEntradaFinalizadaTemPosicaoNula(): void
    {
        $primeiro = $this->codigo(1);
        $this->fila->finalizar(self::SLUG, $primeiro);

        $consulta = $this->fila->consultar(self::SLUG, $primeiro);

        $this->assertSame('finalizado', $consulta['status']);
        $this->assertNull($consulta['posicao']);
    }

    public function testConsultaNaoDevolveTelefone(): void
    {
        $codigo = $this->codigo(1);

        $consulta = $this->fila->consultar(self::SLUG, $codigo);

        $this->assertSame(['codigo', 'posicao', 'status'], array_keys($consulta));
    }

    public function testConsultaDeCodigoInexistenteOuDeOutroEstabelecimentoLancaNaoEncontrado(): void
    {
        $codigo = $this->codigo(1);
        $this->criarEstabelecimento('outra-loja');

        foreach ([['veste-bem', 'inexistente'], ['outra-loja', $codigo]] as [$slug, $cod]) {
            try {
                $this->fila->consultar($slug, $cod);
                $this->fail("$slug/$cod deveria ser não encontrado");
            } catch (NaoEncontradoException) {
                // esperado
            }
        }
        $this->addToAssertionCount(1);
    }

    // --- queue-management: listagem ------------------------------------------

    public function testListagemTraTresEntradasEmOrdemComTelefoneMascarado(): void
    {
        $this->fila->entrar(self::SLUG, '(11) 97177-8203');
        $this->fila->entrar(self::SLUG, '(11) 98888-7777');
        $this->fila->entrar(self::SLUG, '(11) 95555-1234');

        $lista = $this->fila->listar(self::SLUG);

        $this->assertSame([1, 2, 3], array_column($lista, 'posicao'));
        $this->assertSame(['em_atendimento', 'aguardando', 'aguardando'], array_column($lista, 'status'));
        $this->assertSame(['*****8203', '*****7777', '*****1234'], array_column($lista, 'telefone'));
        $this->assertStringNotContainsString('5511971', json_encode($lista, JSON_THROW_ON_ERROR));
    }

    public function testListagemDeFilaVaziaEUmaListaVazia(): void
    {
        $this->assertSame([], $this->fila->listar(self::SLUG));
    }

    public function testListagemNaoMostraEntradasFinalizadas(): void
    {
        $primeiro = $this->codigo(1);
        $segundo = $this->codigo(2);
        $this->fila->finalizar(self::SLUG, $primeiro);

        $codigos = array_column($this->fila->listar(self::SLUG), 'codigo');

        $this->assertSame([$segundo], $codigos);
    }

    // --- queue-management: finalizar -----------------------------------------

    public function testFinalizarPromoveOAguardanteMaisAntigoEPreservaAOrdem(): void
    {
        $a = $this->codigo(1);
        $b = $this->codigo(2);
        $c = $this->codigo(3);

        $resultado = $this->fila->finalizar(self::SLUG, $a);

        $this->assertSame('finalizado', $resultado['finalizada']['status']);
        $this->assertNull($resultado['finalizada']['posicao']);
        $this->assertSame($b, $resultado['atual']['codigo']);
        $this->assertSame('em_atendimento', $resultado['atual']['status']);
        $this->assertSame(1, $resultado['atual']['posicao']);
        $this->assertSame(2, $this->fila->consultar(self::SLUG, $c)['posicao']);
        $this->assertSame([$b, $c], array_column($this->fila->listar(self::SLUG), 'codigo'));
    }

    public function testFinalizarOUltimoClienteDeixaAFilaVazia(): void
    {
        $unico = $this->codigo(1);

        $resultado = $this->fila->finalizar(self::SLUG, $unico);

        $this->assertNull($resultado['atual']);
        $this->assertSame([], $this->fila->listar(self::SLUG));
    }

    public function testFinalizarEntradaAguardandoLancaConflitoSemAlterarNada(): void
    {
        $this->codigo(1);
        $segundo = $this->codigo(2);
        $antes = $this->statusDaFila();

        try {
            $this->fila->finalizar(self::SLUG, $segundo);
            $this->fail('deveria recusar finalizar quem está aguardando');
        } catch (ConflitoException) {
            // esperado
        }

        $this->assertSame($antes, $this->statusDaFila());
    }

    public function testFinalizacaoRepetidaFinalizaApenasUmAtendimento(): void
    {
        $a = $this->codigo(1);
        $b = $this->codigo(2);
        $this->codigo(3);

        $this->fila->finalizar(self::SLUG, $a);
        try {
            $this->fila->finalizar(self::SLUG, $a);
            $this->fail('a segunda finalização deveria dar conflito');
        } catch (ConflitoException) {
            // esperado: duplo clique
        }

        $this->assertSame(1, EntradaFila::query()->where('status', 'finalizado')->count());
        $this->assertSame('em_atendimento', $this->fila->consultar(self::SLUG, $b)['status']);
    }

    public function testFinalizarCodigoInexistenteLancaNaoEncontrado(): void
    {
        $this->codigo(1);

        $this->expectException(NaoEncontradoException::class);
        $this->fila->finalizar(self::SLUG, 'inexistente');
    }

    public function testSempreHaNoMaximoUmEmAtendimentoEEleEhOPrimeiro(): void
    {
        $codigos = [];
        foreach ([1, 2, 3, 4] as $n) {
            $codigos[] = $this->codigo($n);
            $this->assertInvarianteDaFila();
        }
        foreach ($codigos as $codigo) {
            $this->fila->finalizar(self::SLUG, $codigo);
            $this->assertInvarianteDaFila();
        }
    }

    private function assertInvarianteDaFila(): void
    {
        $status = $this->statusDaFila();
        $emAtendimento = array_keys(array_filter($status, fn ($s) => $s === 'em_atendimento'));

        $this->assertLessThanOrEqual(1, count($emAtendimento));
        if ($status !== []) {
            $this->assertSame([0], $emAtendimento, 'se há fila, o primeiro está em atendimento');
        }
    }

    public function testFinalizarGravaOsHorariosDoAtendimento(): void
    {
        $a = $this->codigo(1);
        $b = $this->codigo(2);

        $this->fila->finalizar(self::SLUG, $a);

        $finalizada = EntradaFila::query()->where('codigo', $a)->first();
        $promovida = EntradaFila::query()->where('codigo', $b)->first();
        $this->assertNotNull($finalizada->finalizou_em);
        $this->assertNotNull($promovida->iniciou_em);
        $this->assertNull($promovida->finalizou_em);
    }
}
