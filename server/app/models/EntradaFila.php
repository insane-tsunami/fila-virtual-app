<?php

declare(strict_types=1);

namespace Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $estabelecimento_id
 * @property string $codigo
 * @property string|null $telefone apagado (NULL) pela retenção após o prazo das entradas encerradas
 * @property string $status
 * @property string $entrou_em
 * @property string|null $iniciou_em
 * @property string|null $finalizou_em hora em que a entrada saiu da fila (finalizada ou cancelada)
 */
final class EntradaFila extends Model
{
    public const AGUARDANDO = 'aguardando';
    public const EM_ATENDIMENTO = 'em_atendimento';
    public const FINALIZADO = 'finalizado';
    /** Encerrada sem atendimento: a retenção produz este status nas entradas esquecidas na fila. */
    public const CANCELADO = 'cancelado';

    /** Status que contam como "na fila". */
    public const ATIVOS = [self::AGUARDANDO, self::EM_ATENDIMENTO];

    protected $table = 'entradas_fila';
    public $timestamps = false;
    protected $guarded = [];
}
