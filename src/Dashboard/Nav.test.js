import React from 'react';
import { MemoryRouter } from 'react-router-dom';
import { render, fireEvent } from '@testing-library/react';

import Nav from './Nav';

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

  it('"Sair" só navega, sem apagar dados armazenados', () => {
    const clear = jest.spyOn(Storage.prototype, 'clear');
    const removeItem = jest.spyOn(Storage.prototype, 'removeItem');
    const { getByText } = renderNav();

    fireEvent.click(getByText('Sair'));

    expect(clear).not.toHaveBeenCalled();
    expect(removeItem).not.toHaveBeenCalled();
    clear.mockRestore();
    removeItem.mockRestore();
  });
});
