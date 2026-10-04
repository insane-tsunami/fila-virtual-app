<?php

declare(strict_types=1);

namespace Controllers;

use Psr\Http\Message\ResponseInterface as Resposta;
use Psr\Http\Message\ServerRequestInterface as Requisicao;
use Services\ConfirmacaoEmailService;
use Support\IpDoCliente;
use Support\Json;

/** Confirmação do e-mail: confirmar (público) e reenviar ou trocar o e-mail (conta logada). */
final class ConfirmacaoEmailController
{
    /** @param list<string> $proxiesConfiaveis */
    public function __construct(
        private readonly ConfirmacaoEmailService $confirmacao,
        private readonly array $proxiesConfiaveis = [],
    ) {
    }

    public function confirmar(Requisicao $requisicao, Resposta $resposta): Resposta
    {
        $corpo = Json::corpo($requisicao);
        $this->confirmacao->confirmar($corpo['token'] ?? null, IpDoCliente::de($requisicao, $this->proxiesConfiaveis));

        return Json::resposta($resposta, ['mensagem' => ConfirmacaoEmailService::MENSAGEM_CONFIRMADO]);
    }

    public function reenviar(Requisicao $requisicao, Resposta $resposta): Resposta
    {
        $mensagem = $this->confirmacao->reenviar((int) $requisicao->getAttribute('conta_id'));

        return Json::resposta($resposta, ['mensagem' => $mensagem], 202);
    }

    public function trocarEmail(Requisicao $requisicao, Resposta $resposta): Resposta
    {
        $conta = $this->confirmacao->trocarEmail((int) $requisicao->getAttribute('conta_id'), Json::corpo($requisicao));

        return Json::resposta($resposta, [
            'conta' => $conta,
            'mensagem' => 'E-mail alterado. Enviamos um link de confirmação para o novo endereço.',
        ]);
    }
}
