<?php

declare(strict_types=1);

namespace Services;

use Models\Estabelecimento;
use Support\EnderecoPublico;

/** Dados públicos da loja e o seu endereço público configurável. */
final class LojaService
{
    /** @return array{nome: string, slug: string, endereco_publico: string|null} */
    public function dados(string $slug): array
    {
        return $this->resumo($this->estabelecimento($slug));
    }

    /**
     * Define o endereço público da loja. `null` ou texto vazio limpam o endereço;
     * o campo ausente é um erro, para um corpo malformado nunca limpar o endereço.
     *
     * @param array<mixed> $corpo
     * @return array{nome: string, slug: string, endereco_publico: string|null}
     */
    public function definirEndereco(string $slug, array $corpo): array
    {
        $estabelecimento = $this->estabelecimento($slug);

        if (!array_key_exists('endereco_publico', $corpo)) {
            throw new DadosInvalidosException(
                'Informe o campo endereco_publico (use null para limpar o endereço).'
            );
        }

        $valor = $corpo['endereco_publico'];
        $endereco = null;
        if ($valor !== null && $valor !== '') {
            $endereco = EnderecoPublico::normalizar($valor)
                ?? throw new DadosInvalidosException(
                    'Endereço inválido: use só a origem do site, como https://loja.exemplo.com '
                    . '(http ou https, sem usuário, caminho, query ou fragmento, até 255 caracteres).'
                );
        }

        $estabelecimento->update(['endereco_publico' => $endereco]);

        return $this->resumo($estabelecimento);
    }

    /** @return array{nome: string, slug: string, endereco_publico: string|null} */
    private function resumo(Estabelecimento $estabelecimento): array
    {
        return [
            'nome' => $estabelecimento->nome,
            'slug' => $estabelecimento->slug,
            'endereco_publico' => $estabelecimento->endereco_publico,
        ];
    }

    private function estabelecimento(string $slug): Estabelecimento
    {
        return Estabelecimento::query()->where('slug', $slug)->first()
            ?? throw new NaoEncontradoException('Estabelecimento não encontrado.');
    }
}
