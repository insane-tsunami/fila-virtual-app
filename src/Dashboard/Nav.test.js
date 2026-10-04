import React from 'react';
import { MemoryRouter } from 'react-router-dom';
import { render, fireEvent } from '@testing-library/react';

import Nav from './Nav';
import { guardarChave, lerChave, apagarChave } from './chave';

function renderNav() {
  return render(
    <MemoryRouter>
      <Nav />
    </MemoryRouter>
  );
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

  it('"Sair" apaga a chave guardada e não mexe em outros dados', () => {
    guardarChave('segredo');
    window.localStorage.setItem('zerafilas:entrada:veste-bem', 'abc');
    const clear = jest.spyOn(Storage.prototype, 'clear');
    const { getByText } = renderNav();

    fireEvent.click(getByText('Sair'));

    expect(lerChave()).toBeNull();
    expect(window.sessionStorage.getItem('zerafilas:chave')).toBeNull();
    expect(clear).not.toHaveBeenCalled();
    expect(window.localStorage.getItem('zerafilas:entrada:veste-bem')).toBe(
      'abc'
    );
    clear.mockRestore();
    window.localStorage.clear();
  });

  it('os outros itens do menu não apagam a chave', () => {
    guardarChave('segredo');
    const { getByText } = renderNav();

    fireEvent.click(getByText('Dashboard'));
    fireEvent.click(getByText('Gerar QRCODE'));

    expect(lerChave()).toBe('segredo');
    apagarChave();
  });
});
