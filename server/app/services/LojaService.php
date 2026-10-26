<?php

declare(strict_types=1);

namespace Services;

use Models\Estabelecimento;
use Support\Avatar;
use Support\EnderecoPublico;

/** Dados públicos da loja, o seu endereço público configurável e o avatar. */
final class LojaService
{
    /** @return array{nome: string, slug: string, endereco_publico: string|null, avatar: array{cor: string, icone: string}|null} */
    public function dados(string $slug): array
    {
        return $this->resumo($this->estabelecimento($slug));
    }

    /**
     * Define o endereço público da loja. `null` ou texto vazio limpam o endereço;
     * o campo ausente é um erro, para um corpo malformado nunca limpar o endereço.
     *
     * @param array<mixed> $corpo
     * @return array{nome: string, slug: string, endereco_publico: string|null, avatar: array{cor: string, icone: string}|null}
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

    /**
     * Define o avatar da loja (cor e ícone das listas fixas) ou o apaga com as duas partes nulas.
     * Qualquer outro corpo é 422 e o avatar anterior fica.
     *
     * @param array<mixed> $corpo
     * @return array{nome: string, slug: string, endereco_publico: string|null, avatar: array{cor: string, icone: string}|null}
     */
    public function definirAvatar(string $slug, array $corpo): array
    {
        $estabelecimento = $this->estabelecimento($slug);

        if (!Avatar::corpoValido($corpo)) {
            throw new DadosInvalidosException(Avatar::MENSAGEM_INVALIDA);
        }

        $estabelecimento->update(['avatar_cor' => $corpo['cor'], 'avatar_icone' => $corpo['icone']]);

        return $this->resumo($estabelecimento);
    }

    /** @return array{nome: string, slug: string, endereco_publico: string|null, avatar: array{cor: string, icone: string}|null} */
    private function resumo(Estabelecimento $estabelecimento): array
    {
        return $estabelecimento->paraResposta();
    }

    private function estabelecimento(string $slug): Estabelecimento
    {
        return Estabelecimento::query()->where('slug', $slug)->first()
            ?? throw new NaoEncontradoException('Estabelecimento não encontrado.');
    }
}
