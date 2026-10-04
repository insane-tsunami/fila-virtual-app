<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

// Tokens de redefinição de senha: só o hash do token, no máximo um por conta (o pedido novo
// substitui o anterior) e de uso único (concluir a redefinição apaga o da conta).
return new class {
    public function up(Capsule $db): void
    {
        $db->schema()->create('redefinicoes_senha', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conta_id')->unique()->constrained('contas');
            $table->char('token_hash', 64)->unique();
            $table->dateTime('criado_em');
            $table->dateTime('expira_em');
        });
    }
};
