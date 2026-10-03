<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return new class {
    public function up(Capsule $db): void
    {
        $db->schema()->create('entradas_fila', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('estabelecimento_id')->constrained('estabelecimentos');
            $table->string('codigo', 32)->unique();
            $table->string('telefone', 20);
            // aguardando | em_atendimento | finalizado | cancelado (validado pela aplicação)
            $table->string('status', 20)->default('aguardando');
            $table->dateTime('entrou_em');
            $table->dateTime('iniciou_em')->nullable();
            $table->dateTime('finalizou_em')->nullable();

            $table->index(['estabelecimento_id', 'status', 'id']);
        });
    }
};
