<?php

declare(strict_types=1);

namespace Support;

/**
 * Endereço público de uma loja: apenas uma ORIGEM (esquema, host e porta opcional),
 * porque o front roda na raiz do site. Rejeita credenciais, caminho, query e
 * fragmento, esquemas que não sejam http(s) e hosts fora de ASCII (use punycode).
 */
final class EnderecoPublico
{
    public const TAMANHO_MAXIMO = 255;

    // `\z` (e não `$`) para não aceitar uma quebra de linha no fim; `i` aceita HTTPS em maiúsculas.
    private const PADRAO = '#^(?<esquema>https?)://(?<host>[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?)*)(?::(?<porta>[0-9]{1,5}))?/?\z#i';

    private const ROTULO_MAXIMO = 63;
    private const HOST_MAXIMO = 253;

    /**
     * Devolve a origem normalizada (esquema e host em minúsculas, sem a barra final)
     * ou null se o valor não for uma origem http(s) válida.
     */
    public static function normalizar(mixed $valor): ?string
    {
        if (!is_string($valor) || $valor === '' || strlen($valor) > self::TAMANHO_MAXIMO) {
            return null;
        }
        if (preg_match(self::PADRAO, $valor, $partes) !== 1) {
            return null;
        }

        $porta = $partes['porta'] ?? '';
        if ($porta !== '' && ((int) $porta < 1 || (int) $porta > 65535)) {
            return null;
        }

        $host = $partes['host'];
        if (strlen($host) > self::HOST_MAXIMO) {
            return null;
        }
        foreach (explode('.', $host) as $rotulo) {
            if (strlen($rotulo) > self::ROTULO_MAXIMO) {
                return null;
            }
        }

        return strtolower($partes['esquema']) . '://' . strtolower($host)
            . ($porta === '' ? '' : ':' . (int) $porta);
    }
}
