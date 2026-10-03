<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

// Endereço público da loja (origem do front em que a página /fila/<slug> está
// publicada). Opcional: sem ele, o QR code usa a origem do próprio front.
return new class {
    public function up(Capsule $db): void
    {
        $db->schema()->table('estabelecimentos', function (Blueprint $table): void {
            $table->string('endereco_publico', 255)->nullable();
        });
    }
};
