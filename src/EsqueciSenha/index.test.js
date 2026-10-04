import React from 'react';
import { MemoryRouter, Route, Switch, useLocation } from 'react-router-dom';
import { render, fireEvent, act } from '@testing-library/react';

import EsqueciSenha from '.';
import {
  SessaoProvider,
  RotaAnonima,
  RotaProtegida,
} from '../sessao/SessaoProvider';
import { guardarToken, apagarToken } from '../sessao/armazenamento';
import * as api from '../api';

jest.mock('../api', () => {
  class ApiErrorMock extends Error {
    constructor(status, message) {
      super(message);
      this.status = status;
    }
  }
  return {
    ApiError: ApiErrorMock,
    obterConta: jest.fn(),
    entrar: jest.fn(),
    sair: jest.fn(),
    pedirRedefinicao: jest.fn(),
  };
});

const REDE = 'Não foi possível falar com o servidor. Tente de novo.';
const PADRAO =
  'Se o e-mail estiver cadastrado, enviamos um link para redefinir a senha.';

function Rota() {
  return <p>rota: {useLocation().pathname}</p>;
}

function renderTela() {
  return render(
    <MemoryRouter initialEntries={['/esqueci-senha']}>
      <SessaoProvider>
        <Switch>
          <Route path="/esqueci-senha">
            <RotaAnonima>
              <EsqueciSenha />
            </RotaAnonima>
          </Route>
          <Route path="/login">
            <Rota />
          </Route>
          <Route path="/dashboard">
            <RotaProtegida>
              <Rota />
            </RotaProtegida>
          </Route>
        </Switch>
      </SessaoProvider>
    </MemoryRouter>
  );
}

const esvaziar = async () => {
  for (let i = 0; i < 4; i += 1) {
    // eslint-disable-next-line no-await-in-loop
    await act(async () => {});
  }
};

async function enviar(u, email) {
  fireEvent.change(u.getByLabelText('E-mail'), { target: { value: email } });
  await act(async () => {
    fireEvent.submit(u.getByLabelText('E-mail').closest('form'));
  });
  await esvaziar();
}

beforeEach(() => {
  Object.values(api).forEach((f) => f.mockReset && f.mockReset());
  apagarToken();
  window.sessionStorage.clear();
});

describe('Esqueci a senha', () => {
  it('pede o e-mail e oferece voltar ao login', async () => {
    const u = renderTela();
    await esvaziar();

    expect(u.getByLabelText('E-mail')).toBeInTheDocument();
    expect(u.getByText('Enviar link')).toBeInTheDocument();
    expect(u.getByText('Voltar ao login').closest('a')).toHaveAttribute(
      'href',
      '/login'
    );
  });

  it('envia o e-mail e mostra a mensagem da API, sem dizer se há conta', async () => {
    api.pedirRedefinicao.mockResolvedValue({ mensagem: PADRAO });
    const u = renderTela();
    await esvaziar();

    await enviar(u, '  dona@exemplo.com ');

    expect(api.pedirRedefinicao).toHaveBeenCalledWith('dona@exemplo.com');
    expect(u.getByText(PADRAO)).toBeInTheDocument();
    expect(u.queryByText(/não encontramos|não existe|sem conta/i)).toBeNull();
  });

  it('e-mail em branco: mensagem e nenhuma requisição', async () => {
    const u = renderTela();
    await esvaziar();

    await enviar(u, '   ');

    expect(u.getByText('Informe o e-mail.')).toBeInTheDocument();
    expect(api.pedirRedefinicao).not.toHaveBeenCalled();
  });

  it('422: mostra a mensagem da API', async () => {
    api.pedirRedefinicao.mockRejectedValue(
      new api.ApiError(422, 'E-mail inválido.')
    );
    const u = renderTela();
    await esvaziar();

    await enviar(u, 'sem-arroba');

    expect(u.getByText('E-mail inválido.')).toBeInTheDocument();
  });

  it('429 (limite de tentativas): mostra a mensagem e continua na tela', async () => {
    api.pedirRedefinicao.mockRejectedValue(
      new api.ApiError(429, 'Muitas tentativas. Tente de novo em 60 minutos.')
    );
    const u = renderTela();
    await esvaziar();

    await enviar(u, 'dona@exemplo.com');

    expect(
      u.getByText('Muitas tentativas. Tente de novo em 60 minutos.')
    ).toBeInTheDocument();
    expect(u.queryByText(REDE)).toBeNull();
    expect(u.getByLabelText('E-mail').value).toBe('dona@exemplo.com');
  });

  it('API fora do ar: mensagem fixa e e-mail mantido', async () => {
    api.pedirRedefinicao.mockRejectedValue(new api.ApiError(0, 'rede'));
    const u = renderTela();
    await esvaziar();

    await enviar(u, 'dona@exemplo.com');

    expect(u.getByText(REDE)).toBeInTheDocument();
    expect(u.getByLabelText('E-mail').value).toBe('dona@exemplo.com');
  });

  it('quem já está logada vai para o dashboard', async () => {
    guardarToken('tok');
    api.obterConta.mockResolvedValue({
      conta: { email: 'a@exemplo.com', cnpj: '93339970000105' },
      loja: { nome: 'Moda Azul', slug: 'moda-azul', endereco_publico: null },
    });
    const u = renderTela();
    await esvaziar();

    expect(u.getByText('rota: /dashboard')).toBeInTheDocument();
  });
});
