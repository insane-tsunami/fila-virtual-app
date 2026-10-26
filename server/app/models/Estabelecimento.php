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
 * @property string|null $avatar_cor
 * @property string|null $avatar_icone
 */
final class Estabelecimento extends Model
{
    protected $table = 'estabelecimentos';
    public $timestamps = false;
    protected $guarded = [];

    /**
     * Dados da loja em todas as respostas da API (consulta pública, endereço, avatar, cadastro,
     * login e GET /api/conta). Avatar só com as duas partes; senão null (o dashboard mostra a inicial).
     *
     * @return array{nome: string, slug: string, endereco_publico: string|null, avatar: array{cor: string, icone: string}|null}
     */
    public function paraResposta(): array
    {
        return [
            'nome' => $this->nome,
            'slug' => $this->slug,
            'endereco_publico' => $this->endereco_publico,
            'avatar' => $this->avatar_cor !== null && $this->avatar_icone !== null
                ? ['cor' => $this->avatar_cor, 'icone' => $this->avatar_icone]
                : null,
        ];
    }
}
