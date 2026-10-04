<?php

declare(strict_types=1);

namespace Services;

/** Escolhe o driver de e-mail pela configuração (`mail` em api/config.php). */
final class FabricaDeMailer
{
    /**
     * @param array{driver?: string, dsn?: string, from?: string} $mail
     * @param callable(string): void $registrar recebe os avisos e, no driver `log`, as mensagens
     */
    public static function criar(array $mail, callable $registrar): Mailer
    {
        $driver = strtolower(trim($mail['driver'] ?? ''));

        return match ($driver) {
            'log' => new MailerLog($registrar),
            'smtp' => new MailerSmtp($mail['dsn'] ?? '', $mail['from'] ?? ''),
            'desligado', '' => new MailerDesligado($registrar),
            default => self::desconhecido($driver, $registrar),
        };
    }

    /** @param callable(string): void $registrar */
    private static function desconhecido(string $driver, callable $registrar): Mailer
    {
        $registrar(sprintf(
            "MAIL_DRIVER '%s' não é reconhecido (use desligado, log ou smtp): usando desligado.",
            $driver
        ));

        return new MailerDesligado($registrar);
    }
}
