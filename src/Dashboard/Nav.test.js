import React from 'react';
import { MemoryRouter } from 'react-router-dom';
import { render, fireEvent } from '@testing-library/react';

import Nav from './Nav';
import SessaoDeTeste from '../sessao/SessaoDeTeste';

function renderNav(sair = jest.fn()) {
  return {
    sair,
    ...render(
      <MemoryRouter>
        <SessaoDeTeste valor={{ sair }}>
          <Nav />
        </SessaoDeTeste>
      </MemoryRouter>
    ),
  };
}

describe('Menu lateral do dashboard', () => {
  it.each([
    ['Dashboard', '/dashboard'],
    ['Gerar QRCODE', '/dashboard/qrcode'],
    ['Configurações', '/dashboard/perfil'],
    ['Sair', '/'],
  ])('o item "%s" leva a %s', (texto, destino) => {
    const { getByText } = renderNav();

    expect(getByText(texto).closest('a')).toHaveAttribute('href', destino);
  });

  it('"Sair" encerra a sessão uma vez', () => {
    const { getByText, sair } = renderNav();

    fireEvent.click(getByText('Sair'));

    expect(sair).toHaveBeenCalledTimes(1);
  });

  it('os outros itens do menu não encerram a sessão', () => {
    const { getByText, sair } = renderNav();

    fireEvent.click(getByText('Dashboard'));
    fireEvent.click(getByText('Gerar QRCODE'));
    fireEvent.click(getByText('Configurações'));

    expect(sair).not.toHaveBeenCalled();
  });
});
