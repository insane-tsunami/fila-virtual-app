<?php

declare(strict_types=1);

namespace Support;

/**
 * CNPJ de uma conta, nos formatos numérico e alfanumérico (Receita Federal, IN RFB 2.229/2024):
 * 12 posições [0-9A-Z] seguidas de 2 dígitos verificadores. Aceito com ou sem máscara e em
 * qualquer caixa; guardado como 14 caracteres, em maiúsculas e sem máscara.
 *
 * O dígito verificador é módulo 11: cada caractere vale o seu código ASCII menos 48
 * (`0`-`9` valem 0-9, `A` vale 17, `Z` vale 42). Para CNPJs só com dígitos, dá o mesmo
 * resultado de sempre.
 */
final class Cnpj
{
    private const PESOS_PRIMEIRO = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    private const PESOS_SEGUNDO = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    /** Devolve os 14 caracteres normalizados ou null se o valor não for um CNPJ válido. */
    public static function normalizar(mixed $valor): ?string
    {
        if (!is_string($valor)) {
            return null;
        }

        // Só pontuação de máscara é descartada; qualquer outro caractere invalida o valor.
        $limpo = preg_replace('/[.\-\/\s]/', '', $valor);
        if (!is_string($limpo)) {
            return null;
        }
        $cnpj = strtoupper($limpo);

        if (preg_match('/\A[0-9A-Z]{12}[0-9]{2}\z/', $cnpj) !== 1) {
            return null;
        }

        // 14 caracteres iguais (como 00000000000000, que passaria na conta) não são CNPJ.
        if (preg_match('/\A(.)\1{13}\z/', $cnpj) === 1) {
            return null;
        }

        return self::digitosVerificadores(substr($cnpj, 0, 12)) === substr($cnpj, 12) ? $cnpj : null;
    }

    /** Os 2 dígitos verificadores de uma base de 12 caracteres [0-9A-Z]. */
    public static function digitosVerificadores(string $base): string
    {
        $valores = array_map(static fn (string $c): int => ord($c) - 48, str_split($base));

        $primeiro = self::digito($valores, self::PESOS_PRIMEIRO);
        $valores[] = $primeiro;
        $segundo = self::digito($valores, self::PESOS_SEGUNDO);

        return $primeiro . $segundo;
    }

    /**
     * @param list<int> $valores
     * @param list<int> $pesos
     */
    private static function digito(array $valores, array $pesos): int
    {
        $soma = 0;
        foreach ($pesos as $i => $peso) {
            $soma += $valores[$i] * $peso;
        }
        $resto = $soma % 11;

        return $resto < 2 ? 0 : 11 - $resto;
    }
}
