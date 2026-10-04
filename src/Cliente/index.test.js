import React from 'react';
import { MemoryRouter, Route } from 'react-router-dom';
import { render, fireEvent, wait, act } from '@testing-library/react';

import Cliente from './index';
import App from '../App';
import { ApiError, buscarLoja, entrarNaFila, consultarEntrada } from '../api';

jest.mock('../api', () => {
  class ApiErrorMock extends Error {
    constructor(status, message) {
      super(message);
      this.status = status;
    }
  }
  return {
    ApiError: ApiErrorMock,
    buscarLoja: jest.fn(),
    entrarNaFila: jest.fn(),
    consultarEntrada: jest.fn(),
  };
});

const LOJA = { nome: 'Veste Bem', slug: 'veste-bem', endereco_publico: null };
const CHAVE = 'zerafilas:entrada:veste-bem';

function renderCliente(caminho = '/fila/veste-bem') {
  return render(
    <MemoryRouter initialEntries={[caminho]}>
      <Route path="/fila/:slug">
        <Cliente />
      </Route>
    </MemoryRouter>
  );
}

async function entrar(utils, telefone = '11971778203') {
  const campo = await utils.findByLabelText('Seu telefone (com DDD)');
  fireEvent.change(campo, { target: { value: telefone } });
  fireEvent.submit(campo.closest('form'));
}

beforeEach(() => {
  buscarLoja.mockReset().mockResolvedValue(LOJA);
  entrarNaFila.mockReset();
  consultarEntrada.mockReset();
  window.localStorage.clear();
});

afterEach(() => {
  jest.clearAllTimers();
  jest.useRealTimers();
  jest.restoreAllMocks();
});

describe('Página pública da fila da loja', () => {
  it('mostra nome da loja, campo e botão', async () => {
    const u = renderCliente();

    expect(await u.findByText('Veste Bem')).toBeInTheDocument();
    expect(u.getByLabelText('Seu telefone (com DDD)')).toBeInTheDocument();
    expect(u.getByText('Entrar na fila')).toBeInTheDocument();
    expect(buscarLoja).toHaveBeenCalledWith('veste-bem');
  });

  it('loja inexistente: mensagem e sem formulário', async () => {
    buscarLoja.mockRejectedValue(new ApiError(404, 'Loja não encontrada'));
    const u = renderCliente('/fila/nao-existe');

    expect(await u.findByText('Loja não encontrada.')).toBeInTheDocument();
    expect(
      u.queryByLabelText('Seu telefone (com DDD)')
    ).not.toBeInTheDocument();
    expect(u.queryByText('Entrar na fila')).not.toBeInTheDocument();
  });

  it('a rota /fila/:slug convive com as rotas atuais', async () => {
    window.history.pushState({}, '', '/fila/veste-bem');
    const u = render(<App />);
    expect(
      await u.findByLabelText('Seu telefone (com DDD)')
    ).toBeInTheDocument();

    window.history.pushState({}, '', '/login');
    const l = render(<App />);
    expect(l.getAllByText(/Entrar/i).length).toBeGreaterThan(0);
    window.history.pushState({}, '', '/');
  });
});

describe('Entrar na fila', () => {
  it('fila vazia: "É a sua vez!" e posição 1, guardando o código', async () => {
    entrarNaFila.mockResolvedValue({
      codigo: 'abc',
      posicao: 1,
      status: 'em_atendimento',
    });
    const u = renderCliente();
    await entrar(u);

    expect(await u.findByText('É a sua vez!')).toBeInTheDocument();
    expect(u.getByText('Sua posição: 1')).toBeInTheDocument();
    expect(entrarNaFila).toHaveBeenCalledWith('veste-bem', '11971778203');
    expect(window.localStorage.getItem(CHAVE)).toBe('abc');
  });

  it('fila com clientes: "Aguardando" e posição 3', async () => {
    entrarNaFila.mockResolvedValue({
      codigo: 'abc',
      posicao: 3,
      status: 'aguardando',
    });
    const u = renderCliente();
    await entrar(u);

    expect(await u.findByText('Aguardando')).toBeInTheDocument();
    expect(u.getByText('Sua posição: 3')).toBeInTheDocument();
  });

  it('telefone que já está na fila mostra a entrada existente', async () => {
    entrarNaFila.mockResolvedValue({
      codigo: 'velho',
      posicao: 2,
      status: 'aguardando',
    });
    const u = renderCliente();
    await entrar(u);

    expect(await u.findByText('Sua posição: 2')).toBeInTheDocument();
    expect(window.localStorage.getItem(CHAVE)).toBe('velho');
  });

  it('422: mostra a mensagem da API e mantém o telefone digitado', async () => {
    entrarNaFila.mockRejectedValue(new ApiError(422, 'Telefone inválido'));
    const u = renderCliente();
    await entrar(u, '123');

    expect(await u.findByText('Telefone inválido')).toBeInTheDocument();
    expect(u.getByLabelText('Seu telefone (com DDD)').value).toBe('123');
    expect(window.localStorage.getItem(CHAVE)).toBeNull();
  });

  it('API fora do ar: mensagem fixa e telefone mantido', async () => {
    entrarNaFila.mockRejectedValue(new ApiError(0, 'qualquer'));
    const u = renderCliente();
    await entrar(u, '11971778203');

    expect(
      await u.findByText(
        'Não foi possível falar com o servidor. Tente de novo.'
      )
    ).toBeInTheDocument();
    expect(u.getByLabelText('Seu telefone (com DDD)').value).toBe(
      '11971778203'
    );
  });

  it('armazenamento bloqueado: entra e mostra a posição normalmente', async () => {
    const erro = () => {
      throw new Error('bloqueado');
    };
    jest.spyOn(Storage.prototype, 'setItem').mockImplementation(erro);
    jest.spyOn(Storage.prototype, 'getItem').mockImplementation(erro);
    jest.spyOn(Storage.prototype, 'removeItem').mockImplementation(erro);
    entrarNaFila.mockResolvedValue({
      codigo: 'abc',
      posicao: 2,
      status: 'aguardando',
    });
    const u = renderCliente();
    await entrar(u);

    expect(await u.findByText('Sua posição: 2')).toBeInTheDocument();
  });
});

describe('Continuar depois de fechar a página', () => {
  it('reabre mostrando a posição atual sem pedir o telefone', async () => {
    window.localStorage.setItem(CHAVE, 'abc');
    consultarEntrada.mockResolvedValue({
      codigo: 'abc',
      posicao: 2,
      status: 'aguardando',
    });
    const u = renderCliente();

    expect(await u.findByText('Sua posição: 2')).toBeInTheDocument();
    expect(
      u.queryByLabelText('Seu telefone (com DDD)')
    ).not.toBeInTheDocument();
    expect(consultarEntrada).toHaveBeenCalledWith('veste-bem', 'abc');
  });

  it('código desconhecido (404): apaga e mostra o formulário', async () => {
    window.localStorage.setItem(CHAVE, 'velho');
    consultarEntrada.mockRejectedValue(new ApiError(404, 'não existe'));
    const u = renderCliente();

    expect(
      await u.findByLabelText('Seu telefone (com DDD)')
    ).toBeInTheDocument();
    expect(window.localStorage.getItem(CHAVE)).toBeNull();
  });
});

describe('Acompanhamento automático', () => {
  const esvaziar = async () => {
    for (let i = 0; i < 5; i += 1) {
      // eslint-disable-next-line no-await-in-loop
      await act(async () => {});
    }
  };

  async function entrarSemAvancarRelogio(u) {
    await esvaziar();
    fireEvent.change(u.getByLabelText('Seu telefone (com DDD)'), {
      target: { value: '11971778203' },
    });
    fireEvent.submit(
      u.getByLabelText('Seu telefone (com DDD)').closest('form')
    );
    await esvaziar();
    expect(u.getByText('Sua posição: 3')).toBeInTheDocument();
  }

  async function entrarComRelogio(respostasSeguintes) {
    entrarNaFila.mockResolvedValue({
      codigo: 'abc',
      posicao: 3,
      status: 'aguardando',
    });
    respostasSeguintes.forEach((r) => {
      if (r instanceof Error) consultarEntrada.mockRejectedValueOnce(r);
      else consultarEntrada.mockResolvedValueOnce(r);
    });
    jest.useFakeTimers();
    const u = renderCliente();
    await entrarSemAvancarRelogio(u);
    return u;
  }

  const avancar = async (ms) => {
    await act(async () => {
      jest.advanceTimersByTime(ms);
    });
  };

  it('a fila anda: atualiza a posição a cada 5 segundos', async () => {
    const u = await entrarComRelogio([
      { codigo: 'abc', posicao: 2, status: 'aguardando' },
    ]);

    await avancar(4900);
    expect(consultarEntrada).not.toHaveBeenCalled();
    await avancar(200);

    await wait(() => expect(u.getByText('Sua posição: 2')).toBeInTheDocument());
    expect(consultarEntrada).toHaveBeenCalledTimes(1);

    await avancar(4900);
    expect(consultarEntrada).toHaveBeenCalledTimes(1);
    await avancar(200);
    expect(consultarEntrada).toHaveBeenCalledTimes(2);
  });

  it('não sobrepõe consultas: espera a resposta antes de agendar a próxima', async () => {
    let resolver;
    entrarNaFila.mockResolvedValue({
      codigo: 'abc',
      posicao: 3,
      status: 'aguardando',
    });
    consultarEntrada.mockImplementation(
      () =>
        new Promise((r) => {
          resolver = r;
        })
    );
    jest.useFakeTimers();
    const u = renderCliente();
    await entrarSemAvancarRelogio(u);

    await avancar(5000);
    await avancar(20000);
    expect(consultarEntrada).toHaveBeenCalledTimes(1);

    await act(async () => {
      resolver({ codigo: 'abc', posicao: 3, status: 'aguardando' });
    });
    await avancar(5000);
    expect(consultarEntrada).toHaveBeenCalledTimes(2);
  });

  it('finalizado: mostra a mensagem, para de consultar e permite entrar de novo', async () => {
    const u = await entrarComRelogio([
      { codigo: 'abc', posicao: null, status: 'finalizado' },
    ]);

    await avancar(5000);
    await wait(() =>
      expect(u.getByText('Atendimento finalizado')).toBeInTheDocument()
    );
    await avancar(30000);
    expect(consultarEntrada).toHaveBeenCalledTimes(1);

    fireEvent.click(u.getByText('Entrar na fila de novo'));
    expect(u.getByLabelText('Seu telefone (com DDD)')).toBeInTheDocument();
    expect(window.localStorage.getItem(CHAVE)).toBeNull();
  });

  it('falha na consulta: mantém a última posição, avisa e volta ao normal', async () => {
    const u = await entrarComRelogio([
      new ApiError(0, 'rede'),
      { codigo: 'abc', posicao: 1, status: 'em_atendimento' },
    ]);

    await avancar(5000);
    await wait(() =>
      expect(
        u.getByText('Não foi possível atualizar. Tentando de novo...')
      ).toBeInTheDocument()
    );
    expect(u.getByText('Sua posição: 3')).toBeInTheDocument();

    await avancar(5000);
    await wait(() => expect(u.getByText('Sua posição: 1')).toBeInTheDocument());
    expect(
      u.queryByText('Não foi possível atualizar. Tentando de novo...')
    ).not.toBeInTheDocument();
  });

  it('desmontar a página cancela as consultas', async () => {
    const u = await entrarComRelogio([]);
    u.unmount();

    await avancar(30000);
    expect(consultarEntrada).not.toHaveBeenCalled();
  });
});

describe('Sem telefones na tela de acompanhamento', () => {
  it('não mostra nenhum telefone', async () => {
    entrarNaFila.mockResolvedValue({
      codigo: 'abc',
      posicao: 3,
      status: 'aguardando',
    });
    const u = renderCliente();
    await entrar(u, '11971778203');
    await u.findByText('Sua posição: 3');

    expect(u.container.textContent).not.toMatch(/\d{4,}/);
    expect(u.queryByDisplayValue('11971778203')).not.toBeInTheDocument();
  });
});
