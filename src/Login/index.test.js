import React from 'react';
import { MemoryRouter, Route, Switch, useLocation } from 'react-router-dom';
import { render, fireEvent, act } from '@testing-library/react';

import Login from '.';
import {
  SessaoProvider,
  RotaAnonima,
  RotaProtegida,
  useSessao,
} from '../sessao/SessaoProvider';
import { guardarToken, apagarToken, lerToken } from '../sessao/armazenamento';
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
    cadastrar: jest.fn(),
    sair: jest.fn(),
  };
});

const REDE = 'Não foi possível falar com o servidor. Tente de novo.';
const SESSAO = {
  token: 'tok',
  conta: { email: 'loja@exemplo.com', cnpj: '93339970000105' },
  loja: { nome: 'Moda Azul', slug: 'moda-azul', endereco_publico: null },
};

function Rota() {
  return <p>rota: {useLocation().pathname}</p>;
}

function Expirar() {
  const { expirar } = useSessao();
  return (
    <button type="button" onClick={expirar}>
      expirar
    </button>
  );
}

function renderLogin(entrada = '/login') {
  return render(
    <MemoryRouter initialEntries={[entrada]}>
      <SessaoProvider>
        <Switch>
          <Route path="/login">
            <RotaAnonima>
              <Login />
            </RotaAnonima>
          </Route>
          <Route path="/dashboard">
            <RotaProtegida>
              <Rota />
              <Expirar />
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

async function preencherEEnviar(u, email, senha) {
  fireEvent.change(u.getByLabelText('E-mail'), { target: { value: email } });
  fireEvent.change(u.getByLabelText('Senha'), { target: { value: senha } });
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

describe('Login do estabelecimento', () => {
  it('exibe e-mail, senha mascarada e o botão "Entrar"', async () => {
    const u = renderLogin();
    await esvaziar();

    expect(u.getByLabelText('E-mail')).toBeInTheDocument();
    expect(u.getByLabelText('Senha')).toHaveAttribute('type', 'password');
    expect(u.getByText('Entrar')).toBeInTheDocument();
  });

  it('leva ao cadastro pelo botão "Cadastre-se aqui"', async () => {
    const u = renderLogin();
    await esvaziar();

    expect(u.getByText('Ainda não tem cadastro?')).toBeInTheDocument();
    expect(u.getByText('Cadastre-se aqui').closest('a')).toHaveAttribute(
      'href',
      '/cadastro'
    );
  });

  it('login correto: envia e-mail e senha e navega para /dashboard', async () => {
    api.entrar.mockResolvedValue(SESSAO);
    const u = renderLogin();
    await esvaziar();

    await preencherEEnviar(u, 'loja@exemplo.com', 'senha-segura-1');

    expect(api.entrar).toHaveBeenCalledWith(
      'loja@exemplo.com',
      'senha-segura-1'
    );
    expect(u.getByText('rota: /dashboard')).toBeInTheDocument();
    expect(lerToken()).toBe('tok');
  });

  it('volta à página que o visitante tentava abrir', async () => {
    api.entrar.mockResolvedValue(SESSAO);
    const u = renderLogin({
      pathname: '/login',
      state: { from: { pathname: '/dashboard' } },
    });
    await esvaziar();

    await preencherEEnviar(u, 'loja@exemplo.com', 'senha-segura-1');

    expect(u.getByText('rota: /dashboard')).toBeInTheDocument();
  });

  it('senha errada: mostra a mensagem, mantém o e-mail e esvazia a senha', async () => {
    api.entrar.mockRejectedValue(
      new api.ApiError(401, 'E-mail ou senha incorretos.')
    );
    const u = renderLogin();
    await esvaziar();

    await preencherEEnviar(u, 'loja@exemplo.com', 'errada-errada');

    expect(u.getByText('E-mail ou senha incorretos.')).toBeInTheDocument();
    expect(u.getByLabelText('E-mail').value).toBe('loja@exemplo.com');
    expect(u.getByLabelText('Senha').value).toBe('');
    expect(u.getByText('Cadastre-se aqui')).toBeInTheDocument();
    expect(lerToken()).toBeNull();
  });

  it('o 401 do login não é tratado como sessão expirada', async () => {
    api.entrar.mockRejectedValue(
      new api.ApiError(401, 'E-mail ou senha incorretos.')
    );
    const u = renderLogin();
    await esvaziar();

    await preencherEEnviar(u, 'loja@exemplo.com', 'errada-errada');

    expect(u.queryByText(/sessão expirou/)).not.toBeInTheDocument();
  });

  it('429 (limite de tentativas): mostra a mensagem e continua no login', async () => {
    api.entrar.mockRejectedValue(
      new api.ApiError(429, 'Muitas tentativas. Tente de novo em 15 minutos.')
    );
    const u = renderLogin();
    await esvaziar();

    await preencherEEnviar(u, 'loja@exemplo.com', 'senha-segura-1');

    expect(
      u.getByText('Muitas tentativas. Tente de novo em 15 minutos.')
    ).toBeInTheDocument();
    expect(u.queryByText(REDE)).not.toBeInTheDocument();
    expect(u.getByLabelText('E-mail').value).toBe('loja@exemplo.com');
    expect(u.queryByText(/rota:/)).not.toBeInTheDocument();
  });

  it('campos em branco: mensagem e nenhuma requisição', async () => {
    const u = renderLogin();
    await esvaziar();

    await preencherEEnviar(u, 'loja@exemplo.com', '');
    expect(u.getByText('Informe o e-mail e a senha.')).toBeInTheDocument();

    await preencherEEnviar(u, '  ', 'senha-segura-1');
    expect(u.getByText('Informe o e-mail e a senha.')).toBeInTheDocument();

    expect(api.entrar).not.toHaveBeenCalled();
  });

  it('API fora do ar: mensagem fixa e campos mantidos', async () => {
    api.entrar.mockRejectedValue(new api.ApiError(0, 'rede'));
    const u = renderLogin();
    await esvaziar();

    await preencherEEnviar(u, 'loja@exemplo.com', 'senha-segura-1');

    expect(u.getByText(REDE)).toBeInTheDocument();
    expect(u.getByLabelText('E-mail').value).toBe('loja@exemplo.com');
  });

  it('sessão vencida durante o uso: leva ao login com o aviso', async () => {
    guardarToken('tok');
    api.obterConta.mockResolvedValue({
      conta: SESSAO.conta,
      loja: SESSAO.loja,
    });
    const u = renderLogin('/dashboard');
    await esvaziar();
    expect(u.getByText('rota: /dashboard')).toBeInTheDocument();

    fireEvent.click(u.getByText('expirar'));
    await esvaziar();

    expect(
      u.getByText('Sua sessão expirou. Entre de novo.')
    ).toBeInTheDocument();
    expect(u.getByLabelText('E-mail')).toBeInTheDocument();
    expect(lerToken()).toBeNull();
  });

  it('API fora do ar ao restaurar a sessão: avisa no login', async () => {
    guardarToken('tok');
    api.obterConta.mockRejectedValue(new api.ApiError(0, 'rede'));
    const u = renderLogin('/dashboard');
    await esvaziar();

    expect(u.getByLabelText('E-mail')).toBeInTheDocument();
    expect(u.getByText(REDE)).toBeInTheDocument();
  });
});
