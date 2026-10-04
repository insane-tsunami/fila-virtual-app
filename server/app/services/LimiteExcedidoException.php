<?php

declare(strict_types=1);

namespace Services;

use RuntimeException;

/** Limite de tentativas esgotado (vira 429 na API, com Retry-After). */
final class LimiteExcedidoException extends RuntimeException
{
    public function __construct(public readonly int $segundos)
    {
        $minutos = max(1, (int) ceil($segundos / 60));

        parent::__construct(sprintf(
            'Muitas tentativas. Tente de novo em %d %s.',
            $minutos,
            $minutos === 1 ? 'minuto' : 'minutos'
        ));
    }
}
