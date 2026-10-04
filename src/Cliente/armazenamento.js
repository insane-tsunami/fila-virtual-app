const chave = (slug) => `zerafilas:entrada:${slug}`;

export function guardarCodigo(slug, codigo) {
  try {
    window.localStorage.setItem(chave(slug), codigo);
  } catch (e) {
    // armazenamento bloqueado: a página segue sem lembrar o código
  }
}

export function lerCodigo(slug) {
  try {
    return window.localStorage.getItem(chave(slug)) || null;
  } catch (e) {
    return null;
  }
}

export function apagarCodigo(slug) {
  try {
    window.localStorage.removeItem(chave(slug));
  } catch (e) {
    // idem
  }
}
