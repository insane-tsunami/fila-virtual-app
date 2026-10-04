<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Services\FabricaDeMailer;
use Services\MailerDesligado;
use Services\MailerFalhouException;
use Services\MailerLog;
use Services\MailerSmtp;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

final class MailerTest extends TestCase
{
    /** @var list<string> */
    private array $log = [];

    private const LINK = 'https://app.exemplo.com/redefinir-senha#token=segredo123';

    private function registrar(): callable
    {
        return function (string $mensagem): void {
            $this->log[] = $mensagem;
        };
    }

    protected function setUp(): void
    {
        $this->log = [];
    }

    private function falso(?TransportException $erro = null): object
    {
        return new class ($erro) implements MailerInterface {
            /** @var list<Email> */
            public array $enviados = [];

            public function __construct(private readonly ?TransportException $erro)
            {
            }

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                if ($this->erro !== null) {
                    throw $this->erro;
                }
                $this->enviados[] = $message;
            }
        };
    }

    // --- Fábrica ----------------------------------------------------------------------------

    public function testPadraoESemDriverEDesligado(): void
    {
        $this->assertInstanceOf(MailerDesligado::class, FabricaDeMailer::criar([], $this->registrar()));
        $this->assertInstanceOf(MailerDesligado::class, FabricaDeMailer::criar(['driver' => ''], $this->registrar()));
        $this->assertInstanceOf(MailerDesligado::class, FabricaDeMailer::criar(['driver' => 'desligado'], $this->registrar()));
        $this->assertSame([], $this->log);
    }

    public function testEscolheOsDriversPeloNomeSemDiferenciarMaiusculas(): void
    {
        $this->assertInstanceOf(MailerLog::class, FabricaDeMailer::criar(['driver' => 'LOG'], $this->registrar()));
        $this->assertInstanceOf(MailerSmtp::class, FabricaDeMailer::criar(['driver' => ' smtp '], $this->registrar()));
    }

    public function testValorDesconhecidoUsaDesligadoEAvisaNoLog(): void
    {
        $mailer = FabricaDeMailer::criar(['driver' => 'sendgrid'], $this->registrar());

        $this->assertInstanceOf(MailerDesligado::class, $mailer);
        $this->assertCount(1, $this->log);
        $this->assertStringContainsString("'sendgrid'", $this->log[0]);
    }

    // --- Desligado e log --------------------------------------------------------------------

    public function testDesligadoNaoGravaOConteudoSoAvisa(): void
    {
        (new MailerDesligado($this->registrar()))->enviar('a@exemplo.com', 'Assunto', self::LINK);

        $this->assertCount(1, $this->log);
        $this->assertStringNotContainsString('segredo123', $this->log[0]);
        $this->assertStringNotContainsString('a@exemplo.com', $this->log[0]);
        $this->assertStringContainsString('desligado', $this->log[0]);
    }

    public function testLogGravaDestinatarioAssuntoETextoComOLink(): void
    {
        (new MailerLog($this->registrar()))->enviar('a@exemplo.com', 'Redefinir', 'Abra ' . self::LINK);

        $this->assertCount(1, $this->log);
        $this->assertStringContainsString('a@exemplo.com', $this->log[0]);
        $this->assertStringContainsString('Redefinir', $this->log[0]);
        $this->assertStringContainsString(self::LINK, $this->log[0]);
    }

    // --- Quebra de linha / injeção de cabeçalho ----------------------------------------------

    /** @return array<string, array{string, string}> */
    public static function mensagensInvalidas(): array
    {
        return [
            'quebra no destinatário' => ["a@exemplo.com\r\nBcc: x@exemplo.com", 'Assunto'],
            'LF no destinatário' => ["a@exemplo.com\nBcc: x@exemplo.com", 'Assunto'],
            'quebra no assunto' => ['a@exemplo.com', "Oi\r\nBcc: x@exemplo.com"],
            'destinatário vazio' => ['', 'Assunto'],
        ];
    }

    #[DataProvider('mensagensInvalidas')]
    public function testTodosOsDriversRecusamQuebraDeLinha(string $para, string $assunto): void
    {
        $drivers = [
            new MailerDesligado($this->registrar()),
            new MailerLog($this->registrar()),
            new MailerSmtp('smtp://u:p@host:25', 'z@exemplo.com', $this->falso()),
        ];

        foreach ($drivers as $mailer) {
            try {
                $mailer->enviar($para, $assunto, 'texto');
                $this->fail($mailer::class . ' devia recusar');
            } catch (MailerFalhouException) {
            }
        }
        $this->assertSame([], $this->log);
    }

    // --- SMTP -------------------------------------------------------------------------------

    public function testSmtpEntregaAoTransporteComRemetenteDestinoAssuntoETexto(): void
    {
        $falso = $this->falso();

        (new MailerSmtp('smtp://u:p@host:25', 'ZeraFilas <nao-responda@exemplo.com>', $falso))
            ->enviar('dona@exemplo.com', 'Redefinir sua senha', 'Link: ' . self::LINK);

        $this->assertCount(1, $falso->enviados);
        $email = $falso->enviados[0];
        $this->assertSame('nao-responda@exemplo.com', $email->getFrom()[0]->getAddress());
        $this->assertSame('dona@exemplo.com', $email->getTo()[0]->getAddress());
        $this->assertSame('Redefinir sua senha', $email->getSubject());
        $this->assertSame('Link: ' . self::LINK, $email->getTextBody());
        $this->assertNull($email->getHtmlBody());
    }

    /** @return array<string, array{string, string}> */
    public static function configuracaoIncompleta(): array
    {
        return ['sem DSN' => ['', 'a@exemplo.com'], 'sem remetente' => ['smtp://u:p@host:25', ''], 'nenhum' => ['', '']];
    }

    #[DataProvider('configuracaoIncompleta')]
    public function testSmtpSemConfiguracaoFalhaSemVazarNada(string $dsn, string $from): void
    {
        $falso = $this->falso();

        try {
            (new MailerSmtp($dsn, $from, $falso))->enviar('dona@exemplo.com', 'Assunto', self::LINK);
            $this->fail('devia falhar');
        } catch (MailerFalhouException $e) {
            $this->assertStringContainsString('MAIL_DSN', $e->getMessage());
            $this->assertStringNotContainsString('segredo123', $e->getMessage());
        }
        $this->assertSame([], $falso->enviados);
    }

    public function testSmtpRecusadoNaoVazaASenhaDoDsnNemOLink(): void
    {
        $erro = new TransportException('Falha em smtp://usuario:SENHA-DO-SMTP@host:587 ao enviar ' . self::LINK);

        try {
            (new MailerSmtp('smtp://usuario:SENHA-DO-SMTP@host:587', 'z@exemplo.com', $this->falso($erro)))
                ->enviar('dona@exemplo.com', 'Assunto', self::LINK);
            $this->fail('devia falhar');
        } catch (MailerFalhouException $e) {
            $this->assertStringNotContainsString('SENHA-DO-SMTP', $e->getMessage());
            $this->assertStringNotContainsString('segredo123', $e->getMessage());
            $this->assertNull($e->getPrevious(), 'a exceção original (com o DSN) não é encadeada');
            $this->assertStringContainsString('TransportException', $e->getMessage());
        }
    }

    public function testSmtpComDsnInvalidoFalhaSemVazarODsn(): void
    {
        try {
            (new MailerSmtp('isto-nao-e-dsn://u:SENHA-DO-SMTP@', 'z@exemplo.com'))
                ->enviar('dona@exemplo.com', 'Assunto', 'texto');
            $this->fail('devia falhar');
        } catch (MailerFalhouException $e) {
            $this->assertStringNotContainsString('SENHA-DO-SMTP', $e->getMessage());
        }
    }
}
