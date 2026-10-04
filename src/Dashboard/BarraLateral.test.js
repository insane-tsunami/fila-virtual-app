import React from 'react';
import { MemoryRouter } from 'react-router-dom';
import { render } from '@testing-library/react';

import Dashboard from '.';
import QrCode from './Qrcode';
import Perfil from './Perfil';
import SessaoDeTeste from '../sessao/SessaoDeTeste';
import { listarFila } from '../api';

jest.mock('../api', () => ({
  ApiError: class extends Error {},
  listarFila: jest.fn(() => new Promise(() => {})),
  buscarLoja: jest.fn(),
  definirEndereco: jest.fn(),
  trocarSenha: jest.fn(),
}));

const paginas = [
  ['Dashboard', Dashboard],
  ['Gerar QRCode', QrCode],
  ['Perfil', Perfil],
];

function renderPagina(Pagina, nome) {
  return render(
    <MemoryRouter>
      <SessaoDeTeste
        valor={{
          loja: { nome, slug: 'qualquer', endereco_publico: null },
          conta: { email: 'a@exemplo.com', cnpj: '93339970000105' },
        }}
      >
        <Pagina />
      </SessaoDeTeste>
    </MemoryRouter>
  );
}

describe.each(paginas)('Barra lateral: %s', (_, Pagina) => {
  it('mostra o nome e a inicial da loja da conta logada, sem consultar a API', () => {
    const u = renderPagina(Pagina, 'moda azul');

    expect(u.getByText('moda azul')).toBeInTheDocument();
    expect(u.getByText('M')).toBeInTheDocument();
    expect(u.getByText('Sair')).toBeInTheDocument();
  });

  it('lojas diferentes mostram nomes e iniciais diferentes', () => {
    const a = renderPagina(Pagina, 'Casa Verde');

    expect(a.getByText('Casa Verde')).toBeInTheDocument();
    expect(a.getByText('C')).toBeInTheDocument();
    expect(a.queryByText('Veste Bem')).not.toBeInTheDocument();
    a.unmount();
    const b = renderPagina(Pagina, 'Ótica Sol');
    expect(b.getByText('Ótica Sol')).toBeInTheDocument();
    expect(b.getByText('Ó')).toBeInTheDocument();
    expect(listarFila).toBeDefined();
  });
});
