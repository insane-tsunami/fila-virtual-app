<?php

declare(strict_types=1);

namespace Support;

/**
 * Avatar da loja: uma cor e um ícone de listas fixas, guardados pelo nome. O front sabe desenhar
 * cada nome; esta lista é a que vale para a API. Sem avatar, o dashboard mostra a inicial da loja.
 */
final class Avatar
{
    public const CORES = ['roxo', 'azul', 'verde', 'laranja', 'vermelho', 'cinza'];
    public const ICONES = ['loja', 'mercado', 'restaurante', 'cafe', 'farmacia', 'beleza'];
    public const MENSAGEM_INVALIDA = 'Escolha uma cor e um ícone da lista.';

    /**
     * Corpo aceito para definir o avatar: as duas chaves com nomes das listas (comparação exata,
     * sem normalizar maiúsculas) ou as duas com null (volta à inicial). Qualquer outro é inválido.
     *
     * @param array<mixed> $corpo
     */
    public static function corpoValido(array $corpo): bool
    {
        if (!array_key_exists('cor', $corpo) || !array_key_exists('icone', $corpo)) {
            return false;
        }
        if ($corpo['cor'] === null && $corpo['icone'] === null) {
            return true;
        }

        return in_array($corpo['cor'], self::CORES, true) && in_array($corpo['icone'], self::ICONES, true);
    }
}
