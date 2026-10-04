<?php

declare(strict_types=1);

namespace Controllers;

use Psr\Http\Message\ResponseInterface as Resposta;
use Psr\Http\Message\ServerRequestInterface as Requisicao;
use Services\ContaService;
use Support\IpDoCliente;
use Support\Json;

/** Cadastro da conta (público) e as chamadas da conta logada. */
final class ContaController
{
    /** @param list<string> $proxiesConfiaveis */
    public function __construct(private readonly ContaService $contas, private readonly array $proxiesConfiaveis = [])
    {
    }

    public function cadastrar(Requisicao $requisicao, Resposta $resposta): Resposta
    {
        return Json::resposta($resposta, $this->contas->cadastrar(Json::corpo($requisicao), IpDoCliente::de($requisicao, $this->proxiesConfiaveis)), 201);
    }

    public function dados(Requisicao $requisicao, Resposta $resposta): Resposta
    {
        return Json::resposta($resposta, $this->contas->dados((int) $requisicao->getAttribute('conta_id')));
    }

    public function trocarSenha(Requisicao $requisicao, Resposta $resposta): Resposta
    {
        $this->contas->trocarSenha(
            (int) $requisicao->getAttribute('conta_id'),
            (int) $requisicao->getAttribute('sessao_id'),
            Json::corpo($requisicao)
        );

        return Json::resposta($resposta, ['mensagem' => 'Senha alterada.']);
    }
}
