<?php

declare(strict_types=1);

namespace Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $conta_id
 * @property string $token_hash
 * @property string $criado_em
 * @property string $expira_em
 */
final class Sessao extends Model
{
    protected $table = 'sessoes';
    public $timestamps = false;
    protected $guarded = [];
}
