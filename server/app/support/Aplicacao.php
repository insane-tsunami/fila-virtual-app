<?php

declare(strict_types=1);

namespace Support;

use Controllers\ClienteController;
use Controllers\ConfirmacaoEmailController;
use Controllers\ContaController;
use Controllers\DashboardController;
use Controllers\LojaController;
use Controllers\RecuperacaoSenhaController;
use Controllers\SessaoController;
use Middleware\Autenticacao;
use Middleware\Cors;
use Middleware\LojaDaConta;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Services\AcessoNegadoException;
use Services\ConflitoException;
use Services\ConfirmacaoEmailService;
use Services\ContaService;
use Services\DadosInvalidosException;
use Services\FabricaDeMailer;
use Services\FilaService;
use Services\LimiteDeTentativas;
use Services\LimiteExcedidoException;
use Services\LojaService;
use Services\NaoAutenticadoException;
use Services\Mailer;
use Services\NaoEncontradoException;
use Services\RecuperacaoSenhaService;
use Services\SessaoService;
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
        $loja = new LojaController(new LojaService());
        // Sem a seção `rate_limit` na configuração não há limite (os testes que não tratam dele).
        $limite = isset($config['rate_limit'])
            ? LimiteDeTentativas::daConfig($config['rate_limit'], $logger)
            : null;
        $proxies = $config['trusted_proxies'] ?? [];
        $sessoes = new SessaoService($limite);
        // E-mail: `mailer` (um objeto Mailer, nos testes) tem prioridade sobre o driver da configuração.
        $registrar = $config['registrar'] ?? static function (string $mensagem): void {
            error_log('[api] ' . $mensagem);
        };
        $mailer = ($config['mailer'] ?? null) instanceof Mailer
            ? $config['mailer']
            : FabricaDeMailer::criar($config['mail'] ?? [], $registrar);
        $appUrl = (string) ($config['app_url'] ?? '');
        $confirmacao = new ConfirmacaoEmailService($mailer, $appUrl, $limite, $registrar);
        $conta = new ContaController(new ContaService($sessoes, $limite, $confirmacao), $proxies);
        $sessao = new SessaoController($sessoes, $proxies);
        $confirmar = new ConfirmacaoEmailController($confirmacao, $proxies);
        $recuperacao = new RecuperacaoSenhaController(
            new RecuperacaoSenhaService($sessoes, $mailer, $appUrl, $limite, $registrar),
            $proxies
        );
        $autenticada = new Autenticacao($sessoes);
        $daLoja = new LojaDaConta();

        // Cliente (públicas)
        $app->post('/api/filas/{slug}/entradas', [$cliente, 'entrar']);
        $app->get('/api/filas/{slug}/entradas/{codigo}', [$cliente, 'consultar']);
        $app->get('/api/filas/{slug}', [$loja, 'dados']);

        // Conta e sessão: cadastro e login são públicos
        $app->post('/api/contas', [$conta, 'cadastrar']);
        $app->post('/api/sessoes', [$sessao, 'entrar']);
        $app->delete('/api/sessao', [$sessao, 'sair'])->add($autenticada);
        $app->get('/api/conta', [$conta, 'dados'])->add($autenticada);
        $app->put('/api/conta/senha', [$conta, 'trocarSenha'])->add($autenticada);

        // Confirmação do e-mail: confirmar é público (o link abre em qualquer aparelho); o resto exige sessão
        $app->post('/api/email/confirmar', [$confirmar, 'confirmar']);
        $app->post('/api/conta/email/reenviar', [$confirmar, 'reenviar'])->add($autenticada);
        $app->put('/api/conta/email', [$confirmar, 'trocarEmail'])->add($autenticada);

        // "Esqueci a senha": públicas
        $app->post('/api/senha/esqueci', [$recuperacao, 'pedir']);
        $app->post('/api/senha/redefinir', [$recuperacao, 'redefinir']);

        // Dashboard: sessão válida (Autenticacao, a mais externa) e loja da própria conta
        $app->get('/api/filas/{slug}/entradas', [$dashboard, 'listar'])->add($daLoja)->add($autenticada);
        $app->post('/api/filas/{slug}/entradas/{codigo}/finalizar', [$dashboard, 'finalizar'])
            ->add($daLoja)->add($autenticada);
        $app->put('/api/filas/{slug}/endereco', [$loja, 'definirEndereco'])->add($daLoja)->add($autenticada);

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
                $erro instanceof NaoAutenticadoException => [401, $erro->getMessage()],
                $erro instanceof AcessoNegadoException => [403, $erro->getMessage()],
                $erro instanceof LimiteExcedidoException => [429, $erro->getMessage()],
                $erro instanceof HttpNotFoundException => [404, 'Rota não encontrada.'],
                $erro instanceof HttpMethodNotAllowedException => [405, 'Método não permitido.'],
                $erro instanceof HttpUnauthorizedException => [401, 'Autenticação ausente ou inválida.'],
                $erro instanceof HttpBadRequestException => [400, $erro->getMessage()],
                $erro instanceof HttpException => [$erro->getCode(), $erro->getMessage()],
                default => [500, 'Erro interno.'],
            };

            if ($status === 500) {
                $logger($erro);
            }

            $resposta = Json::erro((new ResponseFactory())->createResponse(), $status, $mensagem);

            return match (true) {
                $erro instanceof HttpMethodNotAllowedException
                    => $resposta->withHeader('Allow', implode(', ', $erro->getAllowedMethods())),
                $erro instanceof LimiteExcedidoException
                    => $resposta->withHeader('Retry-After', (string) $erro->segundos),
                default => $resposta,
            };
        };
    }
}
