<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

// Confirmação do e-mail da conta. `email_confirmado_em` nulo = não confirmado (as contas que já
// existem ficam assim, sem anistia). A tabela guarda só o hash do token, no máximo um por conta
// (o pedido novo substitui o anterior) e o e-mail para o qual ele foi enviado.
return new class {
    public function up(Capsule $db): void
    {
        $db->schema()->table('contas', function (Blueprint $table): void {
            $table->dateTime('email_confirmado_em')->nullable();
        });

        $db->schema()->create('confirmacoes_email', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conta_id')->unique()->constrained('contas');
            $table->string('email', 254);
            $table->char('token_hash', 64)->unique();
            $table->dateTime('criado_em');
            $table->dateTime('expira_em');
        });
    }
};
