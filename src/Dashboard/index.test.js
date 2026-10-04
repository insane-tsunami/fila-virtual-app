import React from 'react';
import { MemoryRouter } from 'react-router-dom';
import { render, fireEvent, within, act } from '@testing-library/react';

import Dashboard from '.';
import SessaoDeTeste from '../sessao/SessaoDeTeste';
import { ApiError, listarFila, finalizarEntrada } from '../api';

jest.mock('../api', () => {
  class ApiErrorMock extends Error {
    constructor(status, message) {
      super(message);
      this.status = status;
    }
  }
  return {
    ApiError: ApiErrorMock,
    listarFila: jest.fn(),
    finalizarEntrada: jest.fn(),
  };
});

const REDE = 'Não foi possível falar com o servidor. Tente de novo.';
const AVISO = 'Não foi possível atualizar a fila. Tentando de novo...';

// Fila "do servidor": a posição de cada um é o índice + 1, o primeiro está em
// atendimento.
let servidor;
const entrada = (codigo, fim) => ({ codigo, telefone: `*****${fim}` });
const comoApi = () =>
  servidor.map((e, i) => ({
    ...e,
    posicao: i + 1,
    status: i === 0 ? 'em_atendimento' : 'aguardando',
  }));

const recusar = jest.fn();

function renderDashboard() {
  const utils = render(
    <MemoryRouter>
      <SessaoDeTeste
        valor={{
          token: 'k',
          expirar: recusar,
          loja: {
            nome: 'Veste Bem',
            slug: 'veste-bem',
            endereco_publico: null,
          },
        }}
      >
        <Dashboard />
      </SessaoDeTeste>
    </MemoryRouter>
  );
  const painel = (titulo) =>
    utils.getByText(titulo, { selector: 'h3' }).parentElement;
  return {
    ...utils,
    fila: () => painel('Fila'),
    atendimento: () => painel('Em Atendimento'),
  };
}

const esvaziar = async () => {
  for (let i = 0; i < 6; i += 1) {
    // eslint-disable-next-line no-await-in-loop
    await act(async () => {});
  }
};

const avancar = async (ms) => {
  await act(async () => {
    jest.advanceTimersByTime(ms);
  });
  await esvaziar();
};

// Renderiza com o relógio simulado (ligado antes de montar) e sem findBy/wait,
// que avançariam os timers.
async function abrirComRelogio() {
  jest.useFakeTimers();
  const u = renderDashboard();
  await esvaziar();
  return u;
}

beforeEach(() => {
  servidor = [
    entrada('c1', '1111'),
    entrada('c2', '2222'),
    entrada('c3', '3333'),
  ];
  recusar.mockReset();
  listarFila.mockReset().mockImplementation(() => Promise.resolve(comoApi()));
  finalizarEntrada.mockReset().mockImplementation(() => {
    servidor = servidor.slice(1);
    return Promise.resolve({ finalizada: {}, atual: null });
  });
});

afterEach(() => {
  jest.clearAllTimers();
  jest.useRealTimers();
});

describe('Dashboard da fila', () => {
  it('mostra o primeiro cliente em atendimento e os demais na fila, como a API devolveu', async () => {
    const u = renderDashboard();

    expect(await u.findByText('*****2222')).toBeInTheDocument();
    expect(within(u.atendimento()).getByText('1')).toBeInTheDocument();
    expect(within(u.fila()).getByText('2')).toBeInTheDocument();
    expect(within(u.fila()).getByText('3')).toBeInTheDocument();
    expect(
      within(u.fila())
        .getAllByText(/^\*{5}\d{4}$/)
        .map((n) => n.textContent)
    ).toEqual(['*****1111', '*****2222', '*****3333']);
    expect(listarFila).toHaveBeenCalledWith('veste-bem', 'k');
  });

  it('não mostra telefone completo nem clientes que a API não devolveu', async () => {
    const u = renderDashboard();
    await u.findByText('*****2222');

    const semMascarados = u.container.textContent.replace(/\*{5}\d{4}/g, '');
    expect(semMascarados).not.toMatch(/\d{4,}/);
    expect(u.queryByText('*****5314')).not.toBeInTheDocument();
  });

  it('promove o próximo cliente ao finalizar um atendimento', async () => {
    const u = renderDashboard();
    await u.findByText('*****2222');

    fireEvent.click(u.getByText('Finalizar Atendimento'));

    await u.findByText('2', { selector: '.MuiAvatar-root' });
    expect(finalizarEntrada).toHaveBeenCalledWith('veste-bem', 'c1', 'k');
    expect(u.queryByText('*****1111')).not.toBeInTheDocument();
    expect(within(u.atendimento()).getByText('1')).toBeInTheDocument();
    expect(within(u.fila()).getByText('*****3333')).toBeInTheDocument();
  });

  it('mostra "Fila vazia" quando só resta o cliente atual', async () => {
    servidor = [entrada('c1', '1111')];
    const u = renderDashboard();

    expect(await u.findByText('Fila vazia')).toBeInTheDocument();
    expect(within(u.atendimento()).getByText('1')).toBeInTheDocument();
  });

  it('mostra a mensagem final, sem "0" solto, ao atender todos', async () => {
    servidor = [entrada('c1', '1111')];
    const u = renderDashboard();
    await u.findByText('Fila vazia');

    fireEvent.click(u.getByText('Finalizar Atendimento'));

    expect(
      await within(u.atendimento()).findByText(/Atendeu todos os clientes!/)
    ).toBeInTheDocument();
    expect(u.queryByText('Finalizar Atendimento')).not.toBeInTheDocument();
    expect(u.queryByText('0')).not.toBeInTheDocument();
    expect(u.fila().textContent).toBe('Fila');
  });

  it('API devolvendo fila vazia: mensagem final e nenhum "0"', async () => {
    servidor = [];
    const u = renderDashboard();

    expect(
      await u.findByText(/Atendeu todos os clientes!/)
    ).toBeInTheDocument();
    expect(u.queryByText('0')).not.toBeInTheDocument();
  });

  it('exibe nome e inicial do estabelecimento na barra lateral', async () => {
    const u = renderDashboard();

    expect(await u.findByText('Veste Bem')).toBeInTheDocument();
    expect(u.getByText('V')).toBeInTheDocument();
  });
});

describe('Finalizar atendimento: erros e concorrência', () => {
  it('409: recarrega a fila sem mostrar erro e sem finalizar mais ninguém', async () => {
    const u = renderDashboard();
    await u.findByText('*****2222');
    // outro aparelho já finalizou o c1
    servidor = servidor.slice(1);
    finalizarEntrada.mockReset().mockRejectedValue(new ApiError(409, 'x'));
    const antes = listarFila.mock.calls.length;

    fireEvent.click(u.getByText('Finalizar Atendimento'));

    await u.findByText('2', { selector: '.MuiAvatar-root' });
    expect(u.queryByText('*****1111')).not.toBeInTheDocument();
    expect(listarFila.mock.calls.length).toBeGreaterThan(antes);
    expect(finalizarEntrada).toHaveBeenCalledTimes(1);
    expect(u.queryByRole('alert')).not.toBeInTheDocument();
  });

  it('falha de rede ao finalizar: mantém a fila e avisa', async () => {
    const u = renderDashboard();
    await u.findByText('*****2222');
    finalizarEntrada.mockReset().mockRejectedValue(new ApiError(0, REDE));

    fireEvent.click(u.getByText('Finalizar Atendimento'));

    expect(await u.findByText(REDE)).toBeInTheDocument();
    expect(u.getByText('*****1111')).toBeInTheDocument();
    expect(u.getByText('*****2222')).toBeInTheDocument();
  });

  it('clique duplo: finaliza uma vez só', async () => {
    const u = renderDashboard();
    await u.findByText('*****2222');
    let liberar;
    finalizarEntrada.mockReset().mockImplementation(
      () =>
        new Promise((resolve) => {
          liberar = () => {
            servidor = servidor.slice(1);
            resolve({});
          };
        })
    );

    fireEvent.click(u.getByText('Finalizar Atendimento'));
    fireEvent.click(u.getByText('Finalizar Atendimento'));
    expect(finalizarEntrada).toHaveBeenCalledTimes(1);

    await act(async () => liberar());
    await u.findByText('2', { selector: '.MuiAvatar-root' });
    // liberado depois da resposta
    fireEvent.click(u.getByText('Finalizar Atendimento'));
    expect(finalizarEntrada).toHaveBeenCalledTimes(2);
  });

  it('401 ao finalizar: leva ao login (expira a sessão)', async () => {
    const u = renderDashboard();
    await u.findByText('*****2222');
    finalizarEntrada.mockReset().mockRejectedValue(new ApiError(401, 'x'));

    fireEvent.click(u.getByText('Finalizar Atendimento'));
    await esvaziar();

    expect(recusar).toHaveBeenCalledTimes(1);
  });
});

describe('Atualização automática da fila', () => {
  it('cliente novo aparece em até 5 segundos, sem recarregar', async () => {
    const u = await abrirComRelogio();
    expect(u.getByText('*****3333')).toBeInTheDocument();

    servidor = [...servidor, entrada('c4', '4444')];
    await avancar(4900);
    expect(u.queryByText('*****4444')).not.toBeInTheDocument();
    await avancar(200);

    expect(u.getByText('*****4444')).toBeInTheDocument();
  });

  it('não sobrepõe consultas: espera a resposta antes de agendar a próxima', async () => {
    let responder;
    listarFila.mockReset().mockImplementation(
      () =>
        new Promise((resolve) => {
          responder = () => resolve(comoApi());
        })
    );
    jest.useFakeTimers();
    renderDashboard();
    await esvaziar();
    expect(listarFila).toHaveBeenCalledTimes(1);

    await avancar(30000);
    expect(listarFila).toHaveBeenCalledTimes(1);

    await act(async () => responder());
    await esvaziar();
    await avancar(5000);
    expect(listarFila).toHaveBeenCalledTimes(2);
  });

  it('ao sair da página, para de consultar', async () => {
    const u = await abrirComRelogio();
    const feitas = listarFila.mock.calls.length;

    u.unmount();
    await avancar(30000);

    expect(listarFila).toHaveBeenCalledTimes(feitas);
  });

  it('falha e recuperação: mantém a última fila, avisa e volta ao normal', async () => {
    const u = await abrirComRelogio();
    listarFila.mockRejectedValueOnce(new ApiError(0, REDE));

    await avancar(5000);
    expect(u.getByText(AVISO)).toBeInTheDocument();
    expect(u.getByText('*****2222')).toBeInTheDocument();

    servidor = [...servidor, entrada('c4', '4444')];
    await avancar(5000);
    expect(u.queryByText(AVISO)).not.toBeInTheDocument();
    expect(u.getByText('*****4444')).toBeInTheDocument();
  });

  it('primeira carga sem resposta: aviso, nenhum cliente e nada de "Atendeu todos"', async () => {
    listarFila.mockReset().mockRejectedValueOnce(new ApiError(0, REDE));
    jest.useFakeTimers();
    const u = renderDashboard();
    await esvaziar();

    expect(u.getByText(AVISO)).toBeInTheDocument();
    expect(u.queryByText(/Atendeu todos os clientes!/)).not.toBeInTheDocument();
    expect(u.queryByText('Finalizar Atendimento')).not.toBeInTheDocument();

    listarFila.mockImplementation(() => Promise.resolve(comoApi()));
    await avancar(5000);
    expect(u.queryByText(AVISO)).not.toBeInTheDocument();
    expect(u.getByText('*****1111')).toBeInTheDocument();
  });

  it('401 na consulta periódica: leva ao login (expira a sessão)', async () => {
    await abrirComRelogio();
    listarFila.mockRejectedValueOnce(new ApiError(401, 'x'));

    await avancar(5000);

    expect(recusar).toHaveBeenCalledTimes(1);
  });
});
