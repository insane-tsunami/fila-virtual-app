import Storefront from '@material-ui/icons/Storefront';
import LocalGroceryStore from '@material-ui/icons/LocalGroceryStore';
import Restaurant from '@material-ui/icons/Restaurant';
import LocalCafe from '@material-ui/icons/LocalCafe';
import LocalPharmacy from '@material-ui/icons/LocalPharmacy';
import Spa from '@material-ui/icons/Spa';

// Avatar da loja: os mesmos nomes que a API aceita (server/app/support/Avatar.php). Os tons são
// escuros para o ícone branco ter contraste de pelo menos 3:1.
export const CORES = [
  { nome: 'roxo', rotulo: 'Roxo', hex: '#5e35b1' },
  { nome: 'azul', rotulo: 'Azul', hex: '#1565c0' },
  { nome: 'verde', rotulo: 'Verde', hex: '#2e7d32' },
  { nome: 'laranja', rotulo: 'Laranja', hex: '#e65100' },
  { nome: 'vermelho', rotulo: 'Vermelho', hex: '#c62828' },
  { nome: 'cinza', rotulo: 'Cinza', hex: '#546e7a' },
];

export const ICONES = [
  { nome: 'loja', rotulo: 'Loja', Icone: Storefront },
  { nome: 'mercado', rotulo: 'Mercado', Icone: LocalGroceryStore },
  { nome: 'restaurante', rotulo: 'Restaurante', Icone: Restaurant },
  { nome: 'cafe', rotulo: 'Café', Icone: LocalCafe },
  { nome: 'farmacia', rotulo: 'Farmácia', Icone: LocalPharmacy },
  { nome: 'beleza', rotulo: 'Beleza', Icone: Spa },
];

// A cor e o ícone para desenhar o avatar, ou null (sem avatar, ou com um nome desconhecido):
// nesse caso o dashboard mostra a inicial da loja.
export function desenhoDoAvatar(avatar) {
  if (!avatar) return null;
  const cor = CORES.find((c) => c.nome === avatar.cor);
  const icone = ICONES.find((i) => i.nome === avatar.icone);
  return cor && icone ? { cor, icone } : null;
}
