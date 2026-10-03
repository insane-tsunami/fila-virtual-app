<?php

declare(strict_types=1);

namespace Services;

use RuntimeException;

/** Recurso inexistente (vira 404 na API). */
final class NaoEncontradoException extends RuntimeException
{
}
