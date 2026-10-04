<?php

declare(strict_types=1);

namespace Services;

use RuntimeException;

/** Credencial ausente, errada ou vencida (vira 401 na API). */
final class NaoAutenticadoException extends RuntimeException
{
}
