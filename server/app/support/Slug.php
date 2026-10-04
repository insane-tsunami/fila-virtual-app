<?php

declare(strict_types=1);

namespace Support;

/**
 * Slug de uma loja gerado do nome: minúsculas, sem acentos, tudo que não for letra
 * ou número vira um hífen, sem hífens nas pontas e com até 80 caracteres.
 * Não depende da extensão intl (a hospedagem não está definida).
 */
final class Slug
{
    public const TAMANHO_MAXIMO = 80;

    private const ACENTOS = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'ā' => 'a', 'ă' => 'a', 'ą' => 'a',
        'ç' => 'c', 'ć' => 'c', 'č' => 'c',
        'ď' => 'd', 'đ' => 'd',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e', 'ę' => 'e', 'ě' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i', 'ı' => 'i',
        'ł' => 'l', 'ĺ' => 'l', 'ľ' => 'l',
        'ñ' => 'n', 'ń' => 'n', 'ň' => 'n',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'ō' => 'o', 'ő' => 'o',
        'ŕ' => 'r', 'ř' => 'r',
        'ś' => 's', 'š' => 's', 'ş' => 's', 'ß' => 'ss',
        'ť' => 't', 'ţ' => 't',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ū' => 'u', 'ů' => 'u', 'ű' => 'u',
        'ý' => 'y', 'ÿ' => 'y',
        'ź' => 'z', 'ż' => 'z', 'ž' => 'z',
        'æ' => 'ae', 'œ' => 'oe',
    ];

    /** Devolve o slug ou null se o nome não tiver nenhuma letra ou número. */
    public static function deNome(string $nome): ?string
    {
        $texto = strtr(mb_strtolower($nome), self::ACENTOS);
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', $texto), '-');
        $slug = rtrim(substr($slug, 0, self::TAMANHO_MAXIMO), '-');

        return $slug === '' ? null : $slug;
    }
}
