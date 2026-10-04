<?php

declare(strict_types=1);

namespace Support;

use Psr\Http\Message\ServerRequestInterface;

/**
 * IP do cliente para o limite de tentativas. Usa o endereço da conexão; só quando ele é de um
 * proxy confiável (TRUSTED_PROXIES) lê o X-Forwarded-For, da direita para a esquerda, e fica com
 * o primeiro IP que não é de proxy confiável (o que está à esquerda pode ter sido forjado).
 * IPv6 é reduzido ao /64, para quem tem um bloco inteiro não trocar de endereço dentro dele.
 */
final class IpDoCliente
{
    public const DESCONHECIDO = 'desconhecido';

    /** @param list<string> $proxiesConfiaveis IPs ou faixas CIDR */
    public static function de(ServerRequestInterface $requisicao, array $proxiesConfiaveis = []): string
    {
        $conexao = (string) ($requisicao->getServerParams()['REMOTE_ADDR'] ?? '');
        if (filter_var($conexao, FILTER_VALIDATE_IP) === false) {
            return self::DESCONHECIDO;
        }
        if (!self::confiavel($conexao, $proxiesConfiaveis)) {
            return self::agrupar($conexao);
        }

        $itens = array_map('trim', explode(',', $requisicao->getHeaderLine('X-Forwarded-For')));
        foreach (array_reverse($itens) as $item) {
            if (filter_var($item, FILTER_VALIDATE_IP) === false) {
                return self::agrupar($conexao);
            }
            if (!self::confiavel($item, $proxiesConfiaveis)) {
                return self::agrupar($item);
            }
        }

        return self::agrupar($conexao);
    }

    /** @param list<string> $lista */
    private static function confiavel(string $ip, array $lista): bool
    {
        $binario = self::binario($ip);

        foreach ($lista as $item) {
            [$base, $bits] = array_pad(explode('/', $item, 2), 2, null);
            $alvo = self::binario((string) $base);
            if ($binario === null || $alvo === null || strlen($alvo) !== strlen($binario)) {
                continue;
            }
            $bits = $bits === null ? strlen($alvo) * 8 : (ctype_digit($bits) ? (int) $bits : -1);
            if ($bits < 0 || $bits > strlen($alvo) * 8) {
                continue;
            }
            if (self::mesmosBits($binario, $alvo, $bits)) {
                return true;
            }
        }

        return false;
    }

    private static function mesmosBits(string $a, string $b, int $bits): bool
    {
        $bytes = intdiv($bits, 8);
        if (substr($a, 0, $bytes) !== substr($b, 0, $bytes)) {
            return false;
        }
        $resto = $bits % 8;
        if ($resto === 0) {
            return true;
        }
        $mascara = (0xFF << (8 - $resto)) & 0xFF;

        return (ord($a[$bytes]) & $mascara) === (ord($b[$bytes]) & $mascara);
    }

    /** Endereço em bytes (4 ou 16); IPv4 escrito como IPv6 (::ffff:a.b.c.d) vira IPv4. */
    private static function binario(string $ip): ?string
    {
        $binario = @inet_pton($ip);
        if ($binario === false) {
            return null;
        }
        if (strlen($binario) === 16 && str_starts_with($binario, str_repeat("\0", 10) . "\xff\xff")) {
            return substr($binario, 12);
        }

        return $binario;
    }

    private static function agrupar(string $ip): string
    {
        $binario = self::binario($ip);
        if ($binario === null) {
            return self::DESCONHECIDO;
        }
        if (strlen($binario) === 4) {
            return (string) inet_ntop($binario);
        }

        return inet_ntop(substr($binario, 0, 8) . str_repeat("\0", 8)) . '/64';
    }
}
