<?php

declare(strict_types=1);

namespace Tests;

use Services\LimiteDeTentativas;
use Services\LimiteExcedidoException;
use Throwable;

final class LimiteDeTentativasTest extends DatabaseTestCase
{
    private const ESCOPO = LimiteDeTentativas::LOGIN_EMAIL;

    /** @var list<Throwable> */
    private array $logados = [];

    private LimiteDeTentativas $limite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrate();
        $this->logados = [];
        $this->limite = new LimiteDeTentativas(
            [
                self::ESCOPO => ['max' => 3, 'janela' => 900],
                LimiteDeTentativas::CADASTRO_IP => ['max' => 2, 'janela' => 3600],
            ],
            function (Throwable $e): void {
                $this->logados[] = $e;
            }
        );
    }

    private function erros(int $quantos, string $chave = 'k'): void
    {
        for ($i = 0; $i < $quantos; $i++) {
            $this->limite->registrar(self::ESCOPO, $chave);
        }
    }

    public function testNaoBloqueiaAbaixoDoLimite(): void
    {
        $this->erros(2);

        $this->limite->verificar(self::ESCOPO, 'k');
        $this->assertSame(2, (int) $this->db->table('limites_tentativas')->value('contagem'));
    }

    public function testBloqueiaAoAtingirOLimiteComOTempoRestante(): void
    {
        $this->erros(3);

        try {
            $this->limite->verificar(self::ESCOPO, 'k');
            $this->fail('devia bloquear');
        } catch (LimiteExcedidoException $e) {
            $this->assertGreaterThan(890, $e->segundos);
            $this->assertLessThanOrEqual(900, $e->segundos);
            $this->assertSame('Muitas tentativas. Tente de novo em 15 minutos.', $e->getMessage());
        }
    }

    public function testMensagemNoSingularEArredondadaParaCima(): void
    {
        $this->assertSame('Muitas tentativas. Tente de novo em 1 minuto.', (new LimiteExcedidoException(1))->getMessage());
        $this->assertSame('Muitas tentativas. Tente de novo em 1 minuto.', (new LimiteExcedidoException(60))->getMessage());
        $this->assertSame('Muitas tentativas. Tente de novo em 2 minutos.', (new LimiteExcedidoException(61))->getMessage());
    }

    public function testChavesEEscoposSaoIndependentes(): void
    {
        $this->erros(3, 'a');

        $this->limite->verificar(self::ESCOPO, 'b');
        $this->limite->verificar(LimiteDeTentativas::CADASTRO_IP, 'a');
        $this->expectException(LimiteExcedidoException::class);
        $this->limite->verificar(self::ESCOPO, 'a');
    }

    public function testZerarLiberaAChave(): void
    {
        $this->erros(3);
        $this->limite->zerar(self::ESCOPO, 'k');

        $this->limite->verificar(self::ESCOPO, 'k');
        $this->assertSame(0, $this->db->table('limites_tentativas')->count());
    }

    public function testJanelaVencidaRecomecaAContagem(): void
    {
        $this->erros(3);
        $this->db->table('limites_tentativas')->update(['expira_em' => gmdate('Y-m-d H:i:s', time() - 1)]);

        $this->limite->verificar(self::ESCOPO, 'k');
        $this->erros(1);

        $this->assertSame(1, (int) $this->db->table('limites_tentativas')->value('contagem'));
    }

    public function testLinhasVencidasDeOutrasChavesSaoRemovidas(): void
    {
        $this->erros(1, 'velha');
        $this->db->table('limites_tentativas')->update(['expira_em' => gmdate('Y-m-d H:i:s', time() - 5)]);

        $this->erros(1, 'nova');

        $this->assertSame(['nova'], $this->db->table('limites_tentativas')->pluck('chave')->all());
    }

    public function testConsumirPermiteOMaximoEBloqueiaAPartirDoSeguinte(): void
    {
        $this->limite->consumir(LimiteDeTentativas::CADASTRO_IP, 'ip');
        $this->limite->consumir(LimiteDeTentativas::CADASTRO_IP, 'ip');

        $this->expectException(LimiteExcedidoException::class);
        $this->limite->consumir(LimiteDeTentativas::CADASTRO_IP, 'ip');
    }

    public function testChaveEUmHashQueNaoExpoeOValor(): void
    {
        $chave = LimiteDeTentativas::chave('Dona@Exemplo.com');

        $this->assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $chave);
        $this->assertStringNotContainsString('exemplo', $chave);
        $this->assertSame(LimiteDeTentativas::chave(42), LimiteDeTentativas::chave('42'));
    }

    public function testFalhaDoBancoNaoLancaEVaiParaOLog(): void
    {
        $this->db->schema()->drop('limites_tentativas');

        $this->limite->verificar(self::ESCOPO, 'k');
        $this->limite->registrar(self::ESCOPO, 'k');
        $this->limite->consumir(self::ESCOPO, 'k');
        $this->limite->zerar(self::ESCOPO, 'k');

        $this->assertCount(4, $this->logados);
    }

    public function testDaConfigConvertePadroesEMinutosEmSegundos(): void
    {
        $config = (require __DIR__ . '/../api/config.php')['rate_limit'];
        $limite = LimiteDeTentativas::daConfig($config, fn (Throwable $e) => $this->logados[] = $e);

        for ($i = 0; $i < 5; $i++) {
            $limite->consumir(LimiteDeTentativas::CADASTRO_IP, 'ip');
        }
        try {
            $limite->consumir(LimiteDeTentativas::CADASTRO_IP, 'ip');
            $this->fail('o 6º cadastro devia bloquear');
        } catch (LimiteExcedidoException $e) {
            $this->assertGreaterThan(3500, $e->segundos);
        }
        $this->assertSame([], $this->logados);
    }
}
