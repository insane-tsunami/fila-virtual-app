<?php

declare(strict_types=1);

namespace Controllers;

use Psr\Http\Message\ResponseInterface as Resposta;
use Psr\Http\Message\ServerRequestInterface as Requisicao;
use Support\Json;

/** Aviso de privacidade: configuração pública e os prazos que o `php bin/limpar` aplica. */
final class PrivacidadeController
{
    /** Base legal do telefone da fila (ver o teste de balanceamento no server/README.md). */
    private const BASE_LEGAL = 'legítimo interesse (art. 7º, IX, da LGPD)';

    /**
     * @param array{controlador?: string, email?: string, encarregado?: string} $privacidade
     * @param array{entrada_ativa_horas?: int, telefone_dias?: int} $retencao
     */
    public function __construct(
        private readonly array $privacidade,
        private readonly array $retencao,
    ) {
    }

    public function aviso(Requisicao $requisicao, Resposta $resposta): Resposta
    {
        return Json::resposta($resposta, [
            'controlador' => $this->privacidade['controlador'] ?? '',
            'email' => $this->privacidade['email'] ?? '',
            'encarregado' => $this->privacidade['encarregado'] ?? '',
            'base_legal' => self::BASE_LEGAL,
            'retencao_entrada_ativa_horas' => $this->retencao['entrada_ativa_horas'] ?? 24,
            'retencao_telefone_dias' => $this->retencao['telefone_dias'] ?? 30,
        ]);
    }
}
