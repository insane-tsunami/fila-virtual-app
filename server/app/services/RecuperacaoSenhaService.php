<?php

declare(strict_types=1);

namespace Services;

use Illuminate\Database\Capsule\Manager as Capsule;
use Models\Conta;
use Support\Email;
use Support\IpDoCliente;
use Support\Senha;
use Throwable;

/**
 * "Esqueci a senha": o pedido manda um link por e-mail e a redefinição o conclui. O token (256 bits)
 * só existe no e-mail: o banco guarda o sha256 dele, um por conta, válido por 1 hora e de uso único.
 * O pedido responde sempre igual, com ou sem conta, e falha de envio vai para o log, nunca para a
 * resposta: do contrário a resposta revelaria quais e-mails têm conta.
 */
final class RecuperacaoSenhaService
{
    public const VALIDADE_MINUTOS = 60;
    public const MENSAGEM_PEDIDO = 'Se o e-mail estiver cadastrado, enviamos um link para redefinir a senha.';
    public const MENSAGEM_LINK_INVALIDO = 'Link inválido ou expirado. Peça um novo.';
    public const ASSUNTO = 'ZeraFilas: redefinir sua senha';

    /** @var callable(string): void */
    private $registrar;

    /**
     * @param string $appUrl base do link (sem barra final); vazia = o link não pode ser montado
     * @param (callable(string): void)|null $registrar avisos para o log
     */
    public function __construct(
        private readonly SessaoService $sessoes,
        private readonly Mailer $mailer,
        private readonly string $appUrl,
        private readonly ?LimiteDeTentativas $limite = null,
        ?callable $registrar = null,
    ) {
        $this->registrar = $registrar ?? static function (string $mensagem): void {
            error_log('[senha] ' . $mensagem);
        };
    }

    /** Responde sempre com a mesma mensagem; só envia o e-mail se a conta existe. */
    public function pedir(mixed $email, string $ip = IpDoCliente::DESCONHECIDO): string
    {
        $normalizado = Email::normalizar($email) ?? throw new DadosInvalidosException('E-mail inválido.');

        // Todo pedido conta (com ou sem conta): o 429 não pode revelar quais e-mails existem.
        $this->limite?->consumir(LimiteDeTentativas::ESQUECI_IP, LimiteDeTentativas::chave($ip));
        $this->limite?->consumir(LimiteDeTentativas::ESQUECI_EMAIL, LimiteDeTentativas::chave($normalizado));

        $conta = Conta::query()->where('email', $normalizado)->first();
        if ($conta === null) {
            return self::MENSAGEM_PEDIDO;
        }
        if ($this->appUrl === '') {
            ($this->registrar)('APP_URL não está configurada: o link de redefinição de senha não pôde ser montado.');

            return self::MENSAGEM_PEDIDO;
        }

        $token = $this->criarToken($conta->id);
        try {
            $this->mailer->enviar($conta->email, self::ASSUNTO, $this->texto($this->appUrl . '/redefinir-senha#token=' . $token));
        } catch (Throwable $e) {
            ($this->registrar)('Falha ao enviar o e-mail de redefinição de senha: ' . $this->mensagemSegura($e));
        }

        return self::MENSAGEM_PEDIDO;
    }

    /** Troca a senha da conta do token, queima os tokens dela e encerra todas as sessões. */
    public function redefinir(mixed $token, mixed $novaSenha, string $ip = IpDoCliente::DESCONHECIDO): void
    {
        $chaveIp = LimiteDeTentativas::chave($ip);
        $this->limite?->verificar(LimiteDeTentativas::REDEFINIR_IP, $chaveIp);

        $registro = is_string($token) && $token !== ''
            ? Capsule::table('redefinicoes_senha')
                ->where('token_hash', hash('sha256', $token))
                ->where('expira_em', '>', gmdate('Y-m-d H:i:s'))
                ->first()
            : null;
        if ($registro === null) {
            $this->limite?->registrar(LimiteDeTentativas::REDEFINIR_IP, $chaveIp);

            throw new DadosInvalidosException(self::MENSAGEM_LINK_INVALIDO);
        }

        // Senha inválida não queima o token nem conta no limite: a pessoa tenta de novo com o mesmo link.
        if (!Senha::valida($novaSenha)) {
            throw new DadosInvalidosException(Senha::MENSAGEM_INVALIDA);
        }

        Capsule::connection()->transaction(function () use ($registro, $novaSenha): void {
            Conta::query()->whereKey($registro->conta_id)->update(['senha_hash' => Senha::gerarHash($novaSenha)]);
            Capsule::table('redefinicoes_senha')->where('conta_id', $registro->conta_id)->delete();
            $this->sessoes->encerrarTodas((int) $registro->conta_id);
        });
    }

    /** Guarda só o hash do token novo; apaga o anterior da conta e os vencidos de qualquer conta. */
    private function criarToken(int $contaId): string
    {
        $token = bin2hex(random_bytes(32));
        $agora = time();

        Capsule::connection()->transaction(function () use ($contaId, $token, $agora): void {
            Capsule::table('redefinicoes_senha')
                ->where('conta_id', $contaId)
                ->orWhere('expira_em', '<=', gmdate('Y-m-d H:i:s', $agora))
                ->delete();
            Capsule::table('redefinicoes_senha')->insert([
                'conta_id' => $contaId,
                'token_hash' => hash('sha256', $token),
                'criado_em' => gmdate('Y-m-d H:i:s', $agora),
                'expira_em' => gmdate('Y-m-d H:i:s', $agora + self::VALIDADE_MINUTOS * 60),
            ]);
        });

        return $token;
    }

    private function texto(string $link): string
    {
        return "Olá!\n\n"
            . "Recebemos um pedido para redefinir a senha da sua conta no ZeraFilas. "
            . "Para criar uma nova senha, abra o link abaixo. Ele vale por 1 hora e só pode ser usado uma vez:\n\n"
            . $link . "\n\n"
            . "Se você não pediu isso, ignore este e-mail: a sua senha continua a mesma.\n";
    }

    /** Só a mensagem de uma falha de envio conhecida entra no log; qualquer outra vira o nome da classe. */
    private function mensagemSegura(Throwable $e): string
    {
        return $e instanceof MailerFalhouException ? $e->getMessage() : $e::class;
    }
}
