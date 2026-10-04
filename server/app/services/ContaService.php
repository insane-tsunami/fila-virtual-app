<?php

declare(strict_types=1);

namespace Services;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\QueryException;
use Models\Conta;
use Models\Estabelecimento;
use Support\Cnpj;
use Support\Email;
use Support\Senha;
use Support\Slug;

/** Cadastro da conta (junto com a loja que ela possui), consulta dos dados e troca de senha. */
final class ContaService
{
    private const NOME_MINIMO = 2;
    private const NOME_MAXIMO = 120;
    private const TENTATIVAS_DE_SLUG = 50;

    public function __construct(private readonly SessaoService $sessoes)
    {
    }

    /**
     * Cria conta, loja e sessão em uma transação: ou tudo, ou nada.
     *
     * @param array<mixed> $corpo
     * @return array{token: string, expira_em: string, conta: array{email: string, cnpj: string}, loja: array{nome: string, slug: string, endereco_publico: string|null}}
     */
    public function cadastrar(array $corpo): array
    {
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
            throw new DadosInvalidosException(
                'Senha inválida: use de 8 a 72 caracteres (letras acentuadas contam como 2).'
            );
        }

        return Capsule::connection()->transaction(function () use ($email, $cnpj, $nome, $base, $senha): array {
            if (Conta::query()->where('email', $email)->exists()) {
                throw new ConflitoException('Já existe uma conta com este e-mail.');
            }
            if (Conta::query()->where('cnpj', $cnpj)->exists()) {
                throw new ConflitoException('Já existe uma conta com este CNPJ.');
            }

            $conta = Conta::create([
                'email' => $email,
                'cnpj' => $cnpj,
                'senha_hash' => Senha::gerarHash($senha),
                'criado_em' => gmdate('Y-m-d H:i:s'),
            ]);
            $this->criarLoja($conta, $nome, $base);

            return $this->sessoes->abrir($conta);
        });
    }

    /** @return array{conta: array{email: string, cnpj: string}, loja: array{nome: string, slug: string, endereco_publico: string|null}} */
    public function dados(int $contaId): array
    {
        $conta = Conta::query()->find($contaId) ?? throw new NaoEncontradoException('Conta não encontrada.');
        $loja = Estabelecimento::query()->where('conta_id', $contaId)->first()
            ?? throw new NaoEncontradoException('Esta conta não tem loja.');

        return [
            'conta' => ['email' => $conta->email, 'cnpj' => $conta->cnpj],
            'loja' => [
                'nome' => $loja->nome,
                'slug' => $loja->slug,
                'endereco_publico' => $loja->endereco_publico,
            ],
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

        $conta = Conta::query()->find($contaId) ?? throw new NaoEncontradoException('Conta não encontrada.');
        if (!Senha::confere($atual, $conta->senha_hash)) {
            throw new DadosInvalidosException('Senha atual incorreta.');
        }
        if (!Senha::valida($nova)) {
            throw new DadosInvalidosException(
                'Senha inválida: use de 8 a 72 caracteres (letras acentuadas contam como 2).'
            );
        }

        Capsule::connection()->transaction(function () use ($conta, $sessaoId, $nova): void {
            $conta->update(['senha_hash' => Senha::gerarHash($nova)]);
            $this->sessoes->encerrarOutras($conta->id, $sessaoId);
        });
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
            } catch (QueryException) {
                // Outro cadastro levou o slug entre a consulta e o INSERT: tenta o próximo.
                continue;
            }
        }

        throw new ConflitoException('Não foi possível gerar um endereço para esta loja. Tente outro nome.');
    }
}
