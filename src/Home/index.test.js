import React from 'react';
import { MemoryRouter } from 'react-router-dom';
import { render } from '@testing-library/react';

import Home from '.';

function renderHome() {
  return render(
    <MemoryRouter>
      <Home />
    </MemoryRouter>
  );
}

describe('Landing page', () => {
  it('apresenta a marca, o slogan e a plataforma', () => {
    const { getByAltText, getByText } = renderHome();

    expect(getByAltText('ZeraFilas')).toBeInTheDocument();
    expect(getByText('Atendimento seguro e sem fila!')).toBeInTheDocument();
    expect(
      getByText(/Plataforma de gerenciamento de fila virtual/)
    ).toBeInTheDocument();
  });

  it('leva ao cadastro pelo botão "Cadastre-se aqui"', () => {
    const { getByText } = renderHome();

    expect(getByText('Cadastre-se aqui').closest('a')).toHaveAttribute(
      'href',
      '/cadastro'
    );
  });

  it('leva ao login pelo link "Entrar"', () => {
    const { getByText } = renderHome();

    expect(getByText('Entrar').closest('a')).toHaveAttribute('href', '/login');
  });
});
