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
| 4 | `pedidos/templates/pedidos-app.php` | bloco JS inline: `PW_BUILD_VERSION: X.X.X` |
| 5 | `pedidos/printway-pedidos.php` | `define('PW_PERSONALIZADOS_VERSION','X.X.X')` |

A função `pw_personalizados_release_ready()` lê os primeiros 4096 bytes dos arquivos
1, 3 e 4 e exige que todos batam com o valor do arquivo 5. Se divergirem, o sistema
mostra versão vazia no painel.

---

### REGRA 2 — pedidos.min.js é espelho de pedidos.js (não é minificado!)

`pedidos.min.js` **NÃO é minificado** — é uma cópia funcional idêntica de `pedidos.js`.
Toda alteração em `pedidos.js` DEVE ser replicada em `pedidos.min.js`.
Versão, funções novas, correções de lógica — tudo nos dois arquivos.

---

### REGRA 3 — ZIP de entrega: formato FTP, sem prefixo `printway/`

O ZIP contém **apenas os arquivos alterados**, com caminhos relativos à pasta do plugin,
**SEM** o prefixo `printway/` na raiz do ZIP.

**Estrutura correta dentro do ZIP:**
```
pedidos/assets/pedidos.js
pedidos/assets/pedidos.min.js
pedidos/assets/pedidos.css
pedidos/templates/pedidos-app.php
pedidos/printway-pedidos.php
(demais módulos: dtfUV/, editor-de-imagens/, etc., se alterados)
```

**Instalação:** FTP ou cPanel File Manager → extrair em `wp-content/plugins/printway/`

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

### 2026-09-28 — v1.32.386 / printway.php 2.2.80

**O que foi feito:**
- Corrigido encoding de acentos no PDF NF-e Marketplace (`nfe-marketplace.php` linha 233):
  `mb_strlen`/`mb_substr` substituídos por `strlen`/`substr` — a string já está em
  ISO-8859-1 após `$e()` (single-byte), as funções `mb_*` corrompiam bytes como `á` (0xE1).

