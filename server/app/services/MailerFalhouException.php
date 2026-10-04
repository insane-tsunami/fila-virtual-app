<?php

declare(strict_types=1);

namespace Services;

use RuntimeException;

/**
 * O e-mail não saiu. A mensagem nunca traz o DSN, a senha do servidor nem o conteúdo da mensagem
 * (que pode ter o link de redefinição): é segura para ir ao log.
 */
final class MailerFalhouException extends RuntimeException
{
}
