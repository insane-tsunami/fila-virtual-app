<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

// Avatar da loja: uma cor e um ícone de uma lista fixa (ver Support\Avatar), gravados juntos.
// As duas nulas = sem avatar (a barra lateral mostra a inicial). As lojas existentes ficam assim.
return new class {
    public function up(Capsule $db): void
    {
        $db->schema()->table('estabelecimentos', function (Blueprint $table): void {
            $table->string('avatar_cor', 20)->nullable();
            $table->string('avatar_icone', 20)->nullable();
        });
    }
};
