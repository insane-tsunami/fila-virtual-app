<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

// Retenção de dados: o telefone das entradas encerradas há mais de 30 dias é apagado
// (fica NULL), então a coluna passa a aceitar NULL. O índice em `finalizou_em` serve
// ao corte das entradas antigas e ao encerramento das esquecidas.
return new class {
    public function up(Capsule $db): void
    {
        $db->schema()->table('entradas_fila', function (Blueprint $table): void {
            $table->string('telefone', 20)->nullable()->change();
            $table->index('finalizou_em');
        });
    }
};
