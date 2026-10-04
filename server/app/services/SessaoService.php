<?php

declare(strict_types=1);

namespace Services;

use Models\Conta;
use Models\Estabelecimento;
use Models\Sessao;
use Support\Email;
use Support\IpDoCliente;
use Support\Senha;

/**
 * Login e sessões. O token (256 bits aleatórios) só existe na resposta: o banco guarda
 * o sha256 dele. Cada sessão vale 7 dias a partir da criação, sem renovação.
 */
final class SessaoService
{
    public const VALIDADE_DIAS = 7;

    public function __construct(private readonly ?LimiteDeTentativas $limite = null)
    {
    }

    /**
     * @return array{token: string, expira_em: string, conta: array{email: string, cnpj: string, email_confirmado: bool}, loja: array{nome: string, slug: string, endereco_publico: string|null}}
     */
    public function entrar(mixed $email, mixed $senha, string $ip = IpDoCliente::DESCONHECIDO): array
    {
        if (!is_string($email) || !is_string($senha) || trim($email) === '' || $senha === '') {
            throw new DadosInvalidosException('Informe o e-mail e a senha.');
        }

        $normalizado = Email::normalizar($email);
        // E-mail com formato inválido não tem chave estável: conta só contra o IP.
        $chaveEmail = $normalizado === null ? null : LimiteDeTentativas::chave($normalizado);
        $chaveIp = LimiteDeTentativas::chave($ip);

        // Bloqueado: responde 429 antes de gastar um bcrypt, mesmo com a senha certa.
        $this->limite?->verificar(LimiteDeTentativas::LOGIN_IP, $chaveIp);
        if ($chaveEmail !== null) {
            $this->limite?->verificar(LimiteDeTentativas::LOGIN_EMAIL, $chaveEmail);
        }

        $conta = $normalizado === null ? null : Conta::query()->where('email', $normalizado)->first();

        // Sempre confere uma senha (contra um hash falso se a conta não existe): o tempo e a
        // mensagem não revelam se o e-mail está cadastrado.
        if (!Senha::confere($senha, $conta?->senha_hash) || $conta === null) {
            // E-mail desconhecido também conta: o 429 não pode revelar quais e-mails têm conta.
            $this->limite?->registrar(LimiteDeTentativas::LOGIN_IP, $chaveIp);
            if ($chaveEmail !== null) {
                $this->limite?->registrar(LimiteDeTentativas::LOGIN_EMAIL, $chaveEmail);
            }

            throw new NaoAutenticadoException('E-mail ou senha incorretos.');
        }

        if ($chaveEmail !== null) {
            $this->limite?->zerar(LimiteDeTentativas::LOGIN_EMAIL, $chaveEmail);
        }

        Sessao::query()->where('expira_em', '<', gmdate('Y-m-d H:i:s'))->delete();

        return $this->abrir($conta);
    }

    /**
     * Abre uma sessão para a conta e devolve o token (único momento em que ele existe em claro).
     *
     * @return array{token: string, expira_em: string, conta: array{email: string, cnpj: string, email_confirmado: bool}, loja: array{nome: string, slug: string, endereco_publico: string|null}}
     */
    public function abrir(Conta $conta): array
    {
        $token = bin2hex(random_bytes(32));
        $agora = time();
        $expiraEm = gmdate('Y-m-d H:i:s', $agora + self::VALIDADE_DIAS * 86400);

        Sessao::create([
            'conta_id' => $conta->id,
            'token_hash' => self::hashDoToken($token),
            'criado_em' => gmdate('Y-m-d H:i:s', $agora),
            'expira_em' => $expiraEm,
        ]);

        $loja = Estabelecimento::query()->where('conta_id', $conta->id)->first()
            ?? throw new NaoEncontradoException('Esta conta não tem loja.');

        return [
            'token' => $token,
            'expira_em' => $expiraEm,
            'conta' => $conta->paraResposta(),
            'loja' => [
                'nome' => $loja->nome,
                'slug' => $loja->slug,
                'endereco_publico' => $loja->endereco_publico,
            ],
        ];
    }

    /** Sessão válida (existe e não venceu) do token, ou null. */
    public function resolver(string $token): ?Sessao
    {
        if ($token === '') {
            return null;
        }

        return Sessao::query()
            ->where('token_hash', self::hashDoToken($token))
            ->where('expira_em', '>', gmdate('Y-m-d H:i:s'))
            ->first();
    }

    public function sair(int $sessaoId): void
    {
        Sessao::query()->whereKey($sessaoId)->delete();
    }

    /** Encerra todas as sessões da conta, menos a indicada. */
    public function encerrarOutras(int $contaId, int $manterSessaoId): void
    {
        Sessao::query()->where('conta_id', $contaId)->whereKeyNot($manterSessaoId)->delete();
    }

    /** Encerra todas as sessões da conta (usado ao redefinir a senha). */
    public function encerrarTodas(int $contaId): void
    {
        Sessao::query()->where('conta_id', $contaId)->delete();
    }

    private static function hashDoToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
