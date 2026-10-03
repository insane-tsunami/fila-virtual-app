<?php

declare(strict_types=1);

namespace Controllers;

use Psr\Http\Message\ResponseInterface as Resposta;
use Psr\Http\Message\ServerRequestInterface as Requisicao;
use Services\LojaService;
use Support\Json;

/** Dados da loja: a consulta é pública; definir o endereço exige a chave provisória. */
final class LojaController
{
    public function __construct(private readonly LojaService $loja)
    {
    }

    /** @param array<string, string> $args */
    public function dados(Requisicao $requisicao, Resposta $resposta, array $args): Resposta
    {
        return Json::resposta($resposta, $this->loja->dados($args['slug']));
    }

    /** @param array<string, string> $args */
    public function definirEndereco(Requisicao $requisicao, Resposta $resposta, array $args): Resposta
    {
        return Json::resposta($resposta, $this->loja->definirEndereco($args['slug'], Json::corpo($requisicao)));
    }
}
