<?php

declare(strict_types=1);

namespace Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Services\NaoAutenticadoException;
use Services\SessaoService;

/**
 * Exige `Authorization: Bearer <token>` com uma sessão válida (existente e não vencida).
 * Guarda `conta_id` e `sessao_id` na requisição para os controllers e para `LojaDaConta`.
 */
final class Autenticacao implements MiddlewareInterface
{
    public function __construct(private readonly SessaoService $sessoes)
    {
    }

    public function process(ServerRequestInterface $requisicao, RequestHandlerInterface $proximo): ResponseInterface
    {
        $token = preg_match('/\ABearer ([^\s]+)\z/', trim($requisicao->getHeaderLine('Authorization')), $partes) === 1
            ? $partes[1]
            : '';

        $sessao = $this->sessoes->resolver($token)
            ?? throw new NaoAutenticadoException('Autenticação ausente ou inválida.');

        return $proximo->handle(
            $requisicao
                ->withAttribute('conta_id', (int) $sessao->conta_id)
                ->withAttribute('sessao_id', (int) $sessao->id)
        );
    }
}
