const BASE = (process.env.REACT_APP_API_URL || '').replace(/\/+$/, '');

export class ApiError extends Error {
  constructor(status, message) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
  }
}

async function requisitar(metodo, caminho, corpo) {
  let resposta;
  try {
    resposta = await fetch(`${BASE}${caminho}`, {
      method: metodo,
      headers: corpo ? { 'Content-Type': 'application/json' } : undefined,
      body: corpo ? JSON.stringify(corpo) : undefined,
    });
  } catch (e) {
    throw new ApiError(0, 'Não foi possível falar com o servidor.');
  }

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

export const buscarLoja = (slug) => requisitar('GET', `/api/filas/${slugUrl(slug)}`);

export const entrarNaFila = (slug, telefone) =>
  requisitar('POST', `/api/filas/${slugUrl(slug)}/entradas`, { telefone });

export const consultarEntrada = (slug, codigo) =>
  requisitar(
    'GET',
    `/api/filas/${slugUrl(slug)}/entradas/${encodeURIComponent(codigo)}`
  );
