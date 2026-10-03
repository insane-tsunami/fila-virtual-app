<?php

declare(strict_types=1);

namespace Tests;

use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Support\Aplicacao;
use Throwable;

/** Base dos testes da API: chama a aplicação Slim direto, sem servidor HTTP. */
abstract class ApiTestCase extends DatabaseTestCase
{
    protected const CHAVE = 'segredo-de-teste';
    protected const SLUG = 'veste-bem';

    /** @var list<Throwable> erros inesperados (500) recebidos pelo logger */
    protected array $errosLogados = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrate();
        $this->errosLogados = [];
    }

    /** @param array<string, mixed> $config */
    protected function app(array $config = []): App
    {
        $padrao = ['api_key' => self::CHAVE, 'cors_origin' => ''];

        return Aplicacao::criar(
            array_merge($padrao, $config),
            function (Throwable $erro): void {
                $this->errosLogados[] = $erro;
            }
        );
    }

    /**
     * @param array<mixed>|string|null $corpo array vira JSON; string é enviada como está
     * @param array<string, string> $cabecalhos
     * @param array<string, mixed> $config
     */
    protected function chamar(
        string $metodo,
        string $uri,
        array|string|null $corpo = null,
        array $cabecalhos = [],
        array $config = [],
    ): ResponseInterface {
        $requisicao = (new ServerRequestFactory())->createServerRequest($metodo, $uri);

        foreach ($cabecalhos as $nome => $valor) {
            $requisicao = $requisicao->withHeader($nome, $valor);
        }
        if ($corpo !== null) {
            $bruto = is_string($corpo) ? $corpo : json_encode($corpo, JSON_THROW_ON_ERROR);
            $requisicao = $requisicao
                ->withHeader('Content-Type', 'application/json')
                ->withBody((new StreamFactory())->createStream($bruto));
        }

        return $this->app($config)->handle($requisicao);
    }

    /** @return array<mixed>|null */
    protected function json(ResponseInterface $resposta): ?array
    {
        return json_decode((string) $resposta->getBody(), true);
    }

    /** @return array<string, string> */
    protected function comChave(): array
    {
        return ['X-API-Key' => self::CHAVE];
    }

    /** Entra na fila pela API com o telefone de número `$n` e devolve o corpo da resposta. */
    protected function entrarPelaApi(int $n): array
    {
        $resposta = $this->chamar(
            'POST',
            '/api/filas/' . self::SLUG . '/entradas',
            ['telefone' => sprintf('(11) 9%04d-%04d', $n, $n)]
        );
        $this->assertContains($resposta->getStatusCode(), [200, 201]);

        return $this->json($resposta);
    }

    /** @return list<string> nomes dos cabeçalhos CORS presentes, em minúsculas */
    protected function cabecalhosCors(ResponseInterface $resposta): array
    {
        return array_values(array_filter(
            array_map('strtolower', array_keys($resposta->getHeaders())),
            fn (string $nome) => str_starts_with($nome, 'access-control-')
        ));
    }
}
