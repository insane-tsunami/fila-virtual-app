<?php

declare(strict_types=1);

namespace Controllers;

use Psr\Http\Message\ResponseInterface as Resposta;
use Psr\Http\Message\ServerRequestInterface as Requisicao;
use Services\RecuperacaoSenhaService;
use Support\IpDoCliente;
use Support\Json;

/** "Esqueci a senha" (público): pedir o link por e-mail e concluir a redefinição com o token. */
final class RecuperacaoSenhaController
{
    /** @param list<string> $proxiesConfiaveis */
    public function __construct(
        private readonly RecuperacaoSenhaService $recuperacao,
        private readonly array $proxiesConfiaveis = [],
    ) {
    }

    public function pedir(Requisicao $requisicao, Resposta $resposta): Resposta
    {
        $corpo = Json::corpo($requisicao);
        $mensagem = $this->recuperacao->pedir(
            $corpo['email'] ?? null,
            IpDoCliente::de($requisicao, $this->proxiesConfiaveis)
        );

        return Json::resposta($resposta, ['mensagem' => $mensagem], 202);
    }

    public function redefinir(Requisicao $requisicao, Resposta $resposta): Resposta
    {
        $corpo = Json::corpo($requisicao);
        $this->recuperacao->redefinir(
            $corpo['token'] ?? null,
            $corpo['nova_senha'] ?? null,
            IpDoCliente::de($requisicao, $this->proxiesConfiaveis)
        );

        return Json::resposta($resposta, ['mensagem' => 'Senha alterada.']);
    }
}
