import React from 'react';
import { MemoryRouter, Route, Switch } from 'react-router-dom';
import { render, act } from '@testing-library/react';

import ConfirmarEmail from '.';
import { SessaoProvider } from '../sessao/SessaoProvider';
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
    confirmarEmail: jest.fn(),
    sair: jest.fn(),
  };
});

const REDE = 'Não foi possível falar com o servidor. Tente de novo.';
const INVALIDO =
  'Link inválido ou expirado. Peça um novo e-mail de confirmação.';
const TOKEN = 'b'.repeat(64);

function Dashboard() {
  return <p>dashboard</p>;
}

function renderTela(entrada = `/confirmar-email#token=${TOKEN}`) {
  const atual = { location: null };
  const u = render(
    <MemoryRouter initialEntries={[entrada]}>
      <SessaoProvider>
        <Switch>
          <Route path="/confirmar-email">
            <ConfirmarEmail />
          </Route>
          <Route path="/dashboard">
            <Dashboard />
          </Route>
          <Route path="/login">
            <p>login</p>
          </Route>
        </Switch>
      </SessaoProvider>
      <Route
        render={({ location, history }) => {
          atual.location = location;
          atual.irPara = (destino) => history.push(destino);
          return null;
        }}
      />
    </MemoryRouter>
  );
  return { ...u, atual, irPara: (destino) => atual.irPara(destino) };
}

const esvaziar = async () => {
  for (let i = 0; i < 4; i += 1) {
    // eslint-disable-next-line no-await-in-loop
    await act(async () => {});
  }
};

beforeEach(() => {
  Object.values(api).forEach((f) => f.mockReset && f.mockReset());
  apagarToken();
  window.sessionStorage.clear();
});

describe('Página de confirmação do e-mail', () => {
  it('lê o token do fragmento, confirma uma vez e tira o fragmento do endereço', async () => {
    api.confirmarEmail.mockResolvedValue({ mensagem: 'E-mail confirmado.' });
    const u = renderTela();
    await esvaziar();

    expect(api.confirmarEmail).toHaveBeenCalledTimes(1);
    expect(api.confirmarEmail).toHaveBeenCalledWith(TOKEN);
    expect(u.getByText('E-mail confirmado.')).toBeInTheDocument();
    expect(u.getByText('Entrar').closest('a')).toHaveAttribute(
      'href',
      '/login'
    );
    expect(u.atual.location.hash).toBe('');
    expect(u.atual.location.pathname).toBe('/confirmar-email');
  });

  it('um link novo aberto na mesma página (só o fragmento muda) confirma de novo com o token novo', async () => {
    api.confirmarEmail
      .mockRejectedValueOnce(new api.ApiError(422, INVALIDO))
      .mockResolvedValueOnce({ mensagem: 'E-mail confirmado.' });
    const u = renderTela();
    await esvaziar();
    expect(u.getByText(INVALIDO)).toBeInTheDocument();

    act(() => {
      u.irPara(`/confirmar-email#token=${'f'.repeat(64)}`);
    });
    await esvaziar();

    expect(api.confirmarEmail).toHaveBeenCalledTimes(2);
    expect(api.confirmarEmail).toHaveBeenLastCalledWith('f'.repeat(64));
    expect(u.getByText('E-mail confirmado.')).toBeInTheDocument();
    expect(u.atual.location.hash).toBe('');
  });

  it('enquanto a API responde, mostra que está confirmando', async () => {
    api.confirmarEmail.mockReturnValue(new Promise(() => {}));
    const u = renderTela();
    await esvaziar();

    expect(u.getByText('Confirmando seu e-mail...')).toBeInTheDocument();
  });

  it('aberta já logada: funciona e NÃO redireciona para o dashboard', async () => {
    guardarToken('tok');
    api.obterConta.mockResolvedValue({
      conta: { email: 'a@exemplo.com', cnpj: '93339970000105' },
      loja: { nome: 'Moda Azul', slug: 'moda-azul', endereco_publico: null },
    });
    api.confirmarEmail.mockResolvedValue({ mensagem: 'E-mail confirmado.' });
    const u = renderTela();
    await esvaziar();

    expect(u.getByText('E-mail confirmado.')).toBeInTheDocument();
    expect(u.queryByText('dashboard')).toBeNull();
    expect(u.atual.location.pathname).toBe('/confirmar-email');
  });

  it('link vencido (422): mostra a mensagem e orienta a pedir um novo pelo dashboard', async () => {
    api.confirmarEmail.mockRejectedValue(new api.ApiError(422, INVALIDO));
    const u = renderTela();
    await esvaziar();

    expect(u.getByText(INVALIDO)).toBeInTheDocument();
    expect(
      u.getByText(/peça um novo link na faixa "Confirme seu e-mail"/)
    ).toBeInTheDocument();
  });

  it('sem token: avisa que o link é inválido e não faz requisição', async () => {
    const u = renderTela('/confirmar-email');
    await esvaziar();

    expect(
      u.getByText('Link inválido. Peça um novo e-mail de confirmação.')
    ).toBeInTheDocument();
    expect(api.confirmarEmail).not.toHaveBeenCalled();
  });

  it('fragmento sem o campo token também é link inválido', async () => {
    const u = renderTela('/confirmar-email#outra=1');
    await esvaziar();

    expect(
      u.getByText('Link inválido. Peça um novo e-mail de confirmação.')
    ).toBeInTheDocument();
    expect(api.confirmarEmail).not.toHaveBeenCalled();
  });

  it('429 (limite de tentativas): mostra a mensagem recebida', async () => {
    api.confirmarEmail.mockRejectedValue(
      new api.ApiError(429, 'Muitas tentativas. Tente de novo em 15 minutos.')
    );
    const u = renderTela();
    await esvaziar();

    expect(
      u.getByText('Muitas tentativas. Tente de novo em 15 minutos.')
    ).toBeInTheDocument();
    expect(u.queryByText(REDE)).toBeNull();
  });

  it('API fora do ar: mensagem fixa', async () => {
    api.confirmarEmail.mockRejectedValue(new api.ApiError(0, 'rede'));
    const u = renderTela();
    await esvaziar();

    expect(u.getByText(REDE)).toBeInTheDocument();
  });
});
