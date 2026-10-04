<?php

declare(strict_types=1);

namespace Support;

/**
 * Senhas das contas: bcrypt (custo 10). O bcrypt ignora tudo depois de 72 bytes, então
 * senhas maiores são recusadas em vez de truncadas em silêncio.
 */
final class Senha
{
    public const MINIMO_BYTES = 8;
    public const MAXIMO_BYTES = 72;
    public const CUSTO = 10;
    public const MENSAGEM_INVALIDA = 'Senha inválida: use de 8 a 72 caracteres (letras acentuadas contam como 2).';

    // Hash fixo (mesmo custo) para gastar o mesmo tempo quando o e-mail não existe.
    private const HASH_FALSO = '$2y$10$BfrpEpL3q/5qzYien2EWAOfspp/MJYJnq9VZVU/meb5uXI87nmtvy';

    public static function valida(mixed $senha): bool
    {
        return is_string($senha)
            && strlen($senha) >= self::MINIMO_BYTES
            && strlen($senha) <= self::MAXIMO_BYTES;
    }

    public static function gerarHash(string $senha): string
    {
        return password_hash($senha, PASSWORD_BCRYPT, ['cost' => self::CUSTO]);
    }

    /** Com $hash nulo (conta inexistente) confere contra um hash falso e devolve sempre false. */
    public static function confere(string $senha, ?string $hash): bool
    {
        $ok = password_verify($senha, $hash ?? self::HASH_FALSO);

        return $hash !== null && $ok;
    }
}
