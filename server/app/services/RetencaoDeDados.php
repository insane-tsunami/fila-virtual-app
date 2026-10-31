<?php

declare(strict_types=1);

namespace Services;

use Illuminate\Database\Capsule\Manager as Capsule;
use Models\EntradaFila;
use Models\Estabelecimento;

/**
 * Retenção de dados, executada pelo comando `php bin/limpar` (agendado na hospedagem).
 * Cada execução é idempotente e aplica quatro regras:
 *
 * - encerra como `cancelado` as entradas ativas paradas há mais horas que o prazo;
 * - apaga o telefone das entradas encerradas há mais dias que o prazo;
 * - remove sessões, tokens de redefinição/confirmação e contadores de tentativas vencidos;
 * - nunca apaga contas, lojas ou entradas inteiras.
 *
 * O encerramento segue o `FilaService`: uma transação por estabelecimento, que trava a
 * loja antes de mexer na fila, para não correr com `entrar` e `finalizar`.
 */
final class RetencaoDeDados
{
    /** Prazo de uma entrada ativa: a última mudança de estado (entrou ou começou o atendimento). */
    private const ULTIMA_MUDANCA = 'COALESCE(iniciou_em, entrou_em)';

    public function __construct(
        private readonly int $horasAteEncerrar,
        private readonly int $diasAteApagarTelefone,
    ) {
    }

    /**
     * Roda todas as regras e devolve quantas linhas cada uma mudou.
     *
     * @return array{entradas_encerradas: int, telefones_apagados: int, sessoes: int, tokens: int, contadores: int}
     */
    public function limpar(): array
    {
        $agora = gmdate('Y-m-d H:i:s');

        return [
            'entradas_encerradas' => $this->encerrarEsquecidas($agora),
            'telefones_apagados' => $this->apagarTelefones($agora),
            'sessoes' => $this->removerVencidos('sessoes', '<', $agora),
            'tokens' => $this->removerVencidos('redefinicoes_senha', '<=', $agora)
                + $this->removerVencidos('confirmacoes_email', '<=', $agora),
            'contadores' => $this->removerVencidos('limites_tentativas', '<=', $agora),
        ];
    }

    /** Encerra as ativas esquecidas, loja a loja, com a trava da fila. */
    private function encerrarEsquecidas(string $agora): int
    {
        $corte = gmdate('Y-m-d H:i:s', strtotime($agora . ' UTC') - $this->horasAteEncerrar * 3600);
        $lojas = EntradaFila::query()
            ->whereIn('status', EntradaFila::ATIVOS)
            ->whereRaw(self::ULTIMA_MUDANCA . ' < ?', [$corte])
            ->distinct()
            ->pluck('estabelecimento_id');

        $encerradas = 0;
        foreach ($lojas as $lojaId) {
            $encerradas += Capsule::connection()->transaction(function () use ($lojaId, $corte, $agora): int {
                Estabelecimento::query()->whereKey($lojaId)->lockForUpdate()->first();

                // Repete a busca dentro da trava: outra operação pode ter mexido na fila.
                $vencidas = EntradaFila::query()
                    ->where('estabelecimento_id', $lojaId)
                    ->whereIn('status', EntradaFila::ATIVOS)
                    ->whereRaw(self::ULTIMA_MUDANCA . ' < ?', [$corte])
                    ->get();

                foreach ($vencidas as $entrada) {
                    $entrada->update(['status' => EntradaFila::CANCELADO, 'finalizou_em' => $agora]);
                }

                $this->garantirAtendimento((int) $lojaId, $agora);

                return $vencidas->count();
            }, 3);
        }

        return $encerradas;
    }

    /** Mantém a invariante da fila: se há ativas e ninguém em atendimento, promove a mais antiga. */
    private function garantirAtendimento(int $lojaId, string $agora): void
    {
        $ativas = EntradaFila::query()
            ->where('estabelecimento_id', $lojaId)
            ->whereIn('status', EntradaFila::ATIVOS);

        $jaTemAtendimento = (clone $ativas)->where('status', EntradaFila::EM_ATENDIMENTO)->exists();
        if ($jaTemAtendimento) {
            return;
        }

        (clone $ativas)->orderBy('id')->first()?->update([
            'status' => EntradaFila::EM_ATENDIMENTO,
            'iniciou_em' => $agora,
        ]);
    }

    /**
     * Apaga o telefone das encerradas antigas. Entradas encerradas não mudam mais, então o
     * UPDATE único não disputa com a fila; as ativas nunca entram no filtro.
     */
    private function apagarTelefones(string $agora): int
    {
        $corte = gmdate('Y-m-d H:i:s', strtotime($agora . ' UTC') - $this->diasAteApagarTelefone * 86400);

        return EntradaFila::query()
            ->whereIn('status', [EntradaFila::FINALIZADO, EntradaFila::CANCELADO])
            ->whereNotNull('telefone')
            ->where('finalizou_em', '<', $corte)
            ->update(['telefone' => null]);
    }

    private function removerVencidos(string $tabela, string $operador, string $agora): int
    {
        return Capsule::table($tabela)->where('expira_em', $operador, $agora)->delete();
    }
}
