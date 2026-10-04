<?php

declare(strict_types=1);

namespace Tests;

use Services\Mailer;

/** Mailer dos testes: guarda as mensagens (ou falha, se pedido) em vez de enviar. */
final class MailerEmMemoria implements Mailer
{
    /** @var list<array{para: string, assunto: string, texto: string}> */
    public array $enviados = [];

    public function __construct(private readonly ?\Throwable $falha = null)
    {
    }

    public function enviar(string $para, string $assunto, string $texto): void
    {
        if ($this->falha !== null) {
            throw $this->falha;
        }
        $this->enviados[] = ['para' => $para, 'assunto' => $assunto, 'texto' => $texto];
    }

    /** Token (64 hex) do link da última mensagem, ou null. */
    public function ultimoToken(): ?string
    {
        $ultima = end($this->enviados);

        return $ultima !== false && preg_match('/#token=([0-9a-f]{64})/', $ultima['texto'], $m) === 1 ? $m[1] : null;
    }
}
