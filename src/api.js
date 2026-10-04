const BASE = (process.env.REACT_APP_API_URL || '').replace(/\/+$/, '');

export class ApiError extends Error {
  constructor(status, message) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
  }
}

async function requisitar(metodo, caminho, corpo, token) {
  let resposta;
  try {
    resposta = await fetch(`${BASE}${caminho}`, {
      method: metodo,
      headers: {
        ...(corpo !== undefined ? { 'Content-Type': 'application/json' } : {}),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
      body: corpo !== undefined ? JSON.stringify(corpo) : undefined,
    });
  } catch (e) {
    throw new ApiError(0, 'Não foi possível falar com o servidor.');
  }

  if (resposta.status === 204) return {};

  let dados = null;
  try {
    dados = await resposta.json();
  } catch (e) {
    dados = null;
  }

  if (!resposta.ok) {
    throw new ApiError(
      resposta.status,
      (dados && dados.erro) || 'Erro inesperado no servidor.'
    );
  }
  if (dados === null) {
    throw new ApiError(resposta.status, 'Resposta inválida do servidor.');
  }
  return dados;
}

const slugUrl = (slug) => encodeURIComponent(slug);

export const buscarLoja = (slug) =>
  requisitar('GET', `/api/filas/${slugUrl(slug)}`);

export const entrarNaFila = (slug, telefone) =>
  requisitar('POST', `/api/filas/${slugUrl(slug)}/entradas`, { telefone });

export const consultarEntrada = (slug, codigo) =>
  requisitar(
    'GET',
    `/api/filas/${slugUrl(slug)}/entradas/${encodeURIComponent(codigo)}`
  );

export const listarFila = (slug, token) =>
  requisitar('GET', `/api/filas/${slugUrl(slug)}/entradas`, undefined, token);

export const finalizarEntrada = (slug, codigo, token) =>
  requisitar(
    'POST',
    `/api/filas/${slugUrl(slug)}/entradas/${encodeURIComponent(
      codigo
    )}/finalizar`,
    undefined,
    token
  );

export const definirEndereco = (slug, endereco, token) =>
  requisitar(
    'PUT',
    `/api/filas/${slugUrl(slug)}/endereco`,
    { endereco_publico: endereco },
    token
  );

export const cadastrar = ({ email, cnpj, nome, senha }) =>
  requisitar('POST', '/api/contas', { email, cnpj, nome, senha });

export const entrar = (email, senha) =>
  requisitar('POST', '/api/sessoes', { email, senha });

export const sair = (token) =>
  requisitar('DELETE', '/api/sessao', undefined, token);

export const obterConta = (token) =>
  requisitar('GET', '/api/conta', undefined, token);

export const trocarSenha = (token, senhaAtual, novaSenha) =>
  requisitar(
    'PUT',
    '/api/conta/senha',
    { senha_atual: senhaAtual, nova_senha: novaSenha },
    token
  );

export const pedirRedefinicao = (email) =>
  requisitar('POST', '/api/senha/esqueci', { email });

export const redefinirSenha = (token, novaSenha) =>
  requisitar('POST', '/api/senha/redefinir', {
    token,
    nova_senha: novaSenha,
  });

export const confirmarEmail = (token) =>
  requisitar('POST', '/api/email/confirmar', { token });

export const reenviarConfirmacao = (token) =>
  requisitar('POST', '/api/conta/email/reenviar', undefined, token);

export const trocarEmail = (token, email, senha) =>
  requisitar('PUT', '/api/conta/email', { email, senha }, token);
