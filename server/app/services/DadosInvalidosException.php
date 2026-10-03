<?php

declare(strict_types=1);

namespace Services;

use RuntimeException;

/** Dados recusados pela regra de negócio (vira 422 na API). */
final class DadosInvalidosException extends RuntimeException
{
}
