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
 * @property string|null $email_confirmado_em
 */
final class Conta extends Model
{
    protected $table = 'contas';
    public $timestamps = false;
    protected $guarded = [];

    /** Dados da conta nas respostas da API (cadastro, login e GET /api/conta). */
    public function paraResposta(): array
    {
        return [
            'email' => $this->email,
            'cnpj' => $this->cnpj,
            'email_confirmado' => $this->email_confirmado_em !== null,
        ];
    }
}
