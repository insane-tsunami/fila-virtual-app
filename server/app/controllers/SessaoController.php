<?php

declare(strict_types=1);

namespace Controllers;

use Psr\Http\Message\ResponseInterface as Resposta;
use Psr\Http\Message\ServerRequestInterface as Requisicao;
use Services\SessaoService;
use Support\Json;

/** Login (público) e sair (sessão logada). */
final class SessaoController
{
    public function __construct(private readonly SessaoService $sessoes)
    {
    }

    public function entrar(Requisicao $requisicao, Resposta $resposta): Resposta
    {
        $corpo = Json::corpo($requisicao);

        return Json::resposta($resposta, $this->sessoes->entrar($corpo['email'] ?? null, $corpo['senha'] ?? null));
    }

    public function sair(Requisicao $requisicao, Resposta $resposta): Resposta
    {
        $this->sessoes->sair((int) $requisicao->getAttribute('sessao_id'));

        return $resposta->withStatus(204);
    }
}
