<?php

declare(strict_types=1);

namespace Middleware;

use Models\Estabelecimento;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Services\AcessoNegadoException;
use Services\NaoEncontradoException;
use Slim\Routing\RouteContext;

/**
 * Só deixa passar quem é dona da loja do `{slug}` da rota: slug inexistente é 404;
 * loja de outra conta ou sem dono é 403. Deve rodar depois de `Autenticacao`.
 */
final class LojaDaConta implements MiddlewareInterface
{
    public function process(ServerRequestInterface $requisicao, RequestHandlerInterface $proximo): ResponseInterface
    {
        $slug = (string) RouteContext::fromRequest($requisicao)->getRoute()?->getArgument('slug', '');

        $loja = Estabelecimento::query()->where('slug', $slug)->first()
            ?? throw new NaoEncontradoException('Estabelecimento não encontrado.');

        $conta = $requisicao->getAttribute('conta_id');
        if ($loja->conta_id === null || $conta === null || (int) $loja->conta_id !== (int) $conta) {
            throw new AcessoNegadoException('Esta loja não pertence à sua conta.');
        }

        return $proximo->handle($requisicao);
    }
}
