<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

// Prazo para confirmar o e-mail: depois dele, uma conta não confirmada e sem clientes na fila pode
// ser substituída por um cadastro novo com o mesmo e-mail ou CNPJ. As contas não confirmadas que já
// existem ganham 14 dias a partir desta migração (e não do cadastro delas); as confirmadas ficam com
// nulo. Os 14 dias estão repetidos aqui de propósito: a migração é um retrato e não lê o código atual.
return new class {
    private const PRAZO_DIAS = 14;

    public function up(Capsule $db): void
    {
        $db->schema()->table('contas', function (Blueprint $table): void {
            $table->dateTime('confirmar_ate')->nullable();
        });

        $db->table('contas')
            ->whereNull('email_confirmado_em')
            ->update(['confirmar_ate' => gmdate('Y-m-d H:i:s', time() + self::PRAZO_DIAS * 86400)]);
    }
};
