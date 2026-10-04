<?php

declare(strict_types=1);

namespace Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $nome
 * @property string $slug
 * @property string|null $endereco_publico
 * @property int|null $conta_id
 */
final class Estabelecimento extends Model
{
    protected $table = 'estabelecimentos';
    public $timestamps = false;
    protected $guarded = [];
}
