<?php

declare(strict_types=1);

namespace Support;

use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpBadRequestException;

final class Json
{
    public static function resposta(ResponseInterface $resposta, mixed $dados, int $status = 200): ResponseInterface
    {
        $resposta->getBody()->write(
            json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );

        return $resposta->withStatus($status)->withHeader('Content-Type', 'application/json');
    }

    public static function erro(ResponseInterface $resposta, int $status, string $mensagem): ResponseInterface
    {
        return self::resposta($resposta, ['erro' => $mensagem], $status);
    }

    /**
     * Lê o corpo da requisição como objeto JSON. Corpo vazio vale como `[]`;
     * corpo que não é JSON válido ou não é um objeto/lista resulta em 400.
     *
     * @return array<mixed>
     */
    public static function corpo(ServerRequestInterface $requisicao): array
    {
        $bruto = trim((string) $requisicao->getBody());
        if ($bruto === '') {
            return [];
        }

        try {
            $dados = json_decode($bruto, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new HttpBadRequestException($requisicao, 'Corpo da requisição não é um JSON válido.');
        }

        if (!is_array($dados)) {
            throw new HttpBadRequestException($requisicao, 'Corpo da requisição não é um JSON válido.');
        }

        return $dados;
    }
}
