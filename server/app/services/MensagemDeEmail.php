<?php

declare(strict_types=1);

namespace Services;

/** Confere destinatário e assunto antes de qualquer driver: quebra de linha permitiria injetar cabeçalhos. */
final class MensagemDeEmail
{
    /** @throws MailerFalhouException */
    public static function conferir(string $para, string $assunto): void
    {
        if ($para === '' || preg_match('/[\r\n]/', $para . $assunto) === 1) {
            throw new MailerFalhouException('Destinatário ou assunto inválido (vazio ou com quebra de linha).');
        }
    }
}
