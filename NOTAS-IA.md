# PrintWay — Notas para IA e Desenvolvedores

> **Como usar este arquivo:**
> Este é um documento vivo. Qualquer IA ou desenvolvedor que trabalhar neste sistema
> deve LER este arquivo antes de fazer alterações e, ao final da sessão, ATUALIZAR a
> seção "Histórico de Decisões" com o que foi feito, por que, e o que é importante
> saber. Assim a próxima sessão começa com contexto completo sem precisar reler o
> histórico de conversas.
>
> O proprietário pode enviar este arquivo para uma IA junto com as pastas do plugin
> e a IA já saberá como proceder sem precisar de longas explicações.

---

## Visão Geral do Sistema

Plugin WooCommerce `printway/` instalado em `wp-content/plugins/printway/`.
O módulo principal para o dia a dia é `pedidos/` (pedidos de personalizados DTF/UV).
Acesso: **printway.com.br/sistema** → painel de gestão.

---

## Regras Obrigatórias

### REGRA 1 — Versão: sempre subir em QUALQUER alteração

Ao fazer qualquer modificação, bumpar o número de versão em **exatamente 5 arquivos**
com o **mesmo número**:

| # | Arquivo | Onde fica |
|---|---------|-----------|
| 1 | `pedidos/assets/pedidos.js` | linha 1: `// PW_BUILD_VERSION: X.X.X` |
| 2 | `pedidos/assets/pedidos.min.js` | linha 1: `// PW_BUILD_VERSION: X.X.X` |
| 3 | `pedidos/assets/pedidos.css` | linha 1: `/* PW_BUILD_VERSION: X.X.X */` |
| 4 | `pedidos/templates/pedidos-app.php` | linha 3 do HTML comment: `PW_BUILD_VERSION: X.X.X` |
| 5 | `pedidos/printway-pedidos.php` | `define('PW_PERSONALIZADOS_VERSION','X.X.X')` |

A função `pw_personalizados_release_ready()` lê os primeiros 4096 bytes dos arquivos
1, 3 e 4 e exige que todos batam entre si. Se divergirem, o sistema mostra versão
vazia no painel e a notificação de nova versão **nunca aparece**.

#### Como funciona a notificação "Nova versão disponível"

1. Ao carregar a página, o browser recebe `SERVER.version` = versão atual do PHP.
2. A cada 15 segundos, o JS chama o AJAX `pw_personalizados_current_version`.
3. O PHP verifica `release_ready()` (todos os 3 arquivos de build com a mesma versão).
   - Se `ready = false` → retorna versão vazia → link fica oculto (deploy incompleto).
   - Se `ready = true` → retorna `PW_PERSONALIZADOS_VERSION` instalada no servidor.
4. O JS compara a versão retornada com a que estava no browser ao carregar.
   - **Igual** → sem notificação (usuário já está na última versão).
   - **Diferente** → exibe "Nova versão X.X.X — atualizar".

**Conclusão:** a notificação só aparece se o usuário tiver o sistema aberto com a versão
ANTIGA enquanto o servidor já tem a versão NOVA instalada. Após clicar em atualizar
(recarrega a página), a notificação some porque as versões voltam a coincidir.
Isso é o comportamento correto — não é bug.

---

### REGRA 2 — pedidos.min.js é espelho de pedidos.js (não é minificado!)

`pedidos.min.js` **NÃO é minificado** — é uma cópia funcional idêntica de `pedidos.js`.
Toda alteração em `pedidos.js` DEVE ser replicada em `pedidos.min.js`.
Versão, funções novas, correções de lógica — tudo nos dois arquivos.

---

### REGRA 3 — ZIP de entrega: APENAS arquivos alterados, pastas corretas, sem prefixo `printway/`

#### Regra principal — SEMPRE enviar só os arquivos alterados

**NUNCA enviar o plugin completo.** O ZIP deve conter **exclusivamente os arquivos que
foram modificados naquela sessão**, com os caminhos internos corretos.

Enviar arquivos que não mudaram é desperdício e pode sobrescrever versões mais novas
que o usuário tenha instalado por outro meio.

#### Estrutura real do plugin no servidor

O plugin fica em:
```
/domains/printway.com.br/public_html/wp-content/plugins/printway/
```

Módulos (subpastas) atuais:
```
printway/
├── pedidos/
│   ├── assets/         ← pedidos.js, pedidos.min.js, pedidos.css
│   ├── templates/      ← pedidos-app.php
│   └── printway-pedidos.php
├── dtfUV/
│   ├── assets/         ← dtf-uv.css, dtf-uv.js
│   ├── templates/      ← dtf-uv-markup.php
│   └── printway-dtf-orders.php   ← pedidos da calculadora (antes era email/)
├── editor-de-imagens/
├── mercadolivre/
├── pix-qrcode/
├── shopee/
├── printway.php        ← loader principal
└── (outros módulos...)
```

> ⚠️ A pasta `email/` foi removida. O arquivo foi movido para `dtfUV/printway-dtf-orders.php`.

#### Como criar o ZIP corretamente

O usuário conecta via FTP **direto na pasta `printway/`** como raiz.
O ZIP deve conter os arquivos **com os caminhos relativos a `printway/`**, **SEM** incluir
`printway/` como prefixo na raiz do ZIP.

**Comando padrão (a partir de `/home/user/Prestashop`):**
```bash
cd /home/user/Prestashop
zip entrega.zip arquivo1/caminho.php arquivo2/caminho.js ...
```

**Exemplo — alterando só arquivos do módulo `pedidos` (REGRA 1):**
```bash
zip entrega.zip \
  pedidos/assets/pedidos.js \
  pedidos/assets/pedidos.min.js \
  pedidos/assets/pedidos.css \
  pedidos/templates/pedidos-app.php \
  pedidos/printway-pedidos.php
```

**Exemplo — alterando um arquivo do módulo `dtfUV`:**
```bash
zip entrega.zip dtfUV/printway-dtf-orders.php
```

O ZIP resultante terá internamente apenas o que mudou:
```
pedidos/assets/pedidos.js          ✓
pedidos/assets/pedidos.min.js      ✓
...
```

Ao extrair em `plugins/printway/`, cada arquivo vai para o lugar certo e nada mais
é sobrescrito.

#### ERROS HISTÓRICOS — nunca repetir

**Erro 1 — flag `-j` (junk paths):** remove as pastas do ZIP.
```bash
zip -j entrega.zip dtfUV/templates/dtf-uv-markup.php  # ERRADO!
# ZIP contém: dtf-uv-markup.php (sem pasta)
# Vai para: plugins/printway/dtf-uv-markup.php  ✗
```

**Erro 2 — ZIP completo do plugin:** nunca zip da pasta inteira.
```bash
zip -r entrega.zip .  # ERRADO! Manda tudo, sobrescreve o que não mudou
```

**Erro 3 — prefixo errado:** ZIP criado a partir da pasta pai resulta em
`Prestashop/pedidos/...` em vez de `pedidos/...` — extrai no lugar errado.

**Correto:** sempre `cd /home/user/Prestashop` antes de zipar, sem `-j`, sem `-r`,
listando só os arquivos alterados.

#### Instalação

FTP ou cPanel File Manager → extrair em `wp-content/plugins/printway/`

**NUNCA** usar o instalador de plugins do WordPress: ele cria `printway-X.X.X/`
separado em vez de sobrescrever `printway/`, corrompendo a instalação.

---

### REGRA 4 — PHP sem BOM (UTF-8 BOM quebra AJAX silenciosamente)

**JAMAIS** salvar arquivos `.php` com UTF-8 BOM (Byte Order Mark).
Um BOM no início do PHP faz todas as respostas AJAX retornarem HTTP 200 com corpo
vazio/inválido — o erro é silencioso e muito difícil de debugar.
Salvar sempre como **UTF-8 sem BOM**.

---

### REGRA 5 — IIFE: balance de chaves é crítico

Todo `pedidos.js` é um IIFE: `(function () { ... })()`
Um par de chaves `{}` a mais ou a menos impede o script INTEIRO de executar.

**Sintoma:** app fica preso em "Verificando acesso..." sem erros no console.
**Diagnóstico:** a primeira linha do IIFE faz `app.dataset.initialized = '1'`.
Se isso não estiver setado após carregar a página, o IIFE não rodou.

---

### REGRA 6 — AJAX para admin-ajax.php: usar `ajaxPost()`

Não chamar `fetch()` diretamente. Usar:
```javascript
await ajaxPost({ action: 'pw_minha_acao', campo: valor });
```
A função `ajaxPost()` adiciona o nonce (`SERVER.nonce`) automaticamente,
serializa objetos aninhados como `param[subkey]=value` (PHP recebe como array
associativo) e lança `Error` se a resposta não for `{ success: true }`.

---

### REGRA 7 — Diálogos de confirmação: usar `promptConfirm()`

Não usar `window.confirm()` nem criar modais avulsos. Usar:
```javascript
promptConfirm(message, confirmText, cancelText, onConfirm, onCancel);
```

**Por que inline styles e não classes CSS:**
O overlay é inserido em `document.body`, fora de `#pw-personalizados-app`.
O container tem CSS `transform`, criando novo stacking context — `position:fixed`
de um filho se posicionaria relativo ao container, não ao viewport. Por isso o
overlay vai para `document.body` com **estilos inline**. CSS escopado com
`#pw-personalizados-app .pw-confirm-overlay` não se aplicaria ao elemento fora
desse container.

A função já possui guard contra múltiplos diálogos simultâneos.

---

### REGRA 8 — Comentários no código: manter sempre

Preservar comentários explicativos, especialmente:
- **Por que** uma lógica existe (não só o que ela faz)
- Workarounds e restrições não óbvias
- Dependências entre funções

Futuras sessões de IA dependem desses comentários para entender o contexto.

---

## Estrutura do Módulo `pedidos/`

```
pedidos/
├── printway-pedidos.php          ← PHP principal; define PW_PERSONALIZADOS_VERSION
├── assets/
│   ├── pedidos.js                ← JS principal; define PW_BUILD_VERSION
│   ├── pedidos.min.js            ← Espelho de pedidos.js (deve ser idêntico)
│   ├── pedidos.css               ← Estilos; define PW_BUILD_VERSION
│   └── pedidos.min.css           ← Cópia do CSS (NÃO verificada pela release_ready)
├── templates/
│   └── pedidos-app.php           ← Template HTML; define PW_BUILD_VERSION inline
├── includes/                     ← Helpers PHP (melhorenvio, frenet, arts-pdf, etc.)
└── vendor/                       ← Bibliotecas externas (fpdf, fpdi)
```

---

## Funções Utilitárias Importantes (`pedidos.js`)

| Função | O que faz |
|--------|-----------|
| `ajaxPost(data)` | POST para admin-ajax.php com nonce automático |
| `promptConfirm(msg, ok, cancel, onOk, onCancel)` | Dialog de confirmação com inline styles |
| `orderUsesMonthlyClosing(order)` | true se cliente tem `monthlyClosing === true` e `closingDay` válido (1–31) |
| `orderHasNoPendingBalance(order)` | true se `adjustedOrderTotal(order) - order.paid <= 0.005` |
| `isOrderFinalized(order)` | true se `order.finalized === true` OU status "Entregue" sem saldo pendente |
| `adjustedOrderTotal(order)` | Total do pedido com ajustes aplicados |
| `money.format(value)` | Formata valor como moeda brasileira |
| `escapeHtml(str)` | Escapa HTML para prevenir XSS em textos dinâmicos |

---

## Histórico de Decisões

> **Instruções:** ao final de cada sessão de alterações, adicione uma entrada aqui
> com data, versão entregue, o que foi feito e qualquer decisão técnica relevante.
> Use o formato abaixo.

---

### 2026-09-25 — v1.32.343

**O que foi feito:**
- Adicionado bloco de documentação em `pedidos.js` e `pedidos.min.js`
- Criado este arquivo `NOTAS-IA.md` como documento vivo do projeto
- Adicionado comentário em `printway-pedidos.php` apontando para este arquivo

**Versões anteriores desta sessão:**
- v1.32.338 — Corrigido "Erro ao carregar configuração." no painel de Tokens:
  `ajaxPost()` era chamada mas nunca definida. Adicionada a função antes do
  `// Tokens panel`. Também corrigido `loadTokensStatus()` que foi acidentalmente
  removido durante a inserção.
- v1.32.339 — `printArtsConference()`: filtro corrigido para exibir apenas pedidos
  com situação ≤ "Em produção" E finalização "Em aberto" (mudança de `||` para `&&`
  com `!isOrderFinalized(order)`).
- v1.32.340 — Adicionada função `promptConfirm()`.
- v1.32.341 — Ao mudar situação para "Entregue" com saldo pendente (não-mensalista):
  pergunta se deseja registrar pagamento antes. Ao abrir modal de pagamento de cliente
  mensalista: avisa que ele paga no fechamento mensal.
- v1.32.342 — Corrigido `promptConfirm()`: overlay inserido em `document.body` com
  estilos inline (não CSS classes) para funcionar fora do `#pw-personalizados-app`
  que tem `transform` CSS criando novo stacking context. Guard adicionado contra
  múltiplos diálogos simultâneos.

**Decisões técnicas:**
- `promptConfirm()` usa inline styles porque `#pw-personalizados-app` tem `transform`
  CSS → novo stacking context → `position:fixed` de filhos posiciona relativo ao
  container, não ao viewport. Solução: mover para `document.body`.
- `pedidos.min.js` não é minificado — é cópia funcional de `pedidos.js`.
  Manter os dois arquivos sempre sincronizados.

---

### 2026-09-29 — dtfUV: cards Step 2 corrigidos

**O que foi feito:**
- Cards do Step 2 da calculadora DTF UV (`dtfUV/templates/dtf-uv-markup.php`) convertidos
  de `<button>` para `<div role="button" tabindex="0">` para escapar do CSS do tema WordPress
  que força `button { width: 100% !important }`.
- Adicionados inline styles diretamente nos elementos (`width:180px;max-width:180px;
  flex-shrink:0;flex-grow:0`) como camada à prova de falha — inline style não pode ser
  sobrescrito por CSS externo.
- Adicionado bloco `<style>` no PHP com `!important` como segunda camada de proteção.
- CSS atualizado em `dtfUV/assets/dtf-uv.css` com `!important` nas regras de largura.
- Ícone de calculadora no card "Calculadora de medidas"; ícone de PDF no "Já tenho o PDF".
- Responsivo: ≤460px os cards empilham em 100% da largura.

**Decisões técnicas:**
- O tema PrintWay aplica `button { width: 100% !important; background: brown; color: white }`
  globalmente. Seletor ID `#choose-pdf { width: 180px !important }` vencia na teoria
  (ID > element), mas a solução definitiva foi trocar para `<div>` — temas nunca estilizam
  divs genéricos dessa forma.
- Inline styles nos divs são a camada final: nenhuma regra de stylesheet externa sobrescreve
  um inline style (só outro inline style com `!important`, que nenhum tema aplica em IDs).
- Sessões anteriores quebraram porque os ZIPs foram gerados com `-j` (junk paths),
  jogando os arquivos na raiz do plugin em vez das subpastas corretas. **Ver REGRA 3.**

**Arquivos alterados:**
- `dtfUV/templates/dtf-uv-markup.php`
- `dtfUV/assets/dtf-uv.css`

---

### 2026-09-29 — email v2.4.21 / dtfUV fluxo de pagamento MP PIX

**O que foi feito:**
- Removido botão "Pagar depois" do step 3 da calculadora DTF UV.
- Botão "Pagar agora (Pix - QR Code)" renomeado para "Pagar com Pix (QR Code)".
- Adicionado botão "Finalizar sem pagar" (visível apenas para usuários logados).
- Mensagem de espera atualizada: "Aguardando confirmação do pagamento PIX... — Após o pagamento seu pedido será finalizado automaticamente..."
- Step 3 agora é o passo final: ao clicar "Pagar com Pix (QR Code)" e o QR ser gerado,
  o sistema já cria o pedido imediatamente via `createDtfOrder()` em `dtf-uv.js`.
- Banner "Pedido criado com sucesso!" exibido após criação com link para Minha Conta.
- Novos métodos de pagamento em `pw_dtf_send_order()`: `mp_pix` e `finalizar_sem_pagar`.
- Para `mp_pix`/`finalizar_sem_pagar`: sem exigência de comprovante; ordem persiste mesmo se email falhar.
- Novas funções auxiliares `pw_dtf_payment_label()` e `pw_dtf_payment_option_label()`.
- Metas salvas no post `pw_dtf_order`: `_pw_dtf_mp_payment_id`, `_pw_dtf_payment_status`, `_pw_dtf_payment_history`.
- Resposta AJAX agora inclui `order_id` além de `order_reference`.
- Novo AJAX `pw_dtf_register_mp_payment`: atualiza status para 'paid' e registra em `_pw_dtf_payment_history`.
- Novo AJAX `pw_dtf_create_pix_for_order`: gera QR MP PIX a partir do pedido já criado.
- Nova função PHP `pw_dtf_mp_create_pix_payment()`: helper reutilizável para criar pagamento MP PIX.
- `pw_dtf_render_account_orders()` atualizada: nova coluna "Status Pagamento", coluna "Ação" com botão
  "Gerar QR Code para pagamento" para ordens não pagas, modal com QR + polling + registro automático.
- `dtf_orders_url` exposto em `PW_SERVER_DATA` para link direto na área do cliente.
- Checkbox "Enviar cópia para meu email" funciona com os novos métodos (`mp_pix`, `finalizar_sem_pagar`).

**Decisões técnicas:**
- `mp_pix` usa a mesma sessão de pagamento que `pix` (criada por `pw_dtf_prepare_payment`);
  a validação aceita `payment_method = 'pix'` na sessão quando `$payment === 'mp_pix'`.
- Para `finalizar_sem_pagar`: sem sessão de pagamento, status `aguardando`; ordem criada imediatamente.
- Email de falha NÃO cancela a ordem para `mp_pix`/`finalizar_sem_pagar` (QR já foi mostrado ao cliente).
- Polling de 4s na área do cliente usa `pw_dtf_mp_check_pix` (mesmo endpoint do step 3).
- Modal QR na área do cliente não requer reload — registra pagamento e recarrega a página após 3s.

**Arquivos alterados:**
- `dtfUV/templates/dtf-uv-markup.php`
- `dtfUV/assets/dtf-uv.js`
- `email/printway-dtf-email.php` (versão → 2.4.21)

---

### 2026-09-29 — v1.32.398

**O que foi feito:**
- Sync bidirecional WP role ↔ pedidos `clientType`: alterar função WP atualiza campo `Tipo de cliente` no cadastro do pedido e vice-versa. Hook `set_user_role` no WP dispara AJAX para atualizar pedido; ao salvar pedido com clientType alterado, atualiza role WP correspondente.
- Aviso inline na calculadora DTF UV: ao clicar "Avançar" estando em qualquer aba da calculadora (sem ter selecionado PDF), exibe div amarela com botão "Selecionar PDF agora" em vez de `window.confirm`. Botão clica no card "Já tenho o PDF" e esconde o aviso.
- Integração Mercado Pago PIX registrado (calculadora DTF UV passo 3):
  - Card Mercado Pago em Configurações → Tokens com campo `access_token`, salvo em `pw_printway_mp_settings`.
  - Flag `mp_pix_enabled` no `PW_SERVER_DATA` (verdadeiro quando token configurado e válido).
  - AJAX `pw_dtf_mp_create_pix`: cria pagamento PIX na API MP (`/v1/payments`), retorna `payment_id`, `qr_code`, `qr_code_base64`.
  - AJAX `pw_dtf_mp_check_pix`: consulta status do pagamento (`/v1/payments/{id}`).
  - Calculadora: quando MP ativo, "Gerar QR" cria pagamento registrado, exibe QR base64, faz polling a cada 4s. Ao detectar `approved`, confirma automaticamente (`PROOF_VALIDATED = true`) sem upload manual. PIX estático permanece como fallback quando MP não configurado.
  - Validação do token no card faz chamada real à API MP (`/v1/payment_methods`) — coração bate apenas com token válido.

**Decisões técnicas:**
- `mp_pix_enabled` calculado no PHP ao publicar `PW_SERVER_DATA`, evitando AJAX extra só para checar.
- Polling usa `setInterval` de 4s, limpo em `resetPaymentState()` e ao iniciar novo QR, para evitar timers obsoletos.
- `mpPixSeq` incrementado a cada reset — callbacks de polling de rodadas anteriores descartam resultado ao comparar seq.
- Validação do token MP usa endpoint `/v1/payment_methods` (leitura simples, sem criar recurso).

**Arquivos alterados:**
- `pedidos/printway-pedidos.php` (REGRA 1 — versão)
- `pedidos/templates/pedidos-app.php` (REGRA 1 — versão)
- `pedidos/assets/pedidos.js` (REGRA 1 — versão)
- `pedidos/assets/pedidos.min.js` (REGRA 1 — versão)
- `pedidos/assets/pedidos.css` (REGRA 1 — versão)
- `dtfUV/assets/dtf-uv.js`
- `dtfUV/templates/dtf-uv-markup.php`
- `email/printway-dtf-email.php`

---

### 2026-09-28 — v1.32.386 / printway.php 2.2.80

**O que foi feito:**
- Corrigido encoding de acentos no PDF NF-e Marketplace (`nfe-marketplace.php` linha 233):
  `mb_strlen`/`mb_substr` substituídos por `strlen`/`substr` — a string já está em
  ISO-8859-1 após `$e()` (single-byte), as funções `mb_*` corrompiam bytes como `á` (0xE1).
- Mensagens de salvamento unificadas em checklist visual: em vez de duas mensagens
  separadas, `showMessage()` agora exibe uma caixa única com `○ Aguardando confirmação
  do servidor…` / `○ [texto]` e, ao confirmar, atualiza para `✓ Confirmado pelo
  servidor` / `✓ [texto]`. Implementado via `_renderChecklist()` em `pedidos.js/min.js`
  + classe `.pw-msg-step` em `pedidos.css/min.css`.

---

### 2026-09-29 — v1.32.404 / v1.32.405

**O que foi feito (v1.32.404):**
- Remoção da barra de administração do WordPress para todos os usuários logados (`add_filter('show_admin_bar', '__return_false')` em `printway.php`).
- Botão de teste no WhatsApp (Configurações): campo "Número de teste" + botão "Enviar teste" ao lado do campo "Número oficial para atendimento". Envia a mensagem da aba selecionada em "Mensagem por situação" para o número informado.
- Único botão de salvar em Configurações: removidos botões internos das abas; apenas o botão externo `#pw-save-settings`. Verde quando sem alterações pendentes (`pw-cfg-save-clean`), vermelho pulsante quando há alterações (`pw-cfg-save-dirty`). Inclui ícone de salvamento. Funções `markCfgDirty()` / `markCfgClean()`.
- Corrido bug em `pw_personalizados_import_dtf_order()`: campos `name` e `createdAt` não eram exportados ao nível correto para `pw_personalizados_record_columns()`, causando pedidos DTF UV sem nome e sem data no módulo de pedidos. Corrigido adicionando `'name' => $client_name` e `'createdAt' => $now_sql` ao array `$order`.
- Numeração sequencial de pedidos DTF UV: `pw_dtf_generate_order_reference()` agora chama `pw_personalizados_reserve_order_number()` primeiro (número de 5 dígitos), fallback para formato antigo apenas em erro.
- PDF do cliente anexado ao pedido DTF UV na importação (`art_entry` construído a partir de `pdf_attachment_id`/`pdf_attachment_url`).

**O que foi feito (v1.32.405):**
- Restauração da última view/aba ao recarregar a página: `showSystemView()` salva a view em `sessionStorage`; `activateSettingsTab()` salva a aba de configurações. No startup, lê `sessionStorage` e navega para a última posição em vez de sempre ir para o dashboard.

**Decisões técnicas:**
- `sessionStorage` escolhido sobre `localStorage` — persiste durante a sessão (recarregamentos incluindo o botão "Atualizar para a nova versão"), mas limpa ao fechar o navegador/aba. Ideal para "voltar onde estava".
- Todas as chamadas `sessionStorage` envolvidas em `try/catch` para não quebrar em contextos de privacidade (Safari private mode, etc.).
- ZIP de entrega contém SOMENTE os arquivos alterados, com caminhos relativos à raiz do plugin (`pedidos/...`), sem prefixo. Extrair em `wp-content/plugins/printway/`.

**Arquivos alterados (v1.32.404):**
- `printway.php`
- `printway-pedidos.php` (raiz)
- `pedidos/printway-pedidos.php`
- `pedidos/assets/pedidos.js` + `pedidos.min.js`
- `pedidos/assets/pedidos.css`
- `pedidos/templates/pedidos-app.php`
- `email/printway-dtf-email.php`

**Arquivos alterados (v1.32.405):**
- `pedidos/assets/pedidos.js` + `pedidos.min.js`
- `pedidos/assets/pedidos.css`
- `pedidos/templates/pedidos-app.php`
- `pedidos/printway-pedidos.php`

