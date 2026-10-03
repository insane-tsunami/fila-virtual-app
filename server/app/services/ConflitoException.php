<?php

declare(strict_types=1);

namespace Services;

use RuntimeException;

/** Operação incompatível com o estado atual da fila (vira 409 na API). */
final class ConflitoException extends RuntimeException
{
}
