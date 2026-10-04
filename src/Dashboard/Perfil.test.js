import React from 'react';
import { MemoryRouter } from 'react-router-dom';
import { render, fireEvent, act } from '@testing-library/react';

import Perfil from './Perfil';
import SessaoDeTeste from '../sessao/SessaoDeTeste';
import { ApiError, trocarSenha } from '../api';

jest.mock('../api', () => {
  class ApiErrorMock extends Error {
    constructor(status, message) {
      super(message);
      this.status = status;
    }
  }
  return { ApiError: ApiErrorMock, trocarSenha: jest.fn() };
});

const REDE = 'Não foi possível falar com o servidor. Tente de novo.';
const expirar = jest.fn();

function renderPerfil() {
  return render(
    <MemoryRouter>
      <SessaoDeTeste
        valor={{
          token: 'tok',
          expirar,
          conta: { email: 'contato@modaazul.com', cnpj: '93339970000105' },
        }}
      >
        <Perfil />
      </SessaoDeTeste>
    </MemoryRouter>
  );
}

function preencher(u, atual, nova, confirmacao) {
  fireEvent.change(u.getByLabelText('Senha atual'), {
    target: { value: atual },
  });
  fireEvent.change(u.getByLabelText('Nova Senha'), { target: { value: nova } });
  fireEvent.change(u.getByLabelText('Confirme a nova senha'), {
    target: { value: confirmacao },
  });
}

async function atualizar(u) {
  await act(async () => {
    fireEvent.submit(u.getByLabelText('Nova Senha').closest('form'));
  });
}

beforeEach(() => {
  trocarSenha.mockReset();
  expirar.mockReset();
});

describe('Configurações do estabelecimento', () => {
  it('exibe o e-mail e o CNPJ da conta, somente leitura, com as mensagens', () => {
    const { getByLabelText, getByText } = renderPerfil();

    expect(getByLabelText('E-mail')).toHaveAttribute('readonly');
    expect(getByLabelText('E-mail').value).toBe('contato@modaazul.com');
    expect(getByLabelText('CNPJ')).toHaveAttribute('readonly');
    expect(getByLabelText('CNPJ').value).toBe('93.339.970/0001-05');
    expect(getByText('Não é possivel alterar o e-mail')).toBeInTheDocument();
    expect(getByText('Não é possivel alterar o CNPJ')).toBeInTheDocument();
  });

  it('oferece os três campos de senha mascarados e "Atualizar", sem seletor de avatar', () => {
    const {
      getByLabelText,
      getByText,
      queryByText,
      container,
    } = renderPerfil();

    ['Senha atual', 'Nova Senha', 'Confirme a nova senha'].forEach((rotulo) =>
      expect(getByLabelText(rotulo)).toHaveAttribute('type', 'password')
    );
    expect(getByText('Atualizar')).toBeInTheDocument();
    expect(queryByText(/Avatar/i)).not.toBeInTheDocument();
    expect(container.querySelector('input[type="file"]')).toBeNull();
  });

  it('troca bem-sucedida: envia token e senhas, avisa e esvazia os campos', async () => {
    trocarSenha.mockResolvedValue({ mensagem: 'Senha alterada.' });
    const u = renderPerfil();

    preencher(u, 'senha-antiga-1', 'senha-nova-22', 'senha-nova-22');
    await atualizar(u);

    expect(trocarSenha).toHaveBeenCalledWith(
      'tok',
      'senha-antiga-1',
      'senha-nova-22'
    );
    expect(u.getByText('Senha alterada.')).toBeInTheDocument();
    ['Senha atual', 'Nova Senha', 'Confirme a nova senha'].forEach((r) =>
      expect(u.getByLabelText(r).value).toBe('')
    );
  });

  it('senhas diferentes: mensagem e nenhuma requisição', async () => {
    const u = renderPerfil();

    preencher(u, 'senha-antiga-1', 'senha-nova-22', 'outra-coisa-99');
    await atualizar(u);

    expect(u.getByText('As senhas não são iguais.')).toBeInTheDocument();
    expect(trocarSenha).not.toHaveBeenCalled();
  });

  it('senha atual errada (422): mostra a mensagem e NÃO expira a sessão', async () => {
    trocarSenha.mockRejectedValue(new ApiError(422, 'Senha atual incorreta.'));
    const u = renderPerfil();

    preencher(u, 'errada-errada', 'senha-nova-22', 'senha-nova-22');
    await atualizar(u);

    expect(u.getByText('Senha atual incorreta.')).toBeInTheDocument();
    expect(expirar).not.toHaveBeenCalled();
    expect(u.getByLabelText('E-mail').value).toBe('contato@modaazul.com');
  });

  it('401 (sessão inválida): expira a sessão', async () => {
    trocarSenha.mockRejectedValue(new ApiError(401, 'x'));
    const u = renderPerfil();

    preencher(u, 'senha-antiga-1', 'senha-nova-22', 'senha-nova-22');
    await atualizar(u);

    expect(expirar).toHaveBeenCalledTimes(1);
  });

  it('API fora do ar: mensagem fixa e campos mantidos', async () => {
    trocarSenha.mockRejectedValue(new ApiError(0, 'rede'));
    const u = renderPerfil();

    preencher(u, 'senha-antiga-1', 'senha-nova-22', 'senha-nova-22');
    await atualizar(u);

    expect(u.getByText(REDE)).toBeInTheDocument();
    expect(u.getByLabelText('Nova Senha').value).toBe('senha-nova-22');
  });
});
