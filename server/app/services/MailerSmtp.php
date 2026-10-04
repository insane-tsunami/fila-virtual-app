<?php

declare(strict_types=1);

namespace Services;

use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Envio real por SMTP (MAIL_DSN, remetente MAIL_FROM). Toda falha vira uma MailerFalhouException
 * sem a mensagem original: ela pode trazer o DSN, que tem a senha do servidor.
 */
final class MailerSmtp implements Mailer
{
    public function __construct(
        private readonly string $dsn,
        private readonly string $remetente,
        private ?MailerInterface $mailer = null,
    ) {
    }

    public function enviar(string $para, string $assunto, string $texto): void
    {
        MensagemDeEmail::conferir($para, $assunto);
        if ($this->dsn === '' || $this->remetente === '') {
            throw new MailerFalhouException('SMTP não configurado: defina MAIL_DSN e MAIL_FROM.');
        }

        try {
            $this->mailer ??= new SymfonyMailer(Transport::fromDsn($this->dsn));
            $this->mailer->send(
                (new Email())->from($this->remetente)->to($para)->subject($assunto)->text($texto)
            );
        } catch (Throwable $e) {
            throw new MailerFalhouException('Falha ao enviar o e-mail pelo servidor SMTP (' . $e::class . ').');
        }
    }
}
