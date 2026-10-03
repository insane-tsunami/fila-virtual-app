import React from 'react';
import { MemoryRouter, Route } from 'react-router-dom';
import { render, fireEvent } from '@testing-library/react';

import Register from '.';

function renderRegister() {
  const location = {};
  const utils = render(
    <MemoryRouter initialEntries={['/cadastro']}>
      <Register />
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

describe('Cadastro do estabelecimento', () => {
  const originalFetch = window.fetch;
  let fetchSpy;

  beforeEach(() => {
    fetchSpy = jest.fn();
    window.fetch = fetchSpy;
  });

  afterEach(() => {
    window.fetch = originalFetch;
  });

  it('exibe os quatro campos e o botão, com as senhas mascaradas', () => {
    const { getByLabelText, getByText } = renderRegister();

    expect(getByLabelText('E-mail')).toBeInTheDocument();
    expect(getByLabelText('CNPJ')).toBeInTheDocument();
    expect(getByLabelText('Escolha uma Senha')).toHaveAttribute(
      'type',
      'password'
    );
    expect(getByLabelText('Confirme a senha')).toHaveAttribute(
      'type',
      'password'
    );
    expect(getByText('Cadastrar')).toBeInTheDocument();
  });

  it('leva ao login pelo botão "Faça o Login aqui"', () => {
    const { getByText } = renderRegister();

    expect(getByText('Faça o Login aqui').closest('a')).toHaveAttribute(
      'href',
      '/login'
    );
  });

  it('não envia dados nem navega ao acionar "Cadastrar"', () => {
    const { getByLabelText, getByText, location } = renderRegister();

    fireEvent.change(getByLabelText('E-mail'), {
      target: { value: 'loja@exemplo.com' },
    });
    fireEvent.click(getByText('Cadastrar'));

    expect(fetchSpy).not.toHaveBeenCalled();
    expect(location.pathname).toBe('/cadastro');
  });
});
