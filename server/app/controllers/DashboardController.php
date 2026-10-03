<?php

declare(strict_types=1);

namespace Controllers;

use Psr\Http\Message\ResponseInterface as Resposta;
use Psr\Http\Message\ServerRequestInterface as Requisicao;
use Services\FilaService;
use Support\Json;

/** Chamadas do estabelecimento (protegidas pela chave provisória). */
final class DashboardController
{
    public function __construct(private readonly FilaService $fila)
    {
    }

    /** @param array<string, string> $args */
    public function listar(Requisicao $requisicao, Resposta $resposta, array $args): Resposta
    {
        return Json::resposta($resposta, $this->fila->listar($args['slug']));
    }

    /** @param array<string, string> $args */
    public function finalizar(Requisicao $requisicao, Resposta $resposta, array $args): Resposta
    {
        return Json::resposta($resposta, $this->fila->finalizar($args['slug'], $args['codigo']));
    }
}
