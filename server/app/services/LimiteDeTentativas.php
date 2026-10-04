<?php

declare(strict_types=1);

namespace Services;

use Illuminate\Database\Capsule\Manager as Capsule;
use Throwable;

/**
 * Limite de tentativas por (escopo, chave) com janela fixa, guardado no banco. A linha nasce
 * na primeira tentativa e vale até `expira_em`; vencida, recomeça. O incremento é feito pelo
 * banco. Se o banco dos contadores falhar, o limite é ignorado (a falha vai para o logger):
 * ele é uma defesa extra e não pode derrubar o login legítimo.
 */
final class LimiteDeTentativas
{
    public const LOGIN_EMAIL = 'login_email';
    public const LOGIN_IP = 'login_ip';
    public const CADASTRO_IP = 'cadastro_ip';
    public const SENHA_CONTA = 'senha_conta';

    /** @var callable(Throwable): void */
    private $logger;

    /**
     * @param array<string, array{max: int, janela: int}> $limites por escopo: máximo de tentativas e janela em segundos
     * @param (callable(Throwable): void)|null $logger
     */
    public function __construct(private readonly array $limites, ?callable $logger = null)
    {
        $this->logger = $logger ?? static function (Throwable $e): void {
            error_log(sprintf('[limite] %s: %s', $e::class, $e->getMessage()));
        };
    }

    /**
     * Monta os limites a partir da seção `rate_limit` de api/config.php (minutos viram segundos).
     *
     * @param array<string, int> $config
     * @param (callable(Throwable): void)|null $logger
     */
    public static function daConfig(array $config, ?callable $logger = null): self
    {
        $janela = $config['janela_minutos'] * 60;

        return new self([
            self::LOGIN_EMAIL => ['max' => $config['login_email'], 'janela' => $janela],
            self::LOGIN_IP => ['max' => $config['login_ip'], 'janela' => $janela],
            self::SENHA_CONTA => ['max' => $config['senha_conta'], 'janela' => $janela],
            self::CADASTRO_IP => ['max' => $config['cadastro_ip'], 'janela' => $config['cadastro_janela_minutos'] * 60],
        ], $logger);
    }

    /** Hash da chave: o e-mail (ou o IP) nunca fica em claro no banco. */
    public static function chave(string|int $valor): string
    {
        return hash('sha256', (string) $valor);
    }

    /** Lança se a chave já esgotou o limite. Não altera nada. */
    public function verificar(string $escopo, string $chave): void
    {
        $this->seguro(function () use ($escopo, $chave): void {
            $linha = Capsule::table('limites_tentativas')
                ->where('escopo', $escopo)->where('chave', $chave)
                ->where('expira_em', '>', gmdate('Y-m-d H:i:s'))
                ->first();

            if ($linha !== null && (int) $linha->contagem >= $this->limite($escopo)['max']) {
                throw new LimiteExcedidoException($this->segundosAte($linha->expira_em));
            }
        });
    }

    /** Conta uma tentativa (erro) na janela da chave. */
    public function registrar(string $escopo, string $chave): void
    {
        $this->seguro(function () use ($escopo, $chave): void {
            $this->incrementar($escopo, $chave);
        });
    }

    /** Conta a tentativa e lança se ela passou do limite (usado onde toda chamada conta). */
    public function consumir(string $escopo, string $chave): void
    {
        $this->seguro(function () use ($escopo, $chave): void {
            $linha = $this->incrementar($escopo, $chave);

            if ((int) $linha->contagem > $this->limite($escopo)['max']) {
                throw new LimiteExcedidoException($this->segundosAte($linha->expira_em));
            }
        });
    }

    public function zerar(string $escopo, string $chave): void
    {
        $this->seguro(function () use ($escopo, $chave): void {
            Capsule::table('limites_tentativas')->where('escopo', $escopo)->where('chave', $chave)->delete();
        });
    }

    private function incrementar(string $escopo, string $chave): object
    {
        $agora = gmdate('Y-m-d H:i:s');
        $tabela = static fn () => Capsule::table('limites_tentativas');

        // Descarta o que já venceu (de qualquer chave) para a tabela não crescer.
        $tabela()->where('expira_em', '<=', $agora)->delete();
        $tabela()->insertOrIgnore([
            'escopo' => $escopo,
            'chave' => $chave,
            'contagem' => 0,
            'expira_em' => gmdate('Y-m-d H:i:s', time() + $this->limite($escopo)['janela']),
        ]);
        $tabela()->where('escopo', $escopo)->where('chave', $chave)->increment('contagem');

        return $tabela()->where('escopo', $escopo)->where('chave', $chave)->first();
    }

    /** @return array{max: int, janela: int} */
    private function limite(string $escopo): array
    {
        return $this->limites[$escopo];
    }

    private function segundosAte(string $expiraEm): int
    {
        return max(1, strtotime($expiraEm . ' UTC') - time());
    }

    /** Roda a operação; só o limite excedido passa, qualquer outra falha vira log. */
    private function seguro(callable $operacao): void
    {
        try {
            $operacao();
        } catch (LimiteExcedidoException $e) {
            throw $e;
        } catch (Throwable $e) {
            ($this->logger)($e);
        }
    }
}
