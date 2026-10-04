<?php

declare(strict_types=1);

namespace Support;

/**
 * CNPJ de uma conta: aceito com ou sem máscara e guardado só com os 14 dígitos.
 * O dígito verificador NÃO é conferido (fora do escopo por enquanto).
 */
final class Cnpj
{
    /** Devolve os 14 dígitos ou null se o valor não tiver exatamente 14 dígitos. */
    public static function normalizar(mixed $valor): ?string
    {
        if (!is_string($valor)) {
            return null;
        }

        // Só pontuação de máscara é descartada; qualquer outro caractere invalida o valor.
        $digitos = preg_replace('/[.\-\/\s]/', '', $valor);

        return is_string($digitos) && preg_match('/\A[0-9]{14}\z/', $digitos) === 1 ? $digitos : null;
    }
}
