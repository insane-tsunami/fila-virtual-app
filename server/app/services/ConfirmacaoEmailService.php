<?php

declare(strict_types=1);

namespace Services;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\QueryException;
use Models\Conta;
use Support\Email;
use Support\IpDoCliente;
use Support\Senha;
use Throwable;

/**
 * Confirmação do e-mail da conta. O token (256 bits) só existe no e-mail: o banco guarda o sha256,
 * um por conta, válido por 24 horas, de uso único e preso ao e-mail para o qual foi enviado (trocar
 * o e-mail invalida o anterior). Falha de envio e APP_URL ausente vão para o log, nunca para a resposta.
 */
final class ConfirmacaoEmailService
{
    public const VALIDADE_HORAS = 24;
    public const MENSAGEM_CONFIRMADO = 'E-mail confirmado.';
    public const MENSAGEM_REENVIADO = 'Enviamos um novo link de confirmação.';
    public const MENSAGEM_LINK_INVALIDO = 'Link inválido ou expirado. Peça um novo e-mail de confirmação.';
    public const MENSAGEM_JA_CONFIRMADO = 'O e-mail já foi confirmado e não pode ser alterado.';
    public const ASSUNTO = 'ZeraFilas: confirme seu e-mail';

    /** @var callable(string): void */
    private $registrar;

    /**
     * @param string $appUrl base do link (sem barra final); vazia = o link não pode ser montado
     * @param (callable(string): void)|null $registrar avisos para o log
     */
    public function __construct(
        private readonly Mailer $mailer,
        private readonly string $appUrl,
        private readonly ?LimiteDeTentativas $limite = null,
        ?callable $registrar = null,
    ) {
        $this->registrar = $registrar ?? static function (string $mensagem): void {
            error_log('[confirmacao] ' . $mensagem);
        };
    }

    /** Cria um token novo (apaga o anterior da conta) e manda o link; nunca lança por falha de envio. */
    public function enviar(Conta $conta): void
    {
        if ($this->appUrl === '') {
            ($this->registrar)('APP_URL não está configurada: o link de confirmação de e-mail não pôde ser montado.');

            return;
        }

        $token = $this->criarToken($conta);
        try {
            $this->mailer->enviar(
                $conta->email,
                self::ASSUNTO,
                $this->texto($this->appUrl . '/confirmar-email#token=' . $token)
            );
        } catch (Throwable $e) {
            ($this->registrar)(
                'Falha ao enviar o e-mail de confirmação: '
                . ($e instanceof MailerFalhouException ? $e->getMessage() : $e::class)
            );
        }
    }

    /** Confirma o e-mail da conta do token (de uso único, preso ao e-mail atual da conta). */
    public function confirmar(mixed $token, string $ip = IpDoCliente::DESCONHECIDO): void
    {
        $chaveIp = LimiteDeTentativas::chave($ip);
        $this->limite?->verificar(LimiteDeTentativas::CONFIRMAR_IP, $chaveIp);

        $registro = is_string($token) && $token !== ''
            ? Capsule::table('confirmacoes_email')
                ->where('token_hash', hash('sha256', $token))
                ->where('expira_em', '>', gmdate('Y-m-d H:i:s'))
                ->first()
            : null;
        $conta = $registro === null ? null : Conta::query()->find($registro->conta_id);

        if ($registro === null || $conta === null || $conta->email !== $registro->email) {
            $this->limite?->registrar(LimiteDeTentativas::CONFIRMAR_IP, $chaveIp);

            throw new DadosInvalidosException(self::MENSAGEM_LINK_INVALIDO);
        }

        Capsule::connection()->transaction(function () use ($conta): void {
            Conta::query()->whereKey($conta->id)->whereNull('email_confirmado_em')
                ->update(['email_confirmado_em' => gmdate('Y-m-d H:i:s')]);
            Capsule::table('confirmacoes_email')->where('conta_id', $conta->id)->delete();
        });
    }

    /** Reenvia a confirmação (409 se o e-mail já foi confirmado) e devolve a mensagem para a resposta. */
    public function reenviar(int $contaId): string
    {
        $conta = Conta::query()->find($contaId) ?? throw new NaoEncontradoException('Conta não encontrada.');
        if ($conta->email_confirmado_em !== null) {
            throw new ConflitoException('O e-mail já foi confirmado.');
        }

        $this->limite?->consumir(LimiteDeTentativas::CONFIRMACAO_CONTA, LimiteDeTentativas::chave($contaId));
        $this->enviar($conta);

        return self::MENSAGEM_REENVIADO;
    }

    /**
     * Troca o e-mail de uma conta ainda não confirmada (a saída para o e-mail digitado errado):
     * exige a senha atual, invalida a confirmação anterior e manda a nova para o endereço novo.
     *
     * @param array<mixed> $corpo
     * @return array{email: string, cnpj: string, email_confirmado: bool}
     */
    public function trocarEmail(int $contaId, array $corpo): array
    {
        $email = Email::normalizar($corpo['email'] ?? null)
            ?? throw new DadosInvalidosException('E-mail inválido.');
        $senha = $corpo['senha'] ?? null;
        if (!is_string($senha) || $senha === '') {
            throw new DadosInvalidosException('Informe o novo e-mail e a senha atual.');
        }

        $conta = Conta::query()->find($contaId) ?? throw new NaoEncontradoException('Conta não encontrada.');
        if ($conta->email_confirmado_em !== null) {
            throw new ConflitoException(self::MENSAGEM_JA_CONFIRMADO);
        }

        // Mesmo limite de senha errada da troca de senha: errar numa rota não dá tentativas na outra.
        $chaveConta = LimiteDeTentativas::chave($contaId);
        $this->limite?->verificar(LimiteDeTentativas::SENHA_CONTA, $chaveConta);
        if (!Senha::confere($senha, $conta->senha_hash)) {
            $this->limite?->registrar(LimiteDeTentativas::SENHA_CONTA, $chaveConta);

            throw new DadosInvalidosException('Senha atual incorreta.');
        }
        if ($email === $conta->email) {
            throw new DadosInvalidosException('Informe um e-mail diferente do atual.');
        }
        if (Conta::query()->where('email', $email)->exists()) {
            throw new ConflitoException('Já existe uma conta com este e-mail.');
        }

        $this->limite?->consumir(LimiteDeTentativas::CONFIRMACAO_CONTA, $chaveConta);
        try {
            Conta::query()->whereKey($contaId)->update(['email' => $email]);
        } catch (QueryException) {
            // outra conta pegou o e-mail entre a conferência e a gravação (a coluna é única)
            throw new ConflitoException('Já existe uma conta com este e-mail.');
        }
        $this->limite?->zerar(LimiteDeTentativas::SENHA_CONTA, $chaveConta);

        $conta->refresh();
        $this->enviar($conta);

        return $conta->paraResposta();
    }

    /** Guarda só o hash do token novo; apaga o anterior da conta e os vencidos de qualquer conta. */
    private function criarToken(Conta $conta): string
    {
        $token = bin2hex(random_bytes(32));
        $agora = time();

        Capsule::connection()->transaction(function () use ($conta, $token, $agora): void {
            Capsule::table('confirmacoes_email')
                ->where('conta_id', $conta->id)
                ->orWhere('expira_em', '<=', gmdate('Y-m-d H:i:s', $agora))
                ->delete();
            Capsule::table('confirmacoes_email')->insert([
                'conta_id' => $conta->id,
                'email' => $conta->email,
                'token_hash' => hash('sha256', $token),
                'criado_em' => gmdate('Y-m-d H:i:s', $agora),
                'expira_em' => gmdate('Y-m-d H:i:s', $agora + self::VALIDADE_HORAS * 3600),
            ]);
        });

        return $token;
    }

    private function texto(string $link): string
    {
        return "Olá!\n\n"
            . "Para confirmar que este e-mail é seu e concluir o cadastro no ZeraFilas, abra o link abaixo. "
            . "Ele vale por 24 horas e só pode ser usado uma vez:\n\n"
            . $link . "\n\n"
            . "Se você não criou uma conta no ZeraFilas, ignore este e-mail.\n";
    }
}
