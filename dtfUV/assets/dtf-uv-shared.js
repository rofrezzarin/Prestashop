/**
 * DTF UV — funções compartilhadas entre a calculadora pública e o Simulador do painel.
 * Deve ser carregado ANTES de dtf-uv.js e ANTES de pedidos.js.
 *
 * Expõe o namespace global window.DTF com:
 *   DTF.bestOrientationForHeight(w, h, gapH, desiredH, sheetW?, vertGap?)
 *   DTF.bestOrientationForQuantity(w, h, gapH, qty, sheetW?, vertGap?)
 *   DTF.renderLayoutPreview(hostOrId, layout)
 *
 * Todos os cálculos usam as mesmas constantes padrão (28 cm × espaçamento 0,5 cm).
 * Cada chamador pode sobrescrever passando sheetW e vertGap explicitamente.
 */
(function (global) {
  'use strict';
  var DTF = global.DTF = global.DTF || {};

  DTF.SHEET_WIDTH_CM  = 28;
  DTF.VERTICAL_GAP_CM = 0.5;

  function cols(itemW, gapH, sw) {
    var g = Number.isFinite(gapH) && gapH >= 0 ? gapH : 0;
    return Math.max(0, Math.floor((sw + g) / (itemW + g)));
  }

  function sheetHeight(rows, itemH, vg) {
    return rows * itemH + Math.max(0, rows - 1) * vg;
  }

  /**
   * Testa as duas orientações para uma altura de folha desejada.
   * Retorna a que encaixa MAIS adesivos.
   * Resultado: { columns, rows, itemW, itemH, rotated, heightCm, totalFit, gapCm, sheetWidth }
   */
  DTF.bestOrientationForHeight = function (width, height, gapH, desiredHeight, sheetWidthOpt, verticalGapOpt) {
    var sw = Number.isFinite(sheetWidthOpt)  ? sheetWidthOpt  : DTF.SHEET_WIDTH_CM;
    var vg = Number.isFinite(verticalGapOpt) ? verticalGapOpt : DTF.VERTICAL_GAP_CM;
    var gap = Number.isFinite(gapH) && gapH >= 0 ? gapH : 0;
    function test(iW, iH, rotated) {
      var c = cols(iW, gap, sw);
      if (!c) return null;
      var r = Math.max(0, Math.floor((desiredHeight + vg) / (iH + vg)));
      if (!r) return null;
      return { columns: c, rows: r, itemW: iW, itemH: iH, rotated: rotated,
               heightCm: sheetHeight(r, iH, vg), totalFit: c * r, gapCm: gap, sheetWidth: sw };
    }
    var opts = [test(width, height, false), test(height, width, true)].filter(Boolean);
    if (!opts.length) return null;
    opts.sort(function (a, b) { return b.totalFit - a.totalFit; });
    return opts[0];
  };

  /**
   * Testa as duas orientações para uma quantidade desejada de adesivos.
   * Retorna a que usa a MENOR folha possível.
   * Resultado: { columns, rows, itemW, itemH, rotated, heightCm, totalFit, gapCm, sheetWidth }
   */
  DTF.bestOrientationForQuantity = function (width, height, gapH, quantity, sheetWidthOpt, verticalGapOpt) {
    var sw = Number.isFinite(sheetWidthOpt)  ? sheetWidthOpt  : DTF.SHEET_WIDTH_CM;
    var vg = Number.isFinite(verticalGapOpt) ? verticalGapOpt : DTF.VERTICAL_GAP_CM;
    var gap = Number.isFinite(gapH) && gapH >= 0 ? gapH : 0;
    function test(iW, iH, rotated) {
      var c = cols(iW, gap, sw);
      if (!c) return null;
      var r = Math.ceil(quantity / c);
      return { columns: c, rows: r, itemW: iW, itemH: iH, rotated: rotated,
               heightCm: sheetHeight(r, iH, vg), totalFit: c * r, gapCm: gap, sheetWidth: sw };
    }
    var opts = [test(width, height, false), test(height, width, true)].filter(Boolean);
    if (!opts.length) return null;
    opts.sort(function (a, b) {
      var d = Math.abs(a.heightCm - b.heightCm);
      return d > 1e-6 ? a.heightCm - b.heightCm : b.columns - a.columns;
    });
    return opts[0];
  };

  function fmtCm(v) {
    return Number(v || 0).toLocaleString('pt-BR', { maximumFractionDigits: 2 });
  }

  /**
   * Renderiza a prévia SVG da disposição dos adesivos na folha.
   * Escreve dentro de `host` (id string ou elemento) e faz host.hidden = false.
   * layout: { columns, rows, itemW, itemH, sheetWidth?, gapCm?, heightCm? }
   */
  DTF.renderLayoutPreview = function (host, layout) {
    var el = typeof host === 'string' ? document.getElementById(host) : host;
    if (!el) return;
    var c  = layout.columns,  r  = layout.rows;
    var iW = layout.itemW,    iH = layout.itemH;
    var sw = Number.isFinite(layout.sheetWidth) ? layout.sheetWidth : DTF.SHEET_WIDTH_CM;
    var vg = DTF.VERTICAL_GAP_CM;
    var gH = Number.isFinite(layout.gapCm) && layout.gapCm >= 0 ? layout.gapCm : vg;
    var sh = Number.isFinite(layout.heightCm) ? layout.heightCm : sheetHeight(r, iH, vg);
    var scale = Math.max(2, Math.min(10, 260 / sw));
    // pad=2 deixa espaço para o stroke (1px) não ser cortado na borda do SVG
    var pad = 2;
    var svgW = sw * scale + pad * 2, svgH = sh * scale + pad * 2;
    var total = c * r;
    var inner;
    if (total > 4000) {
      inner =
        '<rect x="0" y="0" width="' + svgW.toFixed(1) + '" height="' + svgH.toFixed(1) + '" fill="#f8fafc" stroke="#cbd5e1"/>' +
        '<text x="' + (svgW / 2).toFixed(1) + '" y="' + (svgH / 2).toFixed(1) + '"' +
        ' text-anchor="middle" font-size="' + Math.max(10, scale * 1.4).toFixed(1) + '" fill="#647184">' + total + ' adesivos (detalhe omitido)</text>';
    } else {
      var gW   = c * iW + Math.max(0, c - 1) * gH;
      var offX = Math.max(0, (sw - gW) / 2) * scale + pad;
      var fs   = Math.max(7, Math.min(iW, iH) * scale * 0.32);
      var cells = '';
      for (var row = 0; row < r; row++) {
        for (var col = 0; col < c; col++) {
          var x = offX + col * (iW + gH) * scale;
          var y = row * (iH + vg) * scale + pad;
          var w = Math.max(0.5, iW * scale), h = Math.max(0.5, iH * scale);
          var n = row * c + col + 1;
          cells +=
            '<rect x="' + x.toFixed(1) + '" y="' + y.toFixed(1) +
            '" width="' + w.toFixed(1) + '" height="' + h.toFixed(1) +
            '" rx="' + Math.min(w, h, 6).toFixed(1) + '"' +
            ' fill="#963b00" fill-opacity=".16" stroke="#963b00" stroke-width="1"/>' +
            '<text x="' + (x + w / 2).toFixed(1) + '" y="' + (y + h / 2).toFixed(1) + '"' +
            ' text-anchor="middle" dominant-baseline="central"' +
            ' font-size="' + fs.toFixed(1) + '" fill="#702c00">' + n + '</text>';
        }
      }
      inner =
        '<rect x="0" y="0" width="' + svgW.toFixed(1) + '" height="' + svgH.toFixed(1) + '" fill="#f8fafc" stroke="#cbd5e1"/>' + cells;
    }
    el.innerHTML =
      '<div class="pw-dtf-preview-caption">Disposição na folha — ' + fmtCm(sw) + ' cm × ' + fmtCm(sh) + ' cm' +
      ' (' + total + ' adesivo' + (total === 1 ? '' : 's') + ')</div>' +
      '<div class="pw-dtf-preview-scroll"><svg viewBox="0 0 ' + svgW.toFixed(1) + ' ' + svgH.toFixed(1) +
      '" width="' + svgW.toFixed(1) + '" height="' + svgH.toFixed(1) +
      '" xmlns="http://www.w3.org/2000/svg">' + inner + '</svg></div>';
    el.hidden = false;
  };

})(window);
