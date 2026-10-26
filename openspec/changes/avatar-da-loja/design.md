# Design

## Context

Ver proposal.md (Why). Estado atual relevante:

- A barra lateral (`src/Dashboard/BarraLateral.js`) mostra um `Avatar` do Material-UI com a inicial de `loja.nome` lida da sessão (testes em `BarraLateral.test.js`).
- Os dados da loja nas respostas são montados em três lugares com o mesmo formato (`nome`, `slug`, `endereco_publico`): `LojaService::resumo` (`GET /api/filas/{slug}` e `PUT .../endereco`), `ContaService::dados` (`GET /api/conta`) e `SessaoService::abrir` (cadastro e login).
- A tela do QR code, ao salvar o endereço, troca a loja inteira da sessão pela resposta (`atualizarLoja(dados)`). Se essa resposta não trouxer o avatar, a barra perde o avatar até o próximo login.
- `PUT /api/filas/{slug}/endereco` já é o modelo de rota da dona sobre a própria loja: middlewares `Autenticacao` e `LojaDaConta` (401, 403, 404), corpo JSON e `422` com mensagem.
- `@material-ui/icons` já está instalado e tem os 6 ícones escolhidos.
- A última migração é a `0010`. A #26 vai precisar das seguintes (`0012` em diante).

## Goals / Non-Goals

**Goals:**
- Um único formato da loja nas respostas, para o avatar nunca sumir de uma resposta e ficar em outra.
- Validação estrita no servidor (a lista do servidor manda) e um front que nunca quebra com um valor que não conhece.

**Non-Goals:**
- Guardar cores em hexadecimal ou ícones no banco (o banco guarda só os nomes da lista).
- Limite de tentativas na rota do avatar (a do endereço também não tem; a rota exige sessão e só mexe na própria loja).

## Decisions

### 1. Duas colunas na loja, sempre juntas

Migração `0011_add_avatar_to_estabelecimentos`: `estabelecimentos.avatar_cor` e `estabelecimentos.avatar_icone`, `string(20)`, nulas. As duas são gravadas juntas (ambas preenchidas ou ambas nulas), pela validação da API. Se o banco tiver só uma preenchida, a resposta trata como sem avatar (`null`).

Alternativa descartada: uma coluna só (`"azul:cafe"` ou JSON). Duas colunas são mais simples de validar e de consultar, e não pedem parse.

O avatar fica na **loja** (`estabelecimentos`), e não na conta: o nome e a inicial que o avatar substitui são da loja, e cada conta tem exatamente uma loja.

### 2. Listas fixas em `Support\Avatar` e espelhadas no front

- Servidor: `Support\Avatar` com `CORES` e `ICONES` (só os nomes) e `MENSAGEM_INVALIDA = 'Escolha uma cor e um ícone da lista.'`. A comparação é exata (`in_array(..., true)`), sem normalizar maiúsculas.
- Front: `src/Dashboard/avatar.js` mapeia cada nome para o rótulo e o desenho:

| Cor | Rótulo | Hex | | Ícone | Rótulo | Componente |
|---|---|---|---|---|---|---|
| `roxo` | Roxo | `#5e35b1` | | `loja` | Loja | `Storefront` |
| `azul` | Azul | `#1565c0` | | `mercado` | Mercado | `LocalGroceryStore` |
| `verde` | Verde | `#2e7d32` | | `restaurante` | Restaurante | `Restaurant` |
| `laranja` | Laranja | `#e65100` | | `cafe` | Café | `LocalCafe` |
| `vermelho` | Vermelho | `#c62828` | | `farmacia` | Farmácia | `LocalPharmacy` |
| `cinza` | Cinza | `#546e7a` | | `beleza` | Beleza | `Spa` |

As cores são tons escuros, para o ícone branco ter contraste de pelo menos 3:1 (o mínimo para elementos gráficos). Um teste do front confere que as listas têm os mesmos 6 nomes da spec. Se o servidor um dia aceitar um nome que o front não conhece, a barra mostra a inicial.

Alternativa descartada: o servidor devolver a lista (rota de catálogo). É mais uma rota para uma lista que muda junto com o front.

### 3. Um formato só para a loja: `Estabelecimento::paraResposta()`

Como `Conta::paraResposta()`, o modelo passa a montar `['nome', 'slug', 'endereco_publico', 'avatar']`, com `avatar` igual a `null` ou `['cor' => ..., 'icone' => ...]`. `LojaService::resumo`, `ContaService::dados` e `SessaoService::abrir` passam a usá-lo. Assim o `PUT .../endereco` também devolve o avatar, e a tela do QR code não o apaga da sessão.

Consequência: `GET /api/filas/{slug}`, que é público, também passa a trazer o avatar. Não é dado pessoal (é uma cor e um ícone de uma lista), e a página pública da fila não o mostra.

Alternativa descartada: manter a resposta pública sem avatar e fazer a tela do QR code mesclar a resposta com a loja da sessão. Seriam dois formatos de loja e o risco de esquecer o mesclar na próxima tela.

### 4. Rota `PUT /api/filas/{slug}/avatar`

`LojaService::definirAvatar(string $slug, array $corpo)`, `LojaController::definirAvatar` e a rota com `->add($daLoja)->add($autenticada)`, como a do endereço. Aceita exatamente os dois casos: as duas chaves com strings das listas, ou as duas chaves com `null`. Qualquer outro corpo é `DadosInvalidosException` com a mensagem fixa (`422`), e nada é gravado.

### 5. Front

- `api.definirAvatar(slug, cor, icone, token)` (com `null` e `null` para voltar à inicial).
- Componente `AvatarDaLoja` (em `src/Dashboard/`), usado pela barra lateral e pela pré-visualização do Perfil: recebe a loja e desenha o ícone sobre a cor, ou a inicial quando o avatar é `null` ou desconhecido.
- Perfil: seção "Avatar da loja" com dois `RadioGroup` (rótulo visível de cada cor e de cada ícone, com a amostra ao lado), "Salvar avatar" desativado enquanto falta a cor ou o ícone, e "Usar a inicial" só com avatar definido. Sucesso: `atualizarLoja(dados)`, então a barra muda na hora. Erros seguem o padrão das outras seções do Perfil: `401` expira a sessão, falha de rede mostra a mensagem fixa e as outras recusas mostram a mensagem da API.

## Risks / Trade-offs

- [Listas duplicadas no servidor e no front] → Um teste de cada lado fixa os 6 nomes; o front cai na inicial com valor desconhecido.
- [A resposta pública passa a ter um campo novo] → É aditivo; quem consome a API ignora campos que não conhece. O README documenta.
- [Sem limite de tentativas na rota] → Exige sessão e só mexe na própria loja; o pior caso é a dona trocar o próprio avatar muitas vezes.

## Migration Plan

1. Deploy com `php bin/migrate` (roda a `0011`: duas colunas nulas, nenhuma loja muda).
2. Rollback: voltar ao código anterior; ele ignora as colunas novas.
