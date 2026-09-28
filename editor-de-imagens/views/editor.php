<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<style>#dtfEditor .dtf-tolerance-group{display:none}#dtfStartup .dtf-startup-progress{display:none}</style>
<style>#dtfEditor [data-panel="editar"]>.dtf-group:empty{display:none}</style>
<style>#dtfEditor .dtf-viewport.dtf-dragging{cursor:grabbing!important}#dtfEditor .dtf-viewport.dtf-can-drag{cursor:grab}</style>
<style>#dtfEditor .dtf-history-arrow{display:none!important}</style>
<style>#dtfEditor .dtf-viewport{padding-left:32px;padding-top:22px;box-sizing:border-box}#dtfEditor .dtf-ruler-top{left:32px;top:0;z-index:10!important;overflow:visible!important}#dtfEditor .dtf-ruler-left{left:0;top:22px;z-index:8!important;border-right:0!important}#dtfEditor .dtf-ruler-top span{height:4px;border-left:1px solid #aaa;padding:0!important}#dtfEditor .dtf-ruler-top span.major{height:8px;border-left-color:#777}#dtfEditor .dtf-ruler-top span b{position:absolute;left:0;top:10px;transform:translateX(-50%);font:9px/9px system-ui,sans-serif;font-weight:400;white-space:nowrap}#dtfEditor .dtf-ruler-left span{width:4px;border-top:1px solid #aaa;padding:0!important}#dtfEditor .dtf-ruler-left span.major{width:8px;border-top-color:#777}#dtfEditor .dtf-ruler-left span b{position:absolute;left:11px;top:-4px;font:9px/9px system-ui,sans-serif;font-weight:400;white-space:nowrap}</style>
<style>#dtfEditor .dtf-inner{transform-origin:top left!important}</style>
<style>#dtfEditor .dtf-ruler-top{overflow:visible!important}</style>
<style>#dtfEditor .dtf-inner.dtf-grid{background-image:linear-gradient(rgba(0,115,170,.16) 1px,transparent 1px),linear-gradient(90deg,rgba(0,115,170,.16) 1px,transparent 1px)!important;background-size:var(--dtf-grid-size,10px) var(--dtf-grid-size,10px)!important}#dtfEditor .dtf-lock-badge{position:absolute;z-index:26;font-size:26px;opacity:.82;background:rgba(255,255,255,.7);border-radius:50%;padding:3px;pointer-events:none}</style>
<style>#dtfEditor .dtf-invert-menu{position:relative}#dtfEditor .dtf-invert-submenu{display:none;position:absolute;left:100%;top:0;background:#fff;border:1px solid #ccc;min-width:110px;padding:4px;z-index:5}#dtfEditor .dtf-invert-menu:hover .dtf-invert-submenu{display:block}#dtfEditor .dtf-invert-submenu button{display:block;width:100%;border:0;background:#fff;padding:7px;text-align:left}</style>
<style>#dtfEditor .dtf-properties-menu{position:relative}#dtfEditor .dtf-properties-submenu{display:none;position:absolute;left:100%;bottom:0;background:#fff;border:1px solid #ccc;min-width:140px;padding:4px;z-index:5}#dtfEditor .dtf-properties-menu:hover .dtf-properties-submenu{display:block}#dtfEditor .dtf-properties-submenu button{display:block;width:100%;border:0;background:#fff;padding:7px;text-align:left}</style>
<style>.dtf-properties-modal{position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:100000;display:flex;align-items:center;justify-content:center}.dtf-properties-box{background:#fff;padding:18px;border-radius:8px;width:430px;max-width:calc(100vw - 32px);box-shadow:0 8px 30px #0004;position:absolute}.dtf-properties-box h3{margin:0 0 6px;cursor:move}.dtf-properties-box label{display:block;margin:8px 0}.dtf-properties-box label:has(input[type=checkbox]){display:flex;align-items:center;gap:8px}.dtf-properties-box label:has(input[type=checkbox]) input{width:auto}.dtf-properties-box input{width:100%;box-sizing:border-box;padding:7px}.dtf-properties-actions{display:flex;gap:8px;margin-top:14px;justify-content:flex-end}</style>
<style>.dtf-properties-fields{display:flex;gap:10px}.dtf-properties-fields label{flex:1;margin-top:16px!important}.dtf-properties-box small{display:block;margin-top:8px;color:#666}.dtf-properties-box label:has(#propLock){display:flex!important;align-items:center!important;justify-content:flex-start!important;gap:7px!important;margin-top:14px!important}.dtf-properties-box #propLock{width:18px!important;height:18px;margin:0!important;flex:0 0 18px}</style>
<style>#dtfEditor .dtf-selection-label{display:none;font-size:13px!important;line-height:1.2;padding:5px 8px;background:#0073aa;color:#fff;font-weight:700;z-index:30;transform-origin:left bottom;border-radius:5px;white-space:nowrap}#dtfEditor .dtf-selection.dtf-measuring .dtf-selection-label{display:block}</style>
<style>#dtfEditor .dtf-ruler{position:absolute;z-index:8;background:#fff;color:#777;font:9px system-ui;pointer-events:auto;overflow:hidden;user-select:none;touch-action:none}.dtf-ruler-top{left:0;top:0;width:100%;height:22px;border-bottom:1px solid #ccc}.dtf-ruler-left{left:0;top:0;width:32px;height:100%;border-right:1px solid #ccc}.dtf-ruler-top span{position:absolute;top:0}.dtf-ruler-left span{position:absolute;left:0}#dtfEditor .dtf-ruler-corner{position:absolute;left:0;top:0;width:32px;height:22px;box-sizing:border-box;z-index:11;background:#fff;border-right:1px solid #ccc;border-bottom:1px solid #ccc;pointer-events:none}</style>
<div class="dtf-app-shell" style="width:1120px;max-width:100%;min-width:0;margin:0 auto;box-sizing:border-box;">
  <div id="dtfStartup" data-version="<?php echo esc_attr( defined('DTF_UV_EDITOR_VERSION') ? DTF_UV_EDITOR_VERSION : '1.0.90' ); ?>" role="status" aria-live="polite" style="box-sizing:border-box;min-height:260px;width:100%;padding:70px 20px;text-align:center;background:#fff;border:1px solid #dedede;border-radius:12px;color:#713100;font:600 16px/1.6 system-ui,sans-serif;"><div class="dtf-startup-spinner" aria-hidden="true"></div>Iniciando sistema…<br><small style="font-weight:400">Preparando o Editor de imagens.</small><br><small id="dtfStartupVersion" style="font-weight:400">Versão <?php echo esc_html( defined('DTF_UV_EDITOR_VERSION') ? DTF_UV_EDITOR_VERSION : '1.0.90' ); ?></small><div class="dtf-startup-progress"><i></i></div></div>
  <noscript>Ative o JavaScript para iniciar o Editor de imagens.</noscript>
  <script>
  (function(){var shell=document.currentScript.parentElement;setTimeout(function(){var notice=shell.querySelector('#dtfStartup');if(notice&&notice.style.display!=='none'){notice.textContent='A inicialização não terminou. Recarregue a página. Se persistir, informe que o editor não conseguiu carregar seus arquivos.';}},30000);})();
  </script>
<div class="dtf-editor" id="dtfEditor" data-dtf-uv-editor="1" data-dtf-version="<?php echo esc_attr( DTF_UV_EDITOR_VERSION ); ?>" data-dtf-version-url="<?php echo esc_url( admin_url( 'admin-ajax.php?action=dtf_uv_editor_version' ) ); ?>" aria-busy="true" style="display:none;visibility:hidden">
  <div class="dtf-version">Editor de imagens · versão <?php echo esc_html( DTF_UV_EDITOR_VERSION ); ?></div>
  <input id="fileInput" type="file" multiple accept="image/*,.avif,.webp,.bmp,.gif,.svg,.heic,.heif,.tif,.tiff,image/tiff,image/x-tiff,.pdf,application/pdf" hidden><input id="dtfProjectInput" type="file" accept=".pwedit,application/json" hidden><button id="dtfCenterObject" type="button" hidden aria-hidden="true" tabindex="-1"></button><button id="dtfCompare" type="button" hidden></button><input id="dtfCompareRange" type="range" hidden><span id="dtfCompareValue" hidden>50%</span>
  <div class="dtf-ribbon">
    <div class="dtf-tabs" role="tablist">
      <button class="dtf-tab active" type="button" data-tab="arquivo" role="tab" aria-selected="true">Arquivo</button>
      <button class="dtf-tab" type="button" data-tab="editar" role="tab" aria-selected="false">Editar</button>
      <button class="dtf-tab" type="button" data-tab="visualizar" role="tab" aria-selected="false">Visualizar</button>
      <button class="dtf-tab" type="button" data-tab="alinhamentos" role="tab" aria-selected="false">Alinhamentos</button>
      <button class="dtf-tab" type="button" data-tab="exportar" role="tab" aria-selected="false" aria-haspopup="dialog" title="Abrir exportação">Exportar</button>
      <div class="dtf-global-history" aria-label="Histórico"><div class="dtf-history-action"><button class="dtf-tool" id="dtfUndo" type="button" disabled><span class="dtf-icon">↶</span><span>Desfazer</span></button><button class="dtf-history-arrow" id="dtfUndoMenuBtn" type="button" aria-label="Escolher etapa para desfazer" aria-expanded="false">⌄</button><div class="dtf-history-menu" id="dtfUndoMenu" role="menu"></div></div><div class="dtf-history-action"><button class="dtf-tool" id="dtfRedo" type="button" disabled><span class="dtf-icon">↷</span><span>Refazer</span></button><button class="dtf-history-arrow" id="dtfRedoMenuBtn" type="button" aria-label="Escolher etapa para refazer" aria-expanded="false">⌄</button><div class="dtf-history-menu" id="dtfRedoMenu" role="menu"></div></div></div>
      <button class="dtf-tab dtf-help-tab" type="button" data-tab="ajuda" aria-pressed="false">Ajuda</button>
    </div>

    <section class="dtf-panel active" data-panel="arquivo" role="tabpanel">
      <div class="dtf-group">
        <div class="dtf-caption">ARQUIVO</div>
        <div class="dtf-tools">
          <button class="dtf-tool" id="dtfNewProject" type="button" title="Novo projeto" aria-label="Novo projeto"><span class="dtf-icon">＋</span><span>Novo projeto</span></button><button class="dtf-tool" id="dtfLoad" type="button" title="Importar imagem ou PDF" aria-label="Importar imagem ou PDF"><span class="dtf-icon">↑</span><span>Importar imagem</span></button><button class="dtf-tool" id="dtfOpenProject" type="button" title="Abrir projeto .pwedit" aria-label="Abrir projeto .pwedit"><span class="dtf-icon">📂</span><span>Abrir projeto</span></button><button class="dtf-tool" id="dtfSaveProject" type="button" title="Salvar projeto .pwedit" aria-label="Salvar projeto .pwedit"><span class="dtf-icon">💾</span><span>Salvar projeto</span></button>
        </div>
      </div>
      <div class="dtf-group dtf-workspace-group">
        <div class="dtf-caption">ÁREA DE TRABALHO</div>
        <div class="dtf-workspace-row">
          <span class="dtf-label-inline">Largura</span>
          <input class="dtf-number dtf-work-number" id="dtfWorkW" type="number" min="10" max="1000" step="1" value="280" aria-label="Largura da área de trabalho em milímetros">
          <span class="dtf-mm">mm</span>
          <span class="dtf-label-inline">×</span>
          <span class="dtf-label-inline">Altura</span>
          <input class="dtf-number dtf-work-number" id="dtfWorkH" type="number" min="10" max="10000" step="1" value="100" aria-label="Altura da área de trabalho em milímetros">
          <span class="dtf-mm">mm</span>
          <button class="dtf-secondary dtf-work-apply" id="dtfApplyWork" type="button">Aplicar</button>
        </div>
      </div>
      <div hidden aria-hidden="true"><select id="dtfPdfPage" disabled><option value="1">1</option></select><select id="dtfPdfDpi"><option>150</option><option>200</option><option selected>300</option><option>450</option><option>600</option></select><button id="dtfPdfConvert" type="button" disabled>Converter</button><input id="dtfBgColor" type="color" value="#ffffff"><input id="dtfTransparent" type="checkbox" checked><select id="dtfWorkDpi"><option>150</option><option>200</option><option selected>300</option><option>450</option><option>600</option></select></div>
      <div class="dtf-group"><div class="dtf-caption">PREFERÊNCIAS</div><div class="dtf-row"><label>Unidade de medida</label><select class="dtf-select" id="dtfUnit"><option value="mm" selected>Milímetros (mm)</option><option value="cm">Centímetros (cm)</option><option value="m">Metros (m)</option><option value="px">Pixels (px)</option></select></div></div>
    </section>

    <section class="dtf-panel" data-panel="editar" role="tabpanel">
      <div class="dtf-group dtf-color-tools-removed" aria-hidden="true"><div class="dtf-row"><button class="dtf-secondary" id="dtfPickColor" type="button">Selecionar cor</button><input class="dtf-color" id="dtfPickColorPreview" type="color" value="#ffffff"><label>Suavidade</label><input class="dtf-range" id="dtfEdge" type="range" min="0" max="100" value="30"><span class="dtf-value" id="dtfEdgeValue">30</span><button class="dtf-secondary" id="dtfDehalo" type="button">Limpar halos</button></div></div>
      <div class="dtf-group dtf-edit-primary-group">
        <div class="dtf-caption">AÇÕES</div>
        <div class="dtf-tools dtf-edit-primary-tools">
          <button class="dtf-tool" id="dtfOriginal" type="button" title="Original" aria-label="Original"><span class="dtf-icon">↶</span><span>Original</span></button>
          <span class="dtf-edit-action-gap" aria-hidden="true"></span>
          <button class="dtf-tool" id="dtfReset" type="button" title="Remover fundo: restaura a imagem original e aplica a tolerância escolhida" aria-label="Remover fundo"><span class="dtf-icon">✦</span><span>Remover fundo</span></button>
          <button class="dtf-tool dtf-magic-tool" id="dtfMagicFill" type="button" title="Montagem inteligente (IA)" aria-label="Montagem inteligente com IA" aria-haspopup="dialog"><span class="dtf-icon dtf-magic-wand-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path class="dtf-magic-wand" d="M5 20 16.8 8.2 19.8 11.2 8 23z"/><path class="dtf-magic-spark dtf-magic-spark-a" d="M7 2v4M5 4h4"/><path class="dtf-magic-spark dtf-magic-spark-b" d="M17 1v5M14.5 3.5h5"/><path class="dtf-magic-spark dtf-magic-spark-c" d="M21 7v3M19.5 8.5h3"/></svg></span><span>Montagem inteligente (IA)</span></button>
        </div>
        <div class="dtf-row dtf-tolerance-row"><label for="dtfTol">Tolerância</label><button class="dtf-round" id="dtfTolMinus" type="button">−</button><input class="dtf-range" id="dtfTol" type="range" min="0" max="100" value="20"><button class="dtf-round" id="dtfTolPlus" type="button">+</button><span class="dtf-value" id="dtfTolValue">20</span></div>
      </div>
      <div class="dtf-group">
        <div class="dtf-caption">OBJETO</div>
        <div class="dtf-tools"><button class="dtf-tool" id="dtfDuplicate" type="button" disabled><span class="dtf-icon">▣</span><span>Duplicar</span></button><button class="dtf-tool" id="dtfDelete" type="button" disabled><span class="dtf-icon">⌫</span><span>Excluir</span></button></div>
      </div>
      <div class="dtf-group">
        <div class="dtf-caption">PINCEL</div>
        <div class="dtf-tools"><button class="dtf-tool" id="dtfEraser" type="button"><span class="dtf-icon">◯</span><span>Borracha</span></button><button class="dtf-tool" id="dtfRestoreBrush" type="button"><span class="dtf-icon">◉</span><span>Restaurar</span></button></div><div class="dtf-row"><label for="dtfBrush">Tamanho</label><input class="dtf-range" id="dtfBrush" type="range" min="4" max="180" value="32"><span class="dtf-value" id="dtfBrushValue">32</span></div>
      </div>
      <div class="dtf-group"><div class="dtf-caption">REMOÇÃO DE FUNDO DE ÁREA</div><div class="dtf-tools"><button class="dtf-tool" id="dtfClickRemove" type="button" title="Remoção de fundo de área (sem IA): remove cores semelhantes conectadas ao ponto clicado" aria-label="Remoção de fundo de área sem IA"><span class="dtf-icon">＋</span><span>Remoção de fundo de área (sem IA)</span></button><button class="dtf-tool" id="dtfAreaRemove" type="button" title="Remoção de fundo de área (com IA): arraste o círculo no fundo; a IA protege o objeto e impede que a remoção atravesse seus contornos" aria-label="Remoção de fundo de área com IA"><span class="dtf-icon">▣</span><span>Remoção de fundo de área (com IA)</span></button></div><div class="dtf-row"><label for="dtfClickTol">Borda</label><input class="dtf-range" id="dtfClickTol" type="range" min="1" max="100" value="35"><span class="dtf-value" id="dtfClickTolValue">35</span></div></div>
      <div class="dtf-group"><div class="dtf-caption">REMOÇÃO POR COR</div><div class="dtf-tools"><button class="dtf-tool" id="dtfColorRemove" type="button" title="Remover por cor global: remove a cor clicada em toda a imagem, dentro e fora das regiões" aria-label="Remover por cor global"><span class="dtf-icon">◎</span><span>Remover por cor global</span></button></div></div>
      <div class="dtf-group"><div class="dtf-caption">PREENCHIMENTO</div><div class="dtf-tools"><button class="dtf-tool" id="dtfFillTool" type="button"><span class="dtf-icon">▰</span><span>Lata de tinta</span></button></div><div class="dtf-row"><label for="dtfFillColor">Cor</label><input class="dtf-color" id="dtfFillColor" type="color" value="#8c3f00"></div></div>
    </section>

    <section class="dtf-panel" data-panel="visualizar" role="tabpanel">
      <div class="dtf-group">
        <div class="dtf-caption">SELECIONADO</div>
        <div class="dtf-row"><span class="dtf-workspace-info">Objeto: <b id="dtfSelectedName">Nenhum</b></span></div><div class="dtf-small">Total na área: <b id="dtfObjectTotal">0</b> · Selecionados: <b id="dtfObjectSelected">0</b></div>
      </div>
      <div class="dtf-group">
        <div class="dtf-caption">ZOOM</div>
        <div class="dtf-tools"><button class="dtf-tool" id="dtfZoomOut" type="button"><span class="dtf-icon">−</span><span>Reduzir</span></button><button class="dtf-tool" id="dtfZoom100" type="button"><span class="dtf-icon">100%</span><span>Original</span></button><button class="dtf-tool" id="dtfZoomIn" type="button"><span class="dtf-icon">+</span><span>Ampliar</span></button><button class="dtf-tool" id="dtfZoomFit" type="button" title="Ajustar largura da página" aria-label="Ajustar largura da página"><span class="dtf-icon dtf-align-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3h14v18H5zM3 12h18M5 12l-2-2m2 2-2 2m16 0 2-2-2-2"/></svg></span><span>Ajustar largura da página</span></button></div>
      </div>
    </section>

    <section class="dtf-panel" data-panel="alinhamentos" role="tabpanel">
      <div class="dtf-group"><div class="dtf-caption">ALINHAMENTO HORIZONTAL</div><div class="dtf-tools">
        <button class="dtf-tool dtf-align-btn" id="dtfAlignLeft" type="button" title="Alinhar à esquerda" aria-label="Alinhar à esquerda"><span class="dtf-icon dtf-align-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 3v18M7 6h11v4H7zM7 14h8v4H7z"/></svg></span><span>À esquerda</span></button>
        <button class="dtf-tool dtf-align-btn" id="dtfAlignCenter" type="button" title="Centralizar horizontalmente" aria-label="Centralizar horizontalmente"><span class="dtf-icon dtf-align-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path class="dtf-guide" d="M12 2v20"/><path d="M5 6h14v4H5zM7.5 14h9v4h-9z"/></svg></span><span>Centralizado</span></button>
        <button class="dtf-tool dtf-align-btn" id="dtfAlignRight" type="button" title="Alinhar à direita" aria-label="Alinhar à direita"><span class="dtf-icon dtf-align-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 3v18M6 6h11v4H6zM9 14h8v4H9z"/></svg></span><span>À direita</span></button>
      </div></div>
      <div class="dtf-group"><div class="dtf-caption">ALINHAMENTO VERTICAL</div><div class="dtf-tools">
        <button class="dtf-tool dtf-align-btn" id="dtfAlignTop" type="button" title="Alinhar acima" aria-label="Alinhar acima"><span class="dtf-icon dtf-align-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h18M6 7h4v11H6zM14 7h4v8h-4z"/></svg></span><span>Acima</span></button>
        <button class="dtf-tool dtf-align-btn" id="dtfAlignMiddle" type="button" title="Centralizar verticalmente" aria-label="Centralizar verticalmente"><span class="dtf-icon dtf-align-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path class="dtf-guide" d="M2 12h20"/><path d="M6 5h4v14H6zM14 7.5h4v9h-4z"/></svg></span><span>Centralizado</span></button>
        <button class="dtf-tool dtf-align-btn" id="dtfAlignBottom" type="button" title="Alinhar abaixo" aria-label="Alinhar abaixo"><span class="dtf-icon dtf-align-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 20h18M6 6h4v11H6zM14 9h4v8h-4z"/></svg></span><span>Abaixo</span></button>
      </div></div>
      <div class="dtf-group"><div class="dtf-caption">DISTRIBUIÇÃO</div><div class="dtf-tools">
        <button class="dtf-tool dtf-align-btn" id="dtfDistributeH" type="button" title="Distribuir horizontalmente" aria-label="Distribuir horizontalmente"><span class="dtf-icon dtf-align-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4v16M21 4v16M6 7h3v10H6zM11 5h3v14h-3zM16 7h3v10h-3z"/></svg></span><span>Distribuir horizontal</span></button>
        <button class="dtf-tool dtf-align-btn" id="dtfDistributeV" type="button" title="Distribuir verticalmente" aria-label="Distribuir verticalmente"><span class="dtf-icon dtf-align-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 3h16M4 21h16M7 6h10v3H7zM5 11h14v3H5zM7 16h10v3H7z"/></svg></span><span>Distribuir vertical</span></button>
        <button class="dtf-tool dtf-align-btn" id="dtfOrganize" type="button" title="Organizar objetos em linhas" aria-label="Organizar objetos em linhas" aria-expanded="false"><span class="dtf-icon dtf-align-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h5v5H3zM10 4h5v5h-5zM17 4h4v5h-4zM3 12h5v5H3zM10 12h5v5h-5zM17 12h4v5h-4zM5 20h14"/></svg></span><span>Organizar</span></button>
      </div><div class="dtf-organize-options" id="dtfOrganizeOptions"><label><span>Horizontal (<span class="dtf-unit-label">mm</span>)</span><input id="dtfOrganizeH" type="number" min="0" step="0.1" value="5"></label><label><span>Vertical (<span class="dtf-unit-label">mm</span>)</span><input id="dtfOrganizeV" type="number" min="0" step="0.1" value="5"></label><button class="dtf-secondary" id="dtfOrganizeApply" type="button">Aplicar</button></div></div>
    </section>

    <section class="dtf-panel dtf-export-direct-panel" data-panel="exportar" role="tabpanel" aria-label="Exportação direta" hidden></section>

    <div class="dtf-properties-modal dtf-export-modal" id="dtfExportModal" role="dialog" aria-modal="true" aria-labelledby="dtfExportTitle" hidden>
      <div class="dtf-properties-box dtf-export-box">
        <h3 id="dtfExportTitle">Exportar arquivo</h3>
        <div class="dtf-export-content">
          <div class="dtf-export-controls">
            <div class="dtf-export-section">
              <div class="dtf-export-label">Formato</div>
              <div class="dtf-export-formats" role="radiogroup" aria-label="Formato de exportação">
                <button type="button" class="dtf-export-choice active" data-export-format="png" role="radio" aria-checked="true" title="Imagem PNG sem perda">PNG</button>
                <button type="button" class="dtf-export-choice" data-export-format="tiff" role="radio" aria-checked="false" title="TIFF RGBA sem perda com resolução original automática">TIFF</button>
                <button type="button" class="dtf-export-choice" data-export-format="webp" role="radio" aria-checked="false" title="Imagem WebP compactada">WebP</button>
                <button type="button" class="dtf-export-choice" data-export-format="jpeg" role="radio" aria-checked="false" title="Imagem JPEG compactada">JPEG</button>
                <button type="button" class="dtf-export-choice" data-export-format="pdf" role="radio" aria-checked="false" title="Documento PDF em alta qualidade e na medida exata">PDF</button>
              </div>
            </div>
            <div class="dtf-export-settings" aria-live="polite">
              <div class="dtf-export-section dtf-export-options" id="dtfExportImageOptions" hidden>
                <div class="dtf-export-label">Imagem</div>
                  <label class="dtf-export-quality">Qualidade <span class="dtf-value" id="dtfQualityValue">100</span><input class="dtf-range" id="dtfQuality" type="range" min="60" max="100" value="100"></label>
                  <label class="dtf-export-ai-enhance" title="Melhora contornos e detalhes e exporta JPEG/WebP com resolução ampliada."><input id="dtfExportAiEnhance" type="checkbox" checked> Usar melhoria de IA</label>
              </div>
            </div>
            <div class="dtf-export-summary"><strong id="dtfExportFormatHint">PNG sem perda</strong><span id="dtfOutputInfo">3307 × 1181 px · 300 DPI</span></div>
          </div>
          <div class="dtf-export-preview-section">
            <div class="dtf-export-label">Prévia</div>
            <div class="dtf-export-preview-frame" id="dtfExportPreviewFrame">
              <canvas id="dtfExportPreviewCanvas" width="420" height="280" aria-label="Prévia do arquivo que será exportado"></canvas>
              <span class="dtf-export-preview-empty" id="dtfExportPreviewEmpty">Preparando prévia…</span>
              <div class="dtf-export-preview-progress" id="dtfExportPreviewProgress" hidden><div><i id="dtfExportPreviewProgressBar"></i></div><span id="dtfExportPreviewProgressText">Preparando prévia…</span></div>
            </div>
            <div class="dtf-export-preview-meta" id="dtfExportPreviewMeta" aria-live="polite">Prévia da área de trabalho · dois cliques alternam Enquadrar/100%</div>
          </div>
        </div>
        <div class="dtf-properties-actions"><button type="button" id="dtfExportCancel">Cancelar</button><button type="button" id="dtfExportConfirm">Exportar</button></div>
      </div>
    </div>

    <div class="dtf-properties-modal dtf-magic-modal" id="dtfMagicModal" role="dialog" aria-modal="true" aria-labelledby="dtfMagicTitle" hidden>
      <div class="dtf-properties-box dtf-magic-box">
        <h3 id="dtfMagicTitle">Montagem inteligente (IA)</h3>
        <p class="dtf-magic-message" id="dtfMagicMessage"></p>
        <div class="dtf-magic-source-section">
          <div class="dtf-magic-section-title">Imagens da montagem</div>
          <div class="dtf-magic-upload" id="dtfMagicUploadWrap" hidden>
            <input id="dtfMagicUpload" type="file" accept="image/*,.avif,.webp,.bmp,.gif,.svg,.heic,.heif,.tif,.tiff,image/tiff,image/x-tiff" hidden>
            <button class="dtf-secondary" id="dtfMagicUploadButton" type="button"><span aria-hidden="true">↑</span> Enviar imagem</button>
            <span>Escolha uma imagem compatível.</span>
          </div>
          <div class="dtf-magic-sources" id="dtfMagicSources"></div>
          <div class="dtf-magic-capacity" id="dtfMagicCapacity" role="status" aria-live="polite"></div>
          <label class="dtf-magic-auto-height" id="dtfMagicAutoHeightWrap" title="Ajustar automaticamente a altura da área de trabalho até o final da última linha da montagem." hidden>
            <input id="dtfMagicAutoHeight" type="checkbox" checked aria-label="Ajustar automaticamente a altura da área de trabalho até a última linha">
            <span class="dtf-magic-auto-height-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 3h12v7M5 3v18h12v-5M9 7h8M9 17h8M20 10v6M17.5 13.5 20 16l2.5-2.5"/></svg></span>
          </label>
        </div>
        <div class="dtf-magic-review" id="dtfMagicReview" hidden>
          <div class="dtf-magic-review-head">
            <div><strong>Escolha o melhor recorte</strong><small>Passe o mouse sobre uma miniatura para ampliar.</small></div>
          </div>
          <div class="dtf-magic-variants" id="dtfMagicVariants"></div>
        </div>
        <div class="dtf-magic-plan" id="dtfMagicPlan">
          <strong>Etapas</strong>
          <ol class="dtf-magic-steps" id="dtfMagicSteps">
            <li data-magic-step="source"><span class="dtf-magic-step-mark">1</span><span><b>Original</b><small>Recuperar a imagem original.</small></span></li>
            <li data-magic-step="model"><span class="dtf-magic-step-mark">2</span><span><b>IA local</b><small>Preparar no navegador.</small></span></li>
            <li data-magic-step="remove"><span class="dtf-magic-step-mark">3</span><span><b>Recorte</b><small>Remover o fundo.</small></span></li>
            <li data-magic-step="layout"><span class="dtf-magic-step-mark">4</span><span><b>Montagem</b><small>Organizar com 4 mm.</small></span></li>
            <li data-magic-step="apply"><span class="dtf-magic-step-mark">5</span><span><b>Finalizar</b><small>Separar e centralizar.</small></span></li>
          </ol>
        </div>
        <div class="dtf-magic-progress" id="dtfMagicProgress" aria-live="polite"><div><i id="dtfMagicProgressBar"></i></div><span id="dtfMagicProgressText">Aguardando imagem.</span></div>
        <div class="dtf-magic-warning" id="dtfMagicWarning">O projeto só muda no final e pode ser desfeito de uma vez.</div>
        <div class="dtf-properties-actions"><button type="button" id="dtfMagicCancel" title="Fecha esta janela sem alterar a área de trabalho">Cancelar</button><button type="button" id="dtfMagicCutoutOnly" title="Aplica somente a miniatura de recorte escolhida e fecha" hidden>Aplicar</button><button type="button" id="dtfMagicConfirm" title="Continua para definir e criar a montagem das imagens" disabled>Avançar</button></div>
      </div>
      <aside class="dtf-magic-layout-preview" id="dtfMagicLayoutPreview" hidden aria-label="Prévia da montagem">
        <strong>Prévia da folha</strong>
        <canvas id="dtfMagicLayoutCanvas" width="240" height="170" aria-label="Organização esquemática das imagens"></canvas>
        <small id="dtfMagicLayoutMeta">Ajuste as medidas para visualizar.</small>
      </aside>
      <div class="dtf-magic-zoom" id="dtfMagicZoom" role="tooltip" hidden><img id="dtfMagicZoomImage" alt=""><span id="dtfMagicZoomLabel"></span></div>
    </div>

    <section class="dtf-panel dtf-help-panel" data-panel="ajuda" role="tabpanel">
      <div class="dtf-help-layout">
        <div class="dtf-help-header">
          <div>
            <div class="dtf-caption">AJUDA CONTEXTUAL</div>
            <h3 id="dtfHelpTitle">Arquivo</h3>
            <p id="dtfHelpIntro">Veja aqui somente as funções relacionadas à aba ativa.</p>
          </div>
        </div>

        <div class="dtf-help-grid" id="dtfHelpContent" aria-live="polite">
          <article class="dtf-help-card" data-help-for="arquivo"><h4>Inicialização do sistema</h4><p>Ao abrir a página, Iniciando sistema aparece até os arquivos, fontes e editor estarem prontos. A interface só é revelada após organizar o layout. Se a inicialização demorar demais, aparece um aviso para recarregar a página.</p></article>
          <article class="dtf-help-card" data-help-for="remover"><h4>Tolerância para remoção</h4><p>Ao selecionar uma imagem, o editor sugere um valor inicial com base na cor predominante da borda. Ajuste a barra ou os botões − e + sem alterar a imagem; o processamento acontece somente ao clicar em Remover fundo.</p></article>
          <article class="dtf-help-card" data-help-for="alinhamentos"><h4>Alinhamentos e distribuição</h4><p>Use Ctrl + clique para selecionar vários objetos. Cada comando possui um desenho próprio indicando a borda, o centro ou a direção utilizada. Distribuir horizontal coloca todos na mesma linha e espaça pela largura da área de trabalho. Distribuir vertical coloca todos na mesma coluna e espaça pela altura da área de trabalho.</p></article>
          <article class="dtf-help-card" data-help-for="arquivo"><h4>Ajuda e área de trabalho</h4><p>Ajuda pressionada permanece aberta ao trocar de aba e acompanha o assunto escolhido. Clique novamente em Ajuda para fechar. Trocar abas não muda as medidas, o zoom nem a posição dos objetos. Exemplo: uma área 100 × 100 mm permanece assim ao abrir Exportar.</p></article>

          <article class="dtf-help-card" data-help-for="arquivo">
            <div class="dtf-help-card-head"><span class="dtf-help-icon">↑</span><h4>Importar imagem</h4></div>
            <p>Importar imagem abre formatos compatíveis e PDF, inclusive o mesmo arquivo novamente. Em PDFs com várias páginas, escolha importar todas ou somente algumas; elas são colocadas em sequência vertical e a altura da área cresce quando necessário. A imagem aparece selecionada na área de trabalho, mantendo o fundo original; os quadrinhos indicam transparência. Aguarde a mensagem de conclusão no rodapé. Se o arquivo falhar, o motivo aparece no log de erro. Ajuda fica separada à direita e permanece ativada até outro clique. Enquanto ativa, substitui os botões de opções logo abaixo das abas. Escolher outra aba muda apenas o assunto da ajuda; desligá-la restaura as opções. Trocar abas não muda as medidas da área de trabalho.</p>
            <div class="dtf-help-tip"><strong>Exemplo:</strong> carregue um PNG com fundo branco. Ele continuará igual até você escolher uma ferramenta de remoção.</div>
          </article>

          <article class="dtf-help-card" data-help-for="arquivo">
            <div class="dtf-help-card-head"><span class="dtf-help-icon">↶</span><h4>Original</h4></div>
            <p>Volta o objeto selecionado para sua imagem-base original, sem as alterações de edição aplicadas.</p>
            <div class="dtf-help-tip"><strong>Exemplo:</strong> depois de testar remoção de fundo, use Original para começar novamente.</div>
          </article>

          <article class="dtf-help-card" data-help-for="arquivo">
            <div class="dtf-help-card-head"><span class="dtf-help-icon">✦</span><h4>Remover fundo</h4></div>
            <p>Analisa a cor predominante do fundo na borda e remove somente a região externa conectada, usando a tolerância escolhida.</p>
            <div class="dtf-help-tip"><strong>Exemplo:</strong> aumente a tolerância aos poucos quando houver áreas claras próximas ao objeto.</div>
          </article>

          <article class="dtf-help-card" data-help-for="arquivo">
            <div class="dtf-help-card-head"><span class="dtf-help-icon">PDF</span><h4>PDF → PNG</h4></div>
            <p>Escolha a página e o DPI. A página é rasterizada em alta resolução e passa a ser tratada como PNG.</p>
            <div class="dtf-help-tip"><strong>Exemplo:</strong> para impressão, 300 DPI é uma boa base; use 600 DPI para detalhes finos quando o arquivo suportar.</div>
          </article>

          <article class="dtf-help-card" data-help-for="arquivo">
            <div class="dtf-help-card-head"><span class="dtf-help-icon">↔</span><h4>Área de trabalho</h4></div>
            <p>Define a largura e altura físicas em milímetros. Alterar a área não deve esticar nem redimensionar os objetos.</p>
            <div class="dtf-help-tip"><strong>Exemplo:</strong> 100 × 100 mm cria uma área de 10 × 10 cm mantendo a arte no mesmo tamanho atual.</div>
          </article>

          <article class="dtf-help-card" data-help-for="arquivo">
            <div class="dtf-help-card-head"><span class="dtf-help-icon">[ ]</span><h4>Mostrar o editor em uma página</h4></div>
            <p>Use o shortcode <code>&#91;dtf_uv_editor&#93;</code> em um bloco Shortcode do WordPress ou no widget Shortcode do Elementor.</p>
            <div class="dtf-help-tip"><strong>Exemplo:</strong> crie uma página chamada Editor de imagens, cole o shortcode e publique. O aplicativo completo aparecerá nessa página.</div>
          </article>

          <article class="dtf-help-card" data-help-for="arquivo">
            <div class="dtf-help-card-head"><span class="dtf-help-icon">✓</span><h4>Shortcode ativo</h4></div>
            <p>Use um widget Shortcode com <code>&#91;dtf_uv_editor&#93;</code>. O módulo inicializa seus caminhos antes de registrar o shortcode.</p>
            <div class="dtf-help-tip"><strong>Exemplo:</strong> use um widget Shortcode, publique a página e clique em Importar imagem para abrir uma imagem na área de trabalho.</div>
          </article>

          <article class="dtf-help-card" data-help-for="remover">
            <div class="dtf-help-card-head"><span class="dtf-help-icon">✦</span><h4>Branco inteligente</h4></div>
            <p>Remove a cor de fundo predominante conectada à borda, preservando áreas internas fechadas.</p>
            <div class="dtf-help-tip"><strong>Exemplo:</strong> comece em 20 e ajuste a tolerância apenas quando necessário.</div>
          </article>

          <article class="dtf-help-card" data-help-for="remover">
            <div class="dtf-help-card-head"><span class="dtf-help-icon">≈</span><h4>Suavidade e halos</h4></div>
            <p>Suavidade controla a transição da borda. Limpar halos reduz resíduos claros ao redor do recorte.</p>
            <div class="dtf-help-tip"><strong>Exemplo:</strong> use uma suavidade moderada e só aumente quando aparecer uma borda dura.</div>
          </article>

          <article class="dtf-help-card" data-help-for="editar">
            <div class="dtf-help-card-head"><span class="dtf-help-icon">↶</span><h4>Desfazer / Refazer</h4></div>
            <p>Ctrl+Z desfaz e Ctrl+Y refaz etapas. O histórico guarda várias ações da montagem.</p>
            <div class="dtf-help-tip"><strong>Exemplo:</strong> apague uma parte, mude o tamanho e use Ctrl+Z para voltar uma etapa por vez.</div>
          </article>

          <article class="dtf-help-card" data-help-for="editar">
            <div class="dtf-help-card-head"><span class="dtf-help-icon">◯</span><h4>Borracha</h4></div>
            <p>O círculo representa exatamente a área apagada. Clique e arraste sobre a arte.</p>
            <div class="dtf-help-tip"><strong>Atalhos:</strong> Ctrl + aumenta e Ctrl − diminui o pincel. Limites evitam tamanhos exagerados.</div>
          </article>

          <article class="dtf-help-card" data-help-for="editar">
            <div class="dtf-help-card-head"><span class="dtf-help-icon">◉</span><h4>Restaurar</h4></div>
            <p>Mostra o mesmo círculo sobre a arte e recupera a parte original onde você pintar.</p>
            <div class="dtf-help-tip"><strong>Exemplo:</strong> se a borracha invadir o desenho, ative Restaurar e passe sobre a região.</div>
          </article>

          <article class="dtf-help-card" data-help-for="editar">
            <div class="dtf-help-card-head"><span class="dtf-help-icon">↕</span><h4>Duplicar e Excluir</h4></div>
            <p>Duplicar cria outra cópia do objeto selecionado. Excluir remove somente o objeto selecionado.</p>
            <div class="dtf-help-tip"><strong>Exemplo:</strong> duplique um logo; a cópia fica à direita e, quando não couber, começa a próxima linha à esquerda.</div>
          </article>

          <article class="dtf-help-card" data-help-for="editar">
            <div class="dtf-help-card-head"><span class="dtf-help-icon">🪄</span><h4>Montagem inteligente (IA)</h4></div>
            <p>Sem imagens, permite enviar uma diretamente pela janela. Com uma selecionada, calcula a quantidade automaticamente; com várias, permite definir a quantidade de cada uma.</p>
            <div class="dtf-help-tip"><strong>Atenção:</strong> a IA trabalha no navegador, valida o espaço de 4 mm e só então substitui os objetos. O resultado fica centralizado, com cada imagem independente, e pode ser desfeito de uma vez.</div>
          </article>

          <article class="dtf-help-card" data-help-for="visualizar">
            <div class="dtf-help-card-head"><span class="dtf-help-icon">⌕</span><h4>Zoom</h4></div>
            <p>Use Reduzir, Original, Ampliar ou Ajustar para trabalhar com precisão em detalhes.</p>
            <div class="dtf-help-tip"><strong>Exemplo:</strong> 200% é útil para conferir bordas antes da impressão.</div>
          </article>

          <article class="dtf-help-card" data-help-for="visualizar">
            <div class="dtf-help-card-head"><span class="dtf-help-icon">□</span><h4>Objeto selecionado</h4></div>
            <p>Clique em um objeto para selecioná-lo. Use as alças para redimensionar e arraste o objeto para mover.</p>
            <div class="dtf-help-tip"><strong>Exemplo:</strong> selecione um logo e ajuste largura/altura em milímetros sem alterar a área de trabalho.</div>
          </article>

          <article class="dtf-help-card" data-help-for="visualizar">
            <div class="dtf-help-card-head"><span class="dtf-help-icon">↔</span><h4>Comparar</h4></div>
            <p>Mostra original e resultado do objeto selecionado para verificar se o recorte ficou correto.</p>
            <div class="dtf-help-tip"><strong>Exemplo:</strong> mova o divisor para localizar halos ou partes apagadas acidentalmente.</div>
          </article>

          <article class="dtf-help-card" data-help-for="visualizar">
            <div class="dtf-help-card-head"><span class="dtf-help-icon">◐</span><h4>Fundo</h4></div>
            <p>Escolha uma cor de visualização para avaliar transparência e bordas contra um fundo diferente.</p>
            <div class="dtf-help-tip"><strong>Exemplo:</strong> use cinza médio para perceber halos brancos rapidamente.</div>
          </article>

          <article class="dtf-help-card" data-help-for="exportar">
            <div class="dtf-help-card-head"><span class="dtf-help-icon">PNG</span><h4>PNG</h4></div>
            <p>Exporta a composição rasterizada na dimensão da área de trabalho, preservando transparência.</p>
            <div class="dtf-help-tip"><strong>Exemplo:</strong> use PNG para manter exatamente a aparência final do trabalho.</div>
          </article>

          <article class="dtf-help-card" data-help-for="exportar">
            <div class="dtf-help-card-head"><span class="dtf-help-icon">JPG</span><h4>JPEG e WebP</h4></div>
            <p>Permitem controlar a compactação e usar melhoria inteligente local para reforçar detalhes antes de salvar.</p>
            <div class="dtf-help-tip"><strong>Exemplo:</strong> marque “Usar melhoria de IA” e confira o resultado ampliado na prévia.</div>
          </article>

          <article class="dtf-help-card" data-help-for="exportar">
            <div class="dtf-help-card-head"><span class="dtf-help-icon">TIF</span><h4>TIFF</h4></div>
            <p>Gera TIFF RGBA sem perda e aumenta automaticamente o DPI quando isso for necessário para preservar os pixels originais das imagens.</p>
            <div class="dtf-help-tip"><strong>Exemplo:</strong> use para impressão em alta qualidade; as medidas físicas também são gravadas para o CorelDRAW.</div>
          </article>

          <article class="dtf-help-card" data-help-for="exportar">
            <div class="dtf-help-card-head"><span class="dtf-help-icon">PDF</span><h4>PDF em alta qualidade</h4></div>
            <p>Gera a folha completa em alta resolução, respeitando o tamanho físico definido na área de trabalho.</p>
            <div class="dtf-help-tip"><strong>Exemplo:</strong> use quando o fluxo de produção precisar preservar a medida exata da página.</div>
          </article>

          <article class="dtf-help-card" data-help-for="exportar">
            <div class="dtf-help-card-head"><span class="dtf-help-icon">DPI</span><h4>DPI</h4></div>
            <p>Define a densidade física usada para exportação. A área de trabalho continua sendo medida em milímetros.</p>
            <div class="dtf-help-tip"><strong>Exemplo:</strong> 300 DPI cria uma saída de impressão comum; 600 DPI pode ser usado para trabalhos que exigem mais resolução.</div>
          </article>
        </div>
      </div>
    </section>

  </div>

  <!-- hidden legacy controls kept for compatibility with the existing engine -->
  <div class="dtf-legacy">
    <button id="btnUpload" type="button"></button>
    <button id="btnResetWhite" type="button"></button><button id="btnRestore" type="button"></button><button id="btnDownload" type="button"></button><button id="btnShowBg" type="button"></button>
    <input id="grayColor" type="color" value="#cccccc"><button id="dec" type="button"></button><button id="inc" type="button"></button><input id="tol" type="range" min="0" max="100" value="20"><div id="tolVal">20</div><div id="status">Pronto</div>
    <canvas id="canvas"></canvas><div id="canvasFrame"></div><div id="canvasInner"></div><div id="bgPreview"></div>
    <button id="colorModeToggle" type="button"></button><div id="colorModePanel"></div><div id="colorList"></div><button id="undoColor" type="button"></button><button id="applyColors" type="button"></button><button id="cancelColors" type="button"></button>
    <input id="eraserSize" type="range" min="4" max="180" value="32"><div id="eraserSizeVal">32</div><button id="btnEraser" type="button"></button><button id="btnUndoEraser" type="button"></button><div id="eraserSizeControl"></div>
  </div>

  <div class="dtf-canvas-wrap">
    <div class="dtf-viewport" id="dtfViewport">
      <div class="dtf-ruler-corner" id="dtfRulerCorner" aria-hidden="true"></div><div class="dtf-ruler dtf-ruler-top" id="dtfRulerTop" aria-hidden="true"></div><div class="dtf-ruler dtf-ruler-left" id="dtfRulerLeft" aria-hidden="true"></div>
      <div class="dtf-inner-scroll" id="dtfInnerScroll">
        <div class="dtf-inner dtf-checker" id="dtfInner">
          <canvas id="dtfCanvas"></canvas>
          <div class="dtf-guides-overlay" id="dtfGuidesOverlay" aria-hidden="true"><div class="dtf-guide-safe"></div><div class="dtf-guide-center-v"></div><div class="dtf-guide-center-h"></div><div class="dtf-guide-cut-label">LINHAS DE CORTE</div></div>
          <div class="dtf-compare" id="dtfCompareOverlay"><canvas id="dtfCompareCanvas"></canvas></div>
          <div class="dtf-selection" id="dtfSelection"><div class="dtf-selection-label" id="dtfSelectionLabel"></div>
            <div class="dtf-handle dtf-h-nw" data-handle="nw"></div><div class="dtf-handle dtf-h-n" data-handle="n"></div><div class="dtf-handle dtf-h-ne" data-handle="ne"></div><div class="dtf-handle dtf-h-e" data-handle="e"></div><div class="dtf-handle dtf-h-se" data-handle="se"></div><div class="dtf-handle dtf-h-s" data-handle="s"></div><div class="dtf-handle dtf-h-sw" data-handle="sw"></div><div class="dtf-handle dtf-h-w" data-handle="w"></div>
          </div>
          <div class="dtf-eraser-cursor" id="dtfEraserCursor"></div><div class="dtf-object-badge" id="dtfObjectBadge"></div>
          <div class="dtf-marquee" id="dtfMarquee"></div>
        </div>
      </div>
    </div>
  </div>

  <div class="dtf-bottom"><div class="dtf-status" id="dtfStatus">Pronto</div><div class="dtf-progress" id="dtfProgress"><div class="dtf-progress-bar" id="dtfProgressBar"></div></div><div class="dtf-progress-text" id="dtfProgressText"></div></div>
  <div class="dtf-error" id="dtfError"><div class="dtf-error-title"><span id="dtfErrorTitle">Erro</span><button class="dtf-secondary" id="dtfCopyError" type="button">Copiar log</button></div><pre class="dtf-error-log" id="dtfErrorLog"></pre></div>
  <div class="dtf-canvas-menu" id="dtfCanvasMenu" tabindex="-1"><button type="button" id="dtfSelectAll" aria-keyshortcuts="Control+A">Selecionar todos <kbd>Ctrl+A</kbd></button><button type="button" id="dtfCopyObjects" aria-keyshortcuts="Control+C">Copiar <kbd>Ctrl+C</kbd></button><button type="button" id="dtfPasteObjects" aria-keyshortcuts="Control+V">Colar <kbd>Ctrl+V</kbd></button><button type="button" id="dtfDuplicateObjects" aria-keyshortcuts="Control+D">Duplicar <kbd>Ctrl+D</kbd></button><button type="button" id="dtfDeleteObjects" aria-keyshortcuts="Delete">Excluir <kbd>Delete</kbd></button><button type="button" id="dtfFlipH">Inverter horizontal</button><button type="button" id="dtfFlipV">Inverter vertical</button><button type="button" id="dtfGroupObjects" aria-keyshortcuts="Control+G">Agrupar <kbd>Ctrl+G</kbd></button><button type="button" id="dtfUngroupObjects" aria-keyshortcuts="Control+U">Desagrupar <kbd>Ctrl+U</kbd></button></div>
</div>
</div>
