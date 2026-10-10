<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;

// A `veste-bem` semeada pela 0003 é só uma demonstração (agora criada por `php bin/seed-demo`).
// Esta migração a tira dos bancos em que ela não serve para nada: sem dona e sem nenhuma entrada
// na fila. Com dona ou com entradas ela é preservada (com entradas, avisa). A 0003 não é alterada.
return new class {
    public function up(Capsule $db): void
    {
        $loja = $db->table('estabelecimentos')->where('slug', 'veste-bem')->first();
        if ($loja === null || $loja->conta_id !== null) {
            return;
        }

        if ($db->table('entradas_fila')->where('estabelecimento_id', $loja->id)->exists()) {
            error_log(
                'A loja de demonstração veste-bem foi mantida porque tem entradas na fila. '
                . 'Apague-a manualmente se não precisar mais dela.'
            );

            return;
        }

        $db->table('estabelecimentos')->where('id', $loja->id)->delete();
    }
};
