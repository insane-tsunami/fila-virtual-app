import React from 'react';
import { MemoryRouter } from 'react-router-dom';
import { render } from '@testing-library/react';

import Dashboard from '.';
import QrCode from './Qrcode';
import Perfil from './Perfil';
import { ApiError, buscarLoja } from '../api';
import {
  nome as nomeReserva,
  inicial as inicialReserva,
} from './estabelecimento';

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
    listarFila: jest.fn(() => new Promise(() => {})),
  };
});

const paginas = [
  ['Dashboard', Dashboard],
  ['Gerar QRCode', QrCode],
  ['Perfil', Perfil],
];

function renderPagina(Pagina) {
  return render(
    <MemoryRouter>
      <Pagina />
    </MemoryRouter>
  );
}

beforeEach(() => {
  buscarLoja.mockReset();
});

describe.each(paginas)('Barra lateral: %s', (_, Pagina) => {
  it('mostra o nome e a inicial vindos da API', async () => {
    buscarLoja.mockResolvedValue({ nome: 'moda azul', slug: 'veste-bem' });
    const u = renderPagina(Pagina);

    expect(await u.findByText('moda azul')).toBeInTheDocument();
    expect(u.getByText('M')).toBeInTheDocument();
    expect(buscarLoja).toHaveBeenCalledWith('veste-bem');
  });

  it('usa o nome fixo de reserva enquanto a API não respondeu', () => {
    buscarLoja.mockReturnValue(new Promise(() => {}));
    const u = renderPagina(Pagina);

    expect(u.getByText(nomeReserva)).toBeInTheDocument();
    expect(u.getByText(inicialReserva)).toBeInTheDocument();
  });

  it('mantém o nome de reserva se a API falhar e a página segue utilizável', async () => {
    buscarLoja.mockRejectedValue(new ApiError(0, 'rede'));
    const u = renderPagina(Pagina);

    await new Promise((r) => setTimeout(r, 0));
    expect(u.getByText(nomeReserva)).toBeInTheDocument();
    expect(u.getByText('Sair')).toBeInTheDocument();
  });
});
