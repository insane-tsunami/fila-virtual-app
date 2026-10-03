<?php

declare(strict_types=1);

namespace Support;

use Controllers\ClienteController;
use Controllers\DashboardController;
use Middleware\ChaveDeApi;
use Middleware\Cors;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Services\ConflitoException;
use Services\DadosInvalidosException;
use Services\FilaService;
use Services\NaoEncontradoException;
use Slim\App;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpUnauthorizedException;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Throwable;

/** Monta a aplicação Slim (rotas, erros em JSON e CORS) a partir da configuração. */
final class Aplicacao
{
    /**
     * @param array<string, mixed> $config resultado de api/config.php
     * @param (callable(Throwable): void)|null $logger recebe os erros inesperados (500)
     */
    public static function criar(array $config, ?callable $logger = null): App
    {
        $logger ??= static function (Throwable $e): void {
            error_log(sprintf('[api] %s: %s em %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));
        };

        $app = AppFactory::create();
        $fila = new FilaService();
        $cliente = new ClienteController($fila);
        $dashboard = new DashboardController($fila);
        $chave = new ChaveDeApi((string) ($config['api_key'] ?? ''));

        // Cliente (públicas)
        $app->post('/api/filas/{slug}/entradas', [$cliente, 'entrar']);
        $app->get('/api/filas/{slug}/entradas/{codigo}', [$cliente, 'consultar']);

        // Dashboard (chave provisória)
        $app->get('/api/filas/{slug}/entradas', [$dashboard, 'listar'])->add($chave);
        $app->post('/api/filas/{slug}/entradas/{codigo}/finalizar', [$dashboard, 'finalizar'])->add($chave);

        // Ordem de execução: Cors (mais externo) -> erros -> roteamento -> rota.
        $app->addRoutingMiddleware();
        $erros = $app->addErrorMiddleware(false, false, false);
        $erros->setDefaultErrorHandler(self::tratadorDeErros($logger));
        $app->add(new Cors((string) ($config['cors_origin'] ?? '')));

        return $app;
    }

    /** @param callable(Throwable): void $logger */
    private static function tratadorDeErros(callable $logger): callable
    {
        return static function (ServerRequestInterface $requisicao, Throwable $erro) use ($logger): ResponseInterface {
            [$status, $mensagem] = match (true) {
                $erro instanceof NaoEncontradoException => [404, $erro->getMessage()],
                $erro instanceof DadosInvalidosException => [422, $erro->getMessage()],
                $erro instanceof ConflitoException => [409, $erro->getMessage()],
                $erro instanceof HttpNotFoundException => [404, 'Rota não encontrada.'],
                $erro instanceof HttpMethodNotAllowedException => [405, 'Método não permitido.'],
                $erro instanceof HttpUnauthorizedException => [401, 'Chave de API ausente ou inválida.'],
                $erro instanceof HttpBadRequestException => [400, $erro->getMessage()],
                $erro instanceof HttpException => [$erro->getCode(), $erro->getMessage()],
                default => [500, 'Erro interno.'],
            };

            if ($status === 500) {
                $logger($erro);
            }

            $resposta = Json::erro((new ResponseFactory())->createResponse(), $status, $mensagem);

            return $erro instanceof HttpMethodNotAllowedException
                ? $resposta->withHeader('Allow', implode(', ', $erro->getAllowedMethods()))
                : $resposta;
        };
    }
}
