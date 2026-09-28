<!--
  PRINTWAY - PEDIDOS DE PERSONALIZADOS
  PW_BUILD_VERSION: 1.32.386
  Bloco isolado para inserir em um widget HTML do WordPress/Elementor.
  Não contém nem altera cabeçalho, rodapé, body ou estilos globais da página.
-->



<div id="pw-personalizados-app">
  <div class="pw-message" id="pw-message" role="status" aria-live="polite"><span id="pw-message-text"></span><b id="pw-message-countdown" class="pw-message-countdown"></b></div>

  <div class="pw-access-panel pw-visible" id="pw-access-panel" role="status" aria-live="polite">
    <h3 id="pw-access-title">Verificando acesso...</h3>
    <p id="pw-access-text">Aguarde enquanto identificamos o usuário conectado ao WordPress.</p>
    <div class="pw-startup-progress" id="pw-startup-progress" aria-label="Progresso de abertura do sistema">
      <div class="pw-startup-cards" id="pw-startup-cards">
        <div class="pw-startup-card">
          <span class="pw-startup-card-icon">⛅</span>
          <strong>Previsão do tempo</strong>
          <div class="pw-startup-weather" id="pw-startup-weather"></div>
        </div>
        <div class="pw-startup-card">
          <span class="pw-startup-card-icon">📦</span>
          <strong id="pw-startup-orders-title">Pedidos para hoje</strong>
          <div id="pw-startup-orders-today">Calculando…</div>
        </div>
        <div class="pw-startup-card">
          <span class="pw-startup-card-icon">🎉</span>
          <strong>Próximas melhores datas</strong>
          <div id="pw-startup-holiday" class="pw-startup-holidays" aria-live="polite">Consultando…</div>
        </div>
      </div>
      <div class="pw-startup-progress-head"><strong>Preparando o sistema</strong><span id="pw-startup-progress-percent"></span></div>
      <div class="pw-startup-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><i id="pw-startup-progress-fill"></i></div>
      <small id="pw-startup-progress-detail" class="pw-startup-tip-detail">Calculando a estimativa de abertura...</small>
    </div>
  </div>

  <div class="pw-shell pw-auth-pending" id="pw-personalizados-shell">
    <header class="pw-titlebar">
      <div>
        <h2><span class="pw-brand-logo pw-tooltip" id="pw-brand-logo" data-tooltip="Usuário conectado: —"><img src="<?php echo esc_url( PW_PERSONALIZADOS_URL . 'assets/antasys-logo-v2.png?v=' . PW_PERSONALIZADOS_VERSION ); ?>" alt="AntaSys" width="761" height="194"></span><small>versão <?php echo esc_html( PW_PERSONALIZADOS_VERSION ); ?></small><a id="pw-version-update" href="#" hidden>Nova versão disponível — atualizar</a></h2>
      </div>
      <div class="pw-weather-strip" id="pw-weather-strip"></div>
      <div class="pw-weather-strip-dates" id="pw-weather-strip-dates"></div>
      <strong id="pw-order-number-view" hidden>—</strong>
    </header>

    <div class="pw-user-session" id="pw-user-session"></div>

    <nav class="pw-system-nav" aria-label="Menu do sistema de pedidos">
      <button class="pw-nav-button pw-nav-icon-only pw-tooltip pw-active" type="button" data-system-view="dashboard" data-tooltip="Painel" aria-label="Painel"><span class="pw-nav-icon">⌂</span></button>
      <div class="pw-menu-group">
        <button class="pw-menu-trigger" type="button" aria-expanded="false">Cadastro</button>
        <div class="pw-menu-dropdown">
          <button class="pw-nav-button pw-active" type="button" data-system-view="order" data-new-order-entry="1"><span class="pw-nav-icon">▤</span>Pedido</button>
          <button class="pw-nav-button" id="pw-menu-new-client" type="button"><span class="pw-nav-icon">♙</span>Cliente</button>
          <div class="pw-submenu-group">
            <button class="pw-nav-button pw-submenu-trigger" type="button" aria-expanded="false"><span class="pw-nav-icon">▦</span>Estoque</button>
            <div class="pw-submenu-dropdown">
              <button class="pw-nav-button" id="pw-menu-new-product" type="button"><span class="pw-nav-icon">▣</span>Produto</button>
              <button class="pw-nav-button" type="button" data-registry-open="category"><span class="pw-nav-icon">⌑</span>Categoria</button>
              <button class="pw-nav-button" type="button" data-registry-open="unit"><span class="pw-nav-icon">↔</span>Unidade de venda</button>
              <button class="pw-nav-button" type="button" data-registry-open="orderOrigin"><span class="pw-nav-icon">◈</span>Origem do pedido</button>
            </div>
          </div>
          <button class="pw-nav-button" type="button" data-registry-open="paymentMethod"><span class="pw-nav-icon">$</span>Forma de pagamento</button>
          <button class="pw-nav-button" type="button" data-registry-open="supplier"><span class="pw-nav-icon">♜</span>Fornecedor</button>
          <button class="pw-nav-button pw-admin-only" type="button" data-system-view="nfe"><span class="pw-nav-icon">▤</span>NF-e</button>
          <div class="pw-submenu-group pw-admin-only">
            <button class="pw-nav-button pw-submenu-trigger" type="button" aria-expanded="false"><span class="pw-nav-icon">◉</span>Despesas</button>
            <div class="pw-submenu-dropdown">
              <button class="pw-nav-button" type="button" data-system-view="dtf-costs"><span class="pw-nav-icon">＋</span>Novo</button>
              <hr class="pw-menu-divider" aria-label="Separação dos cadastros auxiliares">
              <button class="pw-nav-button" type="button" data-registry-open="dtfCategory"><span class="pw-nav-icon">≡</span>Categoria</button>
              <button class="pw-nav-button" type="button" data-registry-open="dtfItem"><span class="pw-nav-icon">▦</span>Insumo, peça ou serviço</button>
              <button class="pw-nav-button" type="button" data-registry-open="dtfMovement"><span class="pw-nav-icon">⇄</span>Movimentação</button>
            </div>
          </div>
        </div>
      </div>
      <div class="pw-menu-group">
        <button class="pw-menu-trigger" type="button" aria-expanded="false">Relatórios</button>
        <div class="pw-menu-dropdown">
          <button class="pw-nav-button" type="button" data-system-view="orders"><span class="pw-nav-icon">⌕</span>Pedidos</button>
          <button class="pw-nav-button" type="button" data-system-view="clients"><span class="pw-nav-icon">♙</span>Clientes</button>
          <button class="pw-nav-button" type="button" data-system-view="monthly-closing"><span class="pw-nav-icon">$</span>Fechamento mensal</button>
          <button class="pw-nav-button" type="button" data-system-view="arts"><span class="pw-nav-icon">▧</span>Artes</button>
          <div class="pw-submenu-group">
            <button class="pw-nav-button pw-submenu-trigger" type="button" aria-expanded="false"><span class="pw-nav-icon">▦</span>Estoque</button>
            <div class="pw-submenu-dropdown">
              <button class="pw-nav-button" type="button" data-system-view="products"><span class="pw-nav-icon">▣</span>Produtos</button>
              <button class="pw-nav-button" type="button" data-system-view="categories"><span class="pw-nav-icon">⌑</span>Categorias</button>
              <button class="pw-nav-button" type="button" data-system-view="units"><span class="pw-nav-icon">↔</span>Unidades de venda</button>
              <button class="pw-nav-button" type="button" data-system-view="order-origins"><span class="pw-nav-icon">◈</span>Origem do pedido</button>
            </div>
          </div>
          <button class="pw-nav-button" type="button" data-system-view="payment-methods"><span class="pw-nav-icon">$</span>Formas de pagamento</button>
          <button class="pw-nav-button" type="button" data-system-view="suppliers"><span class="pw-nav-icon">♜</span>Fornecedores</button>
          <div class="pw-submenu-group pw-admin-only">
            <button class="pw-nav-button pw-submenu-trigger" type="button" aria-expanded="false"><span class="pw-nav-icon">◉</span>Despesas</button>
            <div class="pw-submenu-dropdown">
              <button class="pw-nav-button" type="button" data-system-view="dtf-list"><span class="pw-nav-icon">☷</span>Listagem</button>
              <button class="pw-nav-button" type="button" data-system-view="dtf-movement-report"><span class="pw-nav-icon">⇄</span>Movimentação</button>
              <button class="pw-nav-button" type="button" data-system-view="dtf-cost-report"><span class="pw-nav-icon">▤</span>Análise de despesas</button>
              <hr class="pw-menu-divider" aria-label="Separação dos cadastros">
              <button class="pw-nav-button" type="button" data-system-view="dtf-category-report"><span class="pw-nav-icon">≡</span>Categoria</button>
              <button class="pw-nav-button" type="button" data-system-view="dtf-items"><span class="pw-nav-icon">▦</span>Insumo, peça ou serviço</button>
            </div>
          </div>
          <div class="pw-submenu-group">
            <button class="pw-nav-button pw-submenu-trigger" type="button" aria-expanded="false"><span class="pw-nav-icon">⌖</span>Envios</button>
            <div class="pw-submenu-dropdown">
              <button class="pw-nav-button" type="button" data-system-view="shipping-label"><span class="pw-nav-icon">🏷</span>Gerar etiquetas</button>
              <button class="pw-nav-button" type="button" data-system-view="shipping-history"><span class="pw-nav-icon">📦</span>Etiquetas geradas</button>
            </div>
          </div>
          <div class="pw-submenu-group">
            <button class="pw-nav-button pw-submenu-trigger" type="button" aria-expanded="false"><span class="pw-nav-icon">🛒</span>Marketplace</button>
            <div class="pw-submenu-dropdown">
              <button class="pw-nav-button" type="button" data-system-view="marketplace-ml"><span class="pw-nav-icon">🛒</span>Mercado Livre</button>
              <button class="pw-nav-button" type="button" data-system-view="marketplace-shopee"><span class="pw-nav-icon">🛍</span>Shopee</button>
            </div>
          </div>
          <button class="pw-nav-button" type="button" data-system-view="nfe-pending"><span class="pw-nav-icon">📄</span>NF-e</button>
          <button class="pw-nav-button" type="button" data-system-view="dtf-simulator"><span class="pw-nav-icon">📐</span>Simulador DTF UV</button>
        </div>
      </div>
      <div class="pw-menu-group">
        <button class="pw-menu-trigger" type="button" aria-expanded="false">Gráficos</button>
        <div class="pw-menu-dropdown">
          <button class="pw-nav-button" type="button" data-system-view="analytics" data-analytics-focus="overview"><span class="pw-nav-icon">◫</span>Visão geral</button>
          <button class="pw-nav-button" type="button" data-system-view="analytics" data-analytics-focus="sales"><span class="pw-nav-icon">↗</span>Comercial e origens</button>
          <button class="pw-nav-button" type="button" data-system-view="analytics" data-analytics-focus="finance"><span class="pw-nav-icon">$</span>Financeiro</button>
          <button class="pw-nav-button" type="button" data-system-view="analytics" data-analytics-focus="expenses"><span class="pw-nav-icon">◉</span>Despesas e rentabilidade</button>
          <button class="pw-nav-button" type="button" data-system-view="analytics" data-analytics-focus="operation"><span class="pw-nav-icon">◷</span>Entregas e operação</button>
        </div>
      </div>
      <div class="pw-nav-admin-actions" aria-label="Ferramentas do sistema">
        <button class="pw-nav-button pw-nav-icon-only pw-tooltip" id="pw-order-notify-button" type="button" data-tooltip="Novo pedido — clique para ver" aria-label="Novo pedido" hidden><span class="pw-nav-icon">📦</span></button>
        <button class="pw-nav-button pw-nav-icon-only pw-tooltip pw-admin-only" id="pw-shopee-notify-button" type="button" data-system-view="marketplace-shopee" data-tooltip="Shopee: há novidade — clique para ver" aria-label="Shopee: há novidade" hidden><span class="pw-shopee-notify-icon">🛍</span></button>
        <button class="pw-nav-button pw-nav-icon-only pw-tooltip pw-admin-only" id="pw-ml-notify-button" type="button" data-system-view="marketplace-ml" data-tooltip="Mercado Livre: há novidade — clique para ver" aria-label="Mercado Livre: há novidade" hidden><img class="pw-ml-notify-logo" src="https://http2.mlstatic.com/frontend-assets/ml-web-navigation/ui-navigation/5.21.22/mercadolibre/favicon.svg" alt="Mercado Livre" loading="lazy"></button>
        <button class="pw-nav-button pw-nav-icon-only pw-tooltip" id="pw-nfe-pending-notify-button" type="button" data-system-view="nfe-pending" data-tooltip="NF-e: pedidos aguardando XML — clique para ver" aria-label="NF-e: pedidos aguardando XML" hidden><span class="pw-nav-icon">📄</span></button>
        <button class="pw-nav-button pw-nav-icon-only pw-tooltip" id="pw-nfe-ready-notify-button" type="button" data-system-view="nfe-pending" data-tooltip="NF-e: XML pronto para download — clique para ver" aria-label="NF-e: XML pronto para download" hidden><span class="pw-nav-icon">📥</span></button>
        <button class="pw-nav-button pw-nav-icon-only pw-tooltip pw-theme-button" id="pw-theme-button" type="button" data-tooltip="Aparência do sistema" aria-label="Escolher tema"><span class="pw-nav-icon">◐</span></button>
        <button class="pw-nav-button pw-nav-icon-only pw-tooltip" type="button" data-system-view="trash" data-tooltip="Lixeira" aria-label="Lixeira"><span class="pw-nav-icon">🗑️</span><span class="pw-nav-icon-count" id="pw-trash-count"></span></button>
        <button class="pw-nav-button pw-nav-icon-only pw-tooltip" type="button" data-system-view="preferences" data-tooltip="Minhas preferências" aria-label="Minhas preferências">🧭</button>
        <button class="pw-nav-button pw-nav-icon-only pw-tooltip pw-admin-only" type="button" data-system-view="settings" data-tooltip="Configurações — somente administradores" aria-label="Configurações"><span class="pw-nav-icon">⚙</span></button>
      </div>
      <span class="pw-nav-sep" aria-hidden="true"></span>
      <button class="pw-nav-button pw-nav-icon-only pw-tooltip" id="pw-fullscreen-button" type="button" data-tooltip="Tela cheia" aria-label="Alternar tela cheia"><span class="pw-nav-icon" id="pw-fullscreen-icon">⛶</span></button>
    </nav>

    <div class="pw-quick-access-bar" id="pw-quick-access-bar" hidden></div>

    <div class="pw-view-context" id="pw-view-context" role="status" aria-live="polite">
      <span class="pw-view-context-icon" id="pw-view-context-icon" aria-hidden="true">⌂</span>
      <div class="pw-view-context-copy">
        <small id="pw-view-context-group">Painel</small>
        <h2 id="pw-view-context-title">Painel de controle</h2>
        <p id="pw-view-context-description">Visão geral dos pedidos, prazos e indicadores do sistema.</p>
      </div>
      <label class="pw-live-dashboard-toggle pw-live-dashboard-header" id="pw-dashboard-live-control" title="Mantém os indicadores atualizados com dados cadastrados em outros computadores"><input id="pw-dashboard-live" type="checkbox"><i aria-hidden="true"></i><span><strong>Monitor ao vivo</strong><small>Atualização automática</small></span></label>
    </div>

    <div class="pw-editing-note" id="pw-editing-note"></div>

    <section class="pw-system-view pw-active pw-consultation" data-view="dashboard">
      <div class="pw-dashboard-toolbar" role="toolbar" aria-label="Ferramentas do painel"><button class="pw-btn pw-dashboard-tool-button" id="pw-dashboard-toggle-filters" type="button" aria-expanded="false" aria-controls="pw-dashboard-filters">▾ Mostrar filtros</button><button class="pw-btn pw-dashboard-tool-button pw-admin-only" id="pw-dashboard-view-mode" type="button" title="Alternar entre as visões Normal, Administrador e Colaborador">👁 Atual: Normal</button></div>
      <div class="pw-dashboard-filters" id="pw-dashboard-filters" aria-label="Filtros gerais do painel" hidden>
        <div><label for="pw-dashboard-filter-start">Data inicial</label><input id="pw-dashboard-filter-start" type="date"></div>
        <div><label for="pw-dashboard-filter-end">Data final</label><input id="pw-dashboard-filter-end" type="date"></div>
        <div><label for="pw-dashboard-filter-status">Situação</label><select id="pw-dashboard-filter-status"><option value="">Todas</option></select></div>
        <div><label for="pw-dashboard-filter-origin">Origem do pedido</label><select id="pw-dashboard-filter-origin"><option value="">Todas</option></select></div>
        <div><label for="pw-dashboard-filter-client">Cliente</label><select id="pw-dashboard-filter-client"><option value="">Todos</option></select></div>
        <div><label for="pw-dashboard-filter-responsible">Cadastrado por</label><select id="pw-dashboard-filter-responsible"><option value="">Todos os usuários</option></select></div>
        <div><label for="pw-dashboard-filter-category">Categoria</label><select id="pw-dashboard-filter-category"><option value="">Todas</option></select></div>
        <div class="pw-dashboard-filter-actions"><button class="pw-btn pw-btn-primary" id="pw-dashboard-apply-filters" type="button">Aplicar</button><button class="pw-btn pw-btn-soft" id="pw-dashboard-clear-filters" type="button">Limpar</button></div>
        <div class="pw-dashboard-filter-summary" id="pw-dashboard-filter-summary"></div>
      </div>
      <div class="pw-dashboard-grid" id="pw-dashboard-cards"></div>
      <div class="pw-grid pw-dashboard-status-row" style="margin-top:16px">
        <div class="pw-section pw-col-7" data-dashboard-card-key="block-status"><div class="pw-section-title"><div><h3>Pedidos por situação</h3><p>Etapas em andamento. Os pedidos entregues aparecem em um resumo separado por pagamento.</p></div></div><div id="pw-dashboard-status"></div></div>
        <div class="pw-section pw-col-5" data-dashboard-card-key="block-deadlines"><div class="pw-section-title"><div><h3>Alertas importantes</h3><p>Somente itens que exigem atenção: produção, recebimentos, gastos e inconsistências.</p></div></div><div id="pw-dashboard-deadlines"></div></div>
      </div>
      <div class="pw-dashboard-block" data-dashboard-card-group>
        <h3>Desempenho dos pedidos</h3>
        <p>Comparativo do movimento atual com o mês anterior.</p>
        <div class="pw-dashboard-grid" id="pw-dashboard-periods"></div>
      </div>
      <div class="pw-dashboard-block" data-dashboard-card-group><h3>Financeiro e rentabilidade</h3><p>Faturamento, custos, resultado estimado e recebimentos.</p><div class="pw-dashboard-grid" id="pw-dashboard-financial"></div></div>
      <div class="pw-dashboard-block" data-dashboard-card-group><h3>Produção, entrega e finalização</h3><p>Prioriza atrasos da produção, logística dos itens prontos e valores pendentes após a entrega.</p><div class="pw-dashboard-grid" id="pw-dashboard-intelligence"></div></div>
      <div class="pw-dashboard-ranking">
        <div class="pw-section" data-dashboard-card-key="block-products"><div class="pw-section-title"><div><h3>Itens mais vendidos</h3><p>Produtos com maior saída e faturamento nos pedidos.</p></div></div><div id="pw-dashboard-products"></div></div>
        <div class="pw-section" data-dashboard-card-key="block-customers"><div class="pw-section-title"><div><h3>Clientes em destaque</h3><p>Melhores compradores e oportunidades de recuperação.</p></div></div><div id="pw-dashboard-customers"></div></div>
      </div>
      <div class="pw-dashboard-block" data-dashboard-card-group>
        <h3>Cadastros do sistema</h3>
        <p>Quantidade total disponível em cada tipo de registro.</p>
        <div class="pw-dashboard-grid" id="pw-dashboard-registries"></div>
      </div>
    </section>

  <div class="pw-modal" id="pw-nfe-generate-modal" aria-hidden="true">
    <div class="pw-modal-panel pw-nfe-generate-panel" role="dialog" aria-modal="true" aria-labelledby="pw-nfe-generate-title">
      <div class="pw-modal-header"><div><h3 id="pw-nfe-generate-title">Gerar NF-e do pedido</h3><p>Selecione um pedido, valide os dados fiscais e gere os arquivos para conferência.</p></div><button class="pw-btn pw-btn-soft pw-btn-icon" type="button" data-close-modal="pw-nfe-generate-modal" aria-label="Fechar">×</button></div>
      <div class="pw-modal-body">
        <div class="pw-field"><label class="pw-required" for="pw-nfe-order-select">Pedido</label><select id="pw-nfe-order-select"><option value="">Selecione um pedido</option></select></div>
        <div id="pw-nfe-validation" class="pw-nfe-validation" aria-live="polite"><p>Selecione um pedido para iniciar a validação.</p></div>
      </div>
      <div class="pw-modal-footer"><button class="pw-btn pw-btn-primary" id="pw-nfe-validate" type="button">Validar NF-e</button><button class="pw-btn pw-btn-soft" id="pw-nfe-download-xml" type="button" disabled>Baixar XML (rascunho)</button><button class="pw-btn pw-btn-soft" id="pw-nfe-print-pdf" type="button" disabled>Imprimir DANFE (prévia)</button></div>
    </div>
  </div>

  <div class="pw-modal" id="pw-order-arts-modal" aria-hidden="true">
    <div class="pw-modal-panel pw-order-arts-modal-panel" role="dialog" aria-modal="true" aria-labelledby="pw-order-arts-title">
      <div class="pw-modal-header"><div><h3 id="pw-order-arts-title">Baixar anexos do pedido</h3><p id="pw-order-arts-detail">Selecione os anexos que deseja baixar.</p></div><button class="pw-btn pw-btn-soft pw-btn-icon" type="button" data-close-modal="pw-order-arts-modal" aria-label="Fechar">×</button></div>
      <div class="pw-modal-body">
        <label class="pw-check-row pw-order-arts-select-all"><input id="pw-order-arts-select-all" type="checkbox" checked> Selecionar todos</label>
        <div id="pw-order-arts-list" class="pw-order-arts-download-list" aria-label="Anexos disponíveis"></div>
      </div>
      <div class="pw-modal-footer"><button class="pw-btn pw-btn-soft" type="button" data-close-modal="pw-order-arts-modal">Cancelar</button><button class="pw-btn pw-btn-primary" id="pw-order-arts-download-selected" type="button">⇩ Baixar selecionados</button></div>
    </div>
  </div>

  <div class="pw-modal" id="pw-order-art-preview-modal" aria-hidden="true">
    <div class="pw-modal-panel pw-order-art-preview-panel" role="dialog" aria-modal="true" aria-labelledby="pw-order-art-preview-title">
      <div class="pw-modal-header"><div><h3 id="pw-order-art-preview-title">Visualizar anexo</h3><p id="pw-order-art-preview-detail">—</p></div><button class="pw-btn pw-btn-soft pw-btn-icon" type="button" data-close-modal="pw-order-art-preview-modal" aria-label="Fechar">×</button></div>
      <div class="pw-modal-body">
        <div class="pw-order-art-preview-thumb-wrap" id="pw-order-art-preview-thumb-wrap"><span class="pw-order-art-preview-thumb-empty" id="pw-order-art-preview-thumb-empty">Sem pré-visualização disponível.</span><img class="pw-order-art-preview-thumb" id="pw-order-art-preview-thumb" alt="Pré-visualização do anexo" hidden><canvas class="pw-order-art-preview-canvas" id="pw-order-art-preview-canvas" tabindex="0" hidden></canvas></div>
        <p class="pw-order-art-preview-erase-hint" id="pw-order-art-preview-erase-hint" hidden>Clique numa área de fundo conectada pra apagar só ali · Ctrl+Z desfaz o último clique · Delete apaga a versão sem fundo inteira (gera de novo do zero) · roda do mouse amplia/reduz (nunca menor que o tamanho de abertura)</p>
        <p class="pw-order-art-preview-alpha-notice" id="pw-order-art-preview-alpha-notice" hidden></p>
        <div class="pw-order-art-preview-status" id="pw-order-art-preview-status"></div>
      </div>
      <div class="pw-modal-footer pw-order-art-preview-footer"><div class="pw-order-art-preview-nav"><button class="pw-btn pw-btn-soft pw-btn-icon pw-tooltip" id="pw-order-art-preview-prev" type="button" data-tooltip="Pedido anterior" aria-label="Pedido anterior">‹</button><button class="pw-btn pw-btn-soft pw-btn-icon pw-tooltip" id="pw-order-art-preview-next" type="button" data-tooltip="Próximo pedido" aria-label="Próximo pedido">›</button></div><div class="pw-order-art-preview-actions"><button class="pw-btn pw-btn-primary" id="pw-order-art-preview-mode-original" type="button" data-preview-mode="original">Original</button><button class="pw-btn pw-btn-soft pw-tooltip" id="pw-order-art-preview-mode-transparent" type="button" data-preview-mode="transparent" data-tooltip="Remove o fundo inteiro do PDF (PNG em alta resolução, nas mesmas medidas do original)" hidden>Transparente</button><button class="pw-btn pw-btn-primary pw-btn-icon pw-tooltip" id="pw-order-art-preview-download" type="button" data-tooltip="Baixar o que está sendo exibido" aria-label="Baixar">⇩</button></div></div>
    </div>
  </div>

  <div class="pw-modal" id="pw-order-ai-image-modal" aria-hidden="true">
    <div class="pw-modal-panel pw-order-ai-image-panel" role="dialog" aria-modal="true" aria-labelledby="pw-order-ai-image-title">
      <div class="pw-modal-header"><div><h3 id="pw-order-ai-image-title">Cadastro IA por imagem</h3><p>Cole ou envie um print do pedido. A leitura é feita no navegador e os dados ficam para sua conferência.</p></div><button class="pw-btn pw-btn-soft pw-btn-icon" type="button" data-close-modal="pw-order-ai-image-modal" aria-label="Fechar">×</button></div>
      <div class="pw-modal-body">
        <div class="pw-order-ai-image-toolbar"><button class="pw-btn pw-btn-soft" id="pw-order-ai-image-paste" type="button">📋 Colar imagem</button><button class="pw-btn pw-btn-soft" id="pw-order-ai-image-file-button" type="button">📎 Escolher imagem 1</button><button class="pw-btn pw-btn-soft" id="pw-order-ai-image-file-button-two" type="button" hidden>📎 Escolher imagem 2 (Shopee)</button><input id="pw-order-ai-image-file" type="file" accept="image/*" hidden><input id="pw-order-ai-image-file-two" type="file" accept="image/*" hidden></div>
        <div class="pw-order-ai-image-drop" id="pw-order-ai-image-drop" tabindex="0">Cole a imagem 1 com Ctrl+V ou arraste-a para cá.</div>
        <div class="pw-order-ai-image-drop pw-order-ai-image-drop-two" id="pw-order-ai-image-drop-two" tabindex="0" hidden>Cole ou arraste a imagem 2 do Shopee para cá.</div>
        <div class="pw-order-ai-image-workspace">
          <div class="pw-order-ai-image-preview-wrap"><div class="pw-order-ai-image-preview-slot" id="pw-order-ai-image-preview-slot-one"><span class="pw-order-ai-image-preview-label">Imagem 1</span><button class="pw-order-ai-image-remove" id="pw-order-ai-image-remove" type="button" data-order-ai-remove-image="0" aria-label="Remover imagem 1" title="Remover imagem 1" hidden>×</button><img id="pw-order-ai-image-preview" alt="Prévia da imagem 1 do pedido" hidden><span id="pw-order-ai-image-preview-empty">Nenhuma imagem selecionada.</span></div><div class="pw-order-ai-image-preview-slot" id="pw-order-ai-image-preview-slot-two" hidden><span class="pw-order-ai-image-preview-label">Imagem 2 · Shopee</span><button class="pw-order-ai-image-remove" id="pw-order-ai-image-remove-two" type="button" data-order-ai-remove-image="1" aria-label="Remover imagem 2" title="Remover imagem 2" hidden>×</button><img id="pw-order-ai-image-preview-two" alt="Prévia da imagem 2 do pedido" hidden><span id="pw-order-ai-image-preview-empty-two">Nenhuma imagem selecionada.</span></div></div>
          <div class="pw-order-ai-image-result"><div id="pw-order-ai-image-status" class="pw-field-hint" role="status" aria-live="polite">Aguardando uma imagem.</div><div id="pw-order-ai-image-fields" class="pw-order-ai-image-fields" hidden>
            <div class="pw-field"><label for="pw-ai-client-name">Cliente</label><input id="pw-ai-client-name" type="text"></div>
            <div class="pw-field"><label for="pw-ai-client-document">CPF/CNPJ</label><input id="pw-ai-client-document" type="text"></div>
            <div class="pw-field"><label for="pw-ai-origin">Origem do pedido</label><select id="pw-ai-origin"></select></div>
            <div class="pw-field" data-ai-marketplace-field><label for="pw-ai-marketplace-number">Número na plataforma</label><input id="pw-ai-marketplace-number" type="text"></div>
            <div class="pw-field"><label for="pw-ai-delivery">Previsão de entrega</label><input id="pw-ai-delivery" type="date"></div>
            <div class="pw-field pw-ai-field-wide"><label for="pw-ai-product-description">Produto</label><input id="pw-ai-product-description" type="text"></div>
            <div class="pw-field"><label for="pw-ai-quantity">Quantidade</label><input id="pw-ai-quantity" type="number" min="1" step="1"></div>
            <div class="pw-field"><label for="pw-ai-unit">Unidade</label><input id="pw-ai-unit" type="text"></div>
            <div class="pw-field"><label id="pw-ai-sale-price-label" for="pw-ai-sale-price">Valor unitário (R$)</label><input id="pw-ai-sale-price" type="text" inputmode="decimal"></div>
            <div class="pw-field" data-ai-marketplace-field data-ai-non-ml-field><label for="pw-ai-platform-fee">Tarifa da plataforma (R$)</label><input id="pw-ai-platform-fee" type="text" inputmode="decimal"></div>
            <div class="pw-field" data-ai-ml-field><label for="pw-ai-ml-tarifa">Tarifa (R$)</label><input id="pw-ai-ml-tarifa" type="text" inputmode="decimal"></div>
            <div class="pw-field" data-ai-ml-field><label for="pw-ai-ml-frete">Frete/Envios (R$)</label><input id="pw-ai-ml-frete" type="text" inputmode="decimal"></div>
            <div class="pw-field" data-ai-ml-field><label for="pw-ai-ml-bruto">Bruto a receber (R$)</label><input id="pw-ai-ml-bruto" type="text" inputmode="decimal" readonly title="Calculado: Total − Tarifa − Frete"></div>
            <div class="pw-field pw-ai-field-wide"><label for="pw-ai-address">Endereço identificado</label><input id="pw-ai-address" type="text"></div>
          </div><details class="pw-order-ai-image-raw"><summary>Texto lido pela IA</summary><pre id="pw-order-ai-image-raw-text"></pre></details></div>
        </div>
        <p class="pw-field-hint">Confira os campos antes de aplicar. Se o cliente já existir, ele será selecionado; se não existir, o cadastro será aberto preenchido para você confirmar.</p>
      </div>
      <div class="pw-modal-footer"><button class="pw-btn pw-btn-soft" type="button" data-close-modal="pw-order-ai-image-modal">Cancelar</button><button class="pw-btn pw-btn-primary" id="pw-order-ai-image-apply" type="button" disabled>✓ Usar dados no pedido</button></div>
    </div>
  </div>

    <form class="pw-system-view" id="pw-order-form" data-view="order" novalidate>
      <div class="pw-form-toolbar" aria-label="Ferramentas do pedido">
        <button class="pw-btn pw-btn-soft pw-toolbar-button pw-tooltip" id="pw-new-order" type="button" data-tooltip="Novo pedido" aria-label="Novo pedido">＋</button>
        <button class="pw-btn pw-btn-soft pw-toolbar-button pw-tooltip pw-order-ai-image-button" id="pw-order-ai-image" type="button" data-tooltip="IA - cadastro por print marketplaces" aria-label="IA - cadastro por print marketplaces">🧠</button>
        <span class="pw-toolbar-separator" aria-hidden="true"></span>
        <button class="pw-btn pw-btn-soft pw-toolbar-button pw-tooltip" id="pw-order-payments" type="button" data-tooltip="Pagamentos do pedido" aria-label="Pagamentos do pedido">$</button>
        <button class="pw-btn pw-toolbar-button pw-tooltip pw-order-save-button" type="submit" data-order-save data-tooltip="Pedido ainda não salvo" aria-label="Salvar pedido">✓</button>
        <span class="pw-toolbar-spacer" aria-hidden="true"></span>
        <span class="pw-toolbar-separator" aria-hidden="true"></span>
        <button class="pw-btn pw-btn-soft pw-toolbar-button pw-tooltip" id="pw-order-history" type="button" data-tooltip="Histórico e linha do tempo" aria-label="Histórico">◷</button>
        <button class="pw-btn pw-btn-soft pw-toolbar-button pw-tooltip" id="pw-print-order" type="button" data-tooltip="Imprimir pedido em PDF" aria-label="Imprimir pedido">⎙</button>
      </div>
      <div class="pw-content">
        <section class="pw-section">
          <div class="pw-section-title">
            <div>
              <h3>Identificação e prazos</h3>
              <p>As datas automáticas são atualizadas pelo próprio cadastro.</p>
            </div>
            <span id="pw-deadline-status" class="pw-deadline-status pw-status-neutral">Prazo não informado</span>
          </div>

          <div class="pw-grid">
            <div class="pw-field pw-col-3">
              <label for="pw-order-client-origin">Origem do pedido</label>
              <div class="pw-order-origin-control"><span id="pw-order-origin-icon" class="pw-client-origin-dot pw-origin-normal" aria-hidden="true">☺</span><select id="pw-order-client-origin" name="client_origin"><option value="Normal">Normal</option><option value="Mercado Livre">Mercado Livre</option><option value="Shopee">Shopee</option></select></div>
            </div>
            <div class="pw-field pw-col-3">
              <label for="pw-order-number">Número do pedido</label>
              <input id="pw-order-number" name="order_number" type="text" readonly>
            </div>
            <div class="pw-field pw-col-2">
              <label for="pw-created-date">Data de cadastro</label>
              <input id="pw-created-date" name="created_date" type="date" readonly>
            </div>
            <div class="pw-field pw-col-2">
              <label for="pw-order-time">Hora do pedido</label>
              <input id="pw-order-time" name="order_time" type="time" readonly>
            </div>
            <div class="pw-field pw-col-2">
              <label for="pw-last-change">Última alteração</label>
              <input id="pw-last-change" name="last_change" type="text" readonly>
            </div>

            <div class="pw-field pw-col-3">
              <label for="pw-created-by">Pedido cadastrado por</label>
              <input id="pw-created-by" name="created_by" type="text" readonly>
            </div>

            <div class="pw-field pw-col-3 pw-tooltip-field" data-tooltip="Menu rápido disponível: clique com o botão direito nesta data para escolher uma data sugerida." title="Menu rápido disponível: clique com o botão direito nesta data para escolher uma data sugerida.">
              <label class="pw-required" for="pw-requested-delivery">Previsão de entrega</label>
              <input id="pw-requested-delivery" name="requested_delivery" type="date" required>
            </div>
            <div class="pw-field pw-col-3">
              <label class="pw-required" for="pw-status">Situação</label>
              <select id="pw-status" name="status" required>
                <option value="Criação da arte">Criação da arte</option>
                <option value="Arte aprovada">Arte aprovada</option>
                <option value="Em produção">Em produção</option>
                <option value="Produzido">Produção pronta</option>
                <option value="Aguardando entrega">Aguardando entrega</option>
                <option value="Entregue">Entregue</option>
              </select>
            </div>
            <div class="pw-field pw-col-3">
              <label for="pw-current-status-date">Data e hora da situação</label>
              <input id="pw-current-status-date" type="text" readonly value="—">
              <input id="pw-actual-delivery" name="actual_delivery" type="hidden">
            </div>
            <div class="pw-field pw-col-5" id="pw-order-marketplace-number-wrap" hidden>
              <label id="pw-order-marketplace-number-label" for="pw-order-marketplace-number">Número do pedido no marketplace</label>
              <input id="pw-order-marketplace-number" name="marketplace_order_number" type="text" maxlength="80">
            </div>
          </div>
        </section>

        <section class="pw-section pw-financial-readonly" id="pw-financial-readonly" hidden>
          <div class="pw-section-title"><div><h3>Condições atuais de pagamento</h3><p>Resumo somente para consulta. Use o botão <strong>$</strong> da barra de ferramentas para registrar pagamentos ou fazer ajustes.</p></div></div>
          <select id="pw-payment-method" hidden aria-hidden="true"><option value="">Não informada</option></select>
          <select id="pw-payment-status" hidden aria-hidden="true"><option>Pendente</option><option>Parcial</option><option>Pago</option><option>Estornado</option><option>Cortesia</option></select>
          <input id="pw-discount-value" type="hidden" value="0,00">
          <input id="pw-surcharge-value" type="hidden" value="0,00">
          <input id="pw-paid-value" type="hidden" value="0,00">
          <div class="pw-financial-summary">
            <div><span>Forma de pagamento</span><strong id="pw-payment-method-view">Não informada</strong></div>
            <div><span>Situação financeira</span><strong id="pw-payment-status-view">Pendente</strong></div>
            <div><span>Produtos</span><strong id="pw-products-subtotal">R$ 0,00</strong></div>
            <div><span>Desconto</span><strong id="pw-discount-total">R$ 0,00</strong></div>
            <div><span>Acréscimo</span><strong id="pw-surcharge-total">R$ 0,00</strong></div>
            <div><span>Total ajustado</span><strong id="pw-adjusted-total">R$ 0,00</strong></div>
            <div><span>Total recebido</span><strong id="pw-paid-total">R$ 0,00</strong></div>
            <div><span>Saldo pendente</span><strong id="pw-balance-total">R$ 0,00</strong></div>
          </div>
        </section>

        <section class="pw-section">
          <div class="pw-section-title">
            <div>
              <h3>Cliente</h3>
              <p>Pesquise por parte do nome ou por parte do telefone.</p>
            </div>
          </div>

          <div class="pw-search-row">
            <div class="pw-search-wrap">
              <label class="pw-screen-reader" for="pw-client-search">Localizar cliente</label>
              <input id="pw-client-search" type="search" autocomplete="off" placeholder="Digite o nome ou telefone do cliente">
              <div id="pw-client-results" class="pw-search-results" role="listbox"></div>
            </div>
            <button class="pw-btn pw-btn-blue pw-new-registry-button" id="pw-new-client" type="button">+ Cadastrar cliente</button>
          </div>

          <input id="pw-client-id" name="client_id" type="hidden">
          <div class="pw-client-card" id="pw-client-card">
            <div class="pw-client-card-header">
              <div>
                <strong id="pw-client-card-name">Cliente selecionado</strong>
                <span id="pw-client-card-reference"></span>
              </div>
            </div>
            <div class="pw-grid">
              <div class="pw-field pw-col-3">
                <label for="pw-client-phone">Telefone / WhatsApp</label>
                <input id="pw-client-phone" name="client_phone" type="text" readonly>
              </div>
              <div class="pw-field pw-col-5">
                <label for="pw-client-address-summary">Endereço</label>
                <input id="pw-client-address-summary" type="text" readonly>
              </div>
              <div class="pw-field pw-col-2">
                <label for="pw-client-person-type-summary">Tipo de pessoa</label>
                <input id="pw-client-person-type-summary" type="text" readonly>
              </div>
              <div class="pw-field pw-col-2">
                <label for="pw-client-customer-type-summary">Tipo de cliente</label>
                <input id="pw-client-customer-type-summary" type="text" readonly>
              </div>
              <div class="pw-field pw-col-12">
                <label for="pw-client-balance-summary">Saldo pendente (outros pedidos)</label>
                <input id="pw-client-balance-summary" type="text" readonly>
              </div>
              <div class="pw-visually-hidden-fields" hidden>
                <input id="pw-client-name" name="client_name" type="text" readonly>
                <input id="pw-client-email" name="client_email" type="email" readonly>
                <input id="pw-client-document" name="client_document" type="text" readonly>
              </div>
            </div>
          </div>
        </section>

        <section class="pw-section">
          <div class="pw-section-title">
            <div>
              <h3>Produtos do pedido</h3>
              <p>Localize pelo nome parcial ou código. Para calcular uma impressão especial, digite <strong>DTF UV</strong> e selecione o produto encontrado.</p>
            </div>
          </div>

          <div class="pw-product-tools">
            <div class="pw-search-wrap">
              <label for="pw-product-search">Produto — digite também “DTF UV” para abrir a calculadora</label>
              <input id="pw-product-search" type="search" autocomplete="off" placeholder="Nome, código ou DTF UV">
              <div id="pw-product-results" class="pw-search-results" role="listbox"></div>
            </div>
            <button class="pw-btn pw-btn-blue pw-new-registry-button" id="pw-new-product" type="button">+ Cadastrar produto</button>
          </div>

          <div class="pw-table-wrap">
            <table class="pw-items-table">
              <thead>
                <tr id="pw-items-header">
                  <th class="pw-code">Código</th>
                  <th class="pw-description">Descrição</th>
                  <th class="pw-unit">Unidade</th>
                  <th class="pw-quantity">Quantidade</th>
                  <th class="pw-money">Valor unitário</th>
                  <th class="pw-money pw-total">Valor total</th>
                  <th class="pw-art"><span aria-hidden="true" title="Arte aprovada">📎</span><span class="pw-screen-reader">Arte aprovada</span></th>
                  <th class="pw-actions"><span aria-hidden="true" title="Ações">⋮</span><span class="pw-screen-reader">Ações</span></th>
                </tr>
              </thead>
              <tbody id="pw-items-body">
                <tr id="pw-empty-items-row">
                  <td class="pw-empty-items" colspan="8">Nenhum produto incluído neste pedido.</td>
                </tr>
              </tbody>
            </table>
          </div>
          <input id="pw-item-art-file" type="file" accept="application/pdf,.pdf,.ai,.eps,.cdr" hidden>

          <div class="pw-totals">
            <span>Total do pedido</span>
            <strong id="pw-order-total">R$ 0,00</strong>
          </div>
        </section>

        <section class="pw-section pw-collapsible-section" id="pw-details-section">
          <div class="pw-section-title pw-collapsible-toggle" id="pw-details-toggle" role="button" tabindex="0" aria-expanded="false" aria-controls="pw-details-body">
            <div>
              <h3>Detalhes e observações</h3>
              <p>Registre informações da personalização, arte, evento ou entrega.</p>
            </div>
            <span class="pw-collapse-icon" aria-hidden="true">▶</span>
          </div>
          <div class="pw-collapsible-body" id="pw-details-body" hidden>
            <div class="pw-grid">
              <div class="pw-field pw-col-12">
                <label for="pw-personalization-notes">Detalhes da personalização</label>
                <textarea id="pw-personalization-notes" name="personalization_notes" placeholder="Cores, nomes, temas, medidas, acabamento e demais orientações..."></textarea>
              </div>
            </div>
          </div>
        </section>
        <div class="pw-order-bottom-actions"><button class="pw-btn pw-order-save-button" type="submit" data-order-save>✓ Salvar pedido</button></div>
      </div>

    </form>

    <section class="pw-system-view pw-consultation pw-analytics-view" data-view="analytics">
      <div class="pw-analytics-print-header" aria-hidden="true">
        <div><img src="<?php echo esc_url( PW_PERSONALIZADOS_URL . 'assets/antasys-logo-v2.png?v=' . PW_PERSONALIZADOS_VERSION ); ?>" alt="AntaSys"><h1>Relatório de gráficos</h1><p>Visão consolidada de vendas, finanças, despesas e operação.</p></div>
        <div class="pw-analytics-print-meta"><strong id="pw-analytics-print-range">Todo o histórico</strong><span id="pw-analytics-print-generated"></span></div>
      </div>
      <div class="pw-consultation-header pw-analytics-heading">
        <div><h3>Central de gráficos</h3><p>Indicadores visuais de vendas, recebimentos, clientes, produtos e produção.</p></div>
        <div class="pw-analytics-heading-actions"><button class="pw-btn pw-btn-soft" id="pw-analytics-reset-chart-models" type="button" title="Restaurar e salvar os modelos padrão do sistema">↺ Padrão</button><button class="pw-btn pw-btn-primary" id="pw-analytics-print" type="button">⎙ Imprimir gráficos</button></div>
      </div>
      <div class="pw-analytics-filters">
        <label>Período<select id="pw-analytics-period"><option value="all">Todo o histórico</option><option value="30">Últimos 30 dias</option><option value="90">Últimos 90 dias</option><option value="year">Ano atual</option><option value="custom">Personalizado</option></select></label>
        <label>Data inicial<input id="pw-analytics-start" type="date" disabled></label>
        <label>Data final<input id="pw-analytics-end" type="date" disabled></label>
        <label>Situação<select id="pw-analytics-status"><option value="">Todas</option></select></label>
        <label>Cliente<select id="pw-analytics-client"><option value="">Todos</option></select></label>
        <label>Cadastrado por<select id="pw-analytics-user"><option value="">Todos</option></select></label>
        <label>Categoria<select id="pw-analytics-category"><option value="">Todas</option></select></label>
        <label>Origem<select id="pw-analytics-origin"><option value="">Todas</option><option value="Normal">Normal</option><option value="Mercado Livre">Mercado Livre</option><option value="Shopee">Shopee</option></select></label>
        <label>Finalização<select id="pw-analytics-finalization"><option value="">Todos</option><option value="open">Em aberto</option><option value="finalized">Finalizados</option></select></label>
        <div class="pw-analytics-filter-actions"><button class="pw-btn pw-btn-primary" id="pw-analytics-apply" type="button">Aplicar filtros</button><button class="pw-btn pw-btn-soft" id="pw-analytics-clear" type="button">Limpar</button></div>
      </div>
      <div class="pw-analytics-summary" id="pw-analytics-summary"></div>
      <div class="pw-analytics-kpis" id="pw-analytics-kpis"></div>
      <div class="pw-analytics-grid" id="pw-analytics-sales" data-analytics-section="sales">
        <article class="pw-analytics-card pw-analytics-wide"><header><div><small>COMERCIAL</small><h4>Evolução do faturamento</h4><p>Valor líquido dos pedidos, já descontadas tarifas de marketplace e ajustes, agrupado conforme o período escolhido.</p><span class="pw-analytics-range-label" id="pw-analytics-trend-range"></span></div><div class="pw-analytics-card-tools"><span id="pw-analytics-trend-label"></span><label class="pw-analytics-mini-filter">Agrupar por<select id="pw-analytics-revenue-granularity" aria-label="Agrupar faturamento por"><option value="day">Dia</option><option value="month" selected>Mês</option><option value="year">Ano</option></select></label></div></header><div id="pw-analytics-trend"></div></article>
        <article class="pw-analytics-card pw-analytics-wide"><header><div><small>PERSPECTIVA</small><h4>Projeção de vendas até o fim do mês</h4><p>Vendas reais até hoje (azul) e projeção linear dos dias restantes do mês com base na média diária do mês atual (laranja tracejado).</p></div></header><div id="pw-analytics-forecast"></div></article>
        <article class="pw-analytics-card"><header><div><small>PRODUTOS</small><h4>Itens com maior faturamento</h4><p>Produtos, peças e serviços que mais geraram valor líquido nos pedidos.</p></div></header><div id="pw-analytics-products-chart"></div></article>
        <article class="pw-analytics-card"><header><div><small>CLIENTES</small><h4>Clientes que mais compram</h4><p>Ranking pelo valor líquido dos pedidos de cada cliente.</p></div></header><div id="pw-analytics-clients-chart"></div></article>
        <article class="pw-analytics-card"><header><div><small>COMISSÕES DAS PLATAFORMAS</small><h4>Bruto, tarifas e sua parcela</h4><p>Do valor bruto da venda, separa as tarifas da plataforma, descontos e o valor líquido que fica para você.</p></div></header><div id="pw-analytics-platform-share-chart"></div></article>
        <article class="pw-analytics-card"><header><div><small>DIVISÃO DO FATURAMENTO</small><h4>Quem ficou com cada parte da venda</h4><p>Mostra o valor líquido da empresa, as tarifas de marketplace e os descontos concedidos ao cliente.</p></div></header><div id="pw-analytics-commission-share-chart"></div></article>
      </div>
      <div class="pw-analytics-grid" id="pw-analytics-finance" data-analytics-section="finance">
        <article class="pw-analytics-card"><header><div><small>FINANCEIRO</small><h4>Formas de pagamento</h4><p>Valores efetivamente recebidos, agrupados pela forma utilizada.</p></div></header><div id="pw-analytics-payments-chart"></div></article>
        <article class="pw-analytics-card"><header><div><small>A RECEBER</small><h4>Saldo pendente por cliente</h4><p>Quanto ainda falta receber de cada cliente.</p></div></header><div id="pw-analytics-receivables-chart"></div></article>
      </div>
      <div class="pw-analytics-grid" id="pw-analytics-expenses" data-analytics-section="expenses">
        <article class="pw-analytics-card"><header><div><small>DESPESAS</small><h4>Gastos por categoria</h4><p>Onde os gastos foram lançados: insumos, anúncios, manutenção e demais categorias.</p></div></header><div id="pw-analytics-expense-category-chart"></div></article>
        <article class="pw-analytics-card"><header><div><small>FORNECEDORES</small><h4>Gastos por fornecedor</h4><p>Quanto foi gasto em cada fornecedor ou local de compra.</p></div></header><div id="pw-analytics-expense-supplier-chart"></div></article>
        <article class="pw-analytics-card"><header><div><small>ANÚNCIOS</small><h4>Anúncios por fornecedor</h4><p>Participação de cada fornecedor no total gasto com anúncios, em valor e percentual.</p></div></header><div id="pw-analytics-ad-supplier-chart"></div></article>
        <article class="pw-analytics-card"><header><div><small>ANÚNCIOS</small><h4>Gastos de anúncios por data</h4><p>Valores gastos com anúncios agrupados conforme o período escolhido.</p><span class="pw-analytics-range-label" id="pw-analytics-ad-date-range"></span></div><label class="pw-analytics-mini-filter">Agrupar por<select id="pw-analytics-ad-date-granularity" aria-label="Agrupar gastos de anúncios por"><option value="day">Dia</option><option value="month" selected>Mês</option><option value="year">Ano</option></select></label></header><div id="pw-analytics-ad-date-chart"></div></article>
        <article class="pw-analytics-card pw-analytics-wide"><header><div><small>CONSUMO × VENDAS</small><h4>Vendas entre compras de materiais</h4><p>Compara o custo de cada lançamento de material com as vendas e recebimentos líquidos de DTF UV até a próxima compra.</p><span class="pw-analytics-range-label" id="pw-analytics-material-comparison-range"></span></div><label class="pw-analytics-mini-filter">Material<select id="pw-analytics-material-comparison" aria-label="Selecionar material para comparar com vendas"></select></label></header><div id="pw-analytics-material-comparison-chart"></div></article>
        <article class="pw-analytics-card"><header><div><small>RENTABILIDADE</small><h4>Faturamento menos despesas</h4><p>Resultado estimado do período: vendas menos despesas cadastradas.</p></div></header><div id="pw-analytics-profit-chart"></div></article>
        <article class="pw-analytics-card"><header><div><small>SITUAÇÕES</small><h4>Etapas em acompanhamento</h4><p>Mostra a produção e a entrega em andamento. Os entregues são detalhados na finalização.</p></div></header><div id="pw-analytics-status-chart"></div></article>
      </div>
      <div class="pw-analytics-grid" id="pw-analytics-operation" data-analytics-section="operation">
        <article class="pw-analytics-card pw-analytics-wide"><header><div><small>INTELIGÊNCIA OPERACIONAL</small><h4>Produção, artes, finalização e recebimentos</h4><p>Visão consolidada do andamento dos pedidos, artes anexadas, entregas e valores recebidos.</p></div></header><div class="pw-analytics-gauges" id="pw-analytics-operation-chart"></div></article>
        <article class="pw-analytics-card"><header><div><small>FINALIZAÇÃO</small><h4>Pedidos finalizados e em aberto</h4><p>Finalizado somente quando entregue e sem saldo pendente.</p></div></header><div id="pw-analytics-finalization-chart"></div></article>
        <article class="pw-analytics-card"><header><div><small>ACOMPANHAMENTO OPERACIONAL</small><h4>Produção, entrega e recebimento</h4><p>Separa a produção crítica, os pedidos prontos para logística e os entregues ainda a receber.</p></div></header><div id="pw-analytics-pending-chart"></div></article>
        <article class="pw-analytics-card pw-analytics-wide"><header><div><small>FLUXO DE ENTREGA</small><h4>Produção e entregas</h4><p>Produção crítica, itens aguardando entrega e entregas concluídas no prazo ou com atraso.</p></div></header><div id="pw-analytics-delivery-chart"></div></article>
      </div>
    </section>

    <section class="pw-system-view pw-consultation" data-view="orders">
      <div class="pw-consultation-header">
        <input class="pw-consultation-search" id="pw-orders-consult-search" type="search" placeholder="Número, cliente, telefone ou situação">
      </div>
      <div class="pw-period-filter">
        <label><input id="pw-orders-all-dates" type="checkbox" checked> Todas as datas</label>
        <label>Data inicial <input id="pw-orders-date-start" type="date" disabled></label>
        <label>Data final <input id="pw-orders-date-end" type="date" disabled></label>
        <div class="pw-date-quick-btns">
          <button type="button" class="pw-btn pw-btn-soft pw-tooltip" id="pw-date-quick-toggle" data-tooltip="Filtro rápido por data" aria-label="Filtro rápido por data" aria-haspopup="true" aria-expanded="false">📅</button>
          <div class="pw-date-quick-menu" id="pw-date-quick-menu" role="menu">
            <button type="button" class="pw-date-quick-filter" data-quick="hoje" role="menuitem">📅 Hoje</button>
            <button type="button" class="pw-date-quick-filter" data-quick="ontem" role="menuitem">⬅ Ontem / Hoje</button>
            <button type="button" class="pw-date-quick-filter" data-quick="7d" role="menuitem">📆 Últimos 7 dias</button>
          </div>
        </div>
        <label>Finalização <select id="pw-orders-status-filter"><option value="open" selected>Em aberto</option><option value="finalized">Finalizado</option><option value="all">Todos</option></select></label>
      </div>
      <div class="pw-orders-filter-pills-row">
        <div class="pw-origin-filter-bar" id="pw-orders-origin-filter" role="group" aria-label="Filtrar por origem do pedido"></div>
        <span class="pw-orders-filter-pills-separator" aria-hidden="true"></span>
        <div class="pw-origin-filter-bar" id="pw-orders-situation-filter-bar" role="group" aria-label="Filtrar por situação"></div>
      </div>
      <div class="pw-bulk-toolbar" data-bulk-toolbar="order"><label class="pw-check-row"><input type="checkbox" data-select-all="order"> Selecionar todos</label><span class="pw-selected-count" data-selected-count="order">0 selecionados</span><button class="pw-btn pw-btn-soft pw-btn-icon pw-tooltip" id="pw-orders-refresh" type="button" data-tooltip="Atualizar a lista com os pedidos mais recentes" title="Atualizar" aria-label="Atualizar lista de pedidos">↻</button><button class="pw-btn pw-btn-soft pw-btn-icon pw-tooltip" id="pw-print-orders-toggle" type="button" data-tooltip="Imprimir" aria-haspopup="true" aria-expanded="false" aria-label="Imprimir">⎙</button><button class="pw-btn pw-btn-soft pw-btn-icon pw-tooltip pw-admin-only" id="pw-audit-order-status" type="button" data-tooltip="Auditar situação do pedido selecionado" title="Auditar situação" aria-label="Auditar situação" disabled>◉</button><button class="pw-btn pw-btn-soft pw-btn-icon pw-tooltip" type="button" data-bulk-edit="order" data-tooltip="Editar selecionados" title="Editar selecionados" aria-label="Editar selecionados" disabled>✎</button><button class="pw-btn pw-btn-danger pw-btn-icon pw-tooltip" type="button" data-bulk-delete="order" data-tooltip="Excluir selecionados" title="Excluir selecionados" aria-label="Excluir selecionados" disabled>🗑</button></div>
      <div class="pw-print-quick-menu" id="pw-print-quick-menu" role="menu" style="display:none"><button type="button" id="pw-print-selected-orders" role="menuitem" disabled>⎙ Pedidos selecionados</button><button type="button" id="pw-print-arts-conference" role="menuitem">📋 Conferência de artes</button></div>
      <div class="pw-table-wrap"><table class="pw-data-table"><thead><tr><th class="pw-select-cell">Sel.</th><th class="pw-sortable-column" data-sort-key="orderNumber">Pedido<span class="pw-sort-indicator" data-sort-indicator="orderNumber"></span></th><th class="pw-sortable-column" data-sort-key="createdDate">Data pedido<span class="pw-sort-indicator" data-sort-indicator="createdDate"></span></th><th class="pw-sortable-column" data-sort-key="clientName">Cliente<span class="pw-sort-indicator" data-sort-indicator="clientName"></span></th><th class="pw-sortable-column" data-sort-key="status">Situação<span class="pw-sort-indicator" data-sort-indicator="status"></span></th><th>Histórico</th><th>Forma de pagamento</th><th>Info</th><th>Anexos</th><th class="pw-sortable-column" data-sort-key="total">Total<span class="pw-sort-indicator" data-sort-indicator="total"></span></th></tr></thead><tbody id="pw-orders-consult-body"></tbody></table></div>
      <div class="pw-legends-toggle"><button class="pw-btn pw-btn-soft" id="pw-orders-legends-toggle" type="button" aria-expanded="false" aria-controls="pw-orders-legends">▣ Ver legendas</button></div>
      <div class="pw-table-legends" id="pw-orders-legends" hidden>
        <section class="pw-legend-column"><h4>Situações</h4><div id="pw-order-status-legend" class="pw-status-legend" aria-label="Legenda das situações"></div></section>
        <section class="pw-legend-column"><h4>Formas de pagamento</h4><div id="pw-payment-method-legend" class="pw-status-legend pw-payment-method-legend" aria-label="Legenda das formas de pagamento"></div></section>
        <section class="pw-legend-column"><h4>Informações</h4><div id="pw-order-info-legend" class="pw-status-legend" aria-label="Legenda dos ícones de informações"></div></section>
      </div>
    </section>

    <section class="pw-system-view pw-consultation" data-view="clients">
      <div class="pw-consultation-header">
        <input class="pw-consultation-search" id="pw-clients-consult-search" type="search" placeholder="Código, nome, telefone, CPF/CNPJ ou e-mail">
      </div>
      <div class="pw-bulk-toolbar" data-bulk-toolbar="client"><label class="pw-check-row"><input type="checkbox" data-select-all="client"> Selecionar todos</label><span class="pw-selected-count" data-selected-count="client">0 selecionados</span><button class="pw-btn pw-btn-soft pw-btn-icon pw-tooltip" type="button" data-bulk-edit="client" data-tooltip="Editar selecionados" title="Editar selecionados" aria-label="Editar selecionados" disabled>✎</button><button class="pw-btn pw-btn-danger pw-btn-icon pw-tooltip" type="button" data-bulk-delete="client" data-tooltip="Excluir selecionados" title="Excluir selecionados" aria-label="Excluir selecionados" disabled>🗑</button><button class="pw-btn pw-btn-soft pw-tooltip" id="pw-create-wp-login" type="button" data-tooltip="Criar login no WordPress para o cliente selecionado" title="Criar login no WordPress" disabled>⊕ Criar Login</button></div>
      <div class="pw-table-wrap"><table class="pw-data-table"><thead><tr><th class="pw-select-cell">Sel.</th><th>Info</th><th class="pw-sortable-column" data-sort-key="code">Código<span class="pw-sort-indicator" data-sort-indicator="code"></span></th><th class="pw-sortable-column" data-sort-key="name">Cliente<span class="pw-sort-indicator" data-sort-indicator="name"></span></th><th class="pw-sortable-column" data-sort-key="phone">Telefone<span class="pw-sort-indicator" data-sort-indicator="phone"></span></th><th class="pw-sortable-column" data-sort-key="email">E-mail<span class="pw-sort-indicator" data-sort-indicator="email"></span></th><th class="pw-sortable-column" data-sort-key="document">CPF/CNPJ<span class="pw-sort-indicator" data-sort-indicator="document"></span></th></tr></thead><tbody id="pw-clients-consult-body"></tbody></table></div>
      <div class="pw-client-info-legend"><span class="pw-client-info-dot pw-payment-monthly">📅</span><span>Fechamento mensal</span><span class="pw-client-info-dot pw-payment-individual">$</span><span>Pagamento por pedido</span><span class="pw-client-info-dot pw-customer-type-direct"><svg viewBox="0 0 24 24" width="1em" height="1em" fill="currentColor" aria-hidden="true" focusable="false"><circle cx="12" cy="8" r="4"></circle><path d="M4 20.2C4 15.9 7.6 13 12 13s8 2.9 8 7.2V21H4v-.8z"></path></svg></span><span>Cliente direto</span><span class="pw-client-info-dot pw-customer-type-reseller">🏬</span><span>Revenda</span></div>
    </section>

    <section class="pw-system-view pw-consultation" data-view="monthly-closing">
      <div class="pw-mktp-print-header" id="pw-monthly-print-header" aria-hidden="true">
        <div><img src="<?php echo esc_url( PW_PERSONALIZADOS_URL . 'assets/printway-logo.png?v=' . PW_PERSONALIZADOS_VERSION ); ?>" alt="Print Way"><h1>Fechamento mensal</h1><p id="pw-monthly-print-subtitle"></p></div>
        <div class="pw-mktp-print-meta"><strong>Pedidos em aberto</strong><span id="pw-monthly-print-generated"></span></div>
      </div>
      <div class="pw-monthly-toolbar pw-mktp-no-print">
        <div class="pw-field"><label for="pw-monthly-client">Cliente</label><select id="pw-monthly-client"><option value="">Todos os clientes com fechamento mensal</option></select></div>
        <div class="pw-field"><label for="pw-monthly-status">Situação</label><select id="pw-monthly-status"><option value="all">Todos em aberto</option><option value="overdue">Somente vencidos</option><option value="current">Somente no prazo</option></select></div>
        <button class="pw-btn pw-btn-soft" id="pw-monthly-select-all" type="button">☑ Selecionar todos</button>
        <button class="pw-btn pw-btn-soft" id="pw-monthly-select-none" type="button">☐ Desmarcar todos</button>
        <button class="pw-btn pw-btn-soft" id="pw-monthly-print" type="button">🖨️ Imprimir</button>
        <button class="pw-btn pw-btn-primary" id="pw-monthly-close" type="button" disabled>Realizar fechamento</button>
      </div>
      <div class="pw-payment-summary pw-monthly-summary pw-mktp-no-print"><div><small>Pedidos em aberto</small><strong id="pw-monthly-count">0</strong></div><div><small>Total dos pedidos</small><strong id="pw-monthly-total">R$ 0,00</strong></div><div><small>Total recebido</small><strong id="pw-monthly-paid">R$ 0,00</strong></div><div><small>Saldo pendente</small><strong id="pw-monthly-balance">R$ 0,00</strong></div></div>
      <div class="pw-table-wrap pw-mktp-no-print"><table class="pw-data-table"><thead><tr><th class="pw-select-cell">Sel.</th><th>Pedido</th><th>Cliente</th><th>Cadastro</th><th>Vencimento</th><th>Situação</th><th>Total</th><th>Recebido</th><th>Saldo</th></tr></thead><tbody id="pw-monthly-body"></tbody><tfoot id="pw-monthly-tfoot"></tfoot></table></div>
      <div id="pw-monthly-print-body" class="pw-monthly-print-only"></div>
    </section>

    <section class="pw-system-view pw-consultation" data-view="products">
      <div class="pw-consultation-header">
        <input class="pw-consultation-search" id="pw-products-consult-search" type="search" placeholder="Código ou descrição do produto">
      </div>
      <div class="pw-bulk-toolbar" data-bulk-toolbar="product"><label class="pw-check-row"><input type="checkbox" data-select-all="product"> Selecionar todos</label><span class="pw-selected-count" data-selected-count="product">0 selecionados</span><button class="pw-btn pw-btn-soft pw-btn-icon pw-tooltip" type="button" data-bulk-edit="product" data-tooltip="Editar selecionados" title="Editar selecionados" aria-label="Editar selecionados" disabled>✎</button><button class="pw-btn pw-btn-danger pw-btn-icon pw-tooltip" type="button" data-bulk-delete="product" data-tooltip="Excluir selecionados" title="Excluir selecionados" aria-label="Excluir selecionados" disabled>🗑</button></div>
      <div class="pw-table-wrap"><table class="pw-data-table"><thead><tr><th class="pw-select-cell">Sel.</th><th class="pw-sortable-column" data-sort-key="active">Ativo<span class="pw-sort-indicator" data-sort-indicator="active"></span></th><th class="pw-sortable-column" data-sort-key="code">Código<span class="pw-sort-indicator" data-sort-indicator="code"></span></th><th class="pw-sortable-column" data-sort-key="description">Descrição<span class="pw-sort-indicator" data-sort-indicator="description"></span></th><th class="pw-sortable-column" data-sort-key="category">Categoria<span class="pw-sort-indicator" data-sort-indicator="category"></span></th><th class="pw-sortable-column" data-sort-key="unit">Unidade<span class="pw-sort-indicator" data-sort-indicator="unit"></span></th><th class="pw-sortable-column" data-sort-key="cost">Custo<span class="pw-sort-indicator" data-sort-indicator="cost"></span></th><th class="pw-sortable-column" data-sort-key="price">Venda<span class="pw-sort-indicator" data-sort-indicator="price"></span></th></tr></thead><tbody id="pw-products-consult-body"></tbody></table></div>
    </section>

    <section class="pw-system-view pw-consultation" data-view="categories">
      <div class="pw-consultation-header"><input class="pw-consultation-search" id="pw-categories-consult-search" type="search" placeholder="Pesquisar categoria"></div>
      <div class="pw-bulk-toolbar" data-bulk-toolbar="category"><label class="pw-check-row"><input type="checkbox" data-select-all="category"> Selecionar todos</label><span class="pw-selected-count" data-selected-count="category">0 selecionados</span><button class="pw-btn pw-btn-soft pw-btn-icon pw-tooltip" type="button" data-bulk-edit="category" data-tooltip="Editar selecionados" title="Editar selecionados" aria-label="Editar selecionados" disabled>✎</button><button class="pw-btn pw-btn-danger pw-btn-icon pw-tooltip" type="button" data-bulk-delete="category" data-tooltip="Excluir selecionados" title="Excluir selecionados" aria-label="Excluir selecionados" disabled>🗑</button></div>
      <div class="pw-table-wrap"><table class="pw-data-table"><thead><tr><th class="pw-select-cell">Sel.</th><th class="pw-sortable-column" data-sort-key="code">Código<span class="pw-sort-indicator" data-sort-indicator="code"></span></th><th class="pw-sortable-column" data-sort-key="name">Categoria<span class="pw-sort-indicator" data-sort-indicator="name"></span></th><th class="pw-sortable-column" data-sort-key="active">Situação<span class="pw-sort-indicator" data-sort-indicator="active"></span></th></tr></thead><tbody id="pw-categories-consult-body"></tbody></table></div>
    </section>

    <section class="pw-system-view pw-consultation" data-view="units">
      <div class="pw-consultation-header"><input class="pw-consultation-search" id="pw-units-consult-search" type="search" placeholder="Pesquisar unidade"></div>
      <div class="pw-bulk-toolbar" data-bulk-toolbar="unit"><label class="pw-check-row"><input type="checkbox" data-select-all="unit"> Selecionar todos</label><span class="pw-selected-count" data-selected-count="unit">0 selecionados</span><button class="pw-btn pw-btn-soft pw-btn-icon pw-tooltip" type="button" data-expense-print="unit" data-tooltip="Imprimir selecionados ou todos os filtrados" aria-label="Imprimir relatório">⎙</button><button class="pw-btn pw-btn-soft pw-btn-icon pw-tooltip" type="button" data-bulk-edit="unit" data-tooltip="Editar selecionados" title="Editar selecionados" aria-label="Editar selecionados" disabled>✎</button><button class="pw-btn pw-btn-danger pw-btn-icon pw-tooltip" type="button" data-bulk-delete="unit" data-tooltip="Excluir selecionados" title="Excluir selecionados" aria-label="Excluir selecionados" disabled>🗑</button></div>
      <div class="pw-table-wrap"><table class="pw-data-table"><thead><tr><th class="pw-select-cell">Sel.</th><th class="pw-sortable-column" data-sort-key="code">Código<span class="pw-sort-indicator" data-sort-indicator="code"></span></th><th class="pw-sortable-column" data-sort-key="name">Unidade<span class="pw-sort-indicator" data-sort-indicator="name"></span></th><th class="pw-sortable-column" data-sort-key="symbol">Sigla<span class="pw-sort-indicator" data-sort-indicator="symbol"></span></th><th class="pw-sortable-column" data-sort-key="active">Situação<span class="pw-sort-indicator" data-sort-indicator="active"></span></th></tr></thead><tbody id="pw-units-consult-body"></tbody></table></div>
    </section>

    <section class="pw-system-view pw-consultation" data-view="order-origins">
      <div class="pw-consultation-header"><input class="pw-consultation-search" id="pw-order-origins-consult-search" type="search" placeholder="Pesquisar origem"></div>
      <div class="pw-bulk-toolbar" data-bulk-toolbar="orderOrigin"><label class="pw-check-row"><input type="checkbox" data-select-all="orderOrigin"> Selecionar todos</label><span class="pw-selected-count" data-selected-count="orderOrigin">0 selecionados</span><button class="pw-btn pw-btn-soft pw-btn-icon pw-tooltip" type="button" data-bulk-edit="orderOrigin" data-tooltip="Editar selecionados" title="Editar selecionados" aria-label="Editar selecionados" disabled>✎</button><button class="pw-btn pw-btn-danger pw-btn-icon pw-tooltip" type="button" data-bulk-delete="orderOrigin" data-tooltip="Excluir selecionados" title="Excluir selecionados" aria-label="Excluir selecionados" disabled>🗑</button></div>
      <div class="pw-table-wrap"><table class="pw-data-table"><thead><tr><th class="pw-select-cell">Sel.</th><th class="pw-sortable-column" data-sort-key="code">Código<span class="pw-sort-indicator" data-sort-indicator="code"></span></th><th class="pw-sortable-column" data-sort-key="name">Origem do pedido<span class="pw-sort-indicator" data-sort-indicator="name"></span></th><th class="pw-sortable-column" data-sort-key="marketplace">Marketplace<span class="pw-sort-indicator" data-sort-indicator="marketplace"></span></th><th class="pw-sortable-column" data-sort-key="active">Situação<span class="pw-sort-indicator" data-sort-indicator="active"></span></th></tr></thead><tbody id="pw-order-origins-consult-body"></tbody></table></div>
    </section>

    <section class="pw-system-view pw-consultation" data-view="suppliers">
      <div class="pw-consultation-header"><input class="pw-consultation-search" id="pw-suppliers-consult-search" type="search" placeholder="Pesquisar nome, telefone ou documento"></div>
      <div class="pw-bulk-toolbar" data-bulk-toolbar="supplier"><label class="pw-check-row"><input type="checkbox" data-select-all="supplier"> Selecionar todos</label><span class="pw-selected-count" data-selected-count="supplier">0 selecionados</span><button class="pw-btn pw-btn-soft pw-btn-icon pw-tooltip" type="button" data-expense-print="supplier" data-tooltip="Imprimir selecionados ou todos os filtrados" aria-label="Imprimir relatório">⎙</button><button class="pw-btn pw-btn-soft pw-btn-icon pw-tooltip" type="button" data-bulk-edit="supplier" data-tooltip="Editar selecionados" title="Editar selecionados" aria-label="Editar selecionados" disabled>✎</button><button class="pw-btn pw-btn-danger pw-btn-icon pw-tooltip" type="button" data-bulk-delete="supplier" data-tooltip="Excluir selecionados" title="Excluir selecionados" aria-label="Excluir selecionados" disabled>🗑</button></div>
      <div class="pw-table-wrap"><table class="pw-data-table"><thead><tr><th class="pw-select-cell">Sel.</th><th class="pw-sortable-column" data-sort-key="code">Código<span class="pw-sort-indicator" data-sort-indicator="code"></span></th><th class="pw-sortable-column" data-sort-key="name">Fornecedor<span class="pw-sort-indicator" data-sort-indicator="name"></span></th><th class="pw-sortable-column" data-sort-key="supplierType">Tipo<span class="pw-sort-indicator" data-sort-indicator="supplierType"></span></th><th class="pw-sortable-column" data-sort-key="contact">Contato<span class="pw-sort-indicator" data-sort-indicator="contact"></span></th><th class="pw-sortable-column" data-sort-key="phone">Telefone<span class="pw-sort-indicator" data-sort-indicator="phone"></span></th><th class="pw-sortable-column" data-sort-key="document">Documento<span class="pw-sort-indicator" data-sort-indicator="document"></span></th><th class="pw-sortable-column" data-sort-key="active">Situação<span class="pw-sort-indicator" data-sort-indicator="active"></span></th></tr></thead><tbody id="pw-suppliers-consult-body"></tbody></table></div>
    </section>

    <section class="pw-system-view pw-consultation" data-view="payment-methods">
      <div class="pw-consultation-header"><input class="pw-consultation-search" id="pw-payment-methods-consult-search" type="search" placeholder="Pesquisar código ou nome"></div>
      <div class="pw-bulk-toolbar" data-bulk-toolbar="paymentMethod"><label class="pw-check-row"><input type="checkbox" data-select-all="paymentMethod"> Selecionar todos</label><span class="pw-selected-count" data-selected-count="paymentMethod">0 selecionados</span><button class="pw-btn pw-btn-soft pw-btn-icon pw-tooltip" type="button" data-bulk-edit="paymentMethod" data-tooltip="Editar selecionados" title="Editar selecionados" aria-label="Editar selecionados" disabled>✎</button><button class="pw-btn pw-btn-danger pw-btn-icon pw-tooltip" type="button" data-bulk-delete="paymentMethod" data-tooltip="Excluir selecionados" title="Excluir selecionados" aria-label="Excluir selecionados" disabled>🗑</button></div>
      <div class="pw-table-wrap"><table class="pw-data-table"><thead><tr><th class="pw-select-cell">Sel.</th><th class="pw-sortable-column" data-sort-key="code">Código<span class="pw-sort-indicator" data-sort-indicator="code"></span></th><th class="pw-sortable-column" data-sort-key="name">Forma de pagamento<span class="pw-sort-indicator" data-sort-indicator="name"></span></th><th class="pw-sortable-column" data-sort-key="active">Situação<span class="pw-sort-indicator" data-sort-indicator="active"></span></th></tr></thead><tbody id="pw-payment-methods-consult-body"></tbody></table></div>
    </section>

    <section class="pw-system-view pw-consultation" data-view="arts">
      <div class="pw-consultation-header"><div class="pw-arts-filter-controls"><input class="pw-consultation-search" id="pw-arts-search" type="search" placeholder="Código, cliente, pedido, produto ou arquivo"><label class="pw-check-row"><input id="pw-arts-hide-finalized" type="checkbox" checked> Não mostrar pedidos finalizados</label></div></div>
      <div class="pw-bulk-toolbar" data-bulk-toolbar="arts"><label class="pw-check-row"><input type="checkbox" data-select-all="arts"> Selecionar todos</label><span class="pw-selected-count" data-selected-count="arts">0 selecionados</span><button class="pw-btn pw-btn-soft" id="pw-arts-select-unmounted" type="button">☐ Selecionar não montados</button></div>
      <div class="pw-arts-layout">
        <div class="pw-table-wrap"><table class="pw-data-table"><thead><tr><th class="pw-select-cell">Sel.</th><th>Código da arte</th><th>Pedido</th><th>Cliente</th><th>Produto</th><th>Arquivo</th><th>Montagem</th></tr></thead><tbody id="pw-arts-body"></tbody></table></div>
        <aside class="pw-art-preview" id="pw-art-preview">
          <div class="pw-art-preview-empty">Selecione uma arte para visualizar.</div>
        </aside>
      </div>
    </section>

    <section class="pw-system-view pw-consultation" data-view="dtf-simulator">
      <div class="pw-consultation-header pw-dtf-sim-header">
        <div class="pw-field pw-col-4 pw-dtf-sim-client-field" id="pw-dtf-sim-client-field">
          <label for="pw-dtf-sim-client-search">Cliente (opcional)</label>
          <div class="pw-search-wrap">
            <input id="pw-dtf-sim-client-search" type="search" autocomplete="off" placeholder="Buscar por nome ou telefone">
            <div id="pw-dtf-sim-client-results" class="pw-search-results" role="listbox"></div>
          </div>
        </div>
        <div class="pw-dtf-sim-client-selected" id="pw-dtf-sim-client-selected" hidden>
          <span id="pw-dtf-sim-client-selected-name"></span>
          <button type="button" class="pw-btn pw-btn-soft pw-btn-icon" id="pw-dtf-sim-client-clear" title="Remover cliente e voltar à seleção manual">✕</button>
        </div>
        <div class="pw-field pw-col-3 pw-dtf-sim-customer-field"><label for="pw-dtf-sim-customer-type">Tipo de cliente</label><select id="pw-dtf-sim-customer-type"><option value="direto">Direto</option><option value="revenda">Revenda</option></select></div>
      </div>
      <div class="pw-settings-tabs" role="tablist" aria-label="Formas de simular o valor">
        <button class="pw-active" type="button" data-dtf-sim-tab="pdf">Calcular pelo PDF</button>
        <button type="button" data-dtf-sim-tab="height">Calcular por altura da folha</button>
        <button type="button" data-dtf-sim-tab="quantity">Quantos adesivos na folha?</button>
        <button type="button" data-dtf-sim-tab="size">Altura da folha?</button>
        <button type="button" data-dtf-sim-tab="table">Tabela por faixas</button>
      </div>

      <div class="pw-settings-panel pw-active pw-dtf-sim-panel" data-dtf-sim-panel="pdf">
        <p class="pw-dtf-sim-hint">Envie o PDF só para medir a altura automaticamente — o servidor apaga o arquivo assim que lê a medida, nada fica salvo. O arquivo continua selecionado nesta tela até você trocar ou sair, então dá pra mudar o tipo de cliente e recalcular sem enviar de novo.</p>
        <div class="pw-grid">
          <div class="pw-field pw-col-4"><label for="pw-dtf-sim-pdf-file">Arquivo PDF</label><input id="pw-dtf-sim-pdf-file" type="file" accept="application/pdf"></div>
        </div>
        <button type="button" class="pw-btn pw-btn-primary" id="pw-dtf-sim-pdf-run">Medir e calcular</button>
        <button type="button" class="pw-btn pw-btn-soft" id="pw-dtf-sim-pdf-quote" disabled>📄 Gerar orçamento em PDF</button>
        <div class="pw-dtf-sim-pdf-preview" id="pw-dtf-sim-pdf-preview" hidden><img id="pw-dtf-sim-pdf-preview-img" alt="Miniatura do arquivo enviado"></div>
        <div class="pw-dtf-sim-result" id="pw-dtf-sim-pdf-result" hidden></div>
      </div>

      <div class="pw-settings-panel pw-dtf-sim-panel" data-dtf-sim-panel="height">
        <p class="pw-dtf-sim-hint">Informe a altura (comprimento) já conhecida da arte para calcular o valor.</p>
        <div class="pw-grid">
          <div class="pw-field pw-col-3"><label for="pw-dtf-sim-height">Altura (cm)</label><input id="pw-dtf-sim-height" type="number" min="0" step="0.1"></div>
        </div>
        <button type="button" class="pw-btn pw-btn-primary" id="pw-dtf-sim-height-run">Calcular</button>
        <button type="button" class="pw-btn pw-btn-soft" id="pw-dtf-sim-height-quote" disabled>📄 Gerar orçamento em PDF</button>
        <div class="pw-dtf-sim-result" id="pw-dtf-sim-height-result" hidden></div>
      </div>

      <div class="pw-settings-panel pw-dtf-sim-panel" data-dtf-sim-panel="quantity">
        <p class="pw-dtf-sim-hint">Informe a largura e a altura de cada adesivo e quantos você deseja: o sistema testa a peça na horizontal e girada 90° e usa a que ocupar menos altura de folha, calcula quantos adesivos cabem de fato (arredondando pra fileira cheia) e o valor final. A largura da folha é sempre 28 cm.</p>
        <div class="pw-grid">
          <div class="pw-field pw-col-3"><label for="pw-dtf-sim-qty-width">Largura do adesivo (cm)</label><input id="pw-dtf-sim-qty-width" type="number" min="0" step="0.1" placeholder="Ex.: 5"></div>
          <div class="pw-field pw-col-3"><label for="pw-dtf-sim-qty-height">Altura do adesivo (cm)</label><input id="pw-dtf-sim-qty-height" type="number" min="0" step="0.1" placeholder="Ex.: 5"></div>
          <div class="pw-field pw-col-3"><label for="pw-dtf-sim-qty-amount">Quantidade desejada de adesivos</label><input id="pw-dtf-sim-qty-amount" type="number" min="1" step="1"></div>
          <div class="pw-field pw-col-3"><label for="pw-dtf-sim-qty-gap">Espaço entre adesivos (cm)</label><input id="pw-dtf-sim-qty-gap" type="number" min="0" step="0.1" value="0.5"></div>
        </div>
        <button type="button" class="pw-btn pw-btn-primary" id="pw-dtf-sim-qty-run">Calcular</button>
        <button type="button" class="pw-btn pw-btn-soft" id="pw-dtf-sim-qty-quote" disabled>📄 Gerar orçamento em PDF</button>
        <div class="pw-dtf-sim-result" id="pw-dtf-sim-qty-result" hidden></div>
        <div class="pw-dtf-sim-preview" id="pw-dtf-sim-qty-preview" hidden></div>
      </div>

      <div class="pw-settings-panel pw-dtf-sim-panel" data-dtf-sim-panel="size">
        <p class="pw-dtf-sim-hint">Informe a largura e a altura do adesivo e a altura de folha que pretende comprar: o sistema testa a peça na horizontal e girada 90° e usa a que render mais adesivos, calcula a medida final exata (arredondada pra fileira cheia) e o valor. A largura da folha é sempre 28 cm.</p>
        <div class="pw-grid">
          <div class="pw-field pw-col-3"><label for="pw-dtf-sim-size-width">Largura do adesivo (cm)</label><input id="pw-dtf-sim-size-width" type="number" min="0" step="0.1" placeholder="Ex.: 5"></div>
          <div class="pw-field pw-col-3"><label for="pw-dtf-sim-size-height">Altura do adesivo (cm)</label><input id="pw-dtf-sim-size-height" type="number" min="0" step="0.1" placeholder="Ex.: 5"></div>
          <div class="pw-field pw-col-3"><label for="pw-dtf-sim-size-length">Altura da folha (cm)</label><input id="pw-dtf-sim-size-length" type="number" min="0" step="0.1"></div>
          <div class="pw-field pw-col-3"><label for="pw-dtf-sim-size-gap">Espaço entre adesivos (cm)</label><input id="pw-dtf-sim-size-gap" type="number" min="0" step="0.1" value="0.5"></div>
        </div>
        <button type="button" class="pw-btn pw-btn-primary" id="pw-dtf-sim-size-run">Calcular</button>
        <button type="button" class="pw-btn pw-btn-soft" id="pw-dtf-sim-size-quote" disabled>📄 Gerar orçamento em PDF</button>
        <div class="pw-dtf-sim-result" id="pw-dtf-sim-size-result" hidden></div>
        <div class="pw-dtf-sim-preview" id="pw-dtf-sim-size-preview" hidden></div>
      </div>

      <div class="pw-settings-panel pw-dtf-sim-panel" data-dtf-sim-panel="table">
        <p class="pw-dtf-sim-hint">Faixas de preço cadastradas agora em PrintWay → DTF UV — busca a tabela atual toda vez que abre esta aba.</p>
        <button type="button" class="pw-btn pw-btn-soft" id="pw-dtf-sim-table-refresh">🔄 Atualizar</button>
        <div class="pw-table-wrap"><table class="pw-data-table"><thead><tr><th>Medida</th><th>Altura mínima (cm)</th><th>Altura máxima (cm)</th><th>Preço Cliente (R$)</th><th>Preço Revenda (R$)</th></tr></thead><tbody id="pw-dtf-sim-table-body"><tr><td colspan="5" class="pw-empty-table">Abra esta aba pra carregar a tabela.</td></tr></tbody></table></div>
      </div>
    </section>

    <section class="pw-system-view pw-consultation" data-view="marketplace-ml">
      <div class="pw-settings-tabs" role="tablist" aria-label="Mercado Livre">
        <button class="pw-active" type="button" data-mktp-tab="financeiro">Financeiro</button>
        <button type="button" data-mktp-tab="mensagens">Mensagens</button>
        <button type="button" data-mktp-tab="reputacao">Reputação</button>
        <button type="button" data-mktp-tab="configuracao">Configuração</button>
      </div>

      <div class="pw-settings-panel" data-mktp-panel="mensagens">
        <div class="pw-section">
          <div class="pw-section-title"><div><h3>Perguntas sem resposta</h3><p>Perguntas feitas nos anúncios (pré-venda). Atualiza sozinho enquanto esta aba estiver aberta.</p></div><small class="pw-mktp-updated-at" id="pw-mktp-questions-updated"></small></div>
          <div id="pw-mktp-questions-list" class="pw-mktp-list"><p class="pw-empty-table">Carregando…</p></div>
        </div>
        <div class="pw-section">
          <div class="pw-section-title"><div><h3>Mensagens não lidas</h3><p>Conversas de pedidos com mensagem nova do comprador. Atualiza sozinho enquanto esta aba estiver aberta.</p></div><small class="pw-mktp-updated-at" id="pw-mktp-messages-updated"></small></div>
          <div id="pw-mktp-messages-list" class="pw-mktp-list"><p class="pw-empty-table">Carregando…</p></div>
        </div>
      </div>

      <div class="pw-settings-panel pw-active" data-mktp-panel="financeiro">
        <div class="pw-section">
          <div class="pw-mktp-print-header" aria-hidden="true">
            <div><img src="<?php echo esc_url( PW_PERSONALIZADOS_URL . 'assets/antasys-logo-v2.png?v=' . PW_PERSONALIZADOS_VERSION ); ?>" alt="AntaSys"><h1>Relatório financeiro — Mercado Livre</h1><p>Resumo e pedidos pagos no período selecionado.</p></div>
            <div class="pw-mktp-print-meta"><strong id="pw-mktp-print-range"></strong><span id="pw-mktp-print-generated"></span></div>
          </div>
          <div class="pw-section-title pw-mktp-no-print"><div><h3>Resumo financeiro</h3><p>Estimativa com base nos pedidos pagos no período: não desconta frete adicional nem promoções.</p></div>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
              <select id="pw-mktp-financial-period"><option value="today">Hoje</option><option value="month">Este mês</option><option value="year">Este ano</option><option value="7">Últimos 7 dias</option><option value="30" selected>Últimos 30 dias</option><option value="60">Últimos 60 dias</option><option value="90">Últimos 90 dias</option><option value="custom">Período personalizado</option></select>
              <input id="pw-mktp-financial-from" type="date" disabled>
              <span>até</span>
              <input id="pw-mktp-financial-to" type="date" disabled>
              <div class="pw-mktp-financial-actions">
                <button class="pw-btn pw-btn-soft" id="pw-mktp-financial-refresh" type="button">🔄 Atualizar</button>
                <button class="pw-btn pw-btn-primary" id="pw-mktp-financial-print" type="button">⎙ Imprimir</button>
              </div>
            </div>
          </div>
          <div id="pw-mktp-financial-body" class="pw-dashboard-grid"><p class="pw-empty-table">Abra esta aba pra carregar.</p></div>
          <p class="pw-mktp-records-count pw-tooltip" data-tooltip="Total de pedidos pagos em todo o histórico da conta / total de pedidos pagos considerando o período selecionado acima"><strong>Registros:</strong> <span id="pw-mktp-financial-records-value">—/—</span></p>
          <input type="file" id="pw-mktp-financial-xml-input" accept=".xml,application/xml,text/xml" hidden>
          <div class="pw-table-wrap"><table class="pw-data-table"><thead><tr><th>Data</th><th>Comprador</th><th>Itens</th><th>Total</th><th class="pw-tooltip" data-tooltip="Comissão do Mercado Livre — não chega até você">Tarifa</th><th class="pw-tooltip" data-tooltip="Custo de envio descontado do vendedor — não chega até você">Frete</th><th class="pw-tooltip" data-tooltip="Total menos Tarifa e Frete — o que efetivamente chega até você">Bruto</th><th>Status</th><th class="pw-mktp-no-print">Nota Fiscal</th></tr></thead><tbody id="pw-mktp-financial-orders-body"><tr><td colspan="9" class="pw-empty-table">Abra esta aba pra carregar.</td></tr></tbody><tfoot><tr><td colspan="3" class="pw-tooltip" data-tooltip="Soma de TODOS os pedidos pagos no período selecionado — não só os exibidos acima, caso haja mais de 100.">Total geral</td><td id="pw-mktp-financial-total-geral">—</td><td id="pw-mktp-financial-fee-geral">—</td><td id="pw-mktp-financial-shipping-geral">—</td><td id="pw-mktp-financial-net-geral">—</td><td></td><td class="pw-mktp-no-print"></td></tr></tfoot></table></div>
        </div>
      </div>

      <div class="pw-settings-panel" data-mktp-panel="reputacao">
        <div class="pw-section">
          <div class="pw-section-title"><div><h3>Reputação da conta</h3><p>Termômetro e métricas de qualidade usados pelo Mercado Livre.</p></div><button class="pw-btn pw-btn-soft" id="pw-mktp-reputation-refresh" type="button">🔄 Atualizar</button></div>
          <div id="pw-mktp-reputation-body"><p class="pw-empty-table">Abra esta aba pra carregar.</p></div>
        </div>
      </div>

      <div class="pw-settings-panel" data-mktp-panel="configuracao">
        <div class="pw-section">
          <div class="pw-section-title">
            <div><h3>Respostas prontas</h3><p>Cadastre perguntas-modelo e respostas. A IA analisa cada pergunta nova e sugere automaticamente a mais adequada — ou usa palavras-chave como fallback. O sistema também aprende com as respostas que você digitar.</p></div>
            <button type="button" class="pw-btn pw-btn-soft" id="pw-ml-add-saved-response">＋ Adicionar</button>
          </div>
          <div id="pw-ml-saved-response-form" hidden>
            <div class="pw-grid">
              <div class="pw-field pw-col-12"><label for="pw-ml-saved-response-q">Pergunta-modelo <small>(como você identifica o assunto)</small></label><input type="text" id="pw-ml-saved-response-q" maxlength="200" placeholder="Ex.: Como envio o arquivo?"></div>
              <div class="pw-field pw-col-12"><label for="pw-ml-saved-response-a">Resposta pronta</label><textarea id="pw-ml-saved-response-a" rows="5" maxlength="2000" placeholder="Texto completo da resposta que será enviada ao cliente…"></textarea></div>
            </div>
            <div class="pw-order-bottom-actions">
              <button type="button" class="pw-btn pw-btn-primary" id="pw-ml-save-response-btn">✓ Salvar resposta</button>
              <button type="button" class="pw-btn pw-btn-soft" id="pw-ml-cancel-response-btn">Cancelar</button>
              <input type="hidden" id="pw-ml-saved-response-edit-id">
            </div>
          </div>
          <div id="pw-ml-saved-responses-list"><p class="pw-empty-table">Abra esta aba pra carregar.</p></div>
        </div>
        <div class="pw-section">
          <div class="pw-section-title"><div><h3>Sugestão por IA (Claude)</h3><p>Quando configurada, a IA analisa semanticamente a pergunta do cliente. Sem chave, usa correspondência por palavras-chave.</p></div></div>
          <div class="pw-field">
            <label for="pw-ml-ai-key-input">Chave da API Anthropic</label>
            <input type="password" id="pw-ml-ai-key-input" placeholder="sk-ant-…" autocomplete="off" style="max-width:420px">
            <p class="pw-field-hint">Obtenha em <a href="https://console.anthropic.com/" target="_blank" rel="noopener">console.anthropic.com</a>. Deixe em branco para remover.</p>
          </div>
          <button type="button" class="pw-btn pw-btn-primary" id="pw-ml-ai-key-save-btn">Salvar chave</button>
          <span id="pw-ml-ai-key-status" style="margin-left:10px;font-size:.9rem;color:var(--pw-muted)"></span>
        </div>
      </div>
    </section>

    <section class="pw-system-view pw-consultation" data-view="marketplace-shopee">
      <div class="pw-section">
        <div class="pw-section-title">
          <div><h3>Shopee</h3><p>Mensagens, pedidos e status da conexão com a Shopee Open Platform.</p></div>
          <button class="pw-btn pw-btn-soft" id="pw-shopee-refresh" type="button">🔄 Atualizar</button>
        </div>
        <nav class="pw-settings-tabs" role="tablist" aria-label="Shopee">
          <button class="pw-active" data-shopee-tab="status" type="button" role="tab" aria-selected="true">Status</button>
          <button data-shopee-tab="mensagens" type="button" role="tab" aria-selected="false" id="pw-shopee-tab-mensagens">Mensagens</button>
          <button data-shopee-tab="pedidos" type="button" role="tab" aria-selected="false">Pedidos</button>
        </nav>

        <div class="pw-settings-panel pw-active" data-shopee-panel="status">
          <div id="pw-shopee-status-body"><p class="pw-empty-table">Carregando…</p></div>
        </div>

        <div class="pw-settings-panel" data-shopee-panel="mensagens">
          <div class="pw-section-title" style="margin-top:16px"><div><h3>Conversas com compradores</h3><p>Conversas com mensagens não lidas. Atualiza sozinho enquanto esta aba estiver aberta.</p></div><small class="pw-mktp-updated-at" id="pw-shopee-msgs-updated"></small></div>
          <div id="pw-shopee-conversations-list" class="pw-mktp-list"><p class="pw-empty-table">Carregando…</p></div>
        </div>

        <div class="pw-settings-panel" data-shopee-panel="pedidos">
          <div style="margin:12px 0 8px;display:flex;align-items:center;gap:10px">
            <label style="font-size:13px;color:var(--pw-muted)">Período:
              <select id="pw-shopee-orders-days" class="pw-input" style="margin-left:6px">
                <option value="7">Últimos 7 dias</option>
                <option value="15" selected>Últimos 15 dias</option>
                <option value="30">Últimos 30 dias</option>
                <option value="60">Últimos 60 dias</option>
                <option value="90">Últimos 90 dias</option>
              </select>
            </label>
            <button class="pw-btn pw-btn-soft" id="pw-shopee-orders-load" type="button">Carregar</button>
          </div>
          <div id="pw-shopee-orders-body"><p class="pw-empty-table">Selecione o período e clique em Carregar.</p></div>
        </div>
      </div>
    </section>

    <section class="pw-system-view pw-consultation pw-admin-only" data-view="nfe">
      <div class="pw-consultation-header">
        <div><h3>Cadastro fiscal da NF-e</h3><p>Dados do emitente usados na preparação da NF-e e do DANFE. O XML só pode ser transmitido após assinatura e autorização na SEFAZ.</p></div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center"><button class="pw-btn pw-btn-soft" id="pw-nfe-open-generator" type="button">NF-e de um pedido</button><button class="pw-btn pw-btn-primary" id="pw-nfe-save" type="button">✓ Salvar dados fiscais</button></div>
      </div>
      <form id="pw-nfe-form" class="pw-nfe-form" novalidate>
        <div class="pw-grid">
          <div class="pw-field pw-col-3"><label class="pw-required" for="pw-nfe-cnpj">CNPJ</label><input id="pw-nfe-cnpj" type="text" inputmode="numeric" maxlength="18" required></div>
          <div class="pw-field pw-col-5"><label class="pw-required" for="pw-nfe-legal-name">Razão social</label><input id="pw-nfe-legal-name" type="text" required></div>
          <div class="pw-field pw-col-4"><label for="pw-nfe-trade-name">Nome fantasia</label><input id="pw-nfe-trade-name" type="text"></div>
          <div class="pw-field pw-col-3"><label class="pw-required" for="pw-nfe-ie">Inscrição estadual</label><input id="pw-nfe-ie" type="text" required></div>
          <div class="pw-field pw-col-3"><label class="pw-required" for="pw-nfe-crt">Regime tributário (CRT)</label><input id="pw-nfe-crt" type="text" inputmode="numeric" maxlength="2" required></div>
          <div class="pw-field pw-col-3"><label class="pw-required" for="pw-nfe-phone">Telefone</label><input id="pw-nfe-phone" type="text" inputmode="tel" required></div>
        </div>
        <h4 style="margin:18px 0 8px;font-size:13px;color:var(--pw-muted)">Endereço do emitente</h4>
        <div class="pw-grid">
          <div class="pw-field pw-col-5"><label class="pw-required" for="pw-nfe-street">Logradouro</label><input id="pw-nfe-street" type="text" required></div>
          <div class="pw-field pw-col-2"><label class="pw-required" for="pw-nfe-number">Número</label><input id="pw-nfe-number" type="text" required></div>
          <div class="pw-field pw-col-2"><label for="pw-nfe-complement">Complemento</label><input id="pw-nfe-complement" type="text"></div>
          <div class="pw-field pw-col-3"><label class="pw-required" for="pw-nfe-neighborhood">Bairro</label><input id="pw-nfe-neighborhood" type="text" required></div>
          <div class="pw-field pw-col-3"><label class="pw-required" for="pw-nfe-city">Município</label><input id="pw-nfe-city" type="text" required></div>
          <div class="pw-field pw-col-2"><label class="pw-required" for="pw-nfe-city-code">Código IBGE</label><input id="pw-nfe-city-code" type="text" inputmode="numeric" maxlength="7" required></div>
          <div class="pw-field pw-col-1"><label class="pw-required" for="pw-nfe-state">UF</label><input id="pw-nfe-state" type="text" maxlength="2" required></div>
          <div class="pw-field pw-col-2"><label class="pw-required" for="pw-nfe-cep">CEP</label><input id="pw-nfe-cep" type="text" inputmode="numeric" maxlength="9" required></div>
        </div>
        <h4 style="margin:18px 0 8px;font-size:13px;color:var(--pw-muted)">Numeração e operação</h4>
        <div class="pw-grid">
          <div class="pw-field pw-col-2"><label class="pw-required" for="pw-nfe-model">Modelo</label><input id="pw-nfe-model" type="text" value="55" required></div>
          <div class="pw-field pw-col-2"><label class="pw-required" for="pw-nfe-series">Série do AntaSys</label><input id="pw-nfe-series" list="pw-nfe-series-options" type="number" min="1" max="999" required><datalist id="pw-nfe-series-options"><option value="1"></option><option value="2"></option><option value="3"></option><option value="4"></option><option value="5"></option><option value="10"></option></datalist></div>
          <div class="pw-field pw-col-3"><label class="pw-required" for="pw-nfe-next-number">Próximo número nesta série</label><input id="pw-nfe-next-number" type="number" min="1" required></div>
          <div class="pw-field pw-col-5"><label class="pw-required" for="pw-nfe-nature">Natureza da operação</label><input id="pw-nfe-nature" type="text" required></div>
          <div class="pw-field pw-col-3"><label class="pw-required" for="pw-nfe-environment">Ambiente</label><select id="pw-nfe-environment"><option value="1">Produção</option><option value="2">Homologação (testes)</option></select></div>
          <div class="pw-field pw-col-3"><label class="pw-required" for="pw-nfe-freight">Frete padrão</label><select id="pw-nfe-freight"><option value="9">Sem frete</option><option value="0">Emitente</option><option value="1">Destinatário</option><option value="2">Terceiros</option></select></div>
          <div class="pw-field pw-col-3"><label for="pw-nfe-process">Processo emissor</label><input id="pw-nfe-process" type="text" value="ACBrNFe"></div>
          <div class="pw-field pw-col-3"><label for="pw-nfe-ibpt">Fonte/observação de tributos</label><input id="pw-nfe-ibpt" type="text" value="IBPT"></div>
        </div>
        <h4 style="margin:18px 0 8px;font-size:13px;color:var(--pw-muted)">Padrões fiscais dos produtos</h4>
        <div class="pw-grid">
          <div class="pw-field pw-col-3"><label for="pw-nfe-default-ncm">NCM padrão</label><input id="pw-nfe-default-ncm" type="text" inputmode="numeric" maxlength="8"></div>
          <div class="pw-field pw-col-3"><label for="pw-nfe-default-cfop">CFOP padrão</label><input id="pw-nfe-default-cfop" type="text" inputmode="numeric" maxlength="4"></div>
          <div class="pw-field pw-col-2"><label for="pw-nfe-default-unit">Unidade</label><input id="pw-nfe-default-unit" type="text" maxlength="6"></div>
          <div class="pw-field pw-col-2"><label for="pw-nfe-default-origin">Origem</label><input id="pw-nfe-default-origin" type="text" inputmode="numeric" maxlength="1"></div>
          <div class="pw-field pw-col-2"><label for="pw-nfe-default-csosn">CSOSN/CST</label><input id="pw-nfe-default-csosn" type="text" maxlength="4"></div>
          <div class="pw-field pw-col-2"><label for="pw-nfe-default-pis">CST PIS</label><input id="pw-nfe-default-pis" type="text" maxlength="2"></div>
          <div class="pw-field pw-col-2"><label for="pw-nfe-default-cofins">CST COFINS</label><input id="pw-nfe-default-cofins" type="text" maxlength="2"></div>
          <div class="pw-field pw-col-3"><label for="pw-nfe-default-ean">GTIN/EAN padrão</label><input id="pw-nfe-default-ean" type="text" maxlength="14"></div>
          <div class="pw-field pw-col-2"><label for="pw-nfe-default-ibs-cst">CST IBS/CBS</label><input id="pw-nfe-default-ibs-cst" type="text" maxlength="3"></div>
          <div class="pw-field pw-col-3"><label for="pw-nfe-default-ibs-class-trib">Classificação IBS/CBS</label><input id="pw-nfe-default-ibs-class-trib" type="text" maxlength="6"></div>
          <div class="pw-field pw-col-2"><label for="pw-nfe-default-ibs-uf-rate">IBS UF (%)</label><input id="pw-nfe-default-ibs-uf-rate" type="text" inputmode="decimal"></div>
          <div class="pw-field pw-col-2"><label for="pw-nfe-default-cbs-rate">CBS (%)</label><input id="pw-nfe-default-cbs-rate" type="text" inputmode="decimal"></div>
        </div>
        <h4 style="margin:18px 0 8px;font-size:13px;color:var(--pw-muted)">Responsável técnico</h4>
        <div class="pw-grid">
          <div class="pw-field pw-col-3"><label for="pw-nfe-tech-cnpj">CNPJ</label><input id="pw-nfe-tech-cnpj" type="text" inputmode="numeric" maxlength="18"></div>
          <div class="pw-field pw-col-3"><label for="pw-nfe-tech-name">Contato</label><input id="pw-nfe-tech-name" type="text"></div>
          <div class="pw-field pw-col-3"><label for="pw-nfe-tech-email">E-mail</label><input id="pw-nfe-tech-email" type="email"></div>
          <div class="pw-field pw-col-3"><label for="pw-nfe-tech-phone">Telefone</label><input id="pw-nfe-tech-phone" type="text" inputmode="tel"></div>
        </div>
        <p class="pw-field-hint pw-nfe-security-note" style="margin-top:14px"><strong>Validação:</strong> antes de liberar os arquivos, o sistema confere o XML com o leiaute NF-e 4.00 PL_010_V1.30, além da chave, dos documentos e dos totais.<br><strong>Segurança:</strong> o certificado digital A1/A3 e a senha não são armazenados neste formulário. O mesmo A1 válido da empresa poderá ser usado na futura etapa de assinatura e autorização.</p>
      </form>
    </section>

    <section class="pw-system-view pw-consultation" data-view="nfe-pending">
      <div class="pw-consultation-header">
        <div><h3>NF-e — Marketplace</h3><p>Pedidos de Mercado Livre e Shopee. Gere o PDF para emissão manual em software externo e anexe o XML após emitir.</p></div>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
          <label style="display:flex;align-items:center;gap:6px;font-size:13px"><input type="checkbox" id="pw-nfe-select-all"> Selecionar todos</label>
          <button class="pw-btn pw-btn-primary" id="pw-nfe-gen-pdf" type="button" disabled>⎙ Gerar PDF selecionados</button>
        </div>
      </div>
      <div class="pw-table-wrap">
        <table class="pw-data-table">
          <thead><tr>
            <th class="pw-select-cell">Sel.</th>
            <th>Plataforma</th>
            <th>Pedido</th>
            <th>Data</th>
            <th>Cliente</th>
            <th>CPF / CNPJ</th>
            <th>Produtos</th>
            <th>Valor</th>
            <th>NF-e</th>
            <th>Ações</th>
          </tr></thead>
          <tbody id="pw-nfe-pending-body"><tr><td colspan="10" class="pw-table-empty">Carregando…</td></tr></tbody>
        </table>
      </div>
    </section>

    <section class="pw-system-view pw-consultation pw-admin-only" data-view="dtf-costs">
      <div class="pw-consultation-header"><div><h3>Cadastros / Despesas / Novo</h3><p>Registre insumos, anúncios, fornecedores e demais gastos da operação.</p></div></div>
      <div id="pw-dtf-cost-form-home"><form id="pw-dtf-cost-form" class="pw-section" novalidate>
        <div class="pw-expense-tabs" role="tablist" aria-label="Tipo de cadastro de despesa"><button class="pw-expense-tab is-active" type="button" role="tab" aria-selected="true" data-expense-tab="materials">Materiais</button><button class="pw-expense-tab" type="button" role="tab" aria-selected="false" data-expense-tab="ads">Anúncios</button></div>
        <div class="pw-grid">
          <div class="pw-field pw-col-3"><label class="pw-required" for="pw-dtf-cost-date">Data da movimentação</label><input id="pw-dtf-cost-date" type="date" required></div>
          <div class="pw-field pw-col-3"><label class="pw-required" for="pw-dtf-cost-category">Categoria</label><div class="pw-inline-field-control"><select id="pw-dtf-cost-category" required><option value="Tinta">Tinta</option><option value="Filme">Filme</option><option value="Manutenção">Manutenção</option><option value="Peça">Peça</option><option value="Outro">Outro item</option></select><button class="pw-btn pw-btn-soft pw-btn-icon pw-tooltip" type="button" data-registry-open="dtfCategory" data-expense-target="category" data-tooltip="Cadastrar categoria" title="Cadastrar categoria" aria-label="Cadastrar categoria">＋</button></div></div>
          <div class="pw-field pw-col-3"><label class="pw-required" for="pw-dtf-cost-item">Insumo, peça ou serviço</label><div class="pw-inline-field-control"><select id="pw-dtf-cost-item" required></select><button class="pw-btn pw-btn-soft pw-btn-icon pw-tooltip" type="button" data-registry-open="dtfItem" data-expense-target="item" data-tooltip="Cadastrar insumo, peça ou serviço" title="Cadastrar insumo, peça ou serviço" aria-label="Cadastrar insumo, peça ou serviço">＋</button><button class="pw-btn pw-btn-soft pw-btn-icon pw-tooltip pw-dtf-smart-memory-button" id="pw-dtf-smart-memory" type="button" data-tooltip="Lembrança inteligente: preenche os campos com o lançamento mais recente desta categoria e insumo." title="Lembrança inteligente" aria-label="Lembrança inteligente">🧠</button></div></div>
          <div class="pw-field pw-col-3" id="pw-dtf-custom-item-wrap" hidden><label class="pw-required" for="pw-dtf-custom-item">Descrição do outro item</label><input id="pw-dtf-custom-item" type="text" maxlength="100"></div>
          <div class="pw-field pw-col-3"><label class="pw-required" for="pw-dtf-cost-action">Movimentação</label><div class="pw-inline-field-control"><select id="pw-dtf-cost-action" required></select><button class="pw-btn pw-btn-soft pw-btn-icon pw-tooltip" type="button" data-registry-open="dtfMovement" data-expense-target="action" data-tooltip="Cadastrar movimentação" title="Cadastrar movimentação" aria-label="Cadastrar movimentação">＋</button></div></div>
          <div class="pw-field pw-col-2"><label class="pw-required" id="pw-dtf-cost-quantity-label" for="pw-dtf-cost-quantity">Quantidade</label><input id="pw-dtf-cost-quantity" type="number" min="0.01" step="0.01" required></div>
          <div class="pw-field pw-col-3"><label class="pw-required" for="pw-dtf-cost-unit">Unidade</label><div class="pw-inline-field-control"><select id="pw-dtf-cost-unit" required></select><button class="pw-btn pw-btn-soft pw-btn-icon pw-tooltip" type="button" data-registry-open="unit" data-expense-target="unit" data-tooltip="Cadastrar unidade de venda" title="Cadastrar unidade de venda" aria-label="Cadastrar unidade de venda">＋</button></div></div>
          <div class="pw-field pw-col-3"><label class="pw-required" for="pw-dtf-cost-value">Valor total (R$)</label><input id="pw-dtf-cost-value" type="text" inputmode="decimal" placeholder="0,00" required></div>
          <div class="pw-field pw-col-4"><label for="pw-dtf-cost-supplier">Fornecedor / local do gasto</label><div class="pw-inline-field-control"><select id="pw-dtf-cost-supplier"></select><button class="pw-btn pw-btn-soft pw-btn-icon pw-tooltip" type="button" data-registry-open="supplier" data-expense-target="supplier" data-tooltip="Cadastrar fornecedor" title="Cadastrar fornecedor" aria-label="Cadastrar fornecedor">＋</button></div></div>
          <div class="pw-field pw-col-4" id="pw-dtf-custom-supplier-wrap" hidden><label class="pw-required" for="pw-dtf-custom-supplier">Novo fornecedor ou técnico</label><input id="pw-dtf-custom-supplier" type="text" maxlength="120" placeholder="Digite o nome"></div>
          <div class="pw-field pw-col-12"><label for="pw-dtf-cost-notes">Observações</label><textarea id="pw-dtf-cost-notes" placeholder="Lote, motivo da troca, contador da máquina, peça substituída ou outra informação importante"></textarea></div>
        </div>
        <div class="pw-order-bottom-actions"><button class="pw-btn pw-btn-primary" id="pw-save-dtf-cost" type="submit">✓ Registrar despesa</button><button class="pw-btn pw-btn-soft" id="pw-cancel-dtf-cost-edit" type="button" hidden>Cancelar edição</button></div>
      </form></div>

    </section>

    <section class="pw-system-view pw-consultation pw-admin-only" data-view="dtf-items">
      <div class="pw-consultation-header"><input class="pw-consultation-search" type="search" id="pw-dtf-catalog-search" placeholder="Pesquisar"></div>
      
      <div class="pw-bulk-toolbar" data-bulk-toolbar="dtfItem"><label class="pw-check-row"><input type="checkbox" data-select-all="dtfItem" id="pw-dtf-catalog-select-all"> Selecionar todos</label><span class="pw-selected-count" data-selected-count="dtfItem" id="pw-dtf-catalog-selected">0 selecionados</span><button class="pw-btn pw-btn-soft pw-btn-icon pw-tooltip" type="button" data-expense-print="item" data-tooltip="Imprimir selecionados ou todos os filtrados" aria-label="Imprimir relatório">⎙</button><button class="pw-btn pw-btn-soft pw-btn-icon pw-tooltip" type="button" data-bulk-edit="dtfItem" id="pw-dtf-catalog-edit" data-tooltip="Editar selecionados" title="Editar selecionados" aria-label="Editar selecionados" disabled>✎</button><button class="pw-btn pw-btn-danger pw-btn-icon pw-tooltip" type="button" data-bulk-delete="dtfItem" id="pw-dtf-catalog-delete" data-tooltip="Excluir selecionados" title="Excluir selecionados" aria-label="Excluir selecionados" disabled>🗑</button></div>
      <div class="pw-table-wrap"><table class="pw-data-table"><thead><tr><th class="pw-select-cell">Sel.</th><th>Código</th><th>Categoria</th><th>Item</th><th>Unidade sugerida</th><th>Situação</th></tr></thead><tbody id="pw-dtf-catalog-body"></tbody></table></div>
    </section>
    <section class="pw-system-view pw-consultation pw-admin-only" data-view="dtf-categories"><div class="pw-modal-like pw-section"><div class="pw-section-title"><div><h3>Cadastro de categoria de despesa</h3><p>Cadastre e mantenha as categorias disponíveis no formulário de despesas.</p></div></div><div class="pw-action-toolbar"><button class="pw-btn pw-btn-soft pw-btn-icon" type="button" id="pw-dtf-new-category-action" title="Novo cadastro" aria-label="Novo cadastro">＋</button><button class="pw-btn pw-btn-soft pw-btn-icon" type="button" id="pw-dtf-copy-category-action" title="Duplicar categoria" aria-label="Duplicar categoria">▣</button><button class="pw-btn pw-btn-primary pw-btn-icon" type="button" id="pw-dtf-add-category" title="Salvar categoria" aria-label="Salvar categoria">✓</button></div><div class="pw-field"><label for="pw-dtf-new-category">Nome da categoria<span class="pw-required-mark">*</span></label><input id="pw-dtf-new-category" type="text" maxlength="60" placeholder="Ex.: Tinta, Filme, Anúncio"><small class="pw-field-hint">A categoria será exibida no campo Categoria do cadastro de despesas.</small></div><div class="pw-table-wrap"><table class="pw-data-table"><thead><tr><th>Categoria cadastrada</th></tr></thead><tbody id="pw-dtf-category-body"></tbody></table></div></div></section>
    <section class="pw-system-view pw-consultation pw-admin-only" data-view="dtf-units"><div class="pw-consultation-header"><div><h3>Cadastro / Despesas / Unidades sugeridas</h3><p>As unidades sugeridas são editadas nos respectivos itens de despesa.</p></div><button class="pw-btn pw-btn-soft" type="button" data-system-view="dtf-items">Editar unidades</button></div></section>
    <section class="pw-system-view pw-consultation pw-admin-only" data-view="dtf-list" data-list-tab="materials">
      <div class="pw-expense-tabs" role="tablist" aria-label="Tipo de despesa">
        <button class="pw-expense-tab is-active" data-list-tab="materials" type="button" aria-selected="true">Materiais</button>
        <button class="pw-expense-tab" data-list-tab="ads" type="button" aria-selected="false">Anúncios</button>
      </div>
      <div class="pw-consultation-header"><input class="pw-consultation-search" type="search" id="pw-dtf-list-search" placeholder="Pesquisar"></div>
      <div class="pw-period-filter pw-expense-list-filters">
        <label>Data inicial <input id="pw-dtf-list-start" type="date"></label>
        <label>Data final <input id="pw-dtf-list-end" type="date"></label>
        <label class="pw-dtf-list-materials-only">Categoria <select id="pw-dtf-list-category"><option value="">Todas</option></select></label>
        <label class="pw-dtf-list-materials-only">Movimentação <select id="pw-dtf-list-action"><option value="">Todas</option></select></label>
        <label class="pw-dtf-list-materials-only">Item <select id="pw-dtf-list-item"><option value="">Todos</option></select></label>
        <label>Fornecedor <select id="pw-dtf-list-supplier"><option value="">Todos</option></select></label>
        <button class="pw-btn pw-btn-soft" id="pw-dtf-list-clear" type="button">Limpar filtros</button>
      </div>
      <div class="pw-bulk-toolbar" data-bulk-toolbar="dtfCost"><label class="pw-check-row"><input type="checkbox" data-select-all="dtfCost"> Selecionar todos</label><span class="pw-selected-count" data-selected-count="dtfCost">0 selecionados</span><button class="pw-btn pw-btn-soft pw-btn-icon pw-tooltip" type="button" data-expense-print="cost" data-tooltip="Imprimir selecionados ou todos os filtrados" aria-label="Imprimir relatório">⎙</button><button class="pw-btn pw-btn-soft pw-btn-icon pw-tooltip" type="button" data-bulk-edit="dtfCost" data-tooltip="Editar selecionados" title="Editar selecionados" aria-label="Editar selecionados" disabled>✎</button><button class="pw-btn pw-btn-danger pw-btn-icon pw-tooltip" type="button" data-bulk-delete="dtfCost" data-tooltip="Excluir selecionados" title="Excluir selecionados" aria-label="Excluir selecionados" disabled>🗑</button></div>
      <div class="pw-table-wrap"><table class="pw-data-table">
        <thead>
          <tr class="pw-dtf-list-materials-head"><th class="pw-select-cell">Sel.</th><th>Data</th><th>Categoria</th><th>Item</th><th>Movimentação</th><th>Quantidade</th><th>Unidade</th><th>Valor</th><th>Fornecedor</th><th>Observações</th><th>Responsável</th></tr>
          <tr class="pw-dtf-list-ads-head"><th class="pw-select-cell">Sel.</th><th>Data</th><th>Valor</th><th>Fornecedor</th><th>Observações</th><th>Responsável</th></tr>
        </thead>
        <tbody id="pw-dtf-cost-recent"></tbody>
      </table></div>
    </section>
    <section class="pw-system-view pw-consultation pw-admin-only" data-view="dtf-category-report">
      <div class="pw-consultation-header"><input id="pw-dtf-category-report-search" class="pw-consultation-search" type="search" placeholder="Pesquisar categoria"></div>
      <div class="pw-bulk-toolbar" data-bulk-toolbar="dtfCategory"><label class="pw-check-row"><input type="checkbox" data-select-all="dtfCategory"> Selecionar todos</label><span class="pw-selected-count" data-selected-count="dtfCategory">0 selecionados</span><button class="pw-btn pw-btn-soft pw-btn-icon pw-tooltip" type="button" data-expense-print="category" data-tooltip="Imprimir selecionados ou todos os filtrados" aria-label="Imprimir relatório">⎙</button><button class="pw-btn pw-btn-soft pw-btn-icon pw-tooltip" type="button" data-bulk-edit="dtfCategory" data-tooltip="Editar selecionados" title="Editar selecionados" aria-label="Editar selecionados" disabled>✎</button><button class="pw-btn pw-btn-danger pw-btn-icon pw-tooltip" type="button" data-bulk-delete="dtfCategory" data-tooltip="Excluir selecionados" title="Excluir selecionados" aria-label="Excluir selecionados" disabled>🗑</button></div>
      <div class="pw-table-wrap"><table class="pw-data-table"><thead><tr><th class="pw-select-cell">Sel.</th><th>Código</th><th>Categoria</th><th>Situação</th></tr></thead><tbody id="pw-dtf-category-report-body"></tbody></table></div>
    </section>
    <section class="pw-system-view pw-consultation pw-admin-only" data-view="dtf-movement-report">
      <div class="pw-consultation-header"><input class="pw-consultation-search" type="search" id="pw-dtf-movement-report-search" placeholder="Pesquisar"></div>
      
      <div class="pw-bulk-toolbar" data-bulk-toolbar="dtfMovement"><label class="pw-check-row"><input type="checkbox" data-select-all="dtfMovement"> Selecionar todos</label><span class="pw-selected-count" data-selected-count="dtfMovement">0 selecionados</span><button class="pw-btn pw-btn-soft pw-btn-icon pw-tooltip" type="button" data-expense-print="movement" data-tooltip="Imprimir selecionados ou todos os filtrados" aria-label="Imprimir relatório">⎙</button><button class="pw-btn pw-btn-soft pw-btn-icon pw-tooltip" type="button" data-bulk-edit="dtfMovement" data-tooltip="Editar selecionados" title="Editar selecionados" aria-label="Editar selecionados" disabled>✎</button><button class="pw-btn pw-btn-danger pw-btn-icon pw-tooltip" type="button" data-bulk-delete="dtfMovement" data-tooltip="Excluir selecionados" title="Excluir selecionados" aria-label="Excluir selecionados" disabled>🗑</button></div>
      <div class="pw-table-wrap"><table class="pw-data-table"><thead><tr><th class="pw-select-cell">Sel.</th><th>Código</th><th>Movimentação</th><th>Situação</th></tr></thead><tbody id="pw-dtf-movement-report-body"></tbody></table></div>
    </section>

    <section class="pw-system-view pw-consultation pw-admin-only" data-view="dtf-cost-report">
      <div class="pw-consultation-header"><button class="pw-btn pw-btn-soft pw-btn-icon pw-tooltip" type="button" data-expense-print="cost-detail" data-tooltip="Imprimir despesas deste período" aria-label="Imprimir relatório de despesas">⎙</button></div>
      <div class="pw-period-filter pw-dtf-report-filters"><label>Data inicial <input id="pw-dtf-report-start" type="date"></label><label>Data final <input id="pw-dtf-report-end" type="date"></label><label>Categoria <select id="pw-dtf-report-category"><option value="">Todas</option><option>Tinta</option><option>Filme</option><option>Manutenção</option><option>Peça</option><option>Anúncio</option><option>Outro</option></select></label><button class="pw-btn pw-btn-primary" id="pw-dtf-report-apply" type="button">Aplicar período</button><button class="pw-btn pw-btn-soft" id="pw-dtf-report-clear" type="button">Todo o histórico</button></div>
      <div class="pw-dtf-report-cards" id="pw-dtf-report-cards"></div>
      <div class="pw-section pw-dtf-cm-section">
        <div class="pw-section-title"><div><h3>Custo e lucro por cm linear (Mecolour)</h3><p>Despesas do fornecedor Mecolour cruzadas com vendas DTF UV do período. Largura fixa de 28 cm.</p></div></div>
        <div class="pw-dtf-report-cards" id="pw-dtf-cm-cards"></div>
        <div id="pw-dtf-cm-chart-wrap"></div>
      </div>
      <div class="pw-grid pw-dtf-report-details"><section class="pw-section pw-col-6"><div class="pw-section-title"><div><h3>Custos por categoria</h3><p>Participação de cada grupo no custo total.</p></div></div><div id="pw-dtf-category-breakdown"></div></section><section class="pw-section pw-col-6"><div class="pw-section-title"><div><h3>Custos por item</h3><p>Consumo acumulado e última movimentação.</p></div></div><div id="pw-dtf-item-breakdown"></div></section></div>
    </section>

    <section class="pw-system-view pw-consultation" data-view="trash">
      <div class="pw-consultation-header"><div><h3>Lixeira</h3><p>Todos podem consultar. Usuários restauram somente seus próprios registros; administradores podem restaurar ou excluir qualquer item.</p></div></div>
      <div class="pw-bulk-toolbar"><label class="pw-check-row"><input type="checkbox" id="pw-trash-select-all"> Selecionar permitidos</label><span id="pw-trash-selected-count">0 selecionados</span><button class="pw-btn pw-btn-soft" id="pw-trash-restore" type="button">Restaurar</button><span class="pw-bulk-toolbar-spacer"></span><button class="pw-btn pw-btn-danger pw-admin-only" id="pw-trash-delete" type="button">Excluir definitivamente</button></div>
      <div class="pw-table-wrap"><table class="pw-data-table"><thead><tr><th>Sel.</th><th>Origem do registro</th><th>Registro</th><th>Excluído em</th><th>Excluído por</th><th>Dias até excluir automaticamente</th></tr></thead><tbody id="pw-trash-body"></tbody></table></div>
    </section>

    <section class="pw-system-view pw-consultation" data-view="preferences">
      <div class="pw-consultation-header"><div><h3>Minhas preferências</h3><p>Configurações pessoais, disponíveis para qualquer usuário.</p></div></div>
      <section class="pw-section"><div class="pw-section-title"><div><h3>Menu de acesso rápido</h3><p>Botões de atalho que aparecem no topo de cada tela, aprendendo com os menus que você mais usa ao sair dela. É individual — não afeta outros usuários.</p></div></div>
        <div class="pw-grid">
          <div class="pw-field pw-col-4"><label for="pw-preferences-quick-limit">Quantos atalhos mostrar por tela (0 a 10)</label><select id="pw-preferences-quick-limit"><option value="0">0 — desativado</option><option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5" selected>5</option><option value="6">6</option><option value="7">7</option><option value="8">8</option><option value="9">9</option><option value="10">10</option></select></div>
          <div class="pw-field pw-col-3" style="align-self:end"><button class="pw-btn pw-btn-primary" id="pw-preferences-quick-limit-save" type="button">✓ Salvar</button></div>
        </div>
        <button class="pw-btn pw-btn-soft" id="pw-preferences-quick-reset" type="button" style="margin-top:10px">🗑 Resetar histórico de atalhos</button>
      </section>
    </section>

    <section class="pw-system-view pw-consultation" data-view="settings">
      <div class="pw-consultation-header"><div><h3>Configurações e permissões</h3><p>Acesso exclusivo do administrador. Defina o que cada função pode realizar.</p></div><button class="pw-btn pw-btn-primary" id="pw-save-settings" type="button">Salvar configurações</button></div>
      <div class="pw-settings-tabs" role="tablist" aria-label="Tópicos das configurações"><button class="pw-active" type="button" data-settings-tab="access">🔒 Acesso</button><button type="button" data-settings-tab="general">⚙️ Geral</button><button type="button" data-settings-tab="shipping">🚚 Envio</button><button type="button" data-settings-tab="appearance">🎨 Aparência</button><button type="button" data-settings-tab="speech">💬 Falar</button><button type="button" data-settings-tab="tests">🧹 Limpeza</button><button type="button" data-settings-tab="errors">⚠️ Erros</button><button type="button" data-settings-tab="tokens">🔑 Tokens</button><button type="button" data-settings-tab="whatsapp">📲 WhatsApp</button><button type="button" data-settings-tab="nfe">📄 NF-e</button></div>
      <div class="pw-settings-panel pw-active" data-settings-panel="access">
        <section class="pw-section"><div class="pw-section-title"><div><h3>Acesso individual aos menus</h3><p>Selecione um colaborador e determine exatamente quais áreas estarão disponíveis para ele.</p></div></div>
          <div class="pw-access-user-toolbar"><label>Colaborador<select id="pw-access-user"><option value="">Selecione um colaborador</option></select></label><label>Exibição dos menus bloqueados<select id="pw-access-blocked-display"><option value="disabled">Mostrar menus bloqueados desabilitados</option><option value="hidden">Não mostrar os menus bloqueados</option></select></label></div>
          <div class="pw-table-wrap"><table class="pw-data-table pw-access-menu-table"><thead><tr><th>Grupo</th><th>Menu</th><th>Permitir acesso</th></tr></thead><tbody id="pw-access-menu-body"></tbody></table></div>
        </section>
        <section class="pw-section"><div class="pw-section-title"><div><h3>Copiar configurações de acesso</h3><p>Reaproveite as permissões de outro colaborador no usuário selecionado acima.</p></div></div><div class="pw-access-copy"><label>Copiar configurações de<select id="pw-access-copy-source"><option value="">Selecione o usuário de origem</option></select></label><button class="pw-btn pw-btn-soft" id="pw-copy-user-access" type="button">Copiar para o usuário selecionado</button></div></section>
      </div>
      <div class="pw-settings-panel" data-settings-panel="general">
        <section class="pw-section"><div class="pw-section-title"><div><h3>Numeração dos pedidos</h3><p>Controle administrativo da sequência global. O sistema nunca reinicia o sequencial ao mudar o mês e não permite duplicar um pedido existente.</p></div></div><div class="pw-grid"><div class="pw-field pw-col-4"><label>Último pedido cadastrado</label><input id="pw-last-order-number" type="text" readonly value="Nenhum pedido cadastrado"></div><div class="pw-field pw-col-3"><label class="pw-required" for="pw-next-order-sequence">Número sequencial global do próximo pedido</label><input id="pw-next-order-sequence" type="number" min="1" max="99999" step="1" required></div><div class="pw-field pw-col-5"><label>Próximo número completo</label><input id="pw-next-order-preview" type="text" readonly></div></div><p class="pw-field-hint">A alteração afeta somente novos pedidos. Pedidos já cadastrados nunca serão renumerados.</p></section>
        <section class="pw-section"><div class="pw-section-title"><div><h3>Retenção da Lixeira</h3><p>Registros mais antigos que este prazo serão removidos automaticamente.</p></div></div><div class="pw-grid"><div class="pw-field pw-col-3"><label class="pw-required" for="pw-trash-retention-days">Dias para manter os itens</label><input id="pw-trash-retention-days" type="number" min="1" max="3650" step="1" value="30"></div></div></section>
        <section class="pw-section"><div class="pw-section-title"><div><h3>Correção automática de textos em maiúsculo</h3><p>Verifica clientes, fornecedores, categorias, unidades, formas de pagamento e produtos já cadastrados em busca de nomes e descrições digitados fora do padrão (tudo em maiúsculo, por exemplo) e permite corrigir em lote.</p></div><button class="pw-btn pw-btn-soft" id="pw-open-case-correction" type="button">🔤 Verificar cadastros</button></div>
          <div class="pw-case-correction-schedule">
            <label class="pw-check-row"><input type="checkbox" id="pw-case-correction-schedule-enabled"> Corrigir sozinho automaticamente, sem precisar abrir esta tela</label>
            <div class="pw-grid">
              <div class="pw-field pw-col-3"><label for="pw-case-correction-schedule-value">A cada</label><input id="pw-case-correction-schedule-value" type="number" min="1" max="365" step="1" value="30"></div>
              <div class="pw-field pw-col-3"><label for="pw-case-correction-schedule-unit">Período</label><select id="pw-case-correction-schedule-unit"><option value="days">Dia(s)</option><option value="weeks">Semana(s)</option><option value="months">Mês(es)</option></select></div>
              <div class="pw-field pw-col-4" style="align-self:flex-end"><button class="pw-btn pw-btn-primary" id="pw-case-correction-schedule-save" type="button">✓ Salvar agendamento</button></div>
            </div>
            <p class="pw-field-hint" id="pw-case-correction-schedule-status">Quando habilitado, aplica sozinho todas as correções encontradas (sem precisar selecionar uma a uma).</p>
          </div>
        </section>
        <section class="pw-section"><div class="pw-section-title"><div><h3>Controle de arquivos PDF por categoria</h3><p>Defina, para cada categoria de produto, se o envio de arte em PDF é permitido/obrigatório e os limites de largura, altura (mm) e tamanho do arquivo (MB) aceitos.</p></div><button class="pw-btn pw-btn-primary" id="pw-pdf-rules-save" type="button">✓ Salvar regras</button></div>
          <div id="pw-pdf-rules-body"></div>
        </section>
      </div>
      <div class="pw-settings-panel" data-settings-panel="shipping">
        <section class="pw-section"><div class="pw-section-title"><div><img class="pw-frenet-brand-logo" src="https://files.readme.io/6e60e35-logo.svg" alt="Melhor Envio" loading="lazy" style="margin-right:8px;vertical-align:middle"><h3 style="display:inline">Conexão com o Melhor Envio</h3><p>Um único Token aqui vale para consultar CEP e gerar etiqueta. No painel do Melhor Envio: menu <strong>Gerenciar → Tokens → Novo Token</strong>, dê um nome e marque as permissões de cotação e compra de fretes. O token já é liberado na hora, sem homologação. <a href="https://ajuda.melhorenvio.com.br/pt-BR/" target="_blank" rel="noopener noreferrer">Central de ajuda</a></p></div></div>
          <div class="pw-frenet-connection" id="pw-me-settings-connection" role="status"></div>
          <div class="pw-frenet-token-row"><div class="pw-field"><label for="pw-me-settings-token">Token do Melhor Envio</label><input id="pw-me-settings-token" type="password" autocomplete="new-password" maxlength="2048" spellcheck="false" placeholder="Cole o Token gerado no painel"></div><button class="pw-btn pw-btn-primary" type="button" id="pw-me-settings-connect">✓ Salvar Token</button><button class="pw-btn pw-btn-soft" type="button" id="pw-me-settings-check">Verificar validade</button></div>
          <small>O Token é salvo criptografado no servidor e passa a valer para todos os usuários consultarem o CEP e gerarem etiqueta.</small>
        </section>
        <section class="pw-section"><div class="pw-section-title"><div><h3>Rastreio detalhado dos Correios (API PacoteVício)</h3><p>Opcional — permite mostrar o passo a passo completo (postado, em trânsito, saiu para entrega, entregue etc.) direto dentro de "Etiquetas geradas", sem precisar abrir outro site. Crie uma chave gratuita (1.000 consultas/mês) em <a href="https://rapidapi.com/pacotevicio-pacotevicio-default/api/correios-rastreamento-de-encomendas" target="_blank" rel="noopener noreferrer">rapidapi.com</a> (plano BASIC, gratuito) e cole abaixo.</p></div></div>
          <div class="pw-frenet-connection" id="pw-pacotevicio-settings-connection" role="status"></div>
          <div class="pw-frenet-token-row"><div class="pw-field"><label for="pw-pacotevicio-settings-key">Chave da API (X-RapidAPI-Key)</label><input id="pw-pacotevicio-settings-key" type="password" autocomplete="new-password" maxlength="500" spellcheck="false" placeholder="Cole a chave gerada no RapidAPI"></div><button class="pw-btn pw-btn-primary" type="button" id="pw-pacotevicio-settings-connect">✓ Salvar chave</button></div>
          <small>A chave é salva criptografada no servidor. Sem ela, "Etiquetas geradas" continua funcionando normalmente, só sem o passo a passo detalhado.</small>
        </section>
        <section class="pw-section"><div class="pw-section-title"><div><h3>Transportadoras exibidas</h3><p>Escolha quais transportadoras aparecem nas cotações e na geração de etiqueta. Se nenhuma for marcada, todas são exibidas.</p></div><button class="pw-btn pw-btn-soft" id="pw-me-carriers-load" type="button">Carregar transportadoras</button></div>
          <div id="pw-me-carriers-list"><p class="pw-field-hint">Clique em "Carregar transportadoras" (precisa do Token salvo primeiro).</p></div>
          <button class="pw-btn pw-btn-primary" id="pw-me-carriers-save" type="button" hidden style="margin-top:10px">✓ Salvar transportadoras</button>
        </section>
      </div>
      <div class="pw-settings-panel" data-settings-panel="appearance">
        <section class="pw-section pw-status-settings"><div class="pw-section-title"><div><h3>Aparência das situações</h3><p>Personalize a sigla e a cor das bolinhas. As alterações serão aplicadas às listagens, legendas e históricos.</p></div></div><div id="pw-status-settings-grid" class="pw-status-settings-grid"></div></section>
        <section class="pw-section pw-status-settings"><div class="pw-section-title"><div><h3>Aparência das formas de pagamento</h3><p>Defina uma sigla de até três letras e uma cor para cada forma de pagamento cadastrada.</p></div></div><div id="pw-payment-method-settings-grid" class="pw-status-settings-grid"></div></section>
      </div>
      <div class="pw-settings-panel" data-settings-panel="speech">
        <section class="pw-section"><div class="pw-section-title"><div><h3>Voz e velocidade</h3><p>Vale para todos os usuários (fica gravado no servidor, junto com o resto das configurações). A lista de vozes vem do sistema operacional/navegador de quem está configurando agora — se alguém acessar de um aparelho sem essa voz instalada, o navegador dela escolhe sozinho a melhor voz em português disponível.</p></div></div>
          <div class="pw-grid">
            <div class="pw-field pw-col-6"><label for="pw-speech-voice">Voz</label><select id="pw-speech-voice"><option value="">Padrão do navegador (português do Brasil)</option></select></div>
            <div class="pw-field pw-col-4"><label for="pw-speech-rate">Velocidade — <span id="pw-speech-rate-label">1.0×</span></label><input id="pw-speech-rate" type="range" min="0.5" max="2" step="0.1" value="1"></div>
            <div class="pw-field pw-col-2" style="align-self:end"><button class="pw-btn pw-btn-soft" type="button" id="pw-speech-voice-test">▶ Testar</button></div>
          </div>
        </section>
        <section class="pw-section"><div class="pw-section-title"><div><h3>Avisos falados</h3><p>Quando uma notificação nova aparece (pedido novo ou Mercado Livre), o sistema toca um bipe e pode falar uma frase em voz. Personalize o texto de cada aviso abaixo — deixe o campo vazio pra esse local não falar nada (o bipe continua tocando normalmente). Use "▶ Ouvir" pra testar o texto (com a voz/velocidade acima) na hora, sem precisar salvar.</p></div></div>
          <div id="pw-speech-settings-list" class="pw-speech-settings-list"></div>
        </section>
      </div>
      <div class="pw-settings-panel" data-settings-panel="tests"><section class="pw-section"><div class="pw-section-title"><div><h3>Testes e limpeza do sistema</h3><p>Verifica numeração, datas, cálculos, duplicidades, permissões e fluxo de situações sem alterar seus cadastros — e, na mesma passada, faz manutenção real: otimiza tabelas do banco de dados, remove transients expirados e limpa arquivos temporários órfãos.</p></div><div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end"><button class="pw-btn pw-btn-primary" id="pw-run-tests" type="button">🧹 Executar testes e limpeza</button></div></div><div id="pw-cleanup-status" class="pw-cleanup-status">Carregando status da limpeza…</div><div id="pw-test-results"></div></section></div>
      <div class="pw-settings-panel" data-settings-panel="tokens">
        <section class="pw-section"><div class="pw-section-title"><div><h3>APIs e tokens externos</h3><p>Status das integrações. Clique em ⚙ para configurar no sistema ou 🔗 para acessar o site do serviço.</p></div><button class="pw-btn pw-btn-soft" id="pw-tokens-refresh" type="button">↻ Verificar</button></div>
          <div class="pw-token-cards" id="pw-token-cards-body">
            <div class="pw-token-card" data-token-key="shopee">
              <div class="pw-token-card-header"><span class="pw-token-card-name">Shopee</span><div class="pw-token-card-indicators"><button type="button" class="pw-token-cfg-dot-btn pw-token-config-btn" data-token-key="shopee" title="Configurar credenciais"><span class="pw-token-cfg-dot" id="pw-token-cfg-shopee"></span></button><a class="pw-token-heart-link" href="https://open.shopee.com/developer-guide/token" target="_blank" rel="noopener" title="Acessar Shopee API"><span class="pw-token-heart" id="pw-token-heart-shopee"></span></a></div></div>
              <div class="pw-token-verify-bar" id="pw-token-bar-shopee"></div>
              <div class="pw-token-card-desc" id="pw-token-desc-shopee">–</div>
            </div>
            <div class="pw-token-card" data-token-key="mercadolivre">
              <div class="pw-token-card-header"><span class="pw-token-card-name">Mercado Livre</span><div class="pw-token-card-indicators"><button type="button" class="pw-token-cfg-dot-btn pw-token-config-btn" data-token-key="mercadolivre" title="Configurar credenciais"><span class="pw-token-cfg-dot" id="pw-token-cfg-mercadolivre"></span></button><a class="pw-token-heart-link" href="https://developers.mercadolivre.com.br/pt_br/my-applications" target="_blank" rel="noopener" title="Acessar Mercado Livre API"><span class="pw-token-heart" id="pw-token-heart-mercadolivre"></span></a></div></div>
              <div class="pw-token-verify-bar" id="pw-token-bar-mercadolivre"></div>
              <div class="pw-token-card-desc" id="pw-token-desc-mercadolivre">–</div>
            </div>
            <div class="pw-token-card" data-token-key="anthropic">
              <div class="pw-token-card-header"><span class="pw-token-card-name">Anthropic (IA)</span><div class="pw-token-card-indicators"><button type="button" class="pw-token-cfg-dot-btn pw-token-config-btn" data-token-key="anthropic" title="Configurar credenciais"><span class="pw-token-cfg-dot" id="pw-token-cfg-anthropic"></span></button><a class="pw-token-heart-link" href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener" title="Acessar Anthropic Console"><span class="pw-token-heart" id="pw-token-heart-anthropic"></span></a></div></div>
              <div class="pw-token-verify-bar" id="pw-token-bar-anthropic"></div>
              <div class="pw-token-card-desc" id="pw-token-desc-anthropic">–</div>
            </div>
            <div class="pw-token-card" data-token-key="melhorenvio">
              <div class="pw-token-card-header"><span class="pw-token-card-name">Melhor Envio</span><div class="pw-token-card-indicators"><button type="button" class="pw-token-cfg-dot-btn pw-token-config-btn" data-token-key="melhorenvio" title="Configurar credenciais"><span class="pw-token-cfg-dot" id="pw-token-cfg-melhorenvio"></span></button><a class="pw-token-heart-link" href="https://app.melhorenvio.com.br/tokens" target="_blank" rel="noopener" title="Acessar Melhor Envio"><span class="pw-token-heart" id="pw-token-heart-melhorenvio"></span></a></div></div>
              <div class="pw-token-verify-bar" id="pw-token-bar-melhorenvio"></div>
              <div class="pw-token-card-desc" id="pw-token-desc-melhorenvio">–</div>
            </div>
            <div class="pw-token-card" data-token-key="frenet">
              <div class="pw-token-card-header"><span class="pw-token-card-name">Frenet (frete)</span><div class="pw-token-card-indicators"><button type="button" class="pw-token-cfg-dot-btn pw-token-config-btn" data-token-key="frenet" title="Configurar credenciais"><span class="pw-token-cfg-dot" id="pw-token-cfg-frenet"></span></button><a class="pw-token-heart-link" href="https://frenet.com.br/login" target="_blank" rel="noopener" title="Acessar Frenet"><span class="pw-token-heart" id="pw-token-heart-frenet"></span></a></div></div>
              <div class="pw-token-verify-bar" id="pw-token-bar-frenet"></div>
              <div class="pw-token-card-desc" id="pw-token-desc-frenet">–</div>
            </div>
            <div class="pw-token-card" data-token-key="nfe">
              <div class="pw-token-card-header"><span class="pw-token-card-name">NF-e (certificado)</span><div class="pw-token-card-indicators"><button type="button" class="pw-token-cfg-dot-btn pw-token-config-btn" data-token-key="nfe" title="Configurar credenciais"><span class="pw-token-cfg-dot" id="pw-token-cfg-nfe"></span></button><a class="pw-token-heart-link" href="https://www.nfe.fazenda.gov.br/" target="_blank" rel="noopener" title="Portal NF-e"><span class="pw-token-heart" id="pw-token-heart-nfe"></span></a></div></div>
              <div class="pw-token-verify-bar" id="pw-token-bar-nfe"></div>
              <div class="pw-token-card-desc" id="pw-token-desc-nfe">–</div>
            </div>
            <div class="pw-token-card" data-token-key="pix">
              <div class="pw-token-card-header"><span class="pw-token-card-name">Pix / Gateway</span><div class="pw-token-card-indicators"><button type="button" class="pw-token-cfg-dot-btn pw-token-config-btn" data-token-key="pix" title="Configurar credenciais"><span class="pw-token-cfg-dot" id="pw-token-cfg-pix"></span></button><a class="pw-token-heart-link" href="https://www.bcb.gov.br/estabilidadefinanceira/pix" target="_blank" rel="noopener" title="Banco Central - Pix"><span class="pw-token-heart" id="pw-token-heart-pix"></span></a></div></div>
              <div class="pw-token-verify-bar" id="pw-token-bar-pix"></div>
              <div class="pw-token-card-desc" id="pw-token-desc-pix">–</div>
            </div>
            <div class="pw-token-card" data-token-key="whatsapp">
              <div class="pw-token-card-header"><span class="pw-token-card-name">WhatsApp Business</span><div class="pw-token-card-indicators"><button type="button" class="pw-token-cfg-dot-btn pw-token-config-btn" data-token-key="whatsapp" title="Configurar credenciais"><span class="pw-token-cfg-dot" id="pw-token-cfg-whatsapp"></span></button><a class="pw-token-heart-link" href="https://developers.facebook.com/apps/695239673468018/whatsapp-business/wa-dev-console/" target="_blank" rel="noopener" title="WhatsApp Dev Console"><span class="pw-token-heart" id="pw-token-heart-whatsapp"></span></a></div></div>
              <div class="pw-token-verify-bar" id="pw-token-bar-whatsapp"></div>
              <div class="pw-token-card-desc" id="pw-token-desc-whatsapp">–</div>
            </div>
          </div>
        </section>
      </div>
      <div class="pw-settings-panel" data-settings-panel="whatsapp">
        <section class="pw-section">
          <div class="pw-section-title"><div><h3>Notificações por WhatsApp</h3><p>Configure quais mudanças de situação disparam uma mensagem automática no WhatsApp do cliente. O token de acesso é configurado em <strong>🔑 Tokens</strong>.</p></div><button class="pw-btn pw-btn-primary" id="pw-whatsapp-settings-save" type="button">Salvar configurações</button></div>
          <div class="pw-grid">
            <div class="pw-field pw-col-6">
              <label for="pw-wa-official-number">Número oficial para atendimento</label>
              <input id="pw-wa-official-number" type="tel" placeholder="(00) 00000-0000" inputmode="numeric">
              <small class="pw-field-hint">Aparece na mensagem como link de contato. Deixe em branco para não incluir.</small>
            </div>
            <div class="pw-field pw-col-12">
              <label>Enviar mensagem ao mudar a situação para:</label>
              <div style="display:flex;flex-wrap:wrap;gap:8px 24px;margin-top:6px">
                <label class="pw-check-row"><input type="checkbox" class="pw-wa-status-check" value="Criação da arte"> Criação da arte</label>
                <label class="pw-check-row"><input type="checkbox" class="pw-wa-status-check" value="Arte aprovada"> Arte aprovada</label>
                <label class="pw-check-row"><input type="checkbox" class="pw-wa-status-check" value="Em produção"> Em produção</label>
                <label class="pw-check-row"><input type="checkbox" class="pw-wa-status-check" value="Produzido"> Produção pronta</label>
                <label class="pw-check-row"><input type="checkbox" class="pw-wa-status-check" value="Aguardando entrega"> Aguardando entrega</label>
                <label class="pw-check-row"><input type="checkbox" class="pw-wa-status-check" value="Entregue"> Entregue</label>
              </div>
            </div>
            <div class="pw-field pw-col-12">
              <label>Mensagem por situação</label>
              <small class="pw-field-hint">Personalize a mensagem de cada etapa. Deixe vazio para usar o texto padrão. Os botões de formato envolvem o texto selecionado.</small>
              <div class="pw-wa-msg-tabs" role="tablist">
                <button class="pw-wa-msg-tab pw-active" type="button" data-wa-msg-tab="Criação da arte">✍️ Arte</button>
                <button class="pw-wa-msg-tab" type="button" data-wa-msg-tab="Arte aprovada">✅ Aprovada</button>
                <button class="pw-wa-msg-tab" type="button" data-wa-msg-tab="Em produção">🖨️ Produção</button>
                <button class="pw-wa-msg-tab" type="button" data-wa-msg-tab="Produzido">📦 Pronto</button>
                <button class="pw-wa-msg-tab" type="button" data-wa-msg-tab="Aguardando entrega">🚚 Entrega</button>
                <button class="pw-wa-msg-tab" type="button" data-wa-msg-tab="Entregue">🎉 Entregue</button>
              </div>
              <div class="pw-wa-toolbar">
                <details class="pw-wa-dropdown">
                  <summary>📋 Variáveis</summary>
                  <div class="pw-wa-chip-bar">
                    <button type="button" class="pw-wa-chip pw-wa-var-chip" data-var="&lt;nome_cliente&gt;">👤 Nome</button>
                    <button type="button" class="pw-wa-chip pw-wa-var-chip" data-var="&lt;codigo_cliente&gt;">🔢 Código</button>
                    <button type="button" class="pw-wa-chip pw-wa-var-chip" data-var="&lt;numero_pedido&gt;">📋 Pedido</button>
                    <button type="button" class="pw-wa-chip pw-wa-var-chip" data-var="&lt;situacao&gt;">📌 Situação</button>
                    <button type="button" class="pw-wa-chip pw-wa-var-chip" data-var="&lt;produto&gt;">🖨️ Produto</button>
                    <button type="button" class="pw-wa-chip pw-wa-var-chip" data-var="&lt;quantidade&gt;">🔢 Qtde</button>
                    <button type="button" class="pw-wa-chip pw-wa-var-chip" data-var="&lt;valor_total&gt;">💰 Total</button>
                    <button type="button" class="pw-wa-chip pw-wa-var-chip" data-var="&lt;prazo_entrega&gt;">📅 Prazo</button>
                    <button type="button" class="pw-wa-chip pw-wa-var-chip" data-var="&lt;data_criacao&gt;">📅 Criação</button>
                    <button type="button" class="pw-wa-chip pw-wa-var-chip" data-var="&lt;status_pagamento&gt;">💳 Pgto</button>
                    <button type="button" class="pw-wa-chip pw-wa-var-chip" data-var="&lt;link_contato&gt;">📞 Contato</button>
                  </div>
                </details>
                <details class="pw-wa-dropdown">
                  <summary>😀 Emojis</summary>
                  <div class="pw-wa-chip-bar">
                    <button type="button" class="pw-wa-chip pw-wa-emoji-chip" data-var="📦">📦</button>
                    <button type="button" class="pw-wa-chip pw-wa-emoji-chip" data-var="📲">📲</button>
                    <button type="button" class="pw-wa-chip pw-wa-emoji-chip" data-var="✅">✅</button>
                    <button type="button" class="pw-wa-chip pw-wa-emoji-chip" data-var="❌">❌</button>
                    <button type="button" class="pw-wa-chip pw-wa-emoji-chip" data-var="🎨">🎨</button>
                    <button type="button" class="pw-wa-chip pw-wa-emoji-chip" data-var="🖨️">🖨️</button>
                    <button type="button" class="pw-wa-chip pw-wa-emoji-chip" data-var="🚚">🚚</button>
                    <button type="button" class="pw-wa-chip pw-wa-emoji-chip" data-var="📞">📞</button>
                    <button type="button" class="pw-wa-chip pw-wa-emoji-chip" data-var="⏱️">⏱️</button>
                    <button type="button" class="pw-wa-chip pw-wa-emoji-chip" data-var="✍️">✍️</button>
                    <button type="button" class="pw-wa-chip pw-wa-emoji-chip" data-var="🔔">🔔</button>
                    <button type="button" class="pw-wa-chip pw-wa-emoji-chip" data-var="🛍️">🛍️</button>
                    <button type="button" class="pw-wa-chip pw-wa-emoji-chip" data-var="👋">👋</button>
                    <button type="button" class="pw-wa-chip pw-wa-emoji-chip" data-var="⭐">⭐</button>
                    <button type="button" class="pw-wa-chip pw-wa-emoji-chip" data-var="➡️">➡️</button>
                    <button type="button" class="pw-wa-chip pw-wa-emoji-chip" data-var="🎉">🎉</button>
                    <button type="button" class="pw-wa-chip pw-wa-emoji-chip" data-var="🏪">🏪</button>
                    <button type="button" class="pw-wa-chip pw-wa-emoji-chip" data-var="💡">💡</button>
                  </div>
                </details>
                <details class="pw-wa-dropdown">
                  <summary>✏️ Formatação WA</summary>
                  <div class="pw-wa-chip-bar">
                    <button type="button" class="pw-wa-chip pw-wa-fmt-chip" data-before="*" data-after="*" title="Negrito (*texto*)">𝐁 Negrito</button>
                    <button type="button" class="pw-wa-chip pw-wa-fmt-chip" data-before="_" data-after="_" title="Itálico (_texto_)">𝐼 Itálico</button>
                    <button type="button" class="pw-wa-chip pw-wa-fmt-chip" data-before="~" data-after="~" title="Tachado (~texto~)">S̶ Tachado</button>
                    <button type="button" class="pw-wa-chip pw-wa-fmt-chip" data-before="```" data-after="```" title="Monoespaçado (```texto```)">⌨ Mono</button>
                  </div>
                </details>
              </div>
              <div class="pw-wa-msg-panel pw-active" data-wa-msg-panel="Criação da arte">
                <div class="pw-wa-panel-label">✍️ Criação da arte</div>
                <textarea class="pw-wa-msg-tpl" data-wa-status="Criação da arte" rows="7"></textarea>
                <button type="button" class="pw-wa-reset-tpl" data-wa-status="Criação da arte">↺ Restaurar padrão</button>
              </div>
              <div class="pw-wa-msg-panel" data-wa-msg-panel="Arte aprovada">
                <div class="pw-wa-panel-label">✅ Arte aprovada</div>
                <textarea class="pw-wa-msg-tpl" data-wa-status="Arte aprovada" rows="7"></textarea>
                <button type="button" class="pw-wa-reset-tpl" data-wa-status="Arte aprovada">↺ Restaurar padrão</button>
              </div>
              <div class="pw-wa-msg-panel" data-wa-msg-panel="Em produção">
                <div class="pw-wa-panel-label">🖨️ Em produção</div>
                <textarea class="pw-wa-msg-tpl" data-wa-status="Em produção" rows="7"></textarea>
                <button type="button" class="pw-wa-reset-tpl" data-wa-status="Em produção">↺ Restaurar padrão</button>
              </div>
              <div class="pw-wa-msg-panel" data-wa-msg-panel="Produzido">
                <div class="pw-wa-panel-label">📦 Produzido</div>
                <textarea class="pw-wa-msg-tpl" data-wa-status="Produzido" rows="7"></textarea>
                <button type="button" class="pw-wa-reset-tpl" data-wa-status="Produzido">↺ Restaurar padrão</button>
              </div>
              <div class="pw-wa-msg-panel" data-wa-msg-panel="Aguardando entrega">
                <div class="pw-wa-panel-label">🚚 Aguardando entrega</div>
                <textarea class="pw-wa-msg-tpl" data-wa-status="Aguardando entrega" rows="7"></textarea>
                <button type="button" class="pw-wa-reset-tpl" data-wa-status="Aguardando entrega">↺ Restaurar padrão</button>
              </div>
              <div class="pw-wa-msg-panel" data-wa-msg-panel="Entregue">
                <div class="pw-wa-panel-label">🎉 Entregue</div>
                <textarea class="pw-wa-msg-tpl" data-wa-status="Entregue" rows="7"></textarea>
                <button type="button" class="pw-wa-reset-tpl" data-wa-status="Entregue">↺ Restaurar padrão</button>
              </div>
              <details style="margin-top:10px">
                <summary style="cursor:pointer;font-size:12px;color:var(--pw-muted)">📖 Legenda das variáveis</summary>
                <table style="font-size:12px;border-collapse:collapse;margin-top:6px;width:100%">
                  <thead><tr style="background:var(--pw-light)"><th style="padding:4px 8px;text-align:left;border:1px solid var(--pw-border)">Variável</th><th style="padding:4px 8px;text-align:left;border:1px solid var(--pw-border)">Dados</th><th style="padding:4px 8px;text-align:left;border:1px solid var(--pw-border)">Exemplo</th></tr></thead>
                  <tbody>
                    <tr><td style="padding:4px 8px;border:1px solid var(--pw-border)"><code>&lt;nome_cliente&gt;</code></td><td style="padding:4px 8px;border:1px solid var(--pw-border)">Nome do cliente</td><td style="padding:4px 8px;border:1px solid var(--pw-border)">João Silva</td></tr>
                    <tr><td style="padding:4px 8px;border:1px solid var(--pw-border)"><code>&lt;codigo_cliente&gt;</code></td><td style="padding:4px 8px;border:1px solid var(--pw-border)">Código do cliente</td><td style="padding:4px 8px;border:1px solid var(--pw-border)">0065</td></tr>
                    <tr><td style="padding:4px 8px;border:1px solid var(--pw-border)"><code>&lt;numero_pedido&gt;</code></td><td style="padding:4px 8px;border:1px solid var(--pw-border)">Número do pedido</td><td style="padding:4px 8px;border:1px solid var(--pw-border)">00075</td></tr>
                    <tr><td style="padding:4px 8px;border:1px solid var(--pw-border)"><code>&lt;situacao&gt;</code></td><td style="padding:4px 8px;border:1px solid var(--pw-border)">Nova situação do pedido</td><td style="padding:4px 8px;border:1px solid var(--pw-border)">Em produção</td></tr>
                    <tr><td style="padding:4px 8px;border:1px solid var(--pw-border)"><code>&lt;produto&gt;</code></td><td style="padding:4px 8px;border:1px solid var(--pw-border)">Descrição do 1º item</td><td style="padding:4px 8px;border:1px solid var(--pw-border)">Impressão DTF UV — 28cm × 21cm</td></tr>
                    <tr><td style="padding:4px 8px;border:1px solid var(--pw-border)"><code>&lt;quantidade&gt;</code></td><td style="padding:4px 8px;border:1px solid var(--pw-border)">Quantidade do 1º item</td><td style="padding:4px 8px;border:1px solid var(--pw-border)">21</td></tr>
                    <tr><td style="padding:4px 8px;border:1px solid var(--pw-border)"><code>&lt;valor_total&gt;</code></td><td style="padding:4px 8px;border:1px solid var(--pw-border)">Total do pedido</td><td style="padding:4px 8px;border:1px solid var(--pw-border)">R$ 34,90</td></tr>
                    <tr><td style="padding:4px 8px;border:1px solid var(--pw-border)"><code>&lt;prazo_entrega&gt;</code></td><td style="padding:4px 8px;border:1px solid var(--pw-border)">Prazo de entrega</td><td style="padding:4px 8px;border:1px solid var(--pw-border)">25/09/2026</td></tr>
                    <tr><td style="padding:4px 8px;border:1px solid var(--pw-border)"><code>&lt;data_criacao&gt;</code></td><td style="padding:4px 8px;border:1px solid var(--pw-border)">Data de criação</td><td style="padding:4px 8px;border:1px solid var(--pw-border)">25/09/2026</td></tr>
                    <tr><td style="padding:4px 8px;border:1px solid var(--pw-border)"><code>&lt;status_pagamento&gt;</code></td><td style="padding:4px 8px;border:1px solid var(--pw-border)">Status de pagamento</td><td style="padding:4px 8px;border:1px solid var(--pw-border)">Pendente</td></tr>
                    <tr><td style="padding:4px 8px;border:1px solid var(--pw-border)"><code>&lt;link_contato&gt;</code></td><td style="padding:4px 8px;border:1px solid var(--pw-border)">Link wa.me do número oficial</td><td style="padding:4px 8px;border:1px solid var(--pw-border)">(bloco com o link de contato)</td></tr>
                  </tbody>
                </table>
              </details>
            </div>
            <div class="pw-field pw-col-12">
              <label>Prévia da mensagem (aba ativa):</label>
              <div id="pw-wa-preview" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px 14px;font-size:13px;white-space:pre-wrap;color:#166534;margin-top:4px;line-height:1.55"></div>
            </div>
          </div>
        </section>
      </div>
      <div class="pw-settings-panel" data-settings-panel="nfe">
        <section class="pw-section">
          <div class="pw-section-title"><div><h3>Notificações — Aguardando XML</h3><p>Usuários que receberão um aviso na barra do sistema quando novos pedidos de marketplace precisarem de XML para NF-e. Ao clicar no aviso, a tela Relatórios → NF-e abre automaticamente.</p></div></div>
          <div id="pw-nfe-notify-pending-users" class="pw-nfe-notify-users"><p class="pw-field-hint">Abra esta aba para carregar a lista.</p></div>
        </section>
        <section class="pw-section">
          <div class="pw-section-title"><div><h3>Notificações — XML pronto para download</h3><p>Usuários que receberão um aviso na barra do sistema quando um XML for anexado a um pedido (pronto para baixar e enviar à plataforma de marketplace).</p></div></div>
          <div id="pw-nfe-notify-ready-users" class="pw-nfe-notify-users"><p class="pw-field-hint">Abra esta aba para carregar a lista.</p></div>
        </section>
        <div style="padding:0 0 16px;display:flex;justify-content:flex-end"><button class="pw-btn pw-btn-primary" id="pw-nfe-notify-save" type="button">✓ Salvar configurações de notificação</button></div>
      </div>
      <div class="pw-settings-panel" data-settings-panel="errors"><section class="pw-section"><div class="pw-section-title"><div><h3>Erros do sistema</h3><p>Registro centralizado de tudo que apareceu como erro em qualquer parte do sistema (pedidos, backup, e-mail, editor de imagens, etc.), pra você revisar e me passar pra eu ir corrigindo.</p></div><div class="pw-errors-toolbar"><select id="pw-errors-level-filter"><option value="">Todos os níveis</option><option value="error">Somente erros</option><option value="warning">Somente avisos</option><option value="info">Somente informativos</option></select><button class="pw-btn pw-btn-soft" id="pw-errors-refresh" type="button">↻ Atualizar</button></div></div>
        <div class="pw-bulk-toolbar"><label class="pw-check-row"><input type="checkbox" id="pw-errors-select-all"> Selecionar todos</label><span class="pw-selected-count" id="pw-errors-selected-count">0 selecionados</span><button class="pw-btn pw-btn-soft" id="pw-errors-copy" type="button">⧉ Copiar relatório</button><button class="pw-btn pw-btn-soft" id="pw-errors-clear-selected" type="button" disabled>🗑 Limpar selecionados</button><button class="pw-btn pw-btn-danger" id="pw-errors-clear-all" type="button">🗑 Limpar tudo</button></div>
        <div class="pw-table-wrap"><table class="pw-data-table"><thead><tr><th class="pw-select-cell">Sel.</th><th>Data/hora</th><th>Origem</th><th>Nível</th><th>Mensagem</th></tr></thead><tbody id="pw-errors-body"><tr><td colspan="5" class="pw-empty-table">Abra esta aba para carregar o registro.</td></tr></tbody></table></div>
      </section></div>
    </section>
  </div>

  <div class="pw-modal" id="pw-client-modal" aria-hidden="true">
    <div class="pw-modal-panel" role="dialog" aria-modal="true" aria-labelledby="pw-client-modal-title">
      <div class="pw-modal-header">
        <h3 id="pw-client-modal-title">Cadastrar novo cliente</h3>
        <button class="pw-btn pw-btn-soft pw-btn-icon" type="button" data-close-modal="pw-client-modal" aria-label="Fechar">×</button>
      </div>
      <div class="pw-form-toolbar pw-modal-toolbar" aria-label="Ferramentas do cliente">
        <button class="pw-btn pw-btn-soft pw-toolbar-button pw-tooltip" id="pw-new-client-record" type="button" data-tooltip="Novo cliente" aria-label="Novo cliente">＋</button>
        <button class="pw-btn pw-btn-soft pw-toolbar-button pw-tooltip" id="pw-print-client" type="button" data-tooltip="Imprimir cadastro" aria-label="Imprimir cadastro">⎙</button>
        <button class="pw-btn pw-btn-primary pw-toolbar-button pw-tooltip" id="pw-save-client" type="button" data-tooltip="Salvar cliente" aria-label="Salvar cliente">✓</button>
      </div>
      <div class="pw-modal-body">
        <div class="pw-product-tabs pw-modal-tabs" role="tablist" aria-label="Dados do cliente">
          <button class="pw-product-tab pw-modal-tab pw-active is-active" type="button" data-client-tab="contact" role="tab" aria-selected="true">Contato</button>
          <button class="pw-product-tab pw-modal-tab" type="button" data-client-tab="address" role="tab" aria-selected="false">Endereço</button>
          <button class="pw-product-tab pw-modal-tab" type="button" data-client-tab="shipping" role="tab" aria-selected="false">Envios</button>
          <button class="pw-product-tab pw-modal-tab" type="button" data-client-tab="login" id="pw-client-login-tab" role="tab" aria-selected="false" hidden>Login</button>
        </div>
        <div class="pw-tab-panel pw-active" data-client-panel="contact">
          <div class="pw-grid">
            <div class="pw-field pw-col-3">
              <label class="pw-required" for="pw-new-client-code">Código</label>
              <input id="pw-new-client-code" type="text" readonly required>
            </div>
            <div class="pw-field pw-col-5">
              <label class="pw-required" for="pw-new-client-name">Nome completo</label>
              <input id="pw-new-client-name" type="text" required>
            </div>
            <div class="pw-field pw-col-4">
              <label class="pw-required" id="pw-new-client-phone-label" for="pw-new-client-phone">Telefone / WhatsApp</label>
              <input id="pw-new-client-phone" type="tel" placeholder="(00) 00000-0000" inputmode="numeric" required>
            </div>
            <div class="pw-field pw-col-4">
              <label for="pw-new-client-email">E-mail</label>
              <input id="pw-new-client-email" type="email">
            </div>
            <div class="pw-field pw-col-4">
              <label for="pw-new-client-document">CPF / CNPJ</label>
              <input id="pw-new-client-document" type="text" inputmode="numeric" maxlength="18">
            </div>
            <div class="pw-field pw-col-4">
              <label for="pw-new-client-person-type">Tipo de pessoa</label>
              <input id="pw-new-client-person-type" type="text" readonly placeholder="Identificado pelo CPF/CNPJ">
            </div>
            <div class="pw-field pw-col-4">
              <label for="pw-new-client-customer-type">Tipo de cliente</label>
              <select id="pw-new-client-customer-type"><option value="direto">Cliente direto</option><option value="revenda">Revendedor</option></select>
            </div>
            <div class="pw-field pw-col-8"><label class="pw-check-row"><input id="pw-new-client-monthly-closing" type="checkbox"> Pagamento por fechamento mensal</label><small>Os pedidos serão acumulados e cobrados no dia definido.</small></div>
            <div class="pw-field pw-col-4" id="pw-new-client-closing-day-wrap" hidden><label class="pw-required" for="pw-new-client-closing-day">Dia do fechamento</label><input id="pw-new-client-closing-day" type="number" min="1" max="31" inputmode="numeric" placeholder="1 a 31"></div>
            <div class="pw-field pw-col-12"><label class="pw-check-row"><input id="pw-new-client-whatsapp-notify" type="checkbox" checked> 📲 Enviar notificações de WhatsApp ao mudar a situação do pedido</label><small>Se desmarcado, este cliente não receberá mensagens automáticas de atualização de situação.</small></div>
          </div>
        </div>

        <div class="pw-tab-panel" data-client-panel="address">
          <div class="pw-address-tools">
            <p>Digite o CEP e pressione <strong>ENTER</strong> para preencher o endereço e avançar para o número.</p>
            <button class="pw-btn pw-btn-soft" id="pw-toggle-address-finder" type="button">Não sei o CEP — consultar pelo endereço</button>
          </div>

          <div class="pw-address-finder" id="pw-address-finder">
            <div class="pw-grid">
              <div class="pw-field pw-col-2">
                <label class="pw-required" for="pw-find-state">UF</label>
                <input id="pw-find-state" type="text" maxlength="2" placeholder="SP">
              </div>
              <div class="pw-field pw-col-4">
                <label class="pw-required" for="pw-find-city">Cidade</label>
                <input id="pw-find-city" type="text">
              </div>
              <div class="pw-field pw-col-6">
                <label class="pw-required" for="pw-find-street">Rua / avenida</label>
                <input id="pw-find-street" type="text">
              </div>
              <div class="pw-field pw-col-12">
                <button class="pw-btn pw-btn-blue" id="pw-find-cep" type="button">Pesquisar CEP</button>
              </div>
            </div>
            <div class="pw-address-results" id="pw-address-results"></div>
          </div>

          <div class="pw-grid">
            <div class="pw-field pw-col-3">
              <label for="pw-new-client-cep">CEP</label>
              <input id="pw-new-client-cep" type="text" inputmode="numeric" maxlength="9" placeholder="00000-000">
            </div>
            <div class="pw-field pw-col-7">
              <label for="pw-new-client-street">Endereço</label>
              <input id="pw-new-client-street" type="text">
            </div>
            <div class="pw-field pw-col-2">
              <label for="pw-new-client-number">Número</label>
              <input id="pw-new-client-number" type="text">
            </div>
            <div class="pw-field pw-col-4">
              <label for="pw-new-client-complement">Complemento</label>
              <input id="pw-new-client-complement" type="text">
            </div>
            <div class="pw-field pw-col-4">
              <label for="pw-new-client-neighborhood">Bairro</label>
              <input id="pw-new-client-neighborhood" type="text">
            </div>
            <div class="pw-field pw-col-3">
              <label for="pw-new-client-city">Cidade</label>
              <input id="pw-new-client-city" type="text">
            </div>
            <div class="pw-field pw-col-2">
              <label for="pw-new-client-city-code">Código IBGE</label>
              <input id="pw-new-client-city-code" type="text" inputmode="numeric" maxlength="7" placeholder="Ex.: 3501608">
            </div>
            <div class="pw-field pw-col-1">
              <label for="pw-new-client-state">UF</label>
              <input id="pw-new-client-state" type="text" maxlength="2">
            </div>
          </div>
          <div id="pw-new-client-address-validation" class="pw-field-hint pw-ai-address-validation" role="status" hidden></div>
        </div>

        <div class="pw-tab-panel" data-client-panel="shipping">
          <div class="pw-grid" style="padding-top:10px">
            <div class="pw-field pw-col-12"><label class="pw-check-row"><input id="pw-new-client-accepts-correios" type="checkbox" checked> 📦 Aceita envio pelos Correios</label><small>Se desmarcado, as opções dos Correios não serão exibidas ao gerar etiqueta para este cliente.</small></div>
            <div class="pw-field pw-col-12"><label class="pw-check-row"><input id="pw-new-client-accepts-transportadoras" type="checkbox" checked> 🚚 Aceita envio por Transportadores</label><small>Se desmarcado, as opções de transportadoras (Jadlog, Loggi, etc.) não serão exibidas ao gerar etiqueta para este cliente.</small></div>
          </div>
        </div>

        <div class="pw-tab-panel" data-client-panel="login">
          <div id="pw-client-login-tab-info"></div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px">
            <button class="pw-btn pw-btn-soft" id="pw-client-copy-login-link" type="button">🔗 Copiar link de acesso</button>
            <button class="pw-btn pw-btn-soft" id="pw-client-resend-login-email" type="button">✉ Enviar link por e-mail</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="pw-modal" id="pw-wp-login-modal" aria-hidden="true">
    <div class="pw-modal-panel" role="dialog" aria-modal="true" aria-labelledby="pw-wp-login-modal-title" style="max-width:480px">
      <div class="pw-modal-header">
        <h3 id="pw-wp-login-modal-title">Criar login no WordPress</h3>
        <button class="pw-btn pw-btn-soft pw-btn-icon" type="button" data-close-modal="pw-wp-login-modal" aria-label="Fechar">×</button>
      </div>
      <div class="pw-modal-body">
        <div id="pw-wp-login-existing-info" hidden style="margin-bottom:12px;padding:10px 12px;border-radius:6px;background:var(--pw-bg-soft,#f4f6f8)"></div>
        <div id="pw-wp-login-form">
          <div class="pw-grid">
            <div class="pw-field pw-col-12">
              <label class="pw-required" for="pw-wp-login-email">E-mail para envio do convite</label>
              <input id="pw-wp-login-email" type="email" required>
              <small>Um e-mail com link de acesso será enviado para este endereço.</small>
            </div>
            <div class="pw-field pw-col-12">
              <label for="pw-wp-login-role">Função no WordPress</label>
              <select id="pw-wp-login-role">
                <option value="customer">Cliente</option>
                <option value="revendedor">Revendedor</option>
              </select>
            </div>
          </div>
        </div>
      </div>
      <div class="pw-modal-footer">
        <button class="pw-btn pw-btn-primary" id="pw-wp-login-confirm" type="button">Criar login</button>
      </div>
    </div>
  </div>

  <!-- Painel de rastreio inline (iframe) -->
  <div id="pw-tracking-panel" hidden style="position:fixed;inset:0;z-index:10100;display:flex;flex-direction:column;background:var(--pw-bg,#fff)">
    <div style="display:flex;align-items:center;gap:8px;padding:10px 14px;border-bottom:1px solid var(--pw-border,#dee2e6);background:var(--pw-bg-soft,#f4f6f8);flex-shrink:0">
      <strong style="font-size:.9em">Rastreio</strong>
      <a id="pw-tracking-fallback-link" href="#" target="_blank" rel="noopener noreferrer" style="font-size:.8em;opacity:.7;margin-left:4px">↗ Abrir em nova aba</a>
      <button type="button" id="pw-tracking-panel-close" style="margin-left:auto;background:none;border:none;cursor:pointer;font-size:1.25rem;line-height:1;padding:2px 6px;border-radius:4px" aria-label="Fechar rastreio">✕</button>
    </div>
    <iframe id="pw-tracking-iframe" src="" style="flex:1;border:none;width:100%" title="Rastreio de envio" loading="lazy"></iframe>
    <div style="padding:6px 14px;font-size:.75em;opacity:.6;text-align:center;border-top:1px solid var(--pw-border,#dee2e6)">Se a página não carregar, use o link "Abrir em nova aba" acima.</div>
  </div>

  <div class="pw-modal" id="pw-product-modal" aria-hidden="true">
    <div class="pw-modal-panel" role="dialog" aria-modal="true" aria-labelledby="pw-product-modal-title">
      <div class="pw-modal-header">
        <h3 id="pw-product-modal-title">Cadastrar novo produto</h3>
        <button class="pw-btn pw-btn-soft pw-btn-icon" type="button" data-close-modal="pw-product-modal" aria-label="Fechar">×</button>
      </div>
      <div class="pw-form-toolbar pw-modal-toolbar" aria-label="Ferramentas do produto">
        <button class="pw-btn pw-btn-soft pw-toolbar-button pw-tooltip" id="pw-new-product-record" type="button" data-tooltip="Novo produto" aria-label="Novo produto">＋</button>
        <button class="pw-btn pw-btn-soft pw-toolbar-button pw-tooltip" id="pw-print-product" type="button" data-tooltip="Imprimir cadastro" aria-label="Imprimir cadastro">⎙</button>
        <button class="pw-btn pw-btn-primary pw-toolbar-button pw-tooltip" id="pw-save-product" type="button" data-tooltip="Salvar produto" aria-label="Salvar produto">✓</button>
      </div>
      <div class="pw-modal-body">
        <div class="pw-product-tabs" role="tablist" aria-label="Seções do cadastro do produto">
          <button class="pw-product-tab is-active" type="button" role="tab" aria-selected="true" data-product-tab="details">Dados do produto</button>
          <button class="pw-product-tab" type="button" role="tab" aria-selected="false" data-product-tab="variations">Variações e faixas</button>
          <button class="pw-product-tab" type="button" role="tab" aria-selected="false" data-product-tab="notes">Observações</button>
        </div>
        <div class="pw-grid pw-product-tab-panel is-active" role="tabpanel" data-product-panel="details">
          <div class="pw-field pw-col-12">
            <label class="pw-check-row"><input id="pw-new-product-active" type="checkbox" checked> Produto ativo</label>
          </div>
          <div class="pw-field pw-col-4">
            <label for="pw-new-product-code">Código</label>
            <input id="pw-new-product-code" type="text" readonly>
          </div>
          <div class="pw-field pw-col-8">
            <label class="pw-required" for="pw-new-product-description">Descrição</label>
            <input id="pw-new-product-description" type="text">
          </div>
          <div class="pw-field pw-col-4">
            <label class="pw-required" for="pw-new-product-category">Categoria</label>
            <div class="pw-select-action">
              <select id="pw-new-product-category"></select>
              <button class="pw-quick-add" type="button" data-quick-registry="category" title="Cadastrar nova categoria" aria-label="Cadastrar nova categoria">+</button>
            </div>
          </div>
          <div class="pw-field pw-col-4">
            <label class="pw-required" for="pw-new-product-unit">Unidade de venda</label>
            <div class="pw-select-action">
              <select id="pw-new-product-unit"></select>
              <button class="pw-quick-add" type="button" data-quick-registry="unit" title="Cadastrar nova unidade de venda" aria-label="Cadastrar nova unidade de venda">+</button>
            </div>
          </div>
          <div class="pw-field pw-col-4" hidden aria-hidden="true">
            <label for="pw-new-product-supplier">Fornecedor</label>
            <div class="pw-select-action">
              <select id="pw-new-product-supplier"></select>
              <button class="pw-quick-add" type="button" data-quick-registry="supplier" title="Cadastrar novo fornecedor" aria-label="Cadastrar novo fornecedor">+</button>
            </div>
          </div>
          <div class="pw-field pw-col-4">
            <label for="pw-new-product-cost">Valor de custo</label>
            <input id="pw-new-product-cost" type="text" inputmode="decimal" placeholder="R$ 0,00">
          </div>
          <div class="pw-field pw-col-4">
            <label class="pw-required" for="pw-new-product-price">Valor de venda</label>
            <input id="pw-new-product-price" type="text" inputmode="decimal" placeholder="R$ 0,00">
          </div>
        </div>
        <div class="pw-grid pw-product-tab-panel" role="tabpanel" data-product-panel="variations" hidden>
          <div class="pw-field pw-col-12 pw-variations-editor">
            <div class="pw-section-title pw-variations-title">
              <div>
                <h4>Variações do produto</h4>
                <p>Use quando o mesmo produto possuir descrições, unidades ou preços diferentes conforme a quantidade vendida.</p>
              </div>
              <button class="pw-btn pw-btn-blue" id="pw-add-product-variation" type="button">＋ Adicionar variação</button>
            </div>
            <div class="pw-table-wrap">
              <table class="pw-variations-table">
                <colgroup><col class="pw-vcol-description"><col class="pw-vcol-range"><col class="pw-vcol-range"><col class="pw-vcol-unit"><col class="pw-vcol-money"><col class="pw-vcol-money"><col class="pw-vcol-action"></colgroup>
                <thead><tr><th>Descrição</th><th>Quantidade mínima</th><th>Quantidade máxima</th><th>Unidade</th><th>Custo</th><th>Venda</th><th><span class="pw-screen-reader">Ações</span></th></tr></thead>
                <tbody id="pw-product-variations-body"><tr class="pw-empty-variations"><td colspan="7">Produto sem variações. Serão usados os dados principais acima.</td></tr></tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="pw-grid pw-product-tab-panel" role="tabpanel" data-product-panel="notes" hidden>
          <div class="pw-field pw-col-12 pw-product-notes-field">
            <label for="pw-new-product-notes">Observações internas</label>
            <textarea id="pw-new-product-notes" rows="4" placeholder="Informações internas sobre produção, compra ou utilização do produto"></textarea>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="pw-modal" id="pw-variation-modal" aria-hidden="true">
    <div class="pw-modal-panel pw-variation-picker" role="dialog" aria-modal="true" aria-labelledby="pw-variation-modal-title">
      <div class="pw-modal-header">
        <div><h3 id="pw-variation-modal-title">Escolher variação</h3><p id="pw-variation-product-name"></p></div>
        <button class="pw-btn pw-btn-soft pw-btn-icon" type="button" data-close-modal="pw-variation-modal" aria-label="Fechar">×</button>
      </div>
      <div class="pw-modal-body">
        <p>Este produto possui variações. Selecione uma delas antes de incluí-lo no pedido.</p>
        <div id="pw-variation-options" class="pw-variation-options"></div>
      </div>
    </div>
  </div>

  <div class="pw-modal" id="pw-dtf-cost-modal" aria-hidden="true">
    <div class="pw-modal-panel pw-dtf-cost-modal-panel" role="dialog" aria-modal="true" aria-labelledby="pw-dtf-cost-modal-title">
      <div class="pw-modal-header"><h3 id="pw-dtf-cost-modal-title">Alterar despesa</h3><button class="pw-btn pw-btn-soft pw-btn-icon" type="button" data-close-modal="pw-dtf-cost-modal" aria-label="Fechar">×</button></div>
      <div class="pw-form-toolbar pw-modal-toolbar"><button class="pw-btn pw-btn-soft pw-toolbar-button pw-tooltip" id="pw-print-dtf-cost" type="button" data-tooltip="Imprimir despesa" aria-label="Imprimir despesa">⎙</button></div>
      <div class="pw-modal-body" id="pw-dtf-cost-modal-body"></div>
    </div>
  </div>

  <div class="pw-modal" id="pw-registry-modal" aria-hidden="true">
    <div class="pw-modal-panel" role="dialog" aria-modal="true" aria-labelledby="pw-registry-modal-title" style="max-width:720px">
      <div class="pw-modal-header">
        <h3 id="pw-registry-modal-title">Novo cadastro</h3>
        <button class="pw-btn pw-btn-soft pw-btn-icon" type="button" data-close-modal="pw-registry-modal" aria-label="Fechar">×</button>
      </div>
      <div class="pw-form-toolbar pw-modal-toolbar" aria-label="Ferramentas do cadastro">
        <button class="pw-btn pw-btn-soft pw-toolbar-button pw-tooltip" id="pw-new-registry-record" type="button" data-tooltip="Novo registro" aria-label="Novo registro">＋</button>
        <button class="pw-btn pw-btn-soft pw-toolbar-button pw-tooltip" id="pw-print-registry" type="button" data-tooltip="Imprimir cadastro" aria-label="Imprimir cadastro">⎙</button>
        <button class="pw-btn pw-btn-primary pw-toolbar-button pw-tooltip" id="pw-save-registry" type="button" data-tooltip="Salvar registro" aria-label="Salvar registro">✓</button>
      </div>
      <div class="pw-modal-body">
        <input id="pw-registry-kind" type="hidden">
        <input id="pw-registry-id" type="hidden">
      <div class="pw-grid">
          <div class="pw-field pw-registry-active-field"><label class="pw-check-row"><input id="pw-registry-active" type="checkbox" checked> Cadastro ativo</label></div>
          <div class="pw-field pw-registry-code-field"><label for="pw-registry-code">Código</label><input id="pw-registry-code" type="text" readonly></div>
          <div class="pw-field pw-registry-name-field"><label class="pw-required" for="pw-registry-name">Nome</label><input id="pw-registry-name" type="text"></div>
          <div class="pw-field pw-registry-order-origin-only"><label class="pw-check-row"><input id="pw-registry-marketplace" type="checkbox"> Marketplace</label><small>Ao selecionar esta origem no pedido, serão exibidos valor de venda, tarifa da plataforma e total líquido.</small></div>
          <div class="pw-field pw-registry-unit-only pw-registry-symbol-field"><label for="pw-registry-symbol">Sigla</label><input id="pw-registry-symbol" type="text" maxlength="10" placeholder="Ex.: un."></div>
          <div class="pw-field pw-registry-dtf-item-only"><label class="pw-required" for="pw-registry-expense-category">Categoria</label><select id="pw-registry-expense-category"></select></div>
          <div class="pw-field pw-registry-dtf-item-only"><label class="pw-required" for="pw-registry-expense-unit">Unidade sugerida</label><select id="pw-registry-expense-unit"></select></div>
          <div class="pw-field pw-registry-supplier-only"><label for="pw-registry-supplier-type">Tipo de fornecedor</label><select id="pw-registry-supplier-type"><option value="">Materiais e Anúncios</option><option value="materials">Materiais</option><option value="ads">Anúncios</option></select></div>
          <div class="pw-field pw-registry-supplier-only pw-registry-contact-field"><label for="pw-registry-contact">Pessoa de contato</label><input id="pw-registry-contact" type="text"></div>
          <div class="pw-field pw-registry-supplier-only pw-registry-phone-field"><label for="pw-registry-phone">Telefone / WhatsApp</label><input id="pw-registry-phone" type="tel" inputmode="numeric" placeholder="(00) 00000-0000"></div>
          <div class="pw-field pw-registry-supplier-only pw-registry-email-field"><label for="pw-registry-email">E-mail</label><input id="pw-registry-email" type="email"></div>
          <div class="pw-field pw-registry-supplier-only pw-registry-document-field"><label for="pw-registry-document">CPF / CNPJ</label><input id="pw-registry-document" type="text" inputmode="numeric" maxlength="18"></div>
          <div class="pw-field pw-registry-supplier-only pw-registry-notes-field"><label for="pw-registry-notes">Observações internas</label><textarea id="pw-registry-notes" rows="3"></textarea></div>
        </div>
      </div>
    </div>
  </div>

  <div class="pw-modal pw-unsaved-choice-modal" id="pw-unsaved-choice-modal" aria-hidden="true">
    <div class="pw-modal-panel pw-unsaved-choice-panel" role="alertdialog" aria-modal="true" aria-labelledby="pw-unsaved-choice-title" aria-describedby="pw-unsaved-choice-text">
      <div class="pw-modal-header"><div><h3 id="pw-unsaved-choice-title">Alterações não salvas</h3><p id="pw-unsaved-choice-text">Existem alterações não salvas. O que deseja fazer?</p></div></div>
      <div class="pw-modal-body"><div class="pw-unsaved-choice-actions">
        <button class="pw-btn pw-btn-primary" id="pw-unsaved-save-close" type="button">✓ Salvar e fechar</button>
        <button class="pw-btn pw-btn-danger" id="pw-unsaved-close-without-saving" type="button">↶ Fechar sem salvar</button>
        <button class="pw-btn pw-btn-soft" id="pw-unsaved-cancel" type="button">× Cancelar</button>
      </div></div>
    </div>
  </div>

  <div class="pw-modal pw-linked-delete-modal" id="pw-linked-delete-modal" aria-hidden="true">
    <div class="pw-modal-panel pw-linked-delete-panel" role="alertdialog" aria-modal="true" aria-labelledby="pw-linked-delete-title" aria-describedby="pw-linked-delete-text">
      <div class="pw-modal-header"><div><h3 id="pw-linked-delete-title">Registro com vínculos</h3><p id="pw-linked-delete-text">Este registro está cadastrado em outras partes do sistema.</p></div></div>
      <div class="pw-modal-body"><div class="pw-linked-delete-list" id="pw-linked-delete-list"></div><div class="pw-linked-delete-payments" id="pw-linked-delete-payments" hidden></div><div class="pw-linked-delete-cascade" id="pw-linked-delete-cascade" hidden></div><div class="pw-linked-delete-help" id="pw-linked-delete-help" hidden></div></div>
      <div class="pw-modal-footer pw-linked-delete-actions"><button class="pw-btn pw-btn-soft" id="pw-linked-delete-cancel" type="button">Cancelar</button><button class="pw-btn pw-btn-danger" id="pw-linked-delete-confirm" type="button">EXCLUÍR</button></div>
    </div>
  </div>

  <div class="pw-modal" id="pw-monthly-closing-modal" aria-hidden="true">
    <div class="pw-modal-panel pw-monthly-closing-panel" role="dialog" aria-modal="true" aria-labelledby="pw-monthly-closing-title">
      <div class="pw-modal-header"><div><h3 id="pw-monthly-closing-title">Realizar fechamento mensal</h3><p id="pw-monthly-closing-description">Confira e ajuste a forma de pagamento e o valor de cada pedido. Só os pedidos com a caixinha marcada serão gravados.</p></div><button class="pw-btn pw-btn-soft pw-btn-icon" type="button" data-close-modal="pw-monthly-closing-modal" aria-label="Fechar">×</button></div>
      <div class="pw-modal-body">
        <div class="pw-payment-summary"><div><small>Pedidos marcados</small><strong id="pw-monthly-modal-count">0</strong></div><div><small>Total a receber</small><strong id="pw-monthly-modal-balance">R$ 0,00</strong></div></div>
        <div class="pw-grid">
          <div class="pw-field pw-col-6"><label class="pw-required" for="pw-monthly-payment-date">Data do recebimento</label><input id="pw-monthly-payment-date" type="date"></div>
          <div class="pw-field pw-col-6"><label for="pw-monthly-payment-note">Observação (aplicada a todos os pedidos marcados)</label><input id="pw-monthly-payment-note" type="text" maxlength="180" placeholder="Informações do fechamento"></div>
        </div>
        <div class="pw-table-wrap"><table class="pw-data-table pw-monthly-closing-detail-table">
          <thead><tr><th class="pw-select-cell"><input type="checkbox" id="pw-monthly-detail-select-all" checked title="Marcar/desmarcar todos"></th><th>Pedido</th><th>Cliente</th><th>Saldo</th><th>Forma de pagamento</th><th>Valor a receber</th></tr></thead>
          <tbody id="pw-monthly-closing-detail-body"></tbody>
        </table></div>
      </div>
      <div class="pw-modal-footer"><button class="pw-btn pw-btn-primary" id="pw-confirm-monthly-closing" type="button">Confirmar fechamento</button></div>
    </div>
  </div>

  <div class="pw-modal" id="pw-payment-modal" aria-hidden="true">
    <div class="pw-modal-panel pw-payment-panel" role="dialog" aria-modal="true" aria-labelledby="pw-payment-modal-title">
      <div class="pw-modal-header">
        <div><h3 id="pw-payment-modal-title">Pagamentos do pedido</h3><p id="pw-payment-order-number"></p></div>
        <button class="pw-btn pw-btn-soft pw-btn-icon" type="button" data-close-modal="pw-payment-modal" aria-label="Fechar">×</button>
      </div>
      <div class="pw-modal-body">
        <div class="pw-payment-summary"><div><small>Total do pedido</small><strong id="pw-payment-order-total">R$ 0,00</strong></div><div><small>Total recebido</small><strong id="pw-payment-order-paid">R$ 0,00</strong></div><div><small>Saldo pendente</small><strong id="pw-payment-order-balance">R$ 0,00</strong></div><div><small>Situação financeira</small><strong id="pw-payment-order-status">Pendente</strong></div></div>
        <div class="pw-product-tabs pw-payment-tabs" role="tablist"><button class="pw-product-tab pw-active is-active" type="button" data-payment-tab="adjustments" role="tab" aria-selected="true">Pagamento e ajustes</button><button class="pw-product-tab" type="button" data-payment-tab="history" role="tab" aria-selected="false">Histórico financeiro</button></div>
        <div class="pw-payment-tab-panel pw-active" data-payment-panel="adjustments">
        <section class="pw-payment-adjustments"><h4>Ajustes do pedido</h4><p>Desconto e acréscimo são aplicados junto com o registro do pagamento e só podem ser alterados por Administradores.</p><div class="pw-grid">
          <div class="pw-field pw-col-6"><label for="pw-payment-adjustment-discount">Desconto (R$)</label><input id="pw-payment-adjustment-discount" type="text" inputmode="decimal" value="0,00"></div>
          <div class="pw-field pw-col-6"><label for="pw-payment-adjustment-surcharge">Acréscimo (R$)</label><input id="pw-payment-adjustment-surcharge" type="text" inputmode="decimal" value="0,00"></div>
        </div></section>
        <section class="pw-payment-entry"><h4>Registrar movimentação</h4><div class="pw-grid">
          <div class="pw-field pw-col-4"><label class="pw-required" for="pw-payment-entry-method">Forma de pagamento</label><select id="pw-payment-entry-method"><option value="">Selecione</option></select></div>
          <div class="pw-field pw-col-4"><label class="pw-required" for="pw-payment-entry-type">Tipo</label><select id="pw-payment-entry-type"><option>Total</option><option>Parcial</option></select></div>
          <div class="pw-field pw-col-2"><label class="pw-required" for="pw-payment-entry-value">Valor</label><input id="pw-payment-entry-value" type="text" inputmode="decimal" placeholder="R$ 0,00"></div>
          <div class="pw-field pw-col-2"><label class="pw-required" for="pw-payment-entry-date">Data</label><input id="pw-payment-entry-date" type="date"></div>
        </div><div class="pw-payment-entry-actions"><button class="pw-btn pw-btn-primary" id="pw-payment-entry-save" type="button">Registrar pagamento</button></div></section>
        </div>
        <div class="pw-payment-tab-panel" data-payment-panel="history"><section class="pw-payment-history"><h4>Histórico financeiro</h4><div class="pw-table-wrap"><table class="pw-data-table"><thead><tr><th>Data</th><th>Tipo</th><th>Forma</th><th>Valor</th><th>Responsável</th><th>Observação</th><th>Ações</th></tr></thead><tbody id="pw-payment-history-body"></tbody></table></div></section></div>
      </div>
    </div>
  </div>

  <div class="pw-modal" id="pw-finalize-modal" aria-hidden="true">
    <div class="pw-modal-panel" role="dialog" aria-modal="true" aria-labelledby="pw-finalize-modal-title" style="max-width:760px">
      <div class="pw-modal-header">
        <h3 id="pw-finalize-modal-title">Registrar entrega do pedido</h3>
        <button class="pw-btn pw-btn-soft pw-btn-icon" type="button" data-close-modal="pw-finalize-modal" aria-label="Fechar">×</button>
      </div>
      <div class="pw-modal-body">
        <p>As etapas anteriores são permanentes. Registre a data e a hora da entrega. O pedido será finalizado automaticamente somente quando também estiver totalmente pago.</p>
        <div id="pw-finalize-status-history" class="pw-status-modal-history"></div>
        <div class="pw-grid">
        <div class="pw-field pw-col-6"><label class="pw-required" for="pw-final-status-at">Data e hora da situação</label><input id="pw-final-status-at" type="datetime-local" required></div>
        <div class="pw-field pw-col-6">
          <label class="pw-required" for="pw-quick-actual-delivery">Data em que realmente foi entregue</label>
          <input id="pw-quick-actual-delivery" type="date" required>
        </div>
        </div>
      </div>
      <div class="pw-modal-footer">
        <button class="pw-btn pw-btn-soft" id="pw-cancel-finalize" type="button">Cancelar</button>
        <button class="pw-btn pw-btn-primary" id="pw-confirm-finalize" type="button">Registrar entrega</button>
      </div>
    </div>
  </div>

  <div class="pw-modal" id="pw-dtf-uv-modal" aria-hidden="true">
    <div class="pw-modal-panel pw-dtf-uv-panel" role="dialog" aria-modal="true" aria-labelledby="pw-dtf-uv-title">
      <div class="pw-modal-header">
        <div><h3 id="pw-dtf-uv-title">Adicionar impressão DTF UV</h3><p id="pw-dtf-uv-subtitle">Anexe o PDF para medir a largura e preencher a altura total automaticamente.</p></div>
        <button class="pw-btn pw-btn-soft pw-btn-icon" type="button" data-close-modal="pw-dtf-uv-modal" aria-label="Fechar">×</button>
      </div>
      <div class="pw-modal-body">
        <div class="pw-dtf-art-upload">
          <div class="pw-field"><label for="pw-dtf-art-file">Arquivo de arte em PDF</label>
            <div class="pw-file-upload-row">
              <label class="pw-btn pw-btn-soft pw-file-upload-btn" for="pw-dtf-art-file">📎 Escolher arquivo</label>
              <button class="pw-btn pw-btn-danger pw-file-upload-btn" type="button" id="pw-dtf-art-file-remove" disabled>🗑 Apagar arquivo</button>
              <input id="pw-dtf-art-file" type="file" accept="application/pdf,.pdf" style="display:none">
            </div>
            <small class="pw-file-upload-name" id="pw-dtf-art-file-name">Nenhum arquivo escolhido</small>
            <small class="pw-field-hint" id="pw-dtf-art-status">Opcional ao criar o item; obrigatório antes de aprovar a arte do pedido.</small>
          </div>
          <div class="pw-dtf-measurement" id="pw-dtf-measurement" hidden>
            <span class="pw-dtf-measurement-icon" aria-hidden="true">↔</span>
            <span><strong id="pw-dtf-measurement-title"></strong><small id="pw-dtf-measurement-detail"></small></span>
            <button class="pw-btn pw-btn-soft" id="pw-dtf-use-pdf-height" type="button" hidden>↻ Usar altura medida</button>
          </div>
        </div>
        <div class="pw-dtf-uv-form" id="pw-dtf-standard-form">
          <div class="pw-field"><label for="pw-dtf-customer-type">Tipo de cliente</label><select id="pw-dtf-customer-type"><option value="direto">Cliente direto</option><option value="revenda">Revendedor</option></select><small class="pw-field-hint" id="pw-dtf-customer-type-hint">Selecione o tipo utilizado no cálculo.</small></div>
          <div class="pw-field"><label for="pw-dtf-fixed-width">Largura fixa</label><input id="pw-dtf-fixed-width" type="text" value="28 cm" readonly></div>
          <div class="pw-field"><label class="pw-required" for="pw-dtf-height">Altura total do PDF (cm)</label><input id="pw-dtf-height" type="number" min="0.01" step="0.01" inputmode="decimal" placeholder="Ex.: 25"></div>
          <button class="pw-btn pw-btn-primary" id="pw-dtf-calculate" type="button">Calcular valor</button>
        </div>
        <div class="pw-dtf-marketplace-form" id="pw-dtf-marketplace-form" hidden>
          <div class="pw-dtf-marketplace-note"><strong>Origem Marketplace</strong><span>Informe o preço bruto anunciado e a tarifa cobrada pela plataforma. O tamanho do PDF não será usado no cálculo.</span></div>
          <div class="pw-field"><label class="pw-required" for="pw-dtf-marketplace-sale">Valor de venda na plataforma (R$)</label><input id="pw-dtf-marketplace-sale" type="text" inputmode="decimal" placeholder="0,00"></div>
          <div class="pw-field"><label class="pw-required" for="pw-dtf-marketplace-fee">Tarifa da plataforma (R$)</label><input id="pw-dtf-marketplace-fee" type="text" inputmode="decimal" placeholder="0,00"></div>
          <button class="pw-btn pw-btn-primary" id="pw-dtf-marketplace-calculate" type="button">Calcular valor líquido</button>
        </div>
        <div class="pw-dtf-result" id="pw-dtf-result" aria-live="polite"><span>Informe a altura para calcular.</span></div>
      </div>
      <div class="pw-modal-footer"><button class="pw-btn pw-btn-primary" id="pw-dtf-insert" type="button" disabled>✓ Adicionar ao pedido</button></div>
    </div>
  </div>

  <div class="pw-modal pw-dtf-height-choice-modal" id="pw-dtf-height-choice-modal" aria-hidden="true">
    <div class="pw-modal-panel pw-dtf-height-choice-panel" role="dialog" aria-modal="true" aria-labelledby="pw-dtf-height-choice-title">
      <div class="pw-modal-header"><div><h3 id="pw-dtf-height-choice-title">Altura diferente do PDF</h3><p>A altura cadastrada precisa ser igual à medida confirmada no arquivo.</p></div></div>
      <div class="pw-modal-body"><p id="pw-dtf-height-choice-description">Deseja corrigir agora o campo de altura usando o valor exato do PDF?</p></div>
      <div class="pw-modal-footer pw-dtf-height-choice-actions">
        <button class="pw-btn pw-btn-primary" id="pw-dtf-height-fix-now" type="button">↻ Corrigir altura agora</button>
        <button class="pw-btn pw-btn-danger" id="pw-dtf-height-remove-pdf" type="button">⌫ Fechar e remover PDF do anexo</button>
        <button class="pw-btn pw-btn-soft" id="pw-dtf-height-choice-cancel" type="button">× Cancelar</button>
      </div>
    </div>
  </div>

  <section class="pw-system-view pw-consultation" data-view="shipping-label">
    <div class="pw-frenet-subnav">
      <button type="button" class="pw-frenet-subnav-btn" data-system-view="shipping-label">🏷 Gerar etiqueta</button>
      <button type="button" class="pw-frenet-subnav-btn" data-system-view="shipping-history">📦 Etiquetas geradas</button>
    </div>
    <div class="pw-consultation-header">
      <div><h3>Gerar etiqueta</h3><p>Cotação no Melhor Envio — escolha a melhor opção e gere a etiqueta.</p></div>
    </div>
    <div id="pw-shipping-platform-status" class="pw-shipping-platform-status" hidden></div>
    <div class="pw-frenet-balance-row" id="pw-me-balance-row" hidden><span id="pw-me-balance"></span><a class="pw-btn pw-btn-soft" href="https://melhorenvio.com.br/login" target="_blank" rel="noopener noreferrer">💳 Inserir crédito</a></div>
    <div class="pw-frenet-setup-hint" id="pw-me-setup-hint" hidden><button type="button" class="pw-btn pw-btn-soft" id="pw-me-open-settings">⚙ Configurar Token em Configurações → Envio</button></div>
    <div class="pw-grid">
      <div class="pw-field pw-col-4"><label>Buscar dados do pedido</label><div class="pw-search-wrap"><input id="pw-me-order-number" type="search" autocomplete="off" placeholder="Nº do pedido, nome ou telefone do cliente"><div id="pw-me-order-results" class="pw-search-results" role="listbox"></div></div></div>
      <div class="pw-field pw-col-2" style="align-self:end"><button class="pw-btn pw-btn-soft" type="button" id="pw-me-load-order">Carregar</button></div>
    </div>
    <details class="pw-frenet-sender-section" id="pw-me-sender-section">
      <summary><span>Remetente</span><small id="pw-me-sender-preview"></small></summary>
      <p class="pw-field-hint">Vem preenchido com os dados da Configuração fiscal (NF-e); pode editar se precisar. É sempre o mesmo, então fica fechado por padrão.</p>
      <div class="pw-address-tools">
        <p>Digite o CEP e pressione <strong>ENTER</strong> para preencher o endereço e avançar para o número.</p>
        <button class="pw-btn pw-btn-soft" id="pw-me-sender-toggle-address-finder" type="button">Não sei o CEP — consultar pelo endereço</button>
      </div>
      <div class="pw-address-finder" id="pw-me-sender-address-finder">
        <div class="pw-grid">
          <div class="pw-field pw-col-2"><label class="pw-required" for="pw-me-sender-find-state">UF</label><input id="pw-me-sender-find-state" type="text" maxlength="2" placeholder="SP"></div>
          <div class="pw-field pw-col-4"><label class="pw-required" for="pw-me-sender-find-city">Cidade</label><input id="pw-me-sender-find-city" type="text"></div>
          <div class="pw-field pw-col-6"><label class="pw-required" for="pw-me-sender-find-street">Rua / avenida</label><input id="pw-me-sender-find-street" type="text"></div>
          <div class="pw-field pw-col-12"><button class="pw-btn pw-btn-blue" id="pw-me-sender-find-cep" type="button">Pesquisar CEP</button></div>
        </div>
        <div class="pw-address-results" id="pw-me-sender-address-results"></div>
      </div>
      <div class="pw-grid">
        <div class="pw-field pw-col-6"><label class="pw-required" for="pw-me-sender-name">Nome / Razão social</label><input id="pw-me-sender-name" type="text"></div>
        <div class="pw-field pw-col-3"><label class="pw-required" for="pw-me-sender-document">CPF/CNPJ</label><input id="pw-me-sender-document" type="text"></div>
        <div class="pw-field pw-col-3"><label class="pw-required" for="pw-me-sender-phone">Telefone</label><input id="pw-me-sender-phone" type="text"></div>
        <div class="pw-field pw-col-3"><label class="pw-required" for="pw-me-sender-cep">CEP de origem</label><input id="pw-me-sender-cep" type="text" inputmode="numeric" maxlength="9" placeholder="00000-000"></div>
        <div class="pw-field pw-col-7"><label class="pw-required" for="pw-me-sender-street">Rua</label><input id="pw-me-sender-street" type="text"></div>
        <div class="pw-field pw-col-2"><label class="pw-required" for="pw-me-sender-number">Número</label><input id="pw-me-sender-number" type="text"></div>
        <div class="pw-field pw-col-3"><label for="pw-me-sender-complement">Complemento</label><input id="pw-me-sender-complement" type="text"></div>
        <div class="pw-field pw-col-5"><label class="pw-required" for="pw-me-sender-neighborhood">Bairro</label><input id="pw-me-sender-neighborhood" type="text"></div>
        <div class="pw-field pw-col-2"><label class="pw-required" for="pw-me-sender-city">Cidade</label><input id="pw-me-sender-city" type="text"></div>
        <div class="pw-field pw-col-2"><label class="pw-required" for="pw-me-sender-state">UF</label><input id="pw-me-sender-state" type="text" maxlength="2"></div>
      </div>
    </details>
    <div class="pw-section-title" style="margin-top:6px"><div><h3 style="font-size:14px">Destinatário</h3></div></div>
    <div class="pw-address-tools">
      <p>Digite o CEP e pressione <strong>ENTER</strong> para preencher o endereço e avançar para o número.</p>
      <button class="pw-btn pw-btn-soft" id="pw-me-label-toggle-address-finder" type="button">Não sei o CEP — consultar pelo endereço</button>
    </div>
    <div class="pw-address-finder" id="pw-me-label-address-finder">
      <div class="pw-grid">
        <div class="pw-field pw-col-2"><label class="pw-required" for="pw-me-label-find-state">UF</label><input id="pw-me-label-find-state" type="text" maxlength="2" placeholder="SP"></div>
        <div class="pw-field pw-col-4"><label class="pw-required" for="pw-me-label-find-city">Cidade</label><input id="pw-me-label-find-city" type="text"></div>
        <div class="pw-field pw-col-6"><label class="pw-required" for="pw-me-label-find-street">Rua / avenida</label><input id="pw-me-label-find-street" type="text"></div>
        <div class="pw-field pw-col-12"><button class="pw-btn pw-btn-blue" id="pw-me-label-find-cep" type="button">Pesquisar CEP</button></div>
      </div>
      <div class="pw-address-results" id="pw-me-label-address-results"></div>
    </div>
    <div class="pw-grid">
      <div class="pw-field pw-col-6"><label class="pw-required" for="pw-me-label-name">Nome do destinatário</label><div class="pw-search-wrap"><input id="pw-me-label-name" type="search" autocomplete="off" placeholder="Digite para buscar um cliente cadastrado ou digite manualmente"><div id="pw-me-label-client-results" class="pw-search-results" role="listbox"></div></div></div>
      <div class="pw-field pw-col-3"><label class="pw-required" for="pw-me-label-document">CPF/CNPJ</label><input id="pw-me-label-document" type="text"></div>
      <div class="pw-field pw-col-3"><label class="pw-required" for="pw-me-label-phone">Telefone</label><input id="pw-me-label-phone" type="text"></div>
      <div class="pw-field pw-col-3"><label class="pw-required" for="pw-me-label-cep">CEP de destino</label><input id="pw-me-label-cep" type="text" inputmode="numeric" maxlength="9" placeholder="00000-000"></div>
      <div class="pw-field pw-col-7"><label class="pw-required" for="pw-me-label-street">Rua</label><input id="pw-me-label-street" type="text"></div>
      <div class="pw-field pw-col-2"><label class="pw-required" for="pw-me-label-number">Número</label><input id="pw-me-label-number" type="text"></div>
      <div class="pw-field pw-col-3"><label for="pw-me-label-complement">Complemento</label><input id="pw-me-label-complement" type="text"></div>
      <div class="pw-field pw-col-5"><label class="pw-required" for="pw-me-label-neighborhood">Bairro</label><input id="pw-me-label-neighborhood" type="text"></div>
      <div class="pw-field pw-col-2"><label class="pw-required" for="pw-me-label-city">Cidade</label><input id="pw-me-label-city" type="text"></div>
      <div class="pw-field pw-col-2"><label class="pw-required" for="pw-me-label-state">UF</label><input id="pw-me-label-state" type="text" maxlength="2"></div>
    </div>
    <div class="pw-section-title" style="margin-top:6px"><div><h3 style="font-size:14px">Volume</h3></div></div>
    <div class="pw-grid">
      <div class="pw-field pw-col-2"><label class="pw-required" for="pw-me-label-height">Altura (cm)</label><input id="pw-me-label-height" type="number" min="0.1" step="0.1" value="20"></div>
      <div class="pw-field pw-col-2"><label class="pw-required" for="pw-me-label-width">Largura (cm)</label><input id="pw-me-label-width" type="number" min="0.1" step="0.1" value="30"></div>
      <div class="pw-field pw-col-2"><label class="pw-required" for="pw-me-label-length">Comprimento (cm)</label><input id="pw-me-label-length" type="number" min="0.1" step="0.1" value="30"></div>
      <div class="pw-field pw-col-2"><label class="pw-required" for="pw-me-label-weight">Peso (kg)</label><input id="pw-me-label-weight" type="number" min="0.001" step="0.001" value="1"></div>
      <div class="pw-field pw-col-4"><label class="pw-required" for="pw-me-label-value">Valor declarado (R$)</label><input id="pw-me-label-value" type="text" inputmode="decimal" value="100,00"></div>
    </div>
    <p class="pw-field-hint">A etiqueta é gerada como declaração de conteúdo (sem Nota Fiscal). O valor declarado é usado para o seguro do envio.</p>
    <div class="pw-frenet-label-services" id="pw-me-services"><p>Preencha os dados acima e busque as opções de frete para escolher a transportadora da etiqueta.</p></div>
    <div class="pw-frenet-result" id="pw-me-result" aria-live="polite"></div>
    <div class="pw-frenet-page-actions"><button class="pw-btn pw-btn-soft" id="pw-me-search" type="button">⌖ Buscar opções de frete</button><button class="pw-btn pw-btn-primary" id="pw-me-generate-label" type="button" disabled>🏷 Gerar etiqueta</button></div>
  </section>

  <section class="pw-system-view pw-consultation" data-view="shipping-history">
    <div class="pw-frenet-subnav">
      <button type="button" class="pw-frenet-subnav-btn" data-system-view="shipping-label">🏷 Gerar etiqueta</button>
      <button type="button" class="pw-frenet-subnav-btn" data-system-view="shipping-history">📦 Etiquetas geradas</button>
    </div>
    <div class="pw-consultation-header">
      <div><h3>Etiquetas geradas</h3><p>Histórico das etiquetas geradas pelo sistema via Melhor Envio.</p><small class="pw-mktp-updated-at" id="pw-me-tracking-updated-at"></small></div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="pw-btn pw-btn-soft" id="pw-me-add-balance" href="https://melhorenvio.com.br/login" target="_blank" rel="noopener noreferrer">💳 Inserir crédito no Melhor Envio</a>
      </div>
    </div>
    <div class="pw-frenet-balance-row" id="pw-me-history-balance-row" hidden><span id="pw-me-history-balance"></span></div>
    <div class="pw-grid pw-me-labels-filters">
      <div class="pw-field pw-col-3"><label for="pw-me-filter-status">Situação</label><select id="pw-me-filter-status"><option value="except-delivered">Todas - Exceto Entregue</option><option value="">Todas</option></select></div>
      <div class="pw-field pw-col-3"><label for="pw-me-filter-carrier">Transportadora</label><select id="pw-me-filter-carrier"><option value="">Todas</option></select></div>
      <div class="pw-field pw-col-2"><label for="pw-me-filter-from">De</label><input type="date" id="pw-me-filter-from"></div>
      <div class="pw-field pw-col-2"><label for="pw-me-filter-to">Até</label><input type="date" id="pw-me-filter-to"></div>
      <div class="pw-field pw-col-2" style="align-self:end"><button class="pw-btn pw-btn-soft" id="pw-me-filter-clear" type="button">✕ Limpar filtros</button></div>
    </div>
    <div class="pw-table-wrap"><table class="pw-data-table"><thead><tr><th>Data</th><th>Pedido</th><th>Destinatário</th><th>Transportadora</th><th>Situação</th><th>Valor</th><th>Etiqueta</th><th>Ação</th></tr></thead><tbody id="pw-me-labels-body"></tbody><tfoot id="pw-me-labels-foot" hidden><tr><td colspan="5">Total do período filtrado</td><td id="pw-me-labels-total"></td><td colspan="2" id="pw-me-labels-count"></td></tr></tfoot></table></div>
    <p class="pw-field-hint" id="pw-me-labels-empty" hidden>Nenhuma etiqueta gerada pelo sistema ainda.</p>
  </section>

  <div class="pw-modal" id="pw-status-date-modal" aria-hidden="true">
    <div class="pw-modal-panel" role="dialog" aria-modal="true" aria-labelledby="pw-status-date-title" style="max-width:560px">
      <div class="pw-modal-header"><h3 id="pw-status-date-title">Registrar alteração de situação</h3><button class="pw-btn pw-btn-soft pw-btn-icon" type="button" data-close-modal="pw-status-date-modal" aria-label="Fechar">×</button></div>
      <div class="pw-modal-body"><div id="pw-status-date-history" class="pw-status-modal-history"></div><div class="pw-field"><label for="pw-status-date-value">Data e hora da nova situação</label><div class="pw-search-row"><input id="pw-status-date-value" type="datetime-local"><button class="pw-btn pw-btn-soft pw-btn-icon" id="pw-status-date-now" type="button" title="Usar data e hora atuais" aria-label="Usar data e hora atuais">⏱</button></div></div></div>
      <div class="pw-modal-footer"><button class="pw-btn pw-btn-soft" id="pw-status-date-undo" type="button" style="margin-right:auto;color:#c0392b" hidden>↶ Desfazer</button><button class="pw-btn pw-btn-primary" id="pw-status-date-confirm" type="button">Confirmar alteração</button></div>
    </div>
  </div>

  <div class="pw-modal" id="pw-status-save-reminder-modal" aria-hidden="true">
    <div class="pw-modal-panel pw-status-save-reminder-panel" role="dialog" aria-modal="true" aria-labelledby="pw-status-save-reminder-title">
      <div class="pw-modal-header"><h3 id="pw-status-save-reminder-title">Salve a alteração da situação</h3></div>
      <div class="pw-modal-body"><p>A nova situação foi preparada no formulário, mas ainda não foi gravada no pedido.</p><p>Para confirmar esta e todas as próximas alterações de situação, clique em <strong>Salvar pedido</strong>. Se sair da edição sem salvar, a situação não será alterada.</p></div>
      <div class="pw-modal-footer"><button class="pw-btn pw-btn-primary" id="pw-status-save-reminder-ack" type="button">✓ Entendi</button></div>
    </div>
  </div>

  <div class="pw-modal" id="pw-new-order-notify-modal" aria-hidden="true">
    <div class="pw-modal-panel pw-new-order-notify-panel" role="dialog" aria-modal="true" aria-labelledby="pw-new-order-notify-title">
      <div class="pw-modal-header"><div><h3 id="pw-new-order-notify-title">📦 Novo pedido</h3><p>Um pedido foi cadastrado no sistema.</p></div><button class="pw-btn pw-btn-soft pw-btn-icon" type="button" data-close-modal="pw-new-order-notify-modal" aria-label="Fechar">×</button></div>
      <div class="pw-new-order-notify-nav" id="pw-new-order-notify-nav" hidden>
        <button class="pw-btn pw-btn-soft pw-btn-icon" type="button" id="pw-new-order-notify-prev" aria-label="Pedido anterior">‹</button>
        <span id="pw-new-order-notify-position">1 de 1</span>
        <button class="pw-btn pw-btn-soft pw-btn-icon" type="button" id="pw-new-order-notify-next" aria-label="Próximo pedido">›</button>
      </div>
      <div class="pw-modal-body" id="pw-new-order-notify-body"><p>Carregando…</p></div>
    </div>
  </div>

  <div class="pw-modal" id="pw-ml-manual-nf-modal" aria-hidden="true">
    <div class="pw-modal-panel" role="dialog" aria-modal="true" aria-labelledby="pw-ml-manual-nf-title" style="max-width:420px">
      <div class="pw-modal-header"><h3 id="pw-ml-manual-nf-title">Marcar nota fiscal como já enviada</h3><button class="pw-btn pw-btn-soft pw-btn-icon" type="button" data-close-modal="pw-ml-manual-nf-modal" aria-label="Fechar">×</button></div>
      <div class="pw-modal-body">
        <p class="pw-field-hint">Use isso quando a nota fiscal e a etiqueta já foram resolvidas fora do sistema (ex.: direto pelo painel do Mercado Livre). O botão "Enviar XML" some e não volta a aparecer para este pedido.</p>
        <div class="pw-field"><label for="pw-ml-manual-nf-number">Número da NF *</label><input type="text" id="pw-ml-manual-nf-number" placeholder="Ex.: 12345" maxlength="20"></div>
        <div class="pw-field"><label for="pw-ml-manual-nf-series">Série (opcional)</label><input type="text" id="pw-ml-manual-nf-series" placeholder="Ex.: 1" maxlength="10"></div>
        <div class="pw-field"><label for="pw-ml-manual-nf-xml">Arquivo XML (opcional)</label><input type="file" id="pw-ml-manual-nf-xml" accept=".xml"></div>
      </div>
      <div class="pw-modal-footer"><button class="pw-btn pw-btn-soft" type="button" data-close-modal="pw-ml-manual-nf-modal">Cancelar</button><button class="pw-btn pw-btn-primary" type="button" id="pw-ml-manual-nf-save">Salvar</button></div>
    </div>
  </div>

  <div class="pw-modal" id="pw-duplicate-client-modal" aria-hidden="true">
    <div class="pw-modal-panel" role="dialog" aria-modal="true" aria-labelledby="pw-duplicate-client-title" style="max-width:520px">
      <div class="pw-modal-header"><h3 id="pw-duplicate-client-title">Possível cliente já cadastrado</h3><button class="pw-btn pw-btn-soft pw-btn-icon" type="button" id="pw-duplicate-client-close" aria-label="Fechar">×</button></div>
      <div class="pw-modal-body"><p id="pw-duplicate-client-reason"></p><div class="pw-duplicate-client-match" id="pw-duplicate-client-info"></div><p>Confira se não é o mesmo cliente antes de continuar. Se forem pessoas ou empresas diferentes que apenas compartilham esse dado, pode salvar normalmente.</p></div>
      <div class="pw-modal-footer"><button class="pw-btn pw-btn-soft" id="pw-duplicate-client-cancel" type="button">Cancelar e revisar</button><button class="pw-btn pw-btn-primary" id="pw-duplicate-client-proceed" type="button">Salvar mesmo assim</button></div>
    </div>
  </div>

  <div class="pw-modal" id="pw-case-correction-modal" aria-hidden="true">
    <div class="pw-modal-panel" role="dialog" aria-modal="true" aria-labelledby="pw-case-correction-title" style="max-width:980px">
      <div class="pw-modal-header"><div><h3 id="pw-case-correction-title">Correção automática de textos em maiúsculo</h3><p id="pw-case-correction-subtitle"></p></div><button class="pw-btn pw-btn-soft pw-btn-icon" type="button" data-close-modal="pw-case-correction-modal" aria-label="Fechar">×</button></div>
      <div class="pw-modal-body">
        <div class="pw-product-tabs pw-modal-tabs" role="tablist" aria-label="Correção automática de textos">
          <button class="pw-product-tab pw-modal-tab pw-active is-active" type="button" data-case-correction-tab="pending" role="tab" aria-selected="true">Pendentes</button>
          <button class="pw-product-tab pw-modal-tab" type="button" data-case-correction-tab="ignored" role="tab" aria-selected="false">Ignorados</button>
        </div>
        <div class="pw-case-correction-toolbar"><label class="pw-check-row"><input type="checkbox" id="pw-case-correction-select-all"> Selecionar todos</label><span id="pw-case-correction-count"></span></div>
        <div class="pw-table-wrap"><table class="pw-data-table" id="pw-case-correction-table"><thead><tr><th></th><th>Cadastro</th><th>Registro</th><th>Campo</th><th>Está assim</th><th>Ficaria assim</th></tr></thead><tbody id="pw-case-correction-body"></tbody></table></div>
        <p id="pw-case-correction-empty" hidden>Nenhum campo fora do padrão foi encontrado. Seus cadastros já estão certinhos.</p>
      </div>
      <div class="pw-modal-footer"><button class="pw-btn pw-btn-soft" id="pw-case-correction-ignore" type="button">🙈 Ignorar selecionados</button><button class="pw-btn pw-btn-soft" id="pw-case-correction-restore" type="button" hidden>↺ Restaurar selecionados</button><button class="pw-btn pw-btn-primary" id="pw-case-correction-apply" type="button">✓ Aplicar correções selecionadas</button></div>
    </div>
  </div>

  <div class="pw-modal" id="pw-status-audit-modal" aria-hidden="true">
    <div class="pw-modal-panel" role="dialog" aria-modal="true" aria-labelledby="pw-status-audit-title" style="max-width:760px">
      <div class="pw-modal-header"><div><h3 id="pw-status-audit-title">Auditar situações</h3><p id="pw-status-audit-order"></p></div><button class="pw-btn pw-btn-soft pw-btn-icon" type="button" data-close-modal="pw-status-audit-modal" aria-label="Fechar">×</button></div>
      <div class="pw-modal-body"><div class="pw-status-audit-navigation" id="pw-status-audit-navigation"><button class="pw-btn pw-btn-soft pw-btn-icon pw-tooltip" id="pw-status-audit-prev" type="button" data-tooltip="Pedido anterior" aria-label="Pedido anterior">‹</button><strong id="pw-status-audit-position"></strong><button class="pw-btn pw-btn-soft pw-btn-icon pw-tooltip" id="pw-status-audit-next" type="button" data-tooltip="Próximo pedido" aria-label="Próximo pedido">›</button></div><p>Corrija datas e horários. Como Administrador, também é possível remover apenas a última situação registrada e voltar o pedido para a etapa anterior.</p><div id="pw-status-audit-fields" class="pw-status-audit-fields"></div></div>
      <div class="pw-modal-footer"><button class="pw-btn pw-btn-danger" id="pw-rollback-status-audit" type="button" hidden>↶ Remover última situação</button><button class="pw-btn pw-btn-primary pw-btn-icon pw-tooltip" id="pw-save-status-audit" type="button" data-tooltip="Salvar auditoria" aria-label="Salvar auditoria">✓</button></div>
    </div>
  </div>

  <div class="pw-modal" id="pw-history-modal" aria-hidden="true">
    <div class="pw-modal-panel" role="dialog" aria-modal="true" aria-labelledby="pw-history-title" style="max-width:780px">
      <div class="pw-modal-header"><h3 id="pw-history-title">Histórico do registro</h3><button class="pw-btn pw-btn-soft pw-btn-icon" type="button" data-close-modal="pw-history-modal" aria-label="Fechar">×</button></div>
      <div class="pw-modal-body"><div class="pw-timeline" id="pw-history-timeline"></div></div>
    </div>
  </div>

  <div class="pw-modal" id="pw-print-model-modal" aria-hidden="true">
    <div class="pw-modal-panel pw-print-model-panel" role="dialog" aria-modal="true" aria-labelledby="pw-print-model-title">
      <div class="pw-modal-header">
        <div><h3 id="pw-print-model-title">Escolha o que deseja imprimir</h3><p>Posicione o mouse sobre um modelo para visualizar a miniatura.</p></div>
        <button class="pw-btn pw-btn-soft pw-btn-icon" type="button" data-close-modal="pw-print-model-modal" aria-label="Fechar">×</button>
      </div>
      <div class="pw-modal-body pw-print-model-layout">
        <div class="pw-print-model-list" role="list" aria-label="Modelos disponíveis">
          <button type="button" class="pw-print-model-option" data-print-document="pedido"><span class="pw-print-model-icon">▤</span><span><strong>Pedido</strong><small>Documento completo para conferência e atendimento.</small></span></button>
          <button type="button" class="pw-print-model-option" data-print-document="orcamento"><span class="pw-print-model-icon">◇</span><span><strong>Orçamento</strong><small>Proposta comercial com validade, itens e valores.</small></span></button>
          <button type="button" class="pw-print-model-option" data-print-document="producao"><span class="pw-print-model-icon">⚙</span><span><strong>Ordem de produção</strong><small>Informações técnicas, quantidades e conferência interna.</small></span></button>
          <button type="button" class="pw-print-model-option" data-print-document="recibo"><span class="pw-print-model-icon">✓</span><span><strong>Recibo</strong><small>Comprovante do valor e do pagamento registrado.</small></span></button>
          <button type="button" class="pw-print-model-option" data-print-document="resumo"><span class="pw-print-model-icon">≡</span><span><strong>Resumo</strong><small>Visão rápida dos principais dados do pedido.</small></span></button>
        </div>
        <div class="pw-print-preview-shell">
          <div class="pw-print-preview-label">Prévia do modelo</div>
          <div id="pw-print-model-preview" class="pw-print-model-preview" aria-live="polite"></div>
        </div>
      </div>
    </div>
  </div>

  <div class="pw-modal" id="pw-dashboard-report-modal" aria-hidden="true">
    <div class="pw-modal-panel" role="dialog" aria-modal="true" aria-labelledby="pw-dashboard-report-title" style="width:min(1120px,96vw)">
      <div class="pw-modal-header"><h3 id="pw-dashboard-report-title">Relatório</h3><button class="pw-btn pw-btn-soft pw-btn-icon" type="button" data-close-modal="pw-dashboard-report-modal" aria-label="Fechar">×</button></div>
      <div class="pw-modal-body"><div id="pw-dashboard-report-summary"></div><div class="pw-table-scroll"><table class="pw-data-table"><thead id="pw-dashboard-report-head"></thead><tbody id="pw-dashboard-report-body"></tbody></table></div></div>
    </div>
  </div>

  <div class="pw-modal" id="pw-theme-modal" aria-hidden="true">
    <div class="pw-modal-panel pw-theme-panel" role="dialog" aria-modal="true" aria-labelledby="pw-theme-title">
      <div class="pw-modal-header">
        <div><h3 id="pw-theme-title">Aparência do sistema</h3><p>Escolha um tema de cores e um layout de cabeçalho para o seu usuário. A alteração não afeta os demais usuários.</p></div>
        <button class="pw-btn pw-btn-soft pw-btn-icon pw-tooltip" id="pw-theme-peek" type="button" data-tooltip="Ver por trás da janela por 5 segundos" aria-label="Ver por trás da janela">👁</button>
        <button class="pw-btn pw-btn-soft pw-btn-icon" id="pw-theme-cancel-x" type="button" aria-label="Fechar e cancelar alterações">×</button>
      </div>
      <div class="pw-modal-body">
        <div class="pw-theme-grid" id="pw-theme-grid" role="radiogroup" aria-label="Temas disponíveis"></div>
        <section class="pw-header-layout-section" aria-labelledby="pw-header-layout-title">
          <div class="pw-theme-custom-heading"><div><h4 id="pw-header-layout-title">Layout do cabeçalho</h4><p>Escolha como a barra do topo (logo, previsão do tempo e versão) aparece pra você.</p></div></div>
          <div class="pw-header-layout-grid" id="pw-header-layout-grid" role="radiogroup" aria-label="Layouts de cabeçalho disponíveis"></div>
        </section>
        <section class="pw-theme-custom" aria-labelledby="pw-theme-custom-title">
          <div class="pw-theme-custom-heading"><div><h4 id="pw-theme-custom-title">Personalizado</h4><p>As mudanças aparecem imediatamente apenas como prévia.</p></div><span class="pw-theme-preview-badge">Prévia ao vivo</span></div>
          <div class="pw-grid">
            <div class="pw-field pw-col-4"><label for="pw-theme-primary">Cor principal</label><input id="pw-theme-primary" type="color" value="#963b00"></div>
            <div class="pw-field pw-col-4"><label for="pw-theme-accent">Cor de destaque</label><input id="pw-theme-accent" type="color" value="#0b5ed7"></div>
            <div class="pw-field pw-col-4"><label>Fonte</label><input id="pw-theme-font" type="hidden" value="system-ui, sans-serif"><button class="pw-font-picker-toggle" id="pw-font-picker-toggle" type="button" aria-expanded="false" aria-controls="pw-font-options"><span id="pw-font-picker-label">Sistema</span><span aria-hidden="true">⌄</span></button><div class="pw-font-options" id="pw-font-options" role="listbox" aria-label="Escolha uma fonte"></div></div>
          </div>
        </section>
      </div>
      <div class="pw-modal-footer pw-theme-actions"><button class="pw-btn pw-btn-soft" id="pw-theme-cancel" type="button">Cancelar</button><button class="pw-btn pw-btn-primary" id="pw-theme-save" type="button">Salvar aparência</button></div>
    </div>
  </div>
</div>



