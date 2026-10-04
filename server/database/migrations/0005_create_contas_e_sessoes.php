<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

// Contas dos estabelecimentos, as sessões de login e o vínculo conta -> loja.
// `estabelecimentos.conta_id` fica sem chave estrangeira de propósito: o SQLite não
// acrescenta restrição a uma tabela existente; a integridade é garantida no cadastro.
return new class {
    public function up(Capsule $db): void
    {
        $db->schema()->create('contas', function (Blueprint $table): void {
            $table->id();
            $table->string('email', 254)->unique();
            $table->char('cnpj', 14)->unique();
            $table->string('senha_hash', 255);
            $table->dateTime('criado_em');
        });

        $db->schema()->create('sessoes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas');
            $table->char('token_hash', 64)->unique();
            $table->dateTime('criado_em');
            $table->dateTime('expira_em');
        });

        $db->schema()->table('estabelecimentos', function (Blueprint $table): void {
            $table->unsignedBigInteger('conta_id')->nullable()->unique();
        });
    }
};
