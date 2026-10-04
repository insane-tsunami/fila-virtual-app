import React from 'react';
import { MemoryRouter } from 'react-router-dom';
import { render, fireEvent, wait, act } from '@testing-library/react';

import QrCode from './Qrcode';
import { ChaveProvider } from './ChaveGate';
import { ApiError, buscarLoja, definirEndereco } from '../api';

jest.mock('qrcode.react', () => ({
  // eslint-disable-next-line react/prop-types
  QRCodeSVG: ({ value }) => <svg data-testid="qr" data-value={value} />,
}));

jest.mock('../api', () => {
  class ApiErrorMock extends Error {
    constructor(status, message) {
      super(message);
      this.status = status;
    }
  }
  return {
    ApiError: ApiErrorMock,
    buscarLoja: jest.fn(),
    definirEndereco: jest.fn(),
  };
});

const recusar = jest.fn();

async function renderQrCode() {
  const utils = render(
    <MemoryRouter>
      <ChaveProvider value={{ chave: 'k', recusar }}>
        <QrCode />
      </ChaveProvider>
    </MemoryRouter>
  );
  // deixa a barra lateral terminar de buscar o nome da loja
  await act(async () => {});
  return utils;
}

const botao = (u) =>
  u.getByText('Gerar QRCode', { selector: '.MuiTypography-root' });

beforeEach(() => {
  recusar.mockReset();
  definirEndereco.mockReset();
  buscarLoja.mockReset();
  // a barra lateral também consulta a loja ao montar
  buscarLoja.mockResolvedValue({
    nome: 'Veste Bem',
    slug: 'veste-bem',
    endereco_publico: null,
  });
});

describe('Geração de QR code', () => {
  it('exibe o título e o botão "Gerar QRCode"', async () => {
    const { getByText, container } = await renderQrCode();

    expect(getByText('Gerar QRCode', { selector: 'h1' })).toBeInTheDocument();
    expect(
      getByText('Gerar QRCode', { selector: '.MuiTypography-root' })
    ).toBeInTheDocument();
    expect(container.querySelector('svg')).toBeInTheDocument();
  });

  it('não desenha o QR antes de acionar o botão', async () => {
    const u = await renderQrCode();

    // só a barra lateral consultou a loja; o QR espera o botão
    expect(buscarLoja).toHaveBeenCalledTimes(1);
    expect(u.queryByTestId('qr')).not.toBeInTheDocument();

    fireEvent.click(botao(u));
    expect(await u.findByTestId('qr')).toBeInTheDocument();
    expect(buscarLoja).toHaveBeenCalledTimes(2);
  });

  it('loja com endereço público: QR e texto da URL da loja', async () => {
    buscarLoja.mockResolvedValue({
      nome: 'Veste Bem',
      slug: 'veste-bem',
      endereco_publico: 'https://loja.exemplo.com',
    });
    const u = await renderQrCode();
    fireEvent.click(botao(u));

    const url = 'https://loja.exemplo.com/fila/veste-bem';
    expect(await u.findByText(url)).toBeInTheDocument();
    expect(u.getByTestId('qr').getAttribute('data-value')).toBe(url);
    expect(buscarLoja).toHaveBeenCalledWith('veste-bem');
  });

  it('loja sem endereço: usa a origem do próprio front', async () => {
    buscarLoja.mockResolvedValue({
      nome: 'Veste Bem',
      slug: 'veste-bem',
      endereco_publico: null,
    });
    const u = await renderQrCode();
    fireEvent.click(botao(u));

    const url = `${window.location.origin}/fila/veste-bem`;
    expect(await u.findByText(url)).toBeInTheDocument();
    expect(u.getByTestId('qr').getAttribute('data-value')).toBe(url);
  });

  it('API fora do ar: mensagem e nenhum QR; nova tentativa funciona', async () => {
    const u = await renderQrCode();
    buscarLoja.mockRejectedValueOnce(new ApiError(0, 'rede'));
    fireEvent.click(botao(u));

    expect(
      await u.findByText('Não foi possível gerar o QR code. Tente de novo.')
    ).toBeInTheDocument();
    expect(u.queryByTestId('qr')).not.toBeInTheDocument();

    buscarLoja.mockResolvedValueOnce({
      nome: 'Veste Bem',
      slug: 'veste-bem',
      endereco_publico: 'https://loja.exemplo.com',
    });
    fireEvent.click(botao(u));

    expect(await u.findByTestId('qr')).toBeInTheDocument();
    await wait(() =>
      expect(
        u.queryByText('Não foi possível gerar o QR code. Tente de novo.')
      ).not.toBeInTheDocument()
    );
  });

  it('loja inexistente (404): mensagem e nenhum QR', async () => {
    buscarLoja.mockRejectedValue(new ApiError(404, 'Loja não encontrada'));
    const u = await renderQrCode();
    fireEvent.click(botao(u));

    expect(
      await u.findByText('Não foi possível gerar o QR code. Tente de novo.')
    ).toBeInTheDocument();
    expect(u.queryByTestId('qr')).not.toBeInTheDocument();
  });
});

const REDE = 'Não foi possível falar com o servidor. Tente de novo.';
const campo = (u) => u.getByLabelText('Endereço público da loja');
const salvar = (u) => fireEvent.submit(campo(u).closest('form'));
const loja = (endereco) => ({
  nome: 'Veste Bem',
  slug: 'veste-bem',
  endereco_publico: endereco,
});

describe('Endereço público da loja', () => {
  it('mostra o endereço atual da loja no campo', async () => {
    buscarLoja.mockResolvedValue(loja('https://loja.exemplo.com'));
    const u = await renderQrCode();

    expect(campo(u).value).toBe('https://loja.exemplo.com');
  });

  it('loja sem endereço: campo vazio', async () => {
    const u = await renderQrCode();

    expect(campo(u).value).toBe('');
  });

  it('não pisa no que o dono já digitou quando a loja chega depois', async () => {
    let chegar;
    buscarLoja.mockReset().mockImplementation(
      () =>
        new Promise((resolve) => {
          chegar = () => resolve(loja('https://loja.exemplo.com'));
        })
    );
    const u = await renderQrCode();

    fireEvent.change(campo(u), {
      target: { value: 'https://novo.exemplo.com' },
    });
    await act(async () => chegar());

    expect(campo(u).value).toBe('https://novo.exemplo.com');
  });

  it('salva um endereço válido com a chave e o QR seguinte usa esse endereço', async () => {
    const u = await renderQrCode();
    definirEndereco.mockResolvedValue(loja('https://loja.exemplo.com'));
    fireEvent.change(campo(u), {
      target: { value: 'https://loja.exemplo.com' },
    });
    salvar(u);

    expect(await u.findByText('Endereço salvo.')).toBeInTheDocument();
    expect(definirEndereco).toHaveBeenCalledWith(
      'veste-bem',
      'https://loja.exemplo.com',
      'k'
    );

    buscarLoja.mockResolvedValue(loja('https://loja.exemplo.com'));
    fireEvent.click(botao(u));
    expect(
      await u.findByText('https://loja.exemplo.com/fila/veste-bem')
    ).toBeInTheDocument();
  });

  it('tira os espaços das pontas antes de enviar', async () => {
    const u = await renderQrCode();
    definirEndereco.mockResolvedValue(loja('https://loja.exemplo.com'));
    fireEvent.change(campo(u), {
      target: { value: '  https://loja.exemplo.com  ' },
    });
    salvar(u);

    await u.findByText('Endereço salvo.');
    expect(definirEndereco).toHaveBeenCalledWith(
      'veste-bem',
      'https://loja.exemplo.com',
      'k'
    );
  });

  it('mostra no campo o valor normalizado devolvido pela API', async () => {
    const u = await renderQrCode();
    definirEndereco.mockResolvedValue(loja('https://loja.exemplo.com'));
    fireEvent.change(campo(u), {
      target: { value: 'HTTPS://Loja.Exemplo.com/' },
    });
    salvar(u);

    await u.findByText('Endereço salvo.');
    expect(campo(u).value).toBe('https://loja.exemplo.com');
  });

  it('422: mostra a mensagem da API e mantém o texto digitado', async () => {
    const u = await renderQrCode();
    definirEndereco.mockRejectedValue(
      new ApiError(422, 'Endereço inválido: use só a origem')
    );
    fireEvent.change(campo(u), { target: { value: 'ftp://x' } });
    salvar(u);

    expect(
      await u.findByText('Endereço inválido: use só a origem')
    ).toBeInTheDocument();
    expect(campo(u).value).toBe('ftp://x');
    expect(u.queryByText('Endereço salvo.')).not.toBeInTheDocument();
  });

  it('campo vazio apaga o endereço e o QR seguinte usa a origem do front', async () => {
    buscarLoja.mockResolvedValue(loja('https://loja.exemplo.com'));
    const u = await renderQrCode();
    definirEndereco.mockResolvedValue(loja(null));
    fireEvent.change(campo(u), { target: { value: '' } });
    salvar(u);

    expect(await u.findByText('Endereço salvo.')).toBeInTheDocument();
    expect(definirEndereco).toHaveBeenCalledWith('veste-bem', '', 'k');

    buscarLoja.mockResolvedValue(loja(null));
    fireEvent.click(botao(u));
    expect(
      await u.findByText(`${window.location.origin}/fila/veste-bem`)
    ).toBeInTheDocument();
  });

  it('API fora do ar: mensagem de rede e campo mantido', async () => {
    const u = await renderQrCode();
    definirEndereco.mockRejectedValue(new ApiError(0, REDE));
    fireEvent.change(campo(u), {
      target: { value: 'https://loja.exemplo.com' },
    });
    salvar(u);

    expect(await u.findByText(REDE)).toBeInTheDocument();
    expect(campo(u).value).toBe('https://loja.exemplo.com');
  });

  it('401: pede a chave de novo', async () => {
    const u = await renderQrCode();
    definirEndereco.mockRejectedValue(new ApiError(401, 'x'));
    fireEvent.change(campo(u), {
      target: { value: 'https://loja.exemplo.com' },
    });
    salvar(u);
    await act(async () => {});

    expect(recusar).toHaveBeenCalledTimes(1);
  });

  it('salvar descarta o QR já mostrado', async () => {
    buscarLoja.mockResolvedValue(loja('https://velho.exemplo.com'));
    const u = await renderQrCode();
    fireEvent.click(botao(u));
    await u.findByTestId('qr');

    definirEndereco.mockResolvedValue(loja('https://novo.exemplo.com'));
    fireEvent.change(campo(u), {
      target: { value: 'https://novo.exemplo.com' },
    });
    salvar(u);

    await u.findByText('Endereço salvo.');
    expect(u.queryByTestId('qr')).not.toBeInTheDocument();
    expect(
      u.queryByText('https://velho.exemplo.com/fila/veste-bem')
    ).not.toBeInTheDocument();
  });

  it('um QR que ainda estava sendo gerado não aparece depois de salvar', async () => {
    const u = await renderQrCode();
    let responder;
    buscarLoja.mockImplementationOnce(
      () =>
        new Promise((resolve) => {
          responder = () => resolve(loja('https://velho.exemplo.com'));
        })
    );
    fireEvent.click(botao(u));

    definirEndereco.mockResolvedValue(loja('https://novo.exemplo.com'));
    fireEvent.change(campo(u), {
      target: { value: 'https://novo.exemplo.com' },
    });
    salvar(u);
    await u.findByText('Endereço salvo.');

    await act(async () => responder());
    expect(u.queryByTestId('qr')).not.toBeInTheDocument();
  });
});
