import React from 'react';
import { render, act } from '@testing-library/react';

import App from './App';
import { guardarToken, apagarToken } from './sessao/armazenamento';
import * as api from './api';

jest.mock('./api', () => {
  class ApiErrorMock extends Error {
    constructor(status, message) {
      super(message);
      this.status = status;
    }
  }
  return {
    ApiError: ApiErrorMock,
    obterConta: jest.fn(),
    listarFila: jest.fn(),
    buscarLoja: jest.fn(),
    entrarNaFila: jest.fn(),
    consultarEntrada: jest.fn(),
    entrar: jest.fn(),
    cadastrar: jest.fn(),
    sair: jest.fn(),
    definirEndereco: jest.fn(),
    trocarSenha: jest.fn(),
  };
});

const DADOS = {
  conta: { email: 'contato@modaazul.com', cnpj: '93339970000105' },
  loja: { nome: 'Moda Azul', slug: 'moda-azul', endereco_publico: null },
};

async function abrir(caminho) {
  window.history.pushState({}, '', caminho);
  const utils = render(<App />);
  for (let i = 0; i < 4; i += 1) {
    // eslint-disable-next-line no-await-in-loop
    await act(async () => {});
  }
  return utils;
}

beforeEach(() => {
  Object.values(api).forEach((f) => f.mockReset && f.mockReset());
  api.listarFila.mockResolvedValue([]);
  api.buscarLoja.mockResolvedValue(DADOS.loja);
  apagarToken();
  window.sessionStorage.clear();
});

afterEach(() => {
  window.history.pushState({}, '', '/');
});

describe('Rotas públicas', () => {
  it('exibe a página inicial em /', async () => {
    const { getByText } = await abrir('/');

    expect(getByText('Atendimento seguro e sem fila!')).toBeInTheDocument();
  });

  it('exibe o cadastro em /cadastro e o login em /login', async () => {
    const cadastro = await abrir('/cadastro');
    expect(
      cadastro.getByLabelText('Nome do estabelecimento')
    ).toBeInTheDocument();
    cadastro.unmount();

    const login = await abrir('/login');
    expect(login.getByLabelText('Senha')).toBeInTheDocument();
  });

  it('a página pública da fila abre sem login', async () => {
    const { getByLabelText } = await abrir('/fila/moda-azul');

    expect(getByLabelText('Seu telefone (com DDD)')).toBeInTheDocument();
  });
});

describe('Rotas do dashboard exigem login', () => {
  it.each(['/dashboard', '/dashboard/qrcode', '/dashboard/perfil'])(
    '%s sem sessão leva ao login',
    async (caminho) => {
      const u = await abrir(caminho);

      expect(u.getByLabelText('Senha')).toBeInTheDocument();
      expect(window.location.pathname).toBe('/login');
      expect(api.listarFila).not.toHaveBeenCalled();
    }
  );

  it('logada, exibe o dashboard em /dashboard', async () => {
    guardarToken('tok');
    api.obterConta.mockResolvedValue(DADOS);
    const { getByText } = await abrir('/dashboard');

    expect(getByText('Fila', { selector: 'h3' })).toBeInTheDocument();
    expect(getByText('Em Atendimento', { selector: 'h3' })).toBeInTheDocument();
    expect(api.obterConta).toHaveBeenCalledWith('tok');
    expect(api.listarFila).toHaveBeenCalledWith('moda-azul', 'tok');
  });

  it('logada, exibe a geração de QR code em /dashboard/qrcode', async () => {
    guardarToken('tok');
    api.obterConta.mockResolvedValue(DADOS);
    const { getByText } = await abrir('/dashboard/qrcode');

    expect(getByText('Gerar QRCode', { selector: 'h1' })).toBeInTheDocument();
  });

  it('logada, exibe as configurações em /dashboard/perfil', async () => {
    guardarToken('tok');
    api.obterConta.mockResolvedValue(DADOS);
    const { getByText } = await abrir('/dashboard/perfil');

    expect(getByText('Perfil', { selector: 'h1' })).toBeInTheDocument();
  });

  it('logada, /login e /cadastro levam ao dashboard', async () => {
    guardarToken('tok');
    api.obterConta.mockResolvedValue(DADOS);
    const u = await abrir('/login');

    expect(window.location.pathname).toBe('/dashboard');
    expect(
      u.getByText('Em Atendimento', { selector: 'h3' })
    ).toBeInTheDocument();
  });

  it('o 401 de uma chamada autenticada leva ao login com o aviso', async () => {
    guardarToken('tok');
    api.obterConta.mockResolvedValue(DADOS);
    api.listarFila.mockRejectedValue(new api.ApiError(401, 'x'));
    const u = await abrir('/dashboard');

    expect(window.location.pathname).toBe('/login');
    expect(
      u.getByText('Sua sessão expirou. Entre de novo.')
    ).toBeInTheDocument();
  });
});
