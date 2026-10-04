<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

// Contadores do limite de tentativas (login, cadastro, troca de senha). Uma linha por
// (escopo, chave) e janela: a chave é um hash (e-mail, IP ou id da conta), nunca o valor em claro.
return new class {
    public function up(Capsule $db): void
    {
        $db->schema()->create('limites_tentativas', function (Blueprint $table): void {
            $table->string('escopo', 32);
            $table->char('chave', 64);
            $table->unsignedInteger('contagem')->default(0);
            $table->dateTime('expira_em');

            $table->primary(['escopo', 'chave']);
            $table->index('expira_em');
        });
    }
};
