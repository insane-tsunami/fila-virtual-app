<?php

declare(strict_types=1);

namespace Services;

/**
 * Só para desenvolvimento e testes: grava a mensagem inteira no log em vez de enviá-la. Como o
 * texto traz o link de redefinição (um segredo), este driver não deve ser usado em produção.
 */
final class MailerLog implements Mailer
{
    /** @param callable(string): void $registrar */
    public function __construct(private $registrar)
    {
    }

    public function enviar(string $para, string $assunto, string $texto): void
    {
        MensagemDeEmail::conferir($para, $assunto);

        ($this->registrar)(sprintf("E-mail (MAIL_DRIVER=log) para %s | assunto: %s\n%s", $para, $assunto, $texto));
    }
}
