<?php

declare(strict_types=1);

namespace Support;

use Illuminate\Database\Capsule\Manager as Capsule;

/** Loja de demonstração `veste-bem`: sem dona e sem endereço, criada só sob demanda (`bin/seed-demo`). */
final class LojaDeDemonstracao
{
    public const SLUG = 'veste-bem';
    public const NOME = 'Veste Bem';

    /** Cria a loja se ainda não existir; devolve se criou. */
    public static function semear(Capsule $db): bool
    {
        if ($db->table('estabelecimentos')->where('slug', self::SLUG)->exists()) {
            return false;
        }

        $db->table('estabelecimentos')->insert(['nome' => self::NOME, 'slug' => self::SLUG]);

        return true;
    }
}
