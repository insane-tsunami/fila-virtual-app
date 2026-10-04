<?php

declare(strict_types=1);

namespace Services;

/** Envio de e-mail da API. Cada driver (desligado, log, smtp) implementa esta interface. */
interface Mailer
{
    /**
     * Envia uma mensagem de texto simples.
     *
     * @throws MailerFalhouException se a mensagem não pôde ser enviada (a mensagem da exceção é segura para o log)
     */
    public function enviar(string $para, string $assunto, string $texto): void;
}
