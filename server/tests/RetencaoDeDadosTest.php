<?php

declare(strict_types=1);

namespace Tests;

use Models\Conta;
use Models\EntradaFila;
use Services\ConfirmacaoEmailService;
use Services\ContaService;
use Services\RetencaoDeDados;
use Services\SessaoService;

final class RetencaoDeDadosTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->migrate();
    }

    private function servico(int $horasAteEncerrar = 24, int $diasAteApagarTelefone = 30): RetencaoDeDados
    {
        return new RetencaoDeDados($horasAteEncerrar, $diasAteApagarTelefone);
    }

    private function haHoras(int $horas): string
    {
        return gmdate('Y-m-d H:i:s', time() - $horas * 3600);
    }

    private function haDias(int $dias): string
    {
        return gmdate('Y-m-d H:i:s', time() - $dias * 86400);
    }

    /** @param array<string, mixed> $atributos */
    private function criarEntrada(int $loja, array $atributos = []): int
    {
        return (int) $this->db->table('entradas_fila')->insertGetId($atributos + [
            'estabelecimento_id' => $loja,
            'codigo' => bin2hex(random_bytes(8)),
            'telefone' => '5511971778203',
            'status' => EntradaFila::AGUARDANDO,
            'entrou_em' => $this->haHoras(1),
        ]);
    }

    /** @return object{id: int, codigo: string, telefone: string|null, status: string, entrou_em: string, iniciou_em: string|null, finalizou_em: string|null} */
    private function entrada(int $id): object
    {
        return $this->db->table('entradas_fila')->where('id', $id)->first();
    }

    private function criarConta(string $email = 'dona@exemplo.com', string $cnpj = '93339970000105'): int
    {
        return (int) $this->db->table('contas')->insertGetId([
            'email' => $email, 'cnpj' => $cnpj, 'senha_hash' => 'x', 'criado_em' => $this->haDias(1),
        ]);
    }

    // --- Entradas esquecidas -------------------------------------------------------------------

    public function testEncerraAsAtivasEsquecidasEDeixaAFilaVazia(): void
    {
        $loja = $this->criarEstabelecimento();
        $emAtendimento = $this->criarEntrada($loja, [
            'status' => EntradaFila::EM_ATENDIMENTO, 'entrou_em' => $this->haHoras(30), 'iniciou_em' => $this->haHoras(29),
        ]);
        $aguardando = $this->criarEntrada($loja, ['entrou_em' => $this->haHoras(25)]);

        $contagens = $this->servico()->limpar();

        $this->assertSame(2, $contagens['entradas_encerradas']);
        foreach ([$emAtendimento, $aguardando] as $id) {
            $entrada = $this->entrada($id);
            $this->assertSame(EntradaFila::CANCELADO, $entrada->status);
            $this->assertNotNull($entrada->finalizou_em, 'a hora do encerramento vira o corte do telefone');
        }
    }

    public function testPromoveAProximaQuandoAEmAtendimentoEVencida(): void
    {
        $loja = $this->criarEstabelecimento();
        $vencida = $this->criarEntrada($loja, [
            'status' => EntradaFila::EM_ATENDIMENTO, 'entrou_em' => $this->haHoras(30), 'iniciou_em' => $this->haHoras(30),
        ]);
        $proxima = $this->criarEntrada($loja, ['entrou_em' => $this->haHoras(2)]);

        $this->servico()->limpar();

        $this->assertSame(EntradaFila::CANCELADO, $this->entrada($vencida)->status);
        $nova = $this->entrada($proxima);
        $this->assertSame(EntradaFila::EM_ATENDIMENTO, $nova->status);
        $this->assertGreaterThanOrEqual(time() - 5, strtotime($nova->iniciou_em . ' UTC'), 'promovida agora');
    }

    public function testDentroDoPrazoNaoMuda(): void
    {
        $loja = $this->criarEstabelecimento();
        $id = $this->criarEntrada($loja, ['entrou_em' => $this->haHoras(23)]);

        $this->assertSame(0, $this->servico()->limpar()['entradas_encerradas']);
        $this->assertSame(EntradaFila::AGUARDANDO, $this->entrada($id)->status);
        $this->assertNull($this->entrada($id)->finalizou_em);
    }

    public function testEmAtendimentoRecenteNaoEVencidaMesmoComEntradaAntiga(): void
    {
        $loja = $this->criarEstabelecimento();
        $id = $this->criarEntrada($loja, [
            'status' => EntradaFila::EM_ATENDIMENTO, 'entrou_em' => $this->haHoras(48), 'iniciou_em' => $this->haHoras(1),
        ]);

        $this->servico()->limpar();

        $this->assertSame(EntradaFila::EM_ATENDIMENTO, $this->entrada($id)->status, 'o prazo conta do início do atendimento');
    }

    public function testNaoMexeNasOutrasLojas(): void
    {
        $esquecida = $this->criarEstabelecimento('loja-a', 'Loja A');
        $ativa = $this->criarEstabelecimento('loja-b', 'Loja B');
        $antiga = $this->criarEntrada($esquecida, ['entrou_em' => $this->haHoras(30)]);
        $recente = $this->criarEntrada($ativa, ['entrou_em' => $this->haHoras(1)]);

        $this->servico()->limpar();

        $this->assertSame(EntradaFila::CANCELADO, $this->entrada($antiga)->status);
        $this->assertSame(EntradaFila::AGUARDANDO, $this->entrada($recente)->status);
    }

    public function testPrazoDeEncerramentoEConfiguravel(): void
    {
        $loja = $this->criarEstabelecimento();
        $id = $this->criarEntrada($loja, ['entrou_em' => $this->haHoras(2)]);

        $this->assertSame(0, $this->servico()->limpar()['entradas_encerradas']);
        $this->assertSame(1, $this->servico(1)->limpar()['entradas_encerradas']);
    }

    // --- Telefone ------------------------------------------------------------------------------

    public function testApagaOTelefoneDasEncerradasAntigasEMantemODasRecentes(): void
    {
        $loja = $this->criarEstabelecimento();
        $antiga = $this->criarEntrada($loja, [
            'status' => EntradaFila::FINALIZADO, 'entrou_em' => $this->haDias(31),
            'finalizou_em' => $this->haDias(31),
        ]);
        $recente = $this->criarEntrada($loja, [
            'status' => EntradaFila::FINALIZADO, 'entrou_em' => $this->haDias(29),
            'finalizou_em' => $this->haDias(29),
        ]);

        $this->assertSame(1, $this->servico()->limpar()['telefones_apagados']);
        $this->assertNull($this->entrada($antiga)->telefone);
        $this->assertSame('5511971778203', $this->entrada($recente)->telefone);
    }

    public function testPrazoDoTelefoneEConfiguravel(): void
    {
        $loja = $this->criarEstabelecimento();
        $id = $this->criarEntrada($loja, [
            'status' => EntradaFila::CANCELADO, 'entrou_em' => $this->haDias(8),
            'finalizou_em' => $this->haDias(8),
        ]);

        $this->assertSame(1, $this->servico(24, 7)->limpar()['telefones_apagados']);
        $this->assertNull($this->entrada($id)->telefone);
    }

    public function testMantemCodigoStatusEDatasAoApagarOTelefone(): void
    {
        $loja = $this->criarEstabelecimento();
        $id = $this->criarEntrada($loja, [
            'codigo' => 'codigo-guardado', 'status' => EntradaFila::FINALIZADO,
            'entrou_em' => $this->haDias(40), 'iniciou_em' => $this->haDias(40), 'finalizou_em' => $this->haDias(31),
        ]);
        $antes = $this->entrada($id);

        $this->servico()->limpar();

        $depois = $this->entrada($id);
        $this->assertNull($depois->telefone);
        $this->assertSame($antes->codigo, $depois->codigo);
        $this->assertSame($antes->status, $depois->status);
        $this->assertSame($antes->entrou_em, $depois->entrou_em);
        $this->assertSame($antes->iniciou_em, $depois->iniciou_em);
        $this->assertSame($antes->finalizou_em, $depois->finalizou_em);
    }

    public function testNaoApagaOTelefoneDasAtivas(): void
    {
        $loja = $this->criarEstabelecimento();
        $id = $this->criarEntrada($loja, [
            'status' => EntradaFila::AGUARDANDO, 'entrou_em' => $this->haDias(40),
        ]);

        // Prazo de encerramento alto: a entrada continua ativa e a regra do telefone não a alcança.
        $this->servico(1000)->limpar();

        $this->assertSame(EntradaFila::AGUARDANDO, $this->entrada($id)->status);
        $this->assertSame('5511971778203', $this->entrada($id)->telefone);
    }

    // --- Sessões, tokens e contadores ----------------------------------------------------------

    public function testRemoveOsVencidosEMantemOsValidos(): void
    {
        $conta = $this->criarConta();
        $vencido = $this->haHoras(1);
        $valido = gmdate('Y-m-d H:i:s', time() + 3600);
        $token = str_repeat('a', 64);

        $this->db->table('sessoes')->insert([
            'conta_id' => $conta, 'token_hash' => $token, 'criado_em' => $this->haHoras(2), 'expira_em' => $vencido,
        ]);
        $this->db->table('sessoes')->insert([
            'conta_id' => $conta, 'token_hash' => str_repeat('b', 64), 'criado_em' => $this->haHoras(1), 'expira_em' => $valido,
        ]);
        $this->db->table('redefinicoes_senha')->insert([
            'conta_id' => $conta, 'token_hash' => str_repeat('c', 64), 'criado_em' => $this->haHoras(2), 'expira_em' => $vencido,
        ]);
        $this->db->table('confirmacoes_email')->insert([
            'conta_id' => $conta, 'email' => 'dona@exemplo.com', 'token_hash' => str_repeat('d', 64),
            'criado_em' => $this->haHoras(2), 'expira_em' => $vencido,
        ]);
        $this->db->table('limites_tentativas')->insert([
            'escopo' => 'login_ip', 'chave' => $token, 'contagem' => 1, 'expira_em' => $vencido,
        ]);
        $this->db->table('limites_tentativas')->insert([
            'escopo' => 'login_email', 'chave' => $token, 'contagem' => 1, 'expira_em' => $valido,
        ]);

        $contagens = $this->servico()->limpar();

        $this->assertSame(1, $contagens['sessoes']);
        $this->assertSame(2, $contagens['tokens'], 'redefinição e confirmação somados');
        $this->assertSame(1, $contagens['contadores']);
        $this->assertSame([str_repeat('b', 64)], $this->db->table('sessoes')->pluck('token_hash')->all());
        $this->assertSame(0, $this->db->table('redefinicoes_senha')->count());
        $this->assertSame(0, $this->db->table('confirmacoes_email')->count());
        $this->assertSame(['login_email'], $this->db->table('limites_tentativas')->pluck('escopo')->all());
    }

    public function testSessaoValidaContinuaDandoAcesso(): void
    {
        $sessoes = new SessaoService();
        (new ContaService($sessoes))->cadastrar([
            'email' => 'contato@vestebem.com', 'cnpj' => '93339970000105',
            'nome' => 'Moda Azul', 'senha' => 'senha-segura-1',
        ]);
        // O cadastro também abre uma sessão; começamos do zero para contar só a do login.
        $this->db->table('sessoes')->delete();
        $aberta = $sessoes->entrar('contato@vestebem.com', 'senha-segura-1');
        $conta = Conta::query()->where('email', 'contato@vestebem.com')->firstOrFail();
        $this->db->table('sessoes')->insert([
            'conta_id' => $conta->id, 'token_hash' => str_repeat('a', 64),
            'criado_em' => $this->haDias(8), 'expira_em' => $this->haHoras(1),
        ]);

        $this->servico()->limpar();

        $this->assertNotNull($sessoes->resolver($aberta['token']), 'a sessão aberta agora continua valendo');
        $this->assertSame(1, $this->db->table('sessoes')->count());
    }

    public function testTokenDeConfirmacaoValidoContinuaAFuncionar(): void
    {
        $sessoes = new SessaoService();
        (new ContaService($sessoes))->cadastrar([
            'email' => 'contato@vestebem.com', 'cnpj' => '93339970000105',
            'nome' => 'Moda Azul', 'senha' => 'senha-segura-1',
        ]);
        $conta = Conta::query()->where('email', 'contato@vestebem.com')->firstOrFail();
        $mailer = new MailerEmMemoria();
        $confirmacao = new ConfirmacaoEmailService($mailer, 'https://app.exemplo.com');
        $confirmacao->enviar($conta);
        $token = $mailer->ultimoToken();
        $this->assertNotNull($token, 'o e-mail de confirmação saiu');

        $this->servico()->limpar();
        $confirmacao->confirmar($token);

        $this->assertNotNull(Conta::query()->findOrFail($conta->id)->email_confirmado_em);
    }

    public function testContaNaoConfirmadaVencidaContinuaNoBanco(): void
    {
        $conta = $this->criarConta();
        $this->db->table('contas')->where('id', $conta)->update(['confirmar_ate' => $this->haHoras(1)]);

        $this->servico()->limpar();

        $this->assertTrue($this->db->table('contas')->where('id', $conta)->exists(), 'a retenção não apaga contas');
    }

    public function testSegundaExecucaoComTudoLimpoDevolveZero(): void
    {
        $loja = $this->criarEstabelecimento();
        $this->criarEntrada($loja, ['entrou_em' => $this->haHoras(25)]);
        $this->criarEntrada($loja, [
            'status' => EntradaFila::FINALIZADO, 'entrou_em' => $this->haDias(40), 'finalizou_em' => $this->haDias(31),
        ]);
        $conta = $this->criarConta();
        $this->db->table('sessoes')->insert([
            'conta_id' => $conta, 'token_hash' => str_repeat('a', 64), 'criado_em' => $this->haDias(8), 'expira_em' => $this->haHoras(1),
        ]);
        $this->db->table('redefinicoes_senha')->insert([
            'conta_id' => $conta, 'token_hash' => str_repeat('c', 64), 'criado_em' => $this->haDias(1), 'expira_em' => $this->haHoras(1),
        ]);
        $this->db->table('confirmacoes_email')->insert([
            'conta_id' => $conta, 'email' => 'dona@exemplo.com', 'token_hash' => str_repeat('d', 64),
            'criado_em' => $this->haDias(1), 'expira_em' => $this->haHoras(1),
        ]);
        $this->db->table('limites_tentativas')->insert([
            'escopo' => 'login_ip', 'chave' => str_repeat('e', 64), 'contagem' => 1, 'expira_em' => $this->haHoras(1),
        ]);

        $this->assertSame([
            'entradas_encerradas' => 1,
            'telefones_apagados' => 1,
            'sessoes' => 1,
            'tokens' => 2,
            'contadores' => 1,
        ], $this->servico()->limpar());
        $this->assertSame([
            'entradas_encerradas' => 0,
            'telefones_apagados' => 0,
            'sessoes' => 0,
            'tokens' => 0,
            'contadores' => 0,
        ], $this->servico()->limpar());
    }
}
