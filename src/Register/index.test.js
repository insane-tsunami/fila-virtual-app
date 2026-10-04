import React from 'react';
import { MemoryRouter, Route, Switch, useLocation } from 'react-router-dom';
import { render, fireEvent, act } from '@testing-library/react';

import Register from '.';
import { SessaoProvider, RotaAnonima } from '../sessao/SessaoProvider';
import { apagarToken, lerToken } from '../sessao/armazenamento';
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
  conta: { email: 'contato@modaazul.com', cnpj: '93339970000105' },
  loja: { nome: 'Moda Azul', slug: 'moda-azul', endereco_publico: null },
};

function Rota() {
  return <p>rota: {useLocation().pathname}</p>;
}

function renderRegister() {
  return render(
    <MemoryRouter initialEntries={['/cadastro']}>
      <SessaoProvider>
        <Switch>
          <Route path="/cadastro">
            <RotaAnonima>
              <Register />
            </RotaAnonima>
          </Route>
          <Route path="/dashboard">
            <Rota />
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

function preencher(u, mudancas = {}) {
  const valores = {
    'E-mail': 'contato@modaazul.com',
    CNPJ: '93.339.970/0001-05',
    'Nome do estabelecimento': 'Moda Azul',
    'Escolha uma Senha': 'senha-segura-1',
    'Confirme a senha': 'senha-segura-1',
    ...mudancas,
  };
  Object.entries(valores).forEach(([rotulo, valor]) =>
    fireEvent.change(u.getByLabelText(rotulo), { target: { value: valor } })
  );
}

async function enviar(u) {
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

describe('Cadastro do estabelecimento', () => {
  it('exibe os cinco campos e o botão, com as senhas mascaradas', async () => {
    const u = renderRegister();
    await esvaziar();

    expect(u.getByLabelText('E-mail')).toBeInTheDocument();
    expect(u.getByLabelText('CNPJ')).toBeInTheDocument();
    expect(u.getByLabelText('Nome do estabelecimento')).toBeInTheDocument();
    expect(u.getByLabelText('Escolha uma Senha')).toHaveAttribute(
      'type',
      'password'
    );
    expect(u.getByLabelText('Confirme a senha')).toHaveAttribute(
      'type',
      'password'
    );
    expect(u.getByText('Cadastrar')).toBeInTheDocument();
  });

  it('leva ao login pelo botão "Faça o Login aqui"', async () => {
    const u = renderRegister();
    await esvaziar();

    expect(u.getByText('Faça o Login aqui').closest('a')).toHaveAttribute(
      'href',
      '/login'
    );
  });

  it('cadastro válido: envia os quatro dados, fica logada e vai para /dashboard', async () => {
    api.cadastrar.mockResolvedValue(SESSAO);
    const u = renderRegister();
    await esvaziar();

    preencher(u);
    await enviar(u);

    expect(api.cadastrar).toHaveBeenCalledWith({
      email: 'contato@modaazul.com',
      cnpj: '93.339.970/0001-05',
      nome: 'Moda Azul',
      senha: 'senha-segura-1',
    });
    expect(u.getByText('rota: /dashboard')).toBeInTheDocument();
    expect(lerToken()).toBe('tok');
  });

  it('senhas diferentes: mensagem e nenhuma requisição', async () => {
    const u = renderRegister();
    await esvaziar();

    preencher(u, { 'Confirme a senha': 'outra-coisa-99' });
    await enviar(u);

    expect(u.getByText('As senhas não são iguais.')).toBeInTheDocument();
    expect(api.cadastrar).not.toHaveBeenCalled();
    expect(u.getByLabelText('E-mail').value).toBe('contato@modaazul.com');
  });

  it('409: mostra a mensagem e mantém e-mail, CNPJ e nome (as senhas são esvaziadas)', async () => {
    api.cadastrar.mockRejectedValue(
      new api.ApiError(409, 'Já existe uma conta com este e-mail.')
    );
    const u = renderRegister();
    await esvaziar();

    preencher(u);
    await enviar(u);

    expect(
      u.getByText('Já existe uma conta com este e-mail.')
    ).toBeInTheDocument();
    expect(u.getByLabelText('E-mail').value).toBe('contato@modaazul.com');
    expect(u.getByLabelText('CNPJ').value).toBe('93.339.970/0001-05');
    expect(u.getByLabelText('Nome do estabelecimento').value).toBe('Moda Azul');
    expect(u.getByLabelText('Escolha uma Senha').value).toBe('');
    expect(u.getByLabelText('Confirme a senha').value).toBe('');
    expect(lerToken()).toBeNull();
  });

  it('422: mostra a mensagem da API e continua em /cadastro', async () => {
    api.cadastrar.mockRejectedValue(new api.ApiError(422, 'E-mail inválido.'));
    const u = renderRegister();
    await esvaziar();

    preencher(u, { 'E-mail': 'sem-arroba' });
    await enviar(u);

    expect(u.getByText('E-mail inválido.')).toBeInTheDocument();
    expect(u.getByLabelText('CNPJ')).toBeInTheDocument();
  });

  it('429 (limite de tentativas): mostra a mensagem e mantém os dados', async () => {
    api.cadastrar.mockRejectedValue(
      new api.ApiError(429, 'Muitas tentativas. Tente de novo em 15 minutos.')
    );
    const u = renderRegister();
    await esvaziar();

    preencher(u, { 'E-mail': 'a@exemplo.com' });
    await enviar(u);

    expect(
      u.getByText('Muitas tentativas. Tente de novo em 15 minutos.')
    ).toBeInTheDocument();
    expect(u.getByLabelText('E-mail').value).toBe('a@exemplo.com');
  });

  it('CNPJ digitado em minúsculas aparece em maiúsculas no campo', async () => {
    const u = renderRegister();
    await esvaziar();

    fireEvent.change(u.getByLabelText('CNPJ'), {
      target: { value: '12.abc.345/01de-35' },
    });

    expect(u.getByLabelText('CNPJ').value).toBe('12.ABC.345/01DE-35');
  });

  it('CNPJ colado em minúsculas é enviado em maiúsculas', async () => {
    api.cadastrar.mockResolvedValue(SESSAO);
    const u = renderRegister();
    await esvaziar();

    preencher(u, { CNPJ: '12abc34501de35' });
    expect(u.getByLabelText('CNPJ').value).toBe('12ABC34501DE35');
    await enviar(u);

    expect(api.cadastrar).toHaveBeenCalledWith(
      expect.objectContaining({ cnpj: '12ABC34501DE35' })
    );
  });

  it('dígito verificador errado (422): mostra a mensagem da API e mantém o CNPJ', async () => {
    api.cadastrar.mockRejectedValue(
      new api.ApiError(
        422,
        'CNPJ inválido: confira os caracteres e os dígitos verificadores.'
      )
    );
    const u = renderRegister();
    await esvaziar();

    preencher(u, { CNPJ: '12ABC34501DE36' });
    await enviar(u);

    expect(
      u.getByText(
        'CNPJ inválido: confira os caracteres e os dígitos verificadores.'
      )
    ).toBeInTheDocument();
    expect(u.getByLabelText('CNPJ').value).toBe('12ABC34501DE36');
    expect(u.getByLabelText('Nome do estabelecimento')).toBeInTheDocument();
  });

  it('API fora do ar: mensagem fixa e campos mantidos', async () => {
    api.cadastrar.mockRejectedValue(new api.ApiError(0, 'rede'));
    const u = renderRegister();
    await esvaziar();

    preencher(u);
    await enviar(u);

    expect(u.getByText(REDE)).toBeInTheDocument();
    expect(u.getByLabelText('Nome do estabelecimento').value).toBe('Moda Azul');
  });
});
