<?php

declare(strict_types=1);

namespace Services;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\QueryException;
use Models\Conta;
use Models\EntradaFila;
use Models\Estabelecimento;
use Support\Cnpj;
use Support\Email;
use Support\IpDoCliente;
use Support\Senha;
use Support\Slug;

/** Cadastro da conta (junto com a loja que ela possui), consulta dos dados e troca de senha. */
final class ContaService
{
    /** Prazo para confirmar o e-mail e janela sem entradas na fila que tornam a conta substituível. */
    public const PRAZO_CONFIRMACAO_DIAS = 14;
    private const NOME_MINIMO = 2;
    private const NOME_MAXIMO = 120;
    private const TENTATIVAS_DE_SLUG = 50;
    /** O MySQL pode abortar a transação por deadlock (SELECT ... FOR UPDATE + índices únicos): refaz. */
    private const TENTATIVAS_DA_TRANSACAO = 3;
    private const VIOLACAO_DE_INTEGRIDADE = '23000';
    private const MENSAGEM_EMAIL_EM_USO = 'Já existe uma conta com este e-mail.';
    private const MENSAGEM_CNPJ_EM_USO = 'Já existe uma conta com este CNPJ.';

    public function __construct(
        private readonly SessaoService $sessoes,
        private readonly ?LimiteDeTentativas $limite = null,
        private readonly ?ConfirmacaoEmailService $confirmacao = null,
        private readonly ExclusaoDeConta $exclusao = new ExclusaoDeConta(),
    )
    {
    }

    /**
     * Cria conta, loja e sessão em uma transação: ou tudo, ou nada. As contas abandonadas que ocupam
     * o e-mail ou o CNPJ são apagadas na mesma transação (ver `liberarEmailECnpj`).
     *
     * @param array<mixed> $corpo
     * @return array{token: string, expira_em: string, conta: array{email: string, cnpj: string, email_confirmado: bool, confirmar_ate: string|null}, loja: array{nome: string, slug: string, endereco_publico: string|null, avatar: array{cor: string, icone: string}|null}}
     */
    public function cadastrar(array $corpo, string $ip = IpDoCliente::DESCONHECIDO): array
    {
        // Toda chamada conta (inclusive as recusadas por 409/422): o risco é volume e o 409 revela contas.
        $this->limite?->consumir(LimiteDeTentativas::CADASTRO_IP, LimiteDeTentativas::chave($ip));

        foreach (['email', 'cnpj', 'nome', 'senha'] as $campo) {
            if (!array_key_exists($campo, $corpo)) {
                throw new DadosInvalidosException('Informe e-mail, cnpj, nome e senha.');
            }
        }

        $email = Email::normalizar($corpo['email'])
            ?? throw new DadosInvalidosException('E-mail inválido.');
        $cnpj = Cnpj::normalizar($corpo['cnpj'])
            ?? throw new DadosInvalidosException('CNPJ inválido: confira os caracteres e os dígitos verificadores.');
        $nome = is_string($corpo['nome']) ? trim($corpo['nome']) : '';
        $tamanho = mb_strlen($nome);
        if ($tamanho < self::NOME_MINIMO || $tamanho > self::NOME_MAXIMO) {
            throw new DadosInvalidosException('Nome do estabelecimento inválido: use de 2 a 120 caracteres.');
        }
        $base = Slug::deNome($nome)
            ?? throw new DadosInvalidosException('O nome do estabelecimento precisa ter letras ou números.');
        $senha = $corpo['senha'];
        if (!Senha::valida($senha)) {
            throw new DadosInvalidosException(Senha::MENSAGEM_INVALIDA);
        }

        $conta = null;
        try {
            $resposta = Capsule::connection()->transaction(function () use ($email, $cnpj, $nome, $base, $senha, &$conta): array {
                $this->liberarEmailECnpj($email, $cnpj);

                $agora = time();
                $conta = Conta::create([
                    'email' => $email,
                    'cnpj' => $cnpj,
                    'senha_hash' => Senha::gerarHash($senha),
                    'criado_em' => gmdate('Y-m-d H:i:s', $agora),
                    'confirmar_ate' => gmdate('Y-m-d H:i:s', $agora + self::PRAZO_CONFIRMACAO_DIAS * 86400),
                ]);
                $this->criarLoja($conta, $nome, $base);

                return $this->sessoes->abrir($conta);
            }, self::TENTATIVAS_DA_TRANSACAO);
        } catch (QueryException $e) {
            // Corrida: outro cadastro gravou o mesmo e-mail ou CNPJ entre a busca e o INSERT (UNIQUE).
            if (Conta::query()->where('email', $email)->exists()) {
                throw new ConflitoException(self::MENSAGEM_EMAIL_EM_USO);
            }
            if (Conta::query()->where('cnpj', $cnpj)->exists()) {
                throw new ConflitoException(self::MENSAGEM_CNPJ_EM_USO);
            }

            throw $e;
        }

        // Depois do commit: a falha do envio (que só vai para o log) não pode desfazer o cadastro.
        $this->confirmacao?->enviar($conta);

        return $resposta;
    }

    /** @return array{conta: array{email: string, cnpj: string, email_confirmado: bool, confirmar_ate: string|null}, loja: array{nome: string, slug: string, endereco_publico: string|null, avatar: array{cor: string, icone: string}|null}} */
    public function dados(int $contaId): array
    {
        $conta = Conta::query()->find($contaId) ?? throw new NaoEncontradoException('Conta não encontrada.');
        $loja = Estabelecimento::query()->where('conta_id', $contaId)->first()
            ?? throw new NaoEncontradoException('Esta conta não tem loja.');

        return [
            'conta' => $conta->paraResposta(),
            'loja' => $loja->paraResposta(),
        ];
    }

    /**
     * Troca a senha: a atual errada é 422 (e não 401, que significa "sem sessão válida").
     * As outras sessões da conta são encerradas e a que fez a troca continua.
     *
     * @param array<mixed> $corpo
     */
    public function trocarSenha(int $contaId, int $sessaoId, array $corpo): void
    {
        $atual = $corpo['senha_atual'] ?? null;
        $nova = $corpo['nova_senha'] ?? null;
        if (!is_string($atual) || !is_string($nova) || $atual === '' || $nova === '') {
            throw new DadosInvalidosException('Informe senha_atual e nova_senha.');
        }

        $chave = LimiteDeTentativas::chave($contaId);
        $this->limite?->verificar(LimiteDeTentativas::SENHA_CONTA, $chave);

        $conta = Conta::query()->find($contaId) ?? throw new NaoEncontradoException('Conta não encontrada.');
        if (!Senha::confere($atual, $conta->senha_hash)) {
            $this->limite?->registrar(LimiteDeTentativas::SENHA_CONTA, $chave);

            throw new DadosInvalidosException('Senha atual incorreta.');
        }
        if (!Senha::valida($nova)) {
            throw new DadosInvalidosException(Senha::MENSAGEM_INVALIDA);
        }

        Capsule::connection()->transaction(function () use ($conta, $sessaoId, $nova): void {
            $conta->update(['senha_hash' => Senha::gerarHash($nova)]);
            $this->sessoes->encerrarOutras($conta->id, $sessaoId);
        });
        $this->limite?->zerar(LimiteDeTentativas::SENHA_CONTA, $chave);
    }

    /**
     * Libera o e-mail e o CNPJ para o cadastro: 409 se alguma conta que os ocupa não pode ser
     * substituída (a mensagem do e-mail vem antes da do CNPJ); senão, apaga todas elas.
     */
    private function liberarEmailECnpj(string $email, string $cnpj): void
    {
        $bloqueadoras = Conta::query()
            ->where(static fn ($q) => $q->where('email', $email)->orWhere('cnpj', $cnpj))
            ->lockForUpdate()
            ->get();

        $agora = time();
        $doEmail = $bloqueadoras->firstWhere('email', $email);
        if ($doEmail !== null && !$this->substituivel($doEmail, $agora)) {
            throw new ConflitoException(self::MENSAGEM_EMAIL_EM_USO);
        }
        $doCnpj = $bloqueadoras->firstWhere('cnpj', $cnpj);
        if ($doCnpj !== null && !$this->substituivel($doCnpj, $agora)) {
            throw new ConflitoException(self::MENSAGEM_CNPJ_EM_USO);
        }

        foreach ($bloqueadoras as $abandonada) {
            $this->exclusao->excluir($abandonada->id);
        }
    }

    /**
     * Abandonada: e-mail não confirmado, prazo vencido e nenhuma entrada na fila da loja nos últimos
     * dias. Sem prazo gravado a conta nunca é substituível (na dúvida, não apaga).
     */
    private function substituivel(Conta $conta, int $agora): bool
    {
        if ($conta->email_confirmado_em !== null || $conta->confirmar_ate === null) {
            return false;
        }
        if ($conta->confirmar_ate >= gmdate('Y-m-d H:i:s', $agora)) {
            return false;
        }

        return !EntradaFila::query()
            ->whereIn('estabelecimento_id', Estabelecimento::query()->select('id')->where('conta_id', $conta->id))
            ->where('entrou_em', '>=', gmdate('Y-m-d H:i:s', $agora - self::PRAZO_CONFIRMACAO_DIAS * 86400))
            ->exists();
    }

    /** Cria a loja da conta com o primeiro slug livre (`base`, `base-2`, `base-3`...). */
    private function criarLoja(Conta $conta, string $nome, string $base): void
    {
        for ($n = 1; $n <= self::TENTATIVAS_DE_SLUG; $n++) {
            $sufixo = $n === 1 ? '' : '-' . $n;
            $slug = rtrim(substr($base, 0, Slug::TAMANHO_MAXIMO - strlen($sufixo)), '-') . $sufixo;

            if (Estabelecimento::query()->where('slug', $slug)->exists()) {
                continue;
            }

            try {
                Estabelecimento::create(['nome' => $nome, 'slug' => $slug, 'conta_id' => $conta->id]);

                return;
            } catch (QueryException $e) {
                // Outro cadastro levou o slug entre a consulta e o INSERT: tenta o próximo. Qualquer
                // outro erro (um deadlock, por exemplo) já abortou a transação e precisa subir.
                if ((string) $e->getCode() !== self::VIOLACAO_DE_INTEGRIDADE) {
                    throw $e;
                }
                continue;
            }
        }

        throw new ConflitoException('Não foi possível gerar um endereço para esta loja. Tente outro nome.');
    }
}
