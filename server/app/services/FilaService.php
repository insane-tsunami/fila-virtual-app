<?php

declare(strict_types=1);

namespace Services;

use Illuminate\Database\Capsule\Manager as Capsule;
use Models\EntradaFila;
use Models\Estabelecimento;
use Support\Telefone;

/**
 * Regras da fila de um estabelecimento. Cada operação que escreve roda em uma
 * transação que antes trava a linha do estabelecimento, serializando entrar e
 * finalizar por estabelecimento (duplo clique, corrida de duas finalizações).
 *
 * Invariante: no máximo uma entrada `em_atendimento` por estabelecimento, e ela é
 * sempre a primeira da fila ativa. A ordem de chegada é a do `id` da entrada.
 */
final class FilaService
{
    /**
     * @return array{entrada: array{codigo: string, posicao: int|null, status: string}, criada: bool}
     */
    public function entrar(string $slug, mixed $telefone): array
    {
        $normalizado = Telefone::normalizar($telefone)
            ?? throw new DadosInvalidosException(
                'Telefone inválido: informe de 10 a 13 dígitos (DDD e número, com ou sem DDI 55).'
            );

        return Capsule::connection()->transaction(function () use ($slug, $normalizado): array {
            $estabelecimento = $this->travarEstabelecimento($slug);

            $existente = $this->ativas($estabelecimento)->where('telefone', $normalizado)->first();
            if ($existente !== null) {
                return ['entrada' => $this->resumo($existente), 'criada' => false];
            }

            $filaVazia = !$this->ativas($estabelecimento)->exists();
            $agora = gmdate('Y-m-d H:i:s');

            $entrada = EntradaFila::create([
                'estabelecimento_id' => $estabelecimento->id,
                'codigo' => bin2hex(random_bytes(12)),
                'telefone' => $normalizado,
                'status' => $filaVazia ? EntradaFila::EM_ATENDIMENTO : EntradaFila::AGUARDANDO,
                'entrou_em' => $agora,
                'iniciou_em' => $filaVazia ? $agora : null,
            ]);

            return ['entrada' => $this->resumo($entrada), 'criada' => true];
        });
    }

    /** @return array{codigo: string, posicao: int|null, status: string} */
    public function consultar(string $slug, string $codigo): array
    {
        $estabelecimento = $this->estabelecimento($slug);

        return $this->resumo($this->entrada($estabelecimento, $codigo));
    }

    /**
     * Fila ativa em ordem de chegada, com o telefone mascarado.
     *
     * @return list<array{codigo: string, posicao: int, status: string, telefone: string}>
     */
    public function listar(string $slug): array
    {
        $estabelecimento = $this->estabelecimento($slug);
        $lista = [];

        foreach ($this->ativas($estabelecimento)->orderBy('id')->get() as $indice => $entrada) {
            $lista[] = [
                'codigo' => $entrada->codigo,
                'posicao' => $indice + 1,
                'status' => $entrada->status,
                'telefone' => Telefone::mascarar($entrada->telefone),
            ];
        }

        return $lista;
    }

    /**
     * Finaliza a entrada em atendimento e promove a ativa mais antiga.
     *
     * @return array{
     *   finalizada: array{codigo: string, posicao: int|null, status: string},
     *   atual: array{codigo: string, posicao: int|null, status: string}|null
     * }
     */
    public function finalizar(string $slug, string $codigo): array
    {
        return Capsule::connection()->transaction(function () use ($slug, $codigo): array {
            $estabelecimento = $this->travarEstabelecimento($slug);
            $entrada = $this->entrada($estabelecimento, $codigo);

            if ($entrada->status !== EntradaFila::EM_ATENDIMENTO) {
                throw new ConflitoException('Só a entrada em atendimento pode ser finalizada.');
            }

            $entrada->update([
                'status' => EntradaFila::FINALIZADO,
                'finalizou_em' => gmdate('Y-m-d H:i:s'),
            ]);

            $proxima = $this->ativas($estabelecimento)->orderBy('id')->first();
            $proxima?->update([
                'status' => EntradaFila::EM_ATENDIMENTO,
                'iniciou_em' => gmdate('Y-m-d H:i:s'),
            ]);

            return [
                'finalizada' => $this->resumo($entrada),
                'atual' => $proxima === null ? null : $this->resumo($proxima),
            ];
        });
    }

    /** @return array{codigo: string, posicao: int|null, status: string} */
    private function resumo(EntradaFila $entrada): array
    {
        return [
            'codigo' => $entrada->codigo,
            'posicao' => $this->posicao($entrada),
            'status' => $entrada->status,
        ];
    }

    /** 1 para quem está em atendimento, 2 para o próximo...; null se não está mais na fila. */
    private function posicao(EntradaFila $entrada): ?int
    {
        if (!in_array($entrada->status, EntradaFila::ATIVOS, true)) {
            return null;
        }

        return EntradaFila::query()
            ->where('estabelecimento_id', $entrada->estabelecimento_id)
            ->whereIn('status', EntradaFila::ATIVOS)
            ->where('id', '<=', $entrada->id)
            ->count();
    }

    /** @return \Illuminate\Database\Eloquent\Builder<EntradaFila> */
    private function ativas(Estabelecimento $estabelecimento)
    {
        return EntradaFila::query()
            ->where('estabelecimento_id', $estabelecimento->id)
            ->whereIn('status', EntradaFila::ATIVOS);
    }

    private function estabelecimento(string $slug): Estabelecimento
    {
        return Estabelecimento::query()->where('slug', $slug)->first()
            ?? throw new NaoEncontradoException('Estabelecimento não encontrado.');
    }

    private function travarEstabelecimento(string $slug): Estabelecimento
    {
        return Estabelecimento::query()->where('slug', $slug)->lockForUpdate()->first()
            ?? throw new NaoEncontradoException('Estabelecimento não encontrado.');
    }

    private function entrada(Estabelecimento $estabelecimento, string $codigo): EntradaFila
    {
        return EntradaFila::query()
            ->where('estabelecimento_id', $estabelecimento->id)
            ->where('codigo', $codigo)
            ->first()
            ?? throw new NaoEncontradoException('Entrada não encontrada.');
    }
}
