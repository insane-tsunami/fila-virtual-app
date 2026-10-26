import React from 'react';
import { MemoryRouter } from 'react-router-dom';
import { render, within } from '@testing-library/react';

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

// Só a barra lateral: o Perfil também mostra o avatar na pré-visualização.
function renderPagina(Pagina, nome, avatar = null) {
  const u = render(
    <MemoryRouter>
      <SessaoDeTeste
        valor={{
          loja: { nome, slug: 'qualquer', endereco_publico: null, avatar },
          conta: { email: 'a@exemplo.com', cnpj: '93339970000105' },
        }}
      >
        <Pagina />
      </SessaoDeTeste>
    </MemoryRouter>
  );
  return {
    ...within(u.getByRole('complementary', { name: 'Barra lateral' })),
    unmount: u.unmount,
  };
}

describe.each(paginas)('Barra lateral: %s', (_, Pagina) => {
  it('mostra o nome e a inicial da loja da conta logada, sem consultar a API', () => {
    const u = renderPagina(Pagina, 'moda azul');

    expect(u.getByText('moda azul')).toBeInTheDocument();
    expect(u.getByText('M')).toBeInTheDocument();
    expect(u.getByText('Sair')).toBeInTheDocument();
  });

  it('com avatar mostra o ícone sobre a cor no lugar da inicial', () => {
    const u = renderPagina(Pagina, 'Moda Azul', { cor: 'azul', icone: 'cafe' });

    expect(u.getByText('Moda Azul')).toBeInTheDocument();
    expect(u.getByTitle('Avatar: Café sobre Azul')).toBeInTheDocument();
    expect(u.queryByText('M')).not.toBeInTheDocument();
  });

  it('com avatar desconhecido mostra a inicial, sem quebrar', () => {
    const u = renderPagina(Pagina, 'Moda Azul', { cor: 'rosa', icone: 'cafe' });

    expect(u.getByText('M')).toBeInTheDocument();
    expect(u.queryByTitle(/Avatar:/)).not.toBeInTheDocument();
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
