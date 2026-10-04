import React from 'react';
import { MemoryRouter } from 'react-router-dom';
import { render, fireEvent, act, within } from '@testing-library/react';

import Perfil from './Perfil';
import SessaoDeTeste from '../sessao/SessaoDeTeste';
import { ApiError, trocarEmail, trocarSenha } from '../api';

jest.mock('../api', () => {
  class ApiErrorMock extends Error {
    constructor(status, message) {
      super(message);
      this.status = status;
    }
  }
  return {
    ApiError: ApiErrorMock,
    trocarSenha: jest.fn(),
    trocarEmail: jest.fn(),
    reenviarConfirmacao: jest.fn(),
  };
});

const REDE = 'Não foi possível falar com o servidor. Tente de novo.';
const expirar = jest.fn();

function renderPerfil(cnpj = '93339970000105') {
  return render(
    <MemoryRouter>
      <SessaoDeTeste
        valor={{
          token: 'tok',
          expirar,
          conta: {
            email: 'contato@modaazul.com',
            cnpj,
            email_confirmado: true,
          },
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
  trocarEmail.mockReset();
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

  it('CNPJ alfanumérico aparece com a máscara XX.XXX.XXX/XXXX-XX', () => {
    const { getByLabelText } = renderPerfil('12ABC34501DE35');

    expect(getByLabelText('CNPJ').value).toBe('12.ABC.345/01DE-35');
  });

  it('valor fora do formato é mostrado como veio, sem quebrar', () => {
    const { getByLabelText } = renderPerfil('123');

    expect(getByLabelText('CNPJ').value).toBe('123');
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

  it('429 (limite de tentativas): mostra a mensagem e NÃO expira a sessão', async () => {
    trocarSenha.mockRejectedValue(
      new ApiError(429, 'Muitas tentativas. Tente de novo em 15 minutos.')
    );
    const u = renderPerfil();

    preencher(u, 'errada-errada', 'senha-nova-22', 'senha-nova-22');
    await atualizar(u);

    expect(
      u.getByText('Muitas tentativas. Tente de novo em 15 minutos.')
    ).toBeInTheDocument();
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

describe('E-mail da conta no Perfil', () => {
  const definirConta = jest.fn();
  const NAO_CONFIRMADA = {
    email: 'errado@modaazul.com',
    cnpj: '93339970000105',
    email_confirmado: false,
  };

  function renderNaoConfirmada() {
    return render(
      <MemoryRouter>
        <SessaoDeTeste
          valor={{ token: 'tok', expirar, definirConta, conta: NAO_CONFIRMADA }}
        >
          <Perfil />
        </SessaoDeTeste>
      </MemoryRouter>
    );
  }

  function formulario(u) {
    return within(u.getByLabelText('Trocar e-mail'));
  }

  function preencherEmail(u, email, senha) {
    const f = formulario(u);
    fireEvent.change(f.getByLabelText('Novo e-mail'), {
      target: { value: email },
    });
    fireEvent.change(f.getByLabelText('Senha atual'), {
      target: { value: senha },
    });
  }

  async function enviarEmail(u) {
    await act(async () => {
      fireEvent.submit(u.getByLabelText('Trocar e-mail'));
    });
  }

  beforeEach(() => {
    definirConta.mockReset();
  });

  it('com o e-mail confirmado: mostra o estado e não oferece a troca', () => {
    const u = renderPerfil();

    expect(u.getByText('E-mail confirmado')).toBeInTheDocument();
    expect(u.getByText('Não é possivel alterar o e-mail')).toBeInTheDocument();
    expect(u.queryByLabelText('Novo e-mail')).toBeNull();
    expect(u.queryByText('Trocar e-mail')).toBeNull();
  });

  it('não confirmado: mostra o estado, o CNPJ continua somente leitura e aparece o formulário de troca', () => {
    const u = renderNaoConfirmada();

    expect(u.getByText('E-mail ainda não confirmado')).toBeInTheDocument();
    expect(u.queryByText('Não é possivel alterar o e-mail')).toBeNull();
    expect(u.getByLabelText('CNPJ')).toHaveAttribute('readonly');
    expect(u.getByText('Não é possivel alterar o CNPJ')).toBeInTheDocument();
    expect(formulario(u).getByLabelText('Novo e-mail')).toBeInTheDocument();
    expect(formulario(u).getByLabelText('Senha atual')).toHaveAttribute(
      'type',
      'password'
    );
  });

  it('corrigir o e-mail: envia token, e-mail e senha, aplica a conta nova e esvazia os campos', async () => {
    const contaNova = { ...NAO_CONFIRMADA, email: 'certo@modaazul.com' };
    trocarEmail.mockResolvedValue({
      conta: contaNova,
      mensagem:
        'E-mail alterado. Enviamos um link de confirmação para o novo endereço.',
    });
    const u = renderNaoConfirmada();

    preencherEmail(u, 'certo@modaazul.com', 'senha-atual-1');
    await enviarEmail(u);

    expect(trocarEmail).toHaveBeenCalledWith(
      'tok',
      'certo@modaazul.com',
      'senha-atual-1'
    );
    expect(definirConta).toHaveBeenCalledWith(contaNova);
    expect(
      u.getByText(
        'E-mail alterado. Enviamos um link de confirmação para o novo endereço.'
      )
    ).toBeInTheDocument();
    expect(formulario(u).getByLabelText('Novo e-mail').value).toBe('');
    expect(formulario(u).getByLabelText('Senha atual').value).toBe('');
  });

  it('e-mail de outra conta (409): mostra a mensagem, esvazia só a senha e não expira a sessão', async () => {
    trocarEmail.mockRejectedValue(
      new ApiError(409, 'Já existe uma conta com este e-mail.')
    );
    const u = renderNaoConfirmada();

    preencherEmail(u, 'outra@modaazul.com', 'senha-atual-1');
    await enviarEmail(u);

    expect(
      u.getByText('Já existe uma conta com este e-mail.')
    ).toBeInTheDocument();
    expect(formulario(u).getByLabelText('Novo e-mail').value).toBe(
      'outra@modaazul.com'
    );
    expect(formulario(u).getByLabelText('Senha atual').value).toBe('');
    expect(definirConta).not.toHaveBeenCalled();
    expect(expirar).not.toHaveBeenCalled();
  });

  it('senha errada (422): mostra a mensagem e esvazia só a senha', async () => {
    trocarEmail.mockRejectedValue(new ApiError(422, 'Senha atual incorreta.'));
    const u = renderNaoConfirmada();

    preencherEmail(u, 'certo@modaazul.com', 'errada-errada');
    await enviarEmail(u);

    expect(u.getByText('Senha atual incorreta.')).toBeInTheDocument();
    expect(formulario(u).getByLabelText('Senha atual').value).toBe('');
  });

  it('429 (limite de tentativas): mostra a mensagem e NÃO expira a sessão', async () => {
    trocarEmail.mockRejectedValue(
      new ApiError(429, 'Muitas tentativas. Tente de novo em 60 minutos.')
    );
    const u = renderNaoConfirmada();

    preencherEmail(u, 'certo@modaazul.com', 'senha-atual-1');
    await enviarEmail(u);

    expect(
      u.getByText('Muitas tentativas. Tente de novo em 60 minutos.')
    ).toBeInTheDocument();
    expect(expirar).not.toHaveBeenCalled();
  });

  it('401 (sessão inválida): expira a sessão', async () => {
    trocarEmail.mockRejectedValue(new ApiError(401, 'x'));
    const u = renderNaoConfirmada();

    preencherEmail(u, 'certo@modaazul.com', 'senha-atual-1');
    await enviarEmail(u);

    expect(expirar).toHaveBeenCalledTimes(1);
  });

  it('API fora do ar: mensagem fixa e campos mantidos', async () => {
    trocarEmail.mockRejectedValue(new ApiError(0, 'rede'));
    const u = renderNaoConfirmada();

    preencherEmail(u, 'certo@modaazul.com', 'senha-atual-1');
    await enviarEmail(u);

    expect(u.getByText(REDE)).toBeInTheDocument();
    expect(formulario(u).getByLabelText('Novo e-mail').value).toBe(
      'certo@modaazul.com'
    );
    expect(formulario(u).getByLabelText('Senha atual').value).toBe(
      'senha-atual-1'
    );
  });
});
