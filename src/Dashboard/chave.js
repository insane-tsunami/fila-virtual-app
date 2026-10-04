const CHAVE = 'zerafilas:chave';

// Reserva em memória: vale até recarregar a página, se o armazenamento de
// sessão estiver bloqueado.
let emMemoria = null;

export function lerChave() {
  try {
    const guardada = window.sessionStorage.getItem(CHAVE);
    if (guardada) return guardada;
  } catch (e) {
    // armazenamento bloqueado: usa a memória
  }
  return emMemoria;
}

export function guardarChave(chave) {
  emMemoria = chave;
  try {
    window.sessionStorage.setItem(CHAVE, chave);
  } catch (e) {
    // idem
  }
}

export function apagarChave() {
  emMemoria = null;
  try {
    window.sessionStorage.removeItem(CHAVE);
  } catch (e) {
    // idem
  }
}
