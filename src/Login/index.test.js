import React from 'react';
import { MemoryRouter, Route } from 'react-router-dom';
import { render, fireEvent } from '@testing-library/react';

import Login from '.';

function renderLogin() {
  const location = {};
  const utils = render(
    <MemoryRouter initialEntries={['/login']}>
      <Login />
      <Route
        render={(props) => {
          Object.assign(location, props.location);
          return null;
        }}
      />
    </MemoryRouter>
  );
  return { ...utils, location };
}

describe('Login do estabelecimento', () => {
  const originalFetch = window.fetch;
  let fetchSpy;

  beforeEach(() => {
    fetchSpy = jest.fn();
    window.fetch = fetchSpy;
  });

  afterEach(() => {
    window.fetch = originalFetch;
  });

  it('exibe e-mail, senha mascarada e o botão "Entrar"', () => {
    const { getByLabelText, getByText } = renderLogin();

    expect(getByLabelText('E-mail')).toBeInTheDocument();
    expect(getByLabelText('Senha')).toHaveAttribute('type', 'password');
    expect(getByText('Entrar')).toBeInTheDocument();
  });

  it('leva ao cadastro pelo botão "Cadastre-se aqui"', () => {
    const { getByText } = renderLogin();

    expect(getByText('Ainda não tem cadastro?')).toBeInTheDocument();
    expect(getByText('Cadastre-se aqui').closest('a')).toHaveAttribute(
      'href',
      '/cadastro'
    );
  });

  it('não envia credenciais nem navega ao acionar "Entrar"', () => {
    const { getByLabelText, getByText, location } = renderLogin();

    fireEvent.change(getByLabelText('E-mail'), {
      target: { value: 'loja@exemplo.com' },
    });
    fireEvent.click(getByText('Entrar'));

    expect(fetchSpy).not.toHaveBeenCalled();
    expect(location.pathname).toBe('/login');
  });
});
