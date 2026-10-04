import React from 'react';
import { MemoryRouter, Route, Switch } from 'react-router-dom';
import { render, fireEvent, act } from '@testing-library/react';

import AvisoEmail from './AvisoEmail';
import SessaoDeTeste, { CONTA_DE_TESTE } from '../sessao/SessaoDeTeste';
import { SessaoProvider, RotaProtegida } from '../sessao/SessaoProvider';
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
    reenviarConfirmacao: jest.fn(),
    sair: jest.fn(),
  };
});

const REDE = 'Não foi possível falar com o servidor. Tente de novo.';
const NAO_CONFIRMADA = { ...CONTA_DE_TESTE, email_confirmado: false };
const LOJA = { nome: 'Moda Azul', slug: 'moda-azul', endereco_publico: null };
const expirar = jest.fn();
const atualizarConta = jest.fn();

const esvaziar = async () => {
  for (let i = 0; i < 4; i += 1) {
    // eslint-disable-next-line no-await-in-loop
    await act(async () => {});
  }
};

function renderFaixa(conta = NAO_CONFIRMADA) {
  return render(
    <MemoryRouter>
      <SessaoDeTeste valor={{ token: 'tok', conta, expirar, atualizarConta }}>
        <AvisoEmail />
      </SessaoDeTeste>
    </MemoryRouter>
  );
}

async function clicar(u, texto) {
  await act(async () => {
    fireEvent.click(u.getByText(texto));
  });
  await esvaziar();
}

beforeEach(() => {
  Object.values(api).forEach((f) => f.mockReset && f.mockReset());
  expirar.mockReset();
  atualizarConta.mockReset();
  apagarToken();
  window.sessionStorage.clear();
});

describe('Faixa "Confirme seu e-mail"', () => {
  it('aparece com as três ações quando o e-mail não está confirmado', () => {
    const u = renderFaixa();

    expect(u.getByText('Confirme seu e-mail.')).toBeInTheDocument();
    expect(u.getByText(/contato@modaazul\.com/)).toBeInTheDocument();
    expect(u.getByText('Reenviar e-mail')).toBeInTheDocument();
    expect(u.getByText('Já confirmei')).toBeInTheDocument();
    expect(u.getByText('Trocar e-mail').closest('a')).toHaveAttribute(
      'href',
      '/dashboard/perfil'
    );
  });

  it('não aparece com o e-mail confirmado', () => {
    const u = renderFaixa({ ...CONTA_DE_TESTE, email_confirmado: true });

    expect(u.queryByText('Confirme seu e-mail.')).toBeNull();
    expect(u.queryByText('Reenviar e-mail')).toBeNull();
  });

  it('reenviar: chama a API com o token e mostra a mensagem, e a faixa continua', async () => {
    api.reenviarConfirmacao.mockResolvedValue({
      mensagem: 'Enviamos um novo link de confirmação.',
    });
    const u = renderFaixa();

    await clicar(u, 'Reenviar e-mail');

    expect(api.reenviarConfirmacao).toHaveBeenCalledWith('tok');
    expect(
      u.getByText('Enviamos um novo link de confirmação.')
    ).toBeInTheDocument();
    expect(u.getByText('Confirme seu e-mail.')).toBeInTheDocument();
  });

  it('reenviar com 429: mostra a mensagem e NÃO expira a sessão', async () => {
    api.reenviarConfirmacao.mockRejectedValue(
      new api.ApiError(429, 'Muitas tentativas. Tente de novo em 60 minutos.')
    );
    const u = renderFaixa();

    await clicar(u, 'Reenviar e-mail');

    expect(
      u.getByText('Muitas tentativas. Tente de novo em 60 minutos.')
    ).toBeInTheDocument();
    expect(expirar).not.toHaveBeenCalled();
    expect(u.queryByText(REDE)).toBeNull();
  });

  it('reenviar com 401: expira a sessão', async () => {
    api.reenviarConfirmacao.mockRejectedValue(new api.ApiError(401, 'x'));
    const u = renderFaixa();

    await clicar(u, 'Reenviar e-mail');

    expect(expirar).toHaveBeenCalledTimes(1);
  });

  it('reenviar com a API fora do ar: mensagem fixa', async () => {
    api.reenviarConfirmacao.mockRejectedValue(new api.ApiError(0, 'rede'));
    const u = renderFaixa();

    await clicar(u, 'Reenviar e-mail');

    expect(u.getByText(REDE)).toBeInTheDocument();
    expect(expirar).not.toHaveBeenCalled();
  });

  it('"Já confirmei" com a conta ainda não confirmada: avisa', async () => {
    atualizarConta.mockResolvedValue(NAO_CONFIRMADA);
    const u = renderFaixa();

    await clicar(u, 'Já confirmei');

    expect(atualizarConta).toHaveBeenCalledTimes(1);
    expect(
      u.getByText(
        'Ainda não confirmado. Abra o link do e-mail ou peça um novo.'
      )
    ).toBeInTheDocument();
  });

  it('"Já confirmei" com a API fora do ar: mensagem fixa', async () => {
    atualizarConta.mockRejectedValue(new api.ApiError(0, 'rede'));
    const u = renderFaixa();

    await clicar(u, 'Já confirmei');

    expect(u.getByText(REDE)).toBeInTheDocument();
  });
});

describe('Faixa com a sessão de verdade', () => {
  function renderComSessao() {
    return render(
      <MemoryRouter initialEntries={['/dashboard']}>
        <SessaoProvider>
          <Switch>
            <Route path="/dashboard">
              <RotaProtegida>
                <AvisoEmail />
                <p>dashboard</p>
              </RotaProtegida>
            </Route>
          </Switch>
        </SessaoProvider>
      </MemoryRouter>
    );
  }

  it('"Já confirmei" depois de confirmar em outro aparelho: a faixa some', async () => {
    guardarToken('tok');
    api.obterConta.mockResolvedValue({ conta: NAO_CONFIRMADA, loja: LOJA });
    const u = renderComSessao();
    await esvaziar();
    expect(u.getByText('Confirme seu e-mail.')).toBeInTheDocument();

    api.obterConta.mockResolvedValue({
      conta: { ...CONTA_DE_TESTE, email_confirmado: true },
      loja: LOJA,
    });
    await clicar(u, 'Já confirmei');

    expect(u.queryByText('Confirme seu e-mail.')).toBeNull();
    expect(u.getByText('dashboard')).toBeInTheDocument();
  });

  it('com o e-mail já confirmado a faixa nem aparece', async () => {
    guardarToken('tok');
    api.obterConta.mockResolvedValue({
      conta: { ...CONTA_DE_TESTE, email_confirmado: true },
      loja: LOJA,
    });
    const u = renderComSessao();
    await esvaziar();

    expect(u.getByText('dashboard')).toBeInTheDocument();
    expect(u.queryByText('Confirme seu e-mail.')).toBeNull();
  });
});
