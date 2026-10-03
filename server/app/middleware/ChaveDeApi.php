<?php

declare(strict_types=1);

namespace Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpUnauthorizedException;

/**
 * Chave compartilhada PROVISÓRIA das rotas do dashboard, até existir login.
 * Fechado por padrão: sem chave configurada, nenhuma chamada passa.
 */
final class ChaveDeApi implements MiddlewareInterface
{
    public function __construct(private readonly string $esperada)
    {
    }

    public function process(ServerRequestInterface $requisicao, RequestHandlerInterface $proximo): ResponseInterface
    {
        $recebida = $requisicao->getHeaderLine('X-API-Key');

        if ($this->esperada === '' || $recebida === '' || !hash_equals($this->esperada, $recebida)) {
            throw new HttpUnauthorizedException($requisicao);
        }

        return $proximo->handle($requisicao);
    }
}
