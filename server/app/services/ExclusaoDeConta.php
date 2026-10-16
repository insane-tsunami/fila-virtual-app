<?php

declare(strict_types=1);

namespace Services;

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Apaga uma conta com tudo o que é dela: a loja (e o slug), as entradas da fila da loja, as sessões,
 * os links de confirmação e de redefinição e os contadores de tentativas presos à conta. Não abre
 * transação: roda dentro da do chamador, que decide o que acontece se algo depois falhar.
 *
 * Os contadores por e-mail e por IP ficam: são hashes, vencem sozinhos, e apagá-los deixaria
 * qualquer um zerar o limite de login de um e-mail.
 */
final class ExclusaoDeConta
{
    public function excluir(int $contaId): void
    {
        // Ordem das chaves estrangeiras: primeiro o que aponta para a loja, depois o que aponta para a conta.
        $lojas = Capsule::table('estabelecimentos')->where('conta_id', $contaId)->pluck('id')->all();
        if ($lojas !== []) {
            Capsule::table('entradas_fila')->whereIn('estabelecimento_id', $lojas)->delete();
            Capsule::table('estabelecimentos')->whereIn('id', $lojas)->delete();
        }

        foreach (['confirmacoes_email', 'redefinicoes_senha', 'sessoes'] as $tabela) {
            Capsule::table($tabela)->where('conta_id', $contaId)->delete();
        }

        Capsule::table('limites_tentativas')
            ->whereIn('escopo', [LimiteDeTentativas::SENHA_CONTA, LimiteDeTentativas::CONFIRMACAO_CONTA])
            ->where('chave', LimiteDeTentativas::chave($contaId))
            ->delete();

        Capsule::table('contas')->where('id', $contaId)->delete();
    }
}
