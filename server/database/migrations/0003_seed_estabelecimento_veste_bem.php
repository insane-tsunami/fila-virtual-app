<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;

// Estabelecimento fixo da fatia A (sem multi-loja nem login).
return new class {
    public function up(Capsule $db): void
    {
        $db->table('estabelecimentos')->insert([
            'nome' => 'Veste Bem',
            'slug' => 'veste-bem',
        ]);
    }
};
