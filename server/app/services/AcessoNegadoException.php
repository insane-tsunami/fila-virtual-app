<?php

declare(strict_types=1);

namespace Services;

use RuntimeException;

/** Autenticada, mas sem direito sobre o recurso, como a loja de outra conta (vira 403 na API). */
final class AcessoNegadoException extends RuntimeException
{
}
