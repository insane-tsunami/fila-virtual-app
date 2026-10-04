<?php

declare(strict_types=1);

namespace Services;

/** Padrão: não envia nada e não guarda o conteúdo (que pode ter um link secreto); só avisa no log. */
final class MailerDesligado implements Mailer
{
    /** @param callable(string): void $registrar */
    public function __construct(private $registrar)
    {
    }

    public function enviar(string $para, string $assunto, string $texto): void
    {
        MensagemDeEmail::conferir($para, $assunto);

        ($this->registrar)('Envio de e-mail desligado (MAIL_DRIVER=desligado): a mensagem não foi enviada.');
    }
}
