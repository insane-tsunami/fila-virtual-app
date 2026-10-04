<?php

declare(strict_types=1);

namespace Support;

/** E-mail de uma conta: sem espaços nas pontas, em minúsculas, com formato válido e até 254 caracteres. */
final class Email
{
    public const TAMANHO_MAXIMO = 254;

    /** Devolve o e-mail normalizado ou null se o valor não for um e-mail válido. */
    public static function normalizar(mixed $valor): ?string
    {
        if (!is_string($valor)) {
            return null;
        }

        $email = mb_strtolower(trim($valor));
        if ($email === '' || strlen($email) > self::TAMANHO_MAXIMO) {
            return null;
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) === false ? null : $email;
    }
}
