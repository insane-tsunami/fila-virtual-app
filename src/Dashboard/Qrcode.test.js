import React from 'react';
import { MemoryRouter } from 'react-router-dom';
import { render, fireEvent, wait } from '@testing-library/react';

import QrCode from './Qrcode';
import { ApiError, buscarLoja } from '../api';

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
  return { ApiError: ApiErrorMock, buscarLoja: jest.fn() };
});

function renderQrCode() {
  return render(
    <MemoryRouter>
      <QrCode />
    </MemoryRouter>
  );
}

const botao = (u) =>
  u.getByText('Gerar QRCode', { selector: '.MuiTypography-root' });

beforeEach(() => {
  buscarLoja.mockReset();
});

describe('Geração de QR code', () => {
  it('exibe o título e o botão "Gerar QRCode"', () => {
    const { getByText, container } = renderQrCode();

    expect(getByText('Gerar QRCode', { selector: 'h1' })).toBeInTheDocument();
    expect(
      getByText('Gerar QRCode', { selector: '.MuiTypography-root' })
    ).toBeInTheDocument();
    expect(container.querySelector('svg')).toBeInTheDocument();
  });

  it('não busca a loja nem desenha o QR antes de acionar o botão', () => {
    const u = renderQrCode();

    expect(buscarLoja).not.toHaveBeenCalled();
    expect(u.queryByTestId('qr')).not.toBeInTheDocument();
  });

  it('loja com endereço público: QR e texto da URL da loja', async () => {
    buscarLoja.mockResolvedValue({
      nome: 'Veste Bem',
      slug: 'veste-bem',
      endereco_publico: 'https://loja.exemplo.com',
    });
    const u = renderQrCode();
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
    const u = renderQrCode();
    fireEvent.click(botao(u));

    const url = `${window.location.origin}/fila/veste-bem`;
    expect(await u.findByText(url)).toBeInTheDocument();
    expect(u.getByTestId('qr').getAttribute('data-value')).toBe(url);
  });

  it('API fora do ar: mensagem e nenhum QR; nova tentativa funciona', async () => {
    buscarLoja.mockRejectedValueOnce(new ApiError(0, 'rede'));
    const u = renderQrCode();
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
    const u = renderQrCode();
    fireEvent.click(botao(u));

    expect(
      await u.findByText('Não foi possível gerar o QR code. Tente de novo.')
    ).toBeInTheDocument();
    expect(u.queryByTestId('qr')).not.toBeInTheDocument();
  });
});
