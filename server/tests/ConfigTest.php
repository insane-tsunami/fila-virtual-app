<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    private const VARIAVEIS = ['DB_DRIVER', 'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'API_KEY', 'CORS_ORIGIN',
        'RATE_LIMIT_LOGIN_EMAIL', 'RATE_LIMIT_LOGIN_IP', 'RATE_LIMIT_SENHA_CONTA', 'RATE_LIMIT_JANELA_MIN',
        'RATE_LIMIT_CADASTRO_IP', 'RATE_LIMIT_CADASTRO_JANELA_MIN', 'TRUSTED_PROXIES',
        'APP_URL', 'MAIL_DRIVER', 'MAIL_DSN', 'MAIL_FROM',
        'RATE_LIMIT_ESQUECI_EMAIL', 'RATE_LIMIT_ESQUECI_IP', 'RATE_LIMIT_ESQUECI_JANELA_MIN', 'RATE_LIMIT_REDEFINIR_IP',
        'RATE_LIMIT_CONFIRMACAO_CONTA', 'RATE_LIMIT_CONFIRMACAO_JANELA_MIN', 'RATE_LIMIT_CONFIRMAR_IP'];

    /** @var array<string, string|false> */
    private array $original = [];

    protected function setUp(): void
    {
        foreach (self::VARIAVEIS as $nome) {
            $this->original[$nome] = getenv($nome);
            putenv($nome);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->original as $nome => $valor) {
            putenv($valor === false ? $nome : "$nome=$valor");
        }
    }

    /** @return array<string, mixed> */
    private function carregar(): array
    {
        return require __DIR__ . '/../api/config.php';
    }

    public function testAplicaOsPadroesQuandoNadaEstaDefinido(): void
    {
        $config = $this->carregar();

        $this->assertSame('mysql', $config['db']['driver']);
        $this->assertSame('localhost', $config['db']['host']);
        $this->assertSame('zerafilas', $config['db']['database']);
        $this->assertSame('', $config['db']['username']);
        $this->assertSame('', $config['db']['password']);
        $this->assertArrayNotHasKey('api_key', $config, 'a chave provisória foi removida (login por conta)');
        $this->assertSame('', $config['cors_origin']);
    }

    public function testLeOsValoresDoAmbiente(): void
    {
        putenv('DB_DRIVER=sqlite');
        putenv('DB_NAME=:memory:');
        putenv('API_KEY=ignorada');
        putenv('CORS_ORIGIN=https://front.exemplo.com');

        $config = $this->carregar();

        $this->assertSame('sqlite', $config['db']['driver']);
        $this->assertSame(':memory:', $config['db']['database']);
        $this->assertArrayNotHasKey('api_key', $config, 'API_KEY no ambiente é ignorada');
        $this->assertSame('https://front.exemplo.com', $config['cors_origin']);
    }

    public function testCadaChamadaReleOAmbiente(): void
    {
        putenv('CORS_ORIGIN=https://primeira.exemplo.com');
        $primeira = $this->carregar();
        putenv('CORS_ORIGIN=https://segunda.exemplo.com');
        $segunda = $this->carregar();

        $this->assertSame('https://primeira.exemplo.com', $primeira['cors_origin']);
        $this->assertSame('https://segunda.exemplo.com', $segunda['cors_origin']);
    }

    public function testLimitesDeTentativasTemPadroes(): void
    {
        $config = $this->carregar();

        $this->assertSame([
            'login_email' => 5, 'login_ip' => 20, 'senha_conta' => 5, 'janela_minutos' => 15,
            'cadastro_ip' => 5, 'cadastro_janela_minutos' => 60,
            'esqueci_email' => 3, 'esqueci_ip' => 10, 'esqueci_janela_minutos' => 60, 'redefinir_ip' => 20,
            'confirmacao_conta' => 3, 'confirmacao_janela_minutos' => 60, 'confirmar_ip' => 20,
        ], $config['rate_limit']);
        $this->assertSame([], $config['trusted_proxies']);
    }

    public function testLimitesDeTentativasLemOAmbiente(): void
    {
        putenv('RATE_LIMIT_LOGIN_EMAIL=3');
        putenv('RATE_LIMIT_CADASTRO_JANELA_MIN=30');
        putenv('RATE_LIMIT_ESQUECI_EMAIL=1');
        putenv('RATE_LIMIT_ESQUECI_JANELA_MIN=10');
        putenv('RATE_LIMIT_CONFIRMACAO_CONTA=1');
        putenv('RATE_LIMIT_CONFIRMAR_IP=7');
        putenv('TRUSTED_PROXIES= 10.0.0.1 , 192.168.0.0/16,,');

        $config = $this->carregar();

        $this->assertSame(3, $config['rate_limit']['login_email']);
        $this->assertSame(30, $config['rate_limit']['cadastro_janela_minutos']);
        $this->assertSame(20, $config['rate_limit']['login_ip']);
        $this->assertSame(1, $config['rate_limit']['esqueci_email']);
        $this->assertSame(10, $config['rate_limit']['esqueci_janela_minutos']);
        $this->assertSame(10, $config['rate_limit']['esqueci_ip']);
        $this->assertSame(1, $config['rate_limit']['confirmacao_conta']);
        $this->assertSame(7, $config['rate_limit']['confirmar_ip']);
        $this->assertSame(60, $config['rate_limit']['confirmacao_janela_minutos']);
        $this->assertSame(['10.0.0.1', '192.168.0.0/16'], $config['trusted_proxies']);
    }

    /** @return array<string, array{string}> */
    public static function valoresInvalidos(): array
    {
        return ['zero' => ['0'], 'negativo' => ['-3'], 'texto' => ['muito'], 'decimal' => ['2.5'], 'vazio' => ['']];
    }

    #[DataProvider('valoresInvalidos')]
    public function testLimiteInvalidoUsaOPadrao(string $valor): void
    {
        putenv('RATE_LIMIT_LOGIN_EMAIL=' . $valor);
        putenv('RATE_LIMIT_JANELA_MIN=' . $valor);
        putenv('RATE_LIMIT_ESQUECI_EMAIL=' . $valor);
        putenv('RATE_LIMIT_ESQUECI_JANELA_MIN=' . $valor);
        putenv('RATE_LIMIT_REDEFINIR_IP=' . $valor);
        putenv('RATE_LIMIT_CONFIRMACAO_CONTA=' . $valor);
        putenv('RATE_LIMIT_CONFIRMACAO_JANELA_MIN=' . $valor);
        putenv('RATE_LIMIT_CONFIRMAR_IP=' . $valor);

        $config = $this->carregar();

        $this->assertSame(5, $config['rate_limit']['login_email']);
        $this->assertSame(15, $config['rate_limit']['janela_minutos']);
        $this->assertSame(3, $config['rate_limit']['esqueci_email']);
        $this->assertSame(60, $config['rate_limit']['esqueci_janela_minutos']);
        $this->assertSame(20, $config['rate_limit']['redefinir_ip']);
        $this->assertSame(3, $config['rate_limit']['confirmacao_conta']);
        $this->assertSame(60, $config['rate_limit']['confirmacao_janela_minutos']);
        $this->assertSame(20, $config['rate_limit']['confirmar_ip']);
    }

    public function testEmailEAppUrlTemPadroes(): void
    {
        $config = $this->carregar();

        $this->assertSame('', $config['app_url']);
        $this->assertSame(['driver' => 'desligado', 'dsn' => '', 'from' => ''], $config['mail']);
    }

    public function testEmailEAppUrlLemOAmbiente(): void
    {
        putenv('APP_URL=https://app.exemplo.com/');
        putenv('MAIL_DRIVER=smtp');
        putenv('MAIL_DSN=smtp://u:p@smtp.exemplo.com:587');
        putenv('MAIL_FROM=ZeraFilas <nao-responda@exemplo.com>');

        $config = $this->carregar();

        $this->assertSame('https://app.exemplo.com', $config['app_url'], 'sem a barra final');
        $this->assertSame([
            'driver' => 'smtp',
            'dsn' => 'smtp://u:p@smtp.exemplo.com:587',
            'from' => 'ZeraFilas <nao-responda@exemplo.com>',
        ], $config['mail']);
    }

    public function testNaoDefineConstantesGlobais(): void
    {
        $this->carregar();

        foreach (['DBDRIVER', 'DBHOST', 'DBNAME', 'DBUSER', 'DBPASS'] as $constante) {
            $this->assertFalse(defined($constante), "$constante não deve existir");
        }
    }
}
