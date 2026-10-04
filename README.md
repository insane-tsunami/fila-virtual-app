## MegaHack 2.0

Este projeto foi desenvolvido durante o MegaHack 2.0

Vejá uma prévia aqui: https://zerafilas.now.sh/
Dashboard: https://zerafilas.now.sh/dashboard


## Ambiente de desenvolvimento

O front usa Create React App 3.4.1 (webpack 4), que **só funciona no Node 16**. A versão está fixada no `.nvmrc` e no campo `engines` do `package.json`; em outras versões o `yarn install` recusa com `The engine "node" is incompatible`.

```bash
nvm install 16 && nvm use   # lê o .nvmrc
yarn install --frozen-lockfile
```

Se você realmente precisa usar outro Node, o `engines` também bloqueia `yarn lint`, `yarn test` e `yarn build`; contorne com `yarn --ignore-engines <comando>`. No Node 17+ o build ainda falha com `ERR_OSSL_EVP_UNSUPPORTED`, e aí é preciso `NODE_OPTIONS=--openssl-legacy-provider yarn --ignore-engines build`. O caminho suportado continua sendo o Node 16.

### Falar com a API

O front chama a API do `server/` (página pública do cliente em `/fila/<slug>` e geração do QRCode). O endereço vem de `REACT_APP_API_URL`, **gravada no pacote na hora do build** (mudou o valor, rode `yarn build` de novo). O modelo está em [`.env.example`](.env.example).

- **Desenvolvimento local:** copie `.env.example` para `.env.local`, rode a API na porta 8080 com `CORS_ORIGIN=http://localhost:3000` (veja o [`server/README.md`](server/README.md)) e `yarn start`.
- **Mesma origem:** deixe `REACT_APP_API_URL` vazia se um proxy encaminha `/api` para o PHP; assim não há CORS.
- **Produção:** use HTTPS na API; um site em HTTPS não consegue chamar uma API em HTTP.
- **Rotas do front:** a hospedagem precisa devolver o `index.html` para qualquer caminho (por exemplo `/fila/veste-bem`), senão o link do QRCode dá 404 ao abrir direto.
- **Nunca** coloque segredos em variáveis `REACT_APP_*`: tudo o que começa com esse prefixo vai para o navegador de todos.

### Conta, login e sessão

Cada estabelecimento tem a sua conta. **`/cadastro`** cria a conta e a loja juntas (e-mail, CNPJ, nome do estabelecimento e senha de 8 a 72 caracteres). O CNPJ pode ser numérico ou **alfanumérico** (formato da Receita desde julho de 2026, como `12.ABC.345/01DE-35`): o campo põe as letras em maiúsculas e o servidor confere o dígito verificador (CNPJ com dígito errado é recusado com uma mensagem na tela) e já deixa a pessoa logada; **`/login`** entra com e-mail e senha. `/dashboard`, `/dashboard/qrcode` e `/dashboard/perfil` só abrem para quem está logada (os demais vão para `/login` e voltam para a página pedida depois de entrar), e cada conta só vê e mexe na **própria** loja. A página do cliente (`/fila/<slug>`) continua pública.

- O **token de sessão** vale 7 dias e fica só no `sessionStorage`: fechar a aba o apaga, "Sair" também, e trocar a senha encerra as outras sessões. Ele **nunca** vai no pacote do front.
- Se a API recusar o token (vencido ou encerrado), o dashboard volta ao login com o aviso "Sua sessão expirou. Entre de novo.".
- O **slug da loja** sai do nome no cadastro (`Moda Azul` vira `moda-azul`) e é o que vai no link do QR code. O **endereço público** da loja se define na página "Gerar QRCode", no campo "Endereço público da loja": só a origem do site, como `https://loja.exemplo.com`. Vazio usa o endereço da própria página do dashboard, que pode não ser o público (por exemplo, `localhost`).
- A loja `veste-bem` que veio do primeiro banco **não tem dono**: a página pública dela funciona, mas ninguém a acessa pelo dashboard.
- **Limite de tentativas:** depois de 5 erros de senha no mesmo e-mail (ou 20 vindos do mesmo IP) em 15 minutos, o login é bloqueado por até 15 minutos; o cadastro aceita 5 tentativas por hora por IP e a troca de senha bloqueia a conta depois de 5 senhas atuais erradas. A tela mostra a mensagem da API ("Muitas tentativas. Tente de novo em N minutos."). Atrás de proxy, a API precisa de `TRUSTED_PROXIES` para enxergar o IP real (detalhes e números no [`server/README.md`](server/README.md)).
- **Esqueci a senha:** o login tem o link "Esqueci a senha" (`/esqueci-senha`). A pessoa informa o e-mail e a tela mostra sempre a mesma mensagem, haja conta ou não; se houver, recebe um e-mail com um link `/redefinir-senha#token=...` válido por 1 hora e de uso único. Ao salvar a nova senha, todas as sessões da conta são encerradas e a pessoa entra de novo pelo login. **O e-mail só sai se a API estiver configurada para enviar** (`MAIL_DRIVER=smtp`, `MAIL_DSN`, `MAIL_FROM` e `APP_URL`; sem isso o envio fica desligado e o pedido não manda nada). Para testar localmente use `MAIL_DRIVER=log`, que grava o link no log da API (só desenvolvimento). Veja o [`server/README.md`](server/README.md).
- **Confirmação de e-mail:** o cadastro envia um link `/confirmar-email#token=...` (vale 24 horas, uso único) para o e-mail da conta. A conta não confirmada **entra e usa o dashboard normalmente**: o dashboard mostra a faixa "Confirme seu e-mail" (com "Reenviar e-mail", "Já confirmei" e "Trocar e-mail") até confirmar. O que muda é o "esqueci a senha": o link de redefinição **só é enviado para e-mail confirmado**. Quem digitou o e-mail errado corrige em **Configurações** (informando a senha atual) enquanto ele não estiver confirmado; depois de confirmado, o e-mail não pode mais ser trocado. As contas que já existiam passam a **não confirmadas**. Como no "esqueci a senha", o e-mail só sai com a API configurada (`MAIL_DRIVER=smtp`, `MAIL_DSN`, `MAIL_FROM` e `APP_URL`); sem isso o envio fica desligado e `MAIL_DRIVER=log` serve para ver o link no log em desenvolvimento.
- Pendências conhecidas (veja o [`server/README.md`](server/README.md)): a confirmação não prova o CNPJ e não impede que alguém cadastre o e-mail de outra pessoa e a bloqueie.

### Verificações

O CI (`.github/workflows/ci.yml`) roda estes comandos a cada push e pull request para `master`:

| Comando | Node | O que verifica |
|---|---|---|
| `yarn lint` | 16 | ESLint (airbnb + Prettier) em `src/` |
| `CI=true yarn test --watchAll=false` | 16 | Testes das telas (Jest + Testing Library) |
| `yarn build` | 16 | Build de produção (no CI, warnings viram erro) |
| `openspec validate --all --strict` | 20.19+ | Specs do OpenSpec |
| `composer test` (em `server/`) | PHP 8.3 | Testes da API (PHPUnit), em SQLite e em MySQL |

O OpenSpec exige Node 20.19 ou mais novo, por isso roda separado do app. Para rodá-lo localmente, use outro terminal com Node 20.19+ e a CLI instalada (veja a seção abaixo).

## Desenvolvimento com OpenSpec

O projeto usa o [OpenSpec](https://github.com/Fission-AI/OpenSpec) para desenvolvimento orientado a especificações (spec-driven). As specs e propostas ficam em `openspec/`, e as skills e comandos do Claude Code já estão commitados em `.claude/`.

### Instalação da CLI (opcional)

As skills e os comandos `/opsx:*` funcionam sem a CLI. Para rodar `openspec list`, `openspec validate` etc. na sua máquina:

```bash
npm install -g @fission-ai/openspec
```

### Como usar no Claude Code

| Comando | O que faz |
|---|---|
| `/opsx:propose "ideia"` | Cria uma proposta de mudança (proposta, design, tarefas e specs) |
| `/opsx:explore` | Explora e discute ideias antes de propor |
| `/opsx:apply` | Implementa as tarefas de uma mudança |
| `/opsx:update` | Atualiza os artefatos de uma mudança existente |
| `/opsx:sync` | Sincroniza as specs da mudança com `openspec/specs/` |
| `/opsx:archive` | Arquiva a mudança concluída |

O contexto do projeto (stack, rotas, regras) está em `openspec/config.yaml`. Se os comandos não aparecerem, reinicie a sessão do Claude Code.

### Configuração do servidor

O backend (PHP 8.3 ou mais novo) está em `server/`. Como instalar, configurar, migrar o banco, rodar e testar está no [`server/README.md`](server/README.md).

A configuração vem de variáveis de ambiente (`DB_DRIVER`, `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `CORS_ORIGIN`, `TRUSTED_PROXIES`, `RATE_LIMIT_*`, `APP_URL` e `MAIL_*`); use `server/.env.example` como modelo e nunca commite segredos.
