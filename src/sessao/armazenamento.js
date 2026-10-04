const CHAVE = 'zerafilas:token';

// Reserva em memória: vale até recarregar a página, se o armazenamento de
// sessão estiver bloqueado.
let emMemoria = null;

export function lerToken() {
  try {
    const guardado = window.sessionStorage.getItem(CHAVE);
    if (guardado) return guardado;
  } catch (e) {
    // armazenamento bloqueado: usa a memória
  }
  return emMemoria;
}

export function guardarToken(token) {
  emMemoria = token;
  try {
    window.sessionStorage.setItem(CHAVE, token);
  } catch (e) {
    // idem
  }
}

export function apagarToken() {
  emMemoria = null;
  try {
    window.sessionStorage.removeItem(CHAVE);
  } catch (e) {
    // idem
  }
}
