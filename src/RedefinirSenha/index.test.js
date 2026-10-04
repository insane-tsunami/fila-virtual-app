import React from 'react';
import { MemoryRouter, Route, Switch, useLocation } from 'react-router-dom';
import { render, fireEvent, act } from '@testing-library/react';

import RedefinirSenha from '.';
import { SessaoProvider, RotaAnonima } from '../sessao/SessaoProvider';
import { apagarToken } from '../sessao/armazenamento';
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
    redefinirSenha: jest.fn(),
  };
});

const REDE = 'Não foi possível falar com o servidor. Tente de novo.';
const INVALIDO = 'Link inválido ou expirado. Peça um novo.';
const TOKEN = 'a'.repeat(64);

function Login() {
  const { pathname, state } = useLocation();
  return (
    <p>
      rota: {pathname} aviso: {state && state.aviso}
    </p>
  );
}

function renderTela(entrada = `/redefinir-senha#token=${TOKEN}`) {
  const atual = { location: null };
  const u = render(
    <MemoryRouter initialEntries={[entrada]}>
      <SessaoProvider>
        <Switch>
          <Route path="/redefinir-senha">
            <RotaAnonima>
              <RedefinirSenha />
            </RotaAnonima>
          </Route>
          <Route path="/esqueci-senha">
            <p>pedir novo link</p>
          </Route>
          <Route path="/login">
            <Login />
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

function preencher(u, nova, confirmacao = nova) {
  fireEvent.change(u.getByLabelText('Nova Senha'), { target: { value: nova } });
  fireEvent.change(u.getByLabelText('Confirmar Nova Senha'), {
    target: { value: confirmacao },
  });
}

async function enviar(u) {
  await act(async () => {
    fireEvent.submit(u.getByLabelText('Nova Senha').closest('form'));
  });
  await esvaziar();
}

beforeEach(() => {
  Object.values(api).forEach((f) => f.mockReset && f.mockReset());
  apagarToken();
  window.sessionStorage.clear();
});

describe('Redefinir a senha', () => {
  it('lê o token do fragmento e tira o fragmento do endereço', async () => {
    const u = renderTela();
    await esvaziar();

    expect(u.getByLabelText('Nova Senha')).toHaveAttribute('type', 'password');
    expect(u.getByLabelText('Confirmar Nova Senha')).toHaveAttribute(
      'type',
      'password'
    );
    expect(u.atual.location.hash).toBe('');
    expect(u.atual.location.pathname).toBe('/redefinir-senha');
  });

  it('envia o token lido e a nova senha, e vai ao login com o aviso', async () => {
    api.redefinirSenha.mockResolvedValue({ mensagem: 'Senha alterada.' });
    const u = renderTela();
    await esvaziar();

    preencher(u, 'senha-nova-22');
    await enviar(u);

    expect(api.redefinirSenha).toHaveBeenCalledWith(TOKEN, 'senha-nova-22');
    expect(
      u.getByText(
        /rota: \/login aviso: Senha alterada\. Entre com a nova senha\./
      )
    ).toBeInTheDocument();
  });

  it('um link novo aberto na mesma página (só o fragmento muda) troca o token', async () => {
    api.redefinirSenha.mockResolvedValue({ mensagem: 'Senha alterada.' });
    const u = renderTela();
    await esvaziar();

    act(() => {
      u.irPara(`/redefinir-senha#token=${'f'.repeat(64)}`);
    });
    await esvaziar();
    preencher(u, 'senha-nova-22');
    await enviar(u);

    expect(api.redefinirSenha).toHaveBeenCalledWith(
      'f'.repeat(64),
      'senha-nova-22'
    );
  });

  it('link sem token: avisa e oferece pedir um novo, sem formulário', async () => {
    const u = renderTela('/redefinir-senha');
    await esvaziar();

    expect(u.getByText(INVALIDO)).toBeInTheDocument();
    expect(u.queryByLabelText('Nova Senha')).toBeNull();
    expect(u.getByText('Pedir um novo link').closest('a')).toHaveAttribute(
      'href',
      '/esqueci-senha'
    );
  });

  it('fragmento sem o campo token também é link inválido', async () => {
    const u = renderTela('/redefinir-senha#outra=1');
    await esvaziar();

    expect(u.getByText(INVALIDO)).toBeInTheDocument();
  });

  it('senhas diferentes: mensagem e nenhuma requisição', async () => {
    const u = renderTela();
    await esvaziar();

    preencher(u, 'senha-nova-22', 'outra-coisa-99');
    await enviar(u);

    expect(u.getByText('As senhas não são iguais.')).toBeInTheDocument();
    expect(api.redefinirSenha).not.toHaveBeenCalled();
  });

  it('link vencido (422): mostra a mensagem e oferece pedir um novo', async () => {
    api.redefinirSenha.mockRejectedValue(new api.ApiError(422, INVALIDO));
    const u = renderTela();
    await esvaziar();

    preencher(u, 'senha-nova-22');
    await enviar(u);

    expect(u.getByText(INVALIDO)).toBeInTheDocument();
    expect(u.getByText('Pedir um novo link')).toBeInTheDocument();
  });

  it('senha inválida (422): mostra a mensagem da API e esvazia as senhas', async () => {
    api.redefinirSenha.mockRejectedValue(
      new api.ApiError(422, 'Senha inválida: use de 8 a 72 caracteres.')
    );
    const u = renderTela();
    await esvaziar();

    preencher(u, 'curta');
    await enviar(u);

    expect(
      u.getByText('Senha inválida: use de 8 a 72 caracteres.')
    ).toBeInTheDocument();
    expect(u.queryByText('Pedir um novo link')).toBeNull();
    expect(u.getByLabelText('Nova Senha').value).toBe('');
  });

  it('429 (limite de tentativas): mostra a mensagem e continua na tela', async () => {
    api.redefinirSenha.mockRejectedValue(
      new api.ApiError(429, 'Muitas tentativas. Tente de novo em 15 minutos.')
    );
    const u = renderTela();
    await esvaziar();

    preencher(u, 'senha-nova-22');
    await enviar(u);

    expect(
      u.getByText('Muitas tentativas. Tente de novo em 15 minutos.')
    ).toBeInTheDocument();
    expect(u.queryByText(REDE)).toBeNull();
    expect(u.getByLabelText('Nova Senha')).toBeInTheDocument();
  });

  it('API fora do ar: mensagem fixa e senhas mantidas', async () => {
    api.redefinirSenha.mockRejectedValue(new api.ApiError(0, 'rede'));
    const u = renderTela();
    await esvaziar();

    preencher(u, 'senha-nova-22');
    await enviar(u);

    expect(u.getByText(REDE)).toBeInTheDocument();
    expect(u.getByLabelText('Nova Senha').value).toBe('senha-nova-22');
  });
});
