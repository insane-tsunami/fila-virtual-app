<?php

declare(strict_types=1);

namespace Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $email
 * @property string $cnpj
 * @property string $senha_hash
 * @property string $criado_em
 */
final class Conta extends Model
{
    protected $table = 'contas';
    public $timestamps = false;
    protected $guarded = [];
}
