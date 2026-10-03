<?php

declare(strict_types=1);

namespace Controllers;

use Psr\Http\Message\ResponseInterface as Resposta;
use Psr\Http\Message\ServerRequestInterface as Requisicao;
use Services\FilaService;
use Support\Json;

/** Chamadas públicas: o cliente entra na fila e consulta a própria posição. */
final class ClienteController
{
    public function __construct(private readonly FilaService $fila)
    {
    }

    /** @param array<string, string> $args */
    public function entrar(Requisicao $requisicao, Resposta $resposta, array $args): Resposta
    {
        $dados = Json::corpo($requisicao);
        $resultado = $this->fila->entrar($args['slug'], $dados['telefone'] ?? null);

        return Json::resposta($resposta, $resultado['entrada'], $resultado['criada'] ? 201 : 200);
    }

    /** @param array<string, string> $args */
    public function consultar(Requisicao $requisicao, Resposta $resposta, array $args): Resposta
    {
        return Json::resposta($resposta, $this->fila->consultar($args['slug'], $args['codigo']));
    }
}
