<?php

declare(strict_types=1);

namespace Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $estabelecimento_id
 * @property string $codigo
 * @property string $telefone
 * @property string $status
 * @property string $entrou_em
 * @property string|null $iniciou_em
 * @property string|null $finalizou_em
 */
final class EntradaFila extends Model
{
    public const AGUARDANDO = 'aguardando';
    public const EM_ATENDIMENTO = 'em_atendimento';
    public const FINALIZADO = 'finalizado';
    /** Reservado no esquema; nenhuma operação produz este status ainda. */
    public const CANCELADO = 'cancelado';

    /** Status que contam como "na fila". */
    public const ATIVOS = [self::AGUARDANDO, self::EM_ATENDIMENTO];

    protected $table = 'entradas_fila';
    public $timestamps = false;
    protected $guarded = [];
}
