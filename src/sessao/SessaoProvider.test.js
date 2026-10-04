import React from 'react';
import { MemoryRouter, Route, Switch, useLocation } from 'react-router-dom';
import { render, fireEvent, act } from '@testing-library/react';

import {
  SessaoProvider,
  RotaProtegida,
  RotaAnonima,
  useSessao,
} from './SessaoProvider';
import { lerToken, guardarToken, apagarToken } from './armazenamento';
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

const CONTA = { email: 'a@exemplo.com', cnpj: '93339970000105' };
const LOJA = { nome: 'Moda Azul', slug: 'moda-azul', endereco_publico: null };
const SESSAO = { token: 'tok-novo', conta: CONTA, loja: LOJA };

function Painel() {
  const { estado, conta, loja, aviso, falhaDeRede, ...acoes } = useSessao();
  const local = useLocation();
  return (
    <div>
      <p>estado: {estado}</p>
      <p>rota: {local.pathname}</p>
      <p>conta: {conta ? conta.email : '-'}</p>
      <p>loja: {loja ? loja.nome : '-'}</p>
      <p>aviso: {aviso || '-'}</p>
      <p>rede: {String(falhaDeRede)}</p>
      <p>confirmado: {conta ? String(conta.email_confirmado) : '-'}</p>
      <button
        type="button"
        onClick={() =>
          acoes.entrar('a@exemplo.com', 'senha-1234').catch(() => {})
        }
      >
        entrar
      </button>
      <button
        type="button"
        onClick={() => acoes.cadastrar({ nome: 'x' }).catch(() => {})}
      >
        cadastrar
      </button>
      <button type="button" onClick={acoes.sair}>
        sair
      </button>
      <button type="button" onClick={acoes.expirar}>
        expirar
      </button>
      <button
        type="button"
        onClick={() => acoes.atualizarConta().catch(() => {})}
      >
        atualizar conta
      </button>
      <button
        type="button"
        onClick={() => acoes.definirConta({ ...CONTA, email_confirmado: true })}
      >
        definir conta
      </button>
    </div>
  );
}

function renderApp(caminho = '/dashboard') {
  return render(
    <MemoryRouter initialEntries={[caminho]}>
      <SessaoProvider>
        <Switch>
          <Route path="/login">
            <RotaAnonima>
              <p>página de login</p>
              <Painel />
            </RotaAnonima>
          </Route>
          <Route path="/dashboard/qrcode">
            <RotaProtegida>
              <p>página do QR</p>
              <Painel />
            </RotaProtegida>
          </Route>
          <Route path="/dashboard">
            <RotaProtegida>
              <p>página do dashboard</p>
              <Painel />
            </RotaProtegida>
          </Route>
          <Route path="/">
            <p>página inicial</p>
            <Painel />
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

beforeEach(() => {
  Object.values(api).forEach((f) => f.mockReset && f.mockReset());
  apagarToken();
  window.sessionStorage.clear();
  window.localStorage.clear();
});

afterEach(() => {
  jest.restoreAllMocks();
});

describe('Restaurar a sessão ao abrir a página', () => {
  it('sem token: vai para /login guardando a página pedida e não chama a API', async () => {
    const u = renderApp('/dashboard/qrcode');
    await esvaziar();

    expect(u.getByText('página de login')).toBeInTheDocument();
    expect(api.obterConta).not.toHaveBeenCalled();
  });

  it('com token válido: confirma na API e mostra a página', async () => {
    guardarToken('tok');
    api.obterConta.mockResolvedValue({ conta: CONTA, loja: LOJA });
    const u = renderApp('/dashboard');

    expect(u.getByText('Carregando...')).toBeInTheDocument();
    expect(u.queryByText('página de login')).not.toBeInTheDocument();
    await esvaziar();

    expect(api.obterConta).toHaveBeenCalledWith('tok');
    expect(u.getByText('página do dashboard')).toBeInTheDocument();
    expect(u.getByText('conta: a@exemplo.com')).toBeInTheDocument();
    expect(u.getByText('loja: Moda Azul')).toBeInTheDocument();
  });

  it('token recusado com 401: apaga o token e vai para /login', async () => {
    guardarToken('velho');
    api.obterConta.mockRejectedValue(new api.ApiError(401, 'x'));
    const u = renderApp('/dashboard');
    await esvaziar();

    expect(u.getByText('página de login')).toBeInTheDocument();
    expect(lerToken()).toBeNull();
  });

  it('API fora do ar na restauração: vai para /login, mantém o token e avisa a rede', async () => {
    guardarToken('tok');
    api.obterConta.mockRejectedValue(new api.ApiError(0, 'rede'));
    const u = renderApp('/dashboard');
    await esvaziar();

    expect(u.getByText('página de login')).toBeInTheDocument();
    expect(u.getByText('rede: true')).toBeInTheDocument();
    expect(lerToken()).toBe('tok');
  });
});

describe('Entrar, cadastrar, sair e expirar', () => {
  it('entrar: guarda o token só em sessionStorage e passa a logado', async () => {
    api.entrar.mockResolvedValue(SESSAO);
    const u = renderApp('/login');
    await esvaziar();

    fireEvent.click(u.getByText('entrar'));
    await esvaziar();

    expect(api.entrar).toHaveBeenCalledWith('a@exemplo.com', 'senha-1234');
    expect(lerToken()).toBe('tok-novo');
    expect(window.sessionStorage.getItem('zerafilas:token')).toBe('tok-novo');
    expect(window.localStorage.length).toBe(0);
    // quem entra em /login logado é levado ao dashboard
    expect(u.getByText('página do dashboard')).toBeInTheDocument();
  });

  it('entrar com erro: continua anônimo e não guarda nada', async () => {
    api.entrar.mockRejectedValue(
      new api.ApiError(401, 'E-mail ou senha incorretos.')
    );
    const u = renderApp('/login');
    await esvaziar();

    fireEvent.click(u.getByText('entrar'));
    await esvaziar();

    expect(u.getByText('estado: anonimo')).toBeInTheDocument();
    expect(lerToken()).toBeNull();
  });

  it('cadastrar: já deixa a pessoa logada', async () => {
    api.cadastrar.mockResolvedValue(SESSAO);
    const u = renderApp('/login');
    await esvaziar();

    fireEvent.click(u.getByText('cadastrar'));
    await esvaziar();

    expect(api.cadastrar).toHaveBeenCalledWith({ nome: 'x' });
    expect(lerToken()).toBe('tok-novo');
    expect(u.getByText('página do dashboard')).toBeInTheDocument();
  });

  it('depois de entrar, volta para a página que a pessoa tentava abrir', async () => {
    api.entrar.mockResolvedValue(SESSAO);
    const u = renderApp('/dashboard/qrcode');
    await esvaziar();
    expect(u.getByText('página de login')).toBeInTheDocument();

    fireEvent.click(u.getByText('entrar'));
    await esvaziar();

    expect(u.getByText('página do QR')).toBeInTheDocument();
  });

  it('sair: encerra na API, apaga o token e vai para a página inicial', async () => {
    guardarToken('tok');
    api.obterConta.mockResolvedValue({ conta: CONTA, loja: LOJA });
    api.sair.mockResolvedValue({});
    const u = renderApp('/dashboard');
    await esvaziar();

    fireEvent.click(u.getByText('sair'));
    await esvaziar();

    expect(api.sair).toHaveBeenCalledWith('tok');
    expect(lerToken()).toBeNull();
    expect(u.getByText('página inicial')).toBeInTheDocument();
  });

  it('sair com a API fora do ar: apaga o token e navega do mesmo jeito', async () => {
    guardarToken('tok');
    api.obterConta.mockResolvedValue({ conta: CONTA, loja: LOJA });
    api.sair.mockRejectedValue(new api.ApiError(0, 'rede'));
    const u = renderApp('/dashboard');
    await esvaziar();

    fireEvent.click(u.getByText('sair'));
    await esvaziar();

    expect(lerToken()).toBeNull();
    expect(u.getByText('página inicial')).toBeInTheDocument();
  });

  it('expirar: apaga o token, leva ao login e deixa o aviso', async () => {
    guardarToken('tok');
    api.obterConta.mockResolvedValue({ conta: CONTA, loja: LOJA });
    const u = renderApp('/dashboard');
    await esvaziar();

    fireEvent.click(u.getByText('expirar'));
    await esvaziar();

    expect(lerToken()).toBeNull();
    expect(u.getByText('página de login')).toBeInTheDocument();
    expect(
      u.getByText('aviso: Sua sessão expirou. Entre de novo.')
    ).toBeInTheDocument();
  });

  it('armazenamento bloqueado: entra e funciona com o token em memória', async () => {
    const erro = () => {
      throw new Error('bloqueado');
    };
    jest.spyOn(Storage.prototype, 'setItem').mockImplementation(erro);
    jest.spyOn(Storage.prototype, 'getItem').mockImplementation(erro);
    jest.spyOn(Storage.prototype, 'removeItem').mockImplementation(erro);
    api.entrar.mockResolvedValue(SESSAO);
    const u = renderApp('/login');
    await esvaziar();

    fireEvent.click(u.getByText('entrar'));
    await esvaziar();

    expect(u.getByText('página do dashboard')).toBeInTheDocument();
    expect(lerToken()).toBe('tok-novo');
  });
});

describe('Estado de confirmação do e-mail', () => {
  const NAO_CONFIRMADA = { ...CONTA, email_confirmado: false };

  async function logada() {
    guardarToken('tok');
    api.obterConta.mockResolvedValue({ conta: NAO_CONFIRMADA, loja: LOJA });
    const u = renderApp('/dashboard');
    await esvaziar();
    return u;
  }

  it('a conta da restauração traz email_confirmado', async () => {
    const u = await logada();

    expect(u.getByText('confirmado: false')).toBeInTheDocument();
  });

  it('atualizarConta reconsulta a API com o token e troca a conta da sessão', async () => {
    const u = await logada();
    api.obterConta.mockResolvedValue({
      conta: { ...CONTA, email_confirmado: true },
      loja: LOJA,
    });

    fireEvent.click(u.getByText('atualizar conta'));
    await esvaziar();

    expect(api.obterConta).toHaveBeenLastCalledWith('tok');
    expect(u.getByText('confirmado: true')).toBeInTheDocument();
    expect(u.getByText('estado: logado')).toBeInTheDocument();
  });

  it('atualizarConta com 401 expira a sessão', async () => {
    const u = await logada();
    api.obterConta.mockRejectedValue(new api.ApiError(401, 'x'));

    fireEvent.click(u.getByText('atualizar conta'));
    await esvaziar();

    expect(u.getByText('página de login')).toBeInTheDocument();
    expect(lerToken()).toBeNull();
  });

  it('atualizarConta com a API fora do ar mantém a sessão', async () => {
    const u = await logada();
    api.obterConta.mockRejectedValue(new api.ApiError(0, 'rede'));

    fireEvent.click(u.getByText('atualizar conta'));
    await esvaziar();

    expect(u.getByText('estado: logado')).toBeInTheDocument();
    expect(u.getByText('confirmado: false')).toBeInTheDocument();
    expect(lerToken()).toBe('tok');
  });

  it('definirConta troca só a conta, sem consultar a API', async () => {
    const u = await logada();
    api.obterConta.mockClear();

    fireEvent.click(u.getByText('definir conta'));
    await esvaziar();

    expect(u.getByText('confirmado: true')).toBeInTheDocument();
    expect(u.getByText('loja: Moda Azul')).toBeInTheDocument();
    expect(api.obterConta).not.toHaveBeenCalled();
  });
});
