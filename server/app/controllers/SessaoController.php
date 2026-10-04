<?php

declare(strict_types=1);

namespace Controllers;

use Psr\Http\Message\ResponseInterface as Resposta;
use Psr\Http\Message\ServerRequestInterface as Requisicao;
use Services\SessaoService;
use Support\IpDoCliente;
use Support\Json;

/** Login (público) e sair (sessão logada). */
final class SessaoController
{
    /** @param list<string> $proxiesConfiaveis */
    public function __construct(private readonly SessaoService $sessoes, private readonly array $proxiesConfiaveis = [])
    {
    }

    public function entrar(Requisicao $requisicao, Resposta $resposta): Resposta
    {
        $corpo = Json::corpo($requisicao);

        return Json::resposta($resposta, $this->sessoes->entrar(
            $corpo['email'] ?? null,
            $corpo['senha'] ?? null,
            IpDoCliente::de($requisicao, $this->proxiesConfiaveis)
        ));
    }

    public function sair(Requisicao $requisicao, Resposta $resposta): Resposta
    {
        $this->sessoes->sair((int) $requisicao->getAttribute('sessao_id'));

        return $resposta->withStatus(204);
    }
}
