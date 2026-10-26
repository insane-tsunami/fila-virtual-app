import React from 'react';
import { MemoryRouter } from 'react-router-dom';
import { render, fireEvent, act, within } from '@testing-library/react';

import Perfil from './Perfil';
import SessaoDeTeste from '../sessao/SessaoDeTeste';
import { ApiError, definirAvatar, trocarEmail, trocarSenha } from '../api';

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
    definirAvatar: jest.fn(),
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
  definirAvatar.mockReset();
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

  it('oferece os três campos de senha mascarados e "Atualizar"', () => {
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
    expect(queryByText('Avatar da loja')).toBeInTheDocument();
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

const LOJA = {
  nome: 'Moda Azul',
  slug: 'moda-azul',
  endereco_publico: null,
  avatar: null,
};

// Sessão com a loja em estado: `atualizarLoja` troca a loja como o SessaoProvider faz, e a barra
// lateral (renderizada pelo Perfil) mostra o resultado.
// eslint-disable-next-line react/prop-types
function PerfilComLoja({ lojaInicial }) {
  const [loja, setLoja] = React.useState(lojaInicial);
  return (
    <MemoryRouter>
      <SessaoDeTeste
        valor={{
          token: 'tok',
          expirar,
          loja,
          atualizarLoja: setLoja,
          conta: {
            email: 'contato@modaazul.com',
            cnpj: '93339970000105',
            email_confirmado: true,
          },
        }}
      >
        <Perfil />
      </SessaoDeTeste>
    </MemoryRouter>
  );
}

const renderComLoja = (loja = LOJA) =>
  render(<PerfilComLoja lojaInicial={loja} />);

const secao = (u) => within(u.getByRole('form', { name: 'Avatar da loja' }));

async function salvarAvatar(u) {
  await act(async () => {
    fireEvent.submit(u.getByRole('form', { name: 'Avatar da loja' }));
  });
}

function escolher(u, cor, icone) {
  if (cor) fireEvent.click(u.getByLabelText(cor));
  if (icone) fireEvent.click(u.getByLabelText(icone));
}

describe('Avatar da loja no Perfil', () => {
  it('loja sem avatar: a inicial, nada marcado, sem "Usar a inicial" nem envio de arquivo', () => {
    const u = renderComLoja();
    const s = secao(u);

    expect(s.getByText('Avatar da loja')).toBeInTheDocument();
    expect(s.getByText('M')).toBeInTheDocument();
    expect(u.getAllByRole('radio')).toHaveLength(12);
    u.getAllByRole('radio').forEach((r) => expect(r).not.toBeChecked());
    expect(u.queryByText('Usar a inicial')).not.toBeInTheDocument();
    expect(u.container.querySelector('input[type="file"]')).toBeNull();
  });

  it('loja com avatar: a escolha atual vem marcada e "Usar a inicial" aparece', () => {
    const u = renderComLoja({
      ...LOJA,
      avatar: { cor: 'azul', icone: 'cafe' },
    });

    expect(u.getByLabelText('Azul')).toBeChecked();
    expect(u.getByLabelText('Café')).toBeChecked();
    expect(u.getByText('Usar a inicial')).toBeInTheDocument();
  });

  it('salvar: envia slug, cor, ícone e token, avisa e a barra lateral muda na hora', async () => {
    definirAvatar.mockResolvedValue({
      ...LOJA,
      avatar: { cor: 'verde', icone: 'restaurante' },
    });
    const u = renderComLoja();

    escolher(u, 'Verde', 'Restaurante');
    expect(u.getAllByTitle('Avatar: Restaurante sobre Verde')).toHaveLength(1);
    await salvarAvatar(u);

    expect(definirAvatar).toHaveBeenCalledWith(
      'moda-azul',
      'verde',
      'restaurante',
      'tok'
    );
    expect(u.getByText('Avatar salvo.')).toBeInTheDocument();
    expect(u.getAllByTitle('Avatar: Restaurante sobre Verde')).toHaveLength(2);
    expect(u.getByText('Usar a inicial')).toBeInTheDocument();
  });

  it('escolha incompleta: "Salvar avatar" desativado e nenhuma requisição', async () => {
    const u = renderComLoja();

    expect(u.getByText('Salvar avatar').closest('button')).toBeDisabled();
    escolher(u, 'Verde');
    expect(u.getByText('Salvar avatar').closest('button')).toBeDisabled();
    await salvarAvatar(u);

    expect(definirAvatar).not.toHaveBeenCalled();
  });

  it('"Usar a inicial": envia os dois nulos, avisa e a barra volta à inicial', async () => {
    definirAvatar.mockResolvedValue(LOJA);
    const u = renderComLoja({
      ...LOJA,
      avatar: { cor: 'azul', icone: 'cafe' },
    });

    await act(async () => {
      fireEvent.click(u.getByText('Usar a inicial'));
    });

    expect(definirAvatar).toHaveBeenCalledWith('moda-azul', null, null, 'tok');
    expect(u.getByText('Avatar removido.')).toBeInTheDocument();
    expect(u.queryByTitle(/Avatar:/)).not.toBeInTheDocument();
    expect(u.getAllByText('M')).toHaveLength(2);
    expect(u.queryByText('Usar a inicial')).not.toBeInTheDocument();
  });

  it('422: mostra a mensagem, mantém a escolha e a barra não muda', async () => {
    definirAvatar.mockRejectedValue(
      new ApiError(422, 'Escolha uma cor e um ícone da lista.')
    );
    const u = renderComLoja();

    escolher(u, 'Verde', 'Restaurante');
    await salvarAvatar(u);

    expect(
      u.getByText('Escolha uma cor e um ícone da lista.')
    ).toBeInTheDocument();
    expect(u.getByLabelText('Verde')).toBeChecked();
    expect(u.getAllByTitle('Avatar: Restaurante sobre Verde')).toHaveLength(1);
    expect(expirar).not.toHaveBeenCalled();
  });

  it('401 (sessão inválida): expira a sessão', async () => {
    definirAvatar.mockRejectedValue(new ApiError(401, 'Sessão inválida.'));
    const u = renderComLoja();

    escolher(u, 'Roxo', 'Loja');
    await salvarAvatar(u);

    expect(expirar).toHaveBeenCalled();
  });

  it('API fora do ar: mensagem fixa e a escolha fica', async () => {
    definirAvatar.mockRejectedValue(new ApiError(0, 'Failed to fetch'));
    const u = renderComLoja();

    escolher(u, 'Cinza', 'Beleza');
    await salvarAvatar(u);

    expect(u.getByText(REDE)).toBeInTheDocument();
    expect(u.getByLabelText('Cinza')).toBeChecked();
    expect(u.getByLabelText('Beleza')).toBeChecked();
  });
});
