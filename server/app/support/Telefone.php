<?php

declare(strict_types=1);

namespace Support;

final class Telefone
{
    private const DDI = '55';

    /**
     * Devolve só os dígitos, com o DDI 55, ou null se o número for inválido.
     * Aceita 10 ou 11 dígitos (sem DDI) e 12 ou 13 dígitos começando por 55.
     */
    public static function normalizar(mixed $valor): ?string
    {
        if (is_int($valor)) {
            $valor = (string) $valor;
        }
        if (!is_string($valor)) {
            return null;
        }

        $digitos = preg_replace('/\D+/', '', $valor) ?? '';

        return match (strlen($digitos)) {
            10, 11 => self::DDI . $digitos,
            12, 13 => str_starts_with($digitos, self::DDI) ? $digitos : null,
            default => null,
        };
    }

    /** `*****` mais os quatro últimos dígitos, como a tela do dashboard já exibe. */
    public static function mascarar(string $telefone): string
    {
        return '*****' . substr($telefone, -4);
    }
}
