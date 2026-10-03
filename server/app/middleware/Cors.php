<?php

declare(strict_types=1);

namespace Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;

/**
 * CORS para uma única origem exata. Sem origem configurada, não faz nada.
 * Deve ser o middleware mais externo, para que também as respostas de erro
 * levem os cabeçalhos e o navegador consiga ler o corpo do erro.
 */
final class Cors implements MiddlewareInterface
{
    public function __construct(private readonly string $origem)
    {
    }

    public function process(ServerRequestInterface $requisicao, RequestHandlerInterface $proximo): ResponseInterface
    {
        if ($this->origem === '') {
            return $proximo->handle($requisicao);
        }

        if ($requisicao->getMethod() === 'OPTIONS') {
            $resposta = (new ResponseFactory())->createResponse(204)
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
                ->withHeader('Access-Control-Allow-Headers', 'Content-Type, X-API-Key')
                ->withHeader('Access-Control-Max-Age', '600');
        } else {
            $resposta = $proximo->handle($requisicao);
        }

        return $resposta
            ->withHeader('Access-Control-Allow-Origin', $this->origem)
            ->withHeader('Vary', 'Origin');
    }
}
