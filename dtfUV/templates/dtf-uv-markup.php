  <div class="pw-calc" id="pw-calc">
      <h2>DTF UV - Pedido</h2>

      <div
        id="pw-user-info"
        class="pw-user-info"
        aria-live="polite"
        style="display: none"
      >
        Carregando usuário...
      </div>

      <div id="pw-collab-notice" style="display:none;text-align:center;padding:32px 20px;background:#fffbeb;border:1px solid #fbbf24;border-radius:12px;margin:16px 0;">
        <h3 style="margin:0 0 10px;color:#92400e;">Área reservada para clientes</h3>
        <p style="margin:0 0 20px;color:#78350f;font-size:15px;line-height:1.5;">
          Como <strong>Colaborador</strong>, o cálculo DTF UV deve ser realizado pelo sistema interno, no menu <strong>Relatórios → Simulador DTF UV</strong>.
        </p>
        <a id="pw-collab-link" href="https://printway.com.br/sistema?pw_view=dtf-simulator" class="pw-btn" style="display:inline-block;text-decoration:none;">
          Acessar o Sistema<span id="pw-collab-countdown" style="margin-left:6px;opacity:.75;font-size:13px;font-weight:400;"></span>
        </a>
      </div>

      <div class="pw-stepper">
        <div class="pw-step" data-step="1">
          <div class="dot">1</div>
          <div class="label">Identificação</div>
        </div>

        <div class="pw-step-connector" id="connector-1"></div>

        <div class="pw-step" data-step="2">
          <div class="dot">2</div>
          <div class="label">Fonte do cálculo</div>
        </div>

        <div class="pw-step-connector" id="connector-2"></div>

        <div class="pw-step" data-step="3">
          <div class="dot">3</div>
          <div class="label">Pagamento</div>
        </div>

        <div class="pw-step-connector" id="connector-3"></div>

        <div class="pw-step" data-step="4">
          <div class="dot">4</div>
          <div class="label">Finalizar</div>
        </div>
      </div>

      <div class="pw-step-content">
        <!-- =========================================================
     ETAPA 1
     ========================================================= -->

        <div id="step-1" class="step">
          <h3 style="margin: 0 0 8px; color: var(--primary)">Identificação</h3>

          <p class="muted">
            Preencha seus dados para prosseguir. Serão salvos para sua próxima
            visita.
          </p>

          <div id="pw-client-type-display" style="display:none;margin-bottom:14px;background:#eef9f0;border:1px solid #86efac;border-radius:8px;padding:8px 14px;font-size:14px;color:#166534;">
            Tipo de cliente: <strong id="pw-client-type-label"></strong>
          </div>

          <div class="pw-grid pw-identification-grid">
            <div>
              <label class="pw-field-label" for="sender-name-main">
                Nome completo
              </label>

              <input
                id="sender-name-main"
                class="pw-input"
                type="text"
                placeholder="Nome completo"
              />
            </div>

            <div>
              <label class="pw-field-label" for="sender-whatsapp-main">
                WhatsApp
              </label>

              <input
                id="sender-whatsapp-main"
                class="pw-input"
                type="tel"
                inputmode="numeric"
                autocomplete="tel"
                maxlength="15"
                placeholder="(19) 99999-9999"
              />
            </div>
          
          <div>
            <label class="pw-field-label" for="sender-email-main">
              E-mail
            </label>

            <input
              id="sender-email-main"
              class="pw-input"
              type="email"
              placeholder="seu@exemplo.com"
            />
            <label style="display:flex;align-items:center;gap:8px;margin-top:9px;font-size:14px;cursor:pointer">
              <input id="pw-send-customer-copy" type="checkbox" checked />
              Enviar uma cópia do pedido para meu e-mail
            </label>
          </div>

          </div>

          <!-- Dados complementares: mostrados apenas se algum campo obrigatório faltar -->
          <div id="pw-extra-profile-fields" style="display:none;margin-top:14px">
            <div style="font-size:13px;font-weight:600;color:var(--primary);margin:0 0 10px;padding-bottom:6px;border-bottom:1px solid #e2e8f0">Dados complementares obrigatórios</div>
            <div class="pw-grid pw-identification-grid">
              <div>
                <label class="pw-field-label" for="sender-cpfcnpj">CPF ou CNPJ</label>
                <input id="sender-cpfcnpj" class="pw-input" type="text" inputmode="numeric" maxlength="18" placeholder="000.000.000-00 ou 00.000.000/0000-00" autocomplete="off" />
              </div>
              <div>
                <label class="pw-field-label" for="sender-cep">CEP <span class="muted" style="font-size:11px">(Enter para buscar)</span></label>
                <input id="sender-cep" class="pw-input" type="text" inputmode="numeric" maxlength="9" placeholder="00000-000" autocomplete="off" />
              </div>
            </div>
            <div id="pw-address-fields" style="display:none;margin-top:8px">
              <div class="pw-grid pw-identification-grid">
                <div>
                  <label class="pw-field-label" for="sender-street">Logradouro</label>
                  <input id="sender-street" class="pw-input" type="text" placeholder="Rua / Avenida..." autocomplete="off" />
                </div>
                <div>
                  <label class="pw-field-label" for="sender-number">Número</label>
                  <input id="sender-number" class="pw-input" type="text" placeholder="Ex: 123 ou S/N" autocomplete="off" />
                </div>
              </div>
              <div class="pw-grid pw-identification-grid" style="margin-top:8px">
                <div>
                  <label class="pw-field-label" for="sender-complement">Complemento <span class="muted">(opcional)</span></label>
                  <input id="sender-complement" class="pw-input" type="text" placeholder="Apto, sala, bloco..." autocomplete="off" />
                </div>
                <div>
                  <label class="pw-field-label" for="sender-neighborhood">Bairro</label>
                  <input id="sender-neighborhood" class="pw-input" type="text" placeholder="Bairro" autocomplete="off" />
                </div>
              </div>
              <div class="pw-grid pw-identification-grid" style="margin-top:8px">
                <div>
                  <label class="pw-field-label" for="sender-city">Cidade</label>
                  <input id="sender-city" class="pw-input" type="text" placeholder="Cidade" autocomplete="off" />
                </div>
                <div>
                  <label class="pw-field-label" for="sender-state">Estado (UF)</label>
                  <input id="sender-state" class="pw-input" type="text" maxlength="2" placeholder="SP" autocomplete="off" />
                </div>
              </div>
            </div>
          </div>

          <div style="margin-top: 12px">
            <label class="pw-field-label" for="sender-instructions-main">
              Instruções ou informações complementares
              <span class="muted">(opcional)</span>
            </label>

            <textarea
              id="sender-instructions-main"
              class="pw-textarea"
              maxlength="2000"
              placeholder="Ex.: orientações sobre o arquivo, observações do pedido ou informações importantes para a produção..."
            ></textarea>

            <div class="muted" style="margin-top: 5px">
              Essas informações serão enviadas junto com o pedido.
            </div>
          </div>

          <div id="ident-error" class="pw-error"></div>

          <div class="pw-actions-bottom">
            <button id="btn-start-next" class="pw-btn" type="button">
              Continuar
            </button>
          </div>
        </div>

        <!-- =========================================================
     ETAPA 2
     ========================================================= -->

        <div id="step-2" class="step" style="display: none">
          <p style="font-size: 15px; color: #333">
            Como você quer calcular o valor?
          </p>

          <div style="margin-top: 12px; margin-bottom: 14px">
            <label class="pw-field-label" for="pw-tipo-step">
              Tipo de cliente
            </label>

            <select id="pw-tipo-step" class="pw-select" style="width: 220px">
              <option value="direto">Cliente Direto</option>
              <option value="revenda">Revendedor</option>
            </select>
          </div>

          <style>
            .pw-choice-cards{display:flex!important;gap:16px!important;flex-wrap:wrap!important;justify-content:center!important;margin-top:8px!important;width:100%!important;box-sizing:border-box!important;}
            .pw-source-card{width:180px!important;max-width:180px!important;flex-shrink:0!important;flex-grow:0!important;min-height:240px!important;box-sizing:border-box!important;}
            .pw-source-card-icon{width:62px!important;height:62px!important;flex-shrink:0!important;}
            .pw-source-card-desc{white-space:normal!important;word-break:break-word!important;overflow-wrap:break-word!important;}
            @media(max-width:460px){.pw-source-card{width:100%!important;max-width:100%!important;}}
          </style>
          <div class="pw-choice-cards" style="display:flex;gap:16px;flex-wrap:wrap;justify-content:center;margin-top:8px;width:100%;box-sizing:border-box;">
            <div id="choose-pdf" class="pw-source-card" role="button" tabindex="0" style="width:180px;max-width:180px;flex-shrink:0;flex-grow:0;min-height:240px;box-sizing:border-box;">
              <div class="pw-source-card-check" aria-hidden="true">✓</div>
              <div class="pw-source-card-icon" style="width:62px;height:62px;flex-shrink:0;">
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><path d="M9 13h1.5a1.5 1.5 0 0 1 0 3H9v-3z" stroke-width="1.5"/><path d="M13 13h2v1.5a1.5 1.5 0 0 1-3 0V13z" stroke-width="1.5"/><line x1="16.5" y1="13" x2="16.5" y2="16" stroke-width="1.5"/><line x1="15" y1="14.5" x2="18" y2="14.5" stroke-width="1.5"/></svg>
              </div>
              <div class="pw-source-card-tag">Arquivo pronto</div>
              <div class="pw-source-card-title">Já tenho o PDF</div>
              <div class="pw-source-card-desc">Envie o arquivo já preparado para impressão. As medidas são lidas automaticamente do documento.</div>
            </div>

            <div id="choose-manual" class="pw-source-card" role="button" tabindex="0" style="width:180px;max-width:180px;flex-shrink:0;flex-grow:0;min-height:240px;box-sizing:border-box;">
              <div class="pw-source-card-check" aria-hidden="true">✓</div>
              <div class="pw-source-card-icon" style="width:62px;height:62px;flex-shrink:0;">
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="2" width="16" height="20" rx="2"/><rect x="7" y="5" width="10" height="5" rx="1"/><circle cx="8" cy="14" r="1" fill="currentColor" stroke="none"/><circle cx="12" cy="14" r="1" fill="currentColor" stroke="none"/><circle cx="16" cy="14" r="1" fill="currentColor" stroke="none"/><circle cx="8" cy="18" r="1" fill="currentColor" stroke="none"/><circle cx="12" cy="18" r="1" fill="currentColor" stroke="none"/><circle cx="16" cy="18" r="1" fill="currentColor" stroke="none"/></svg>
              </div>
              <div class="pw-source-card-tag">Cálculo automático</div>
              <div class="pw-source-card-title">Calculadora de medidas</div>
              <div class="pw-source-card-desc">Informe a quantidade e o tamanho de cada imagem. O sistema calcula o layout e o valor total.</div>
            </div>
          </div>

          <input
            id="pw-pdf"
            class="pw-file-hidden"
            type="file"
            accept="application/pdf"
          />

          <div id="pdf-area" style="margin-top: 14px; display: none">
            <span id="pdf-status" class="muted"></span>

            <div id="pdf-thumb-wrap" style="margin-top: 12px"></div>
          </div>

          <div id="manual-area" style="margin-top: 14px; display: none">
            <div class="pw-calc-mode-buttons" role="tablist" aria-label="Modo de cálculo">
              <button id="pw-mode-total-height" class="pw-calc-tab is-active" type="button" role="tab" aria-selected="true" aria-controls="pw-height-calculator" onclick="document.getElementById('pw-height-calculator').style.display='grid';document.getElementById('pw-images-calculator').style.display='none';document.getElementById('pw-size-calculator').style.display='none';this.classList.add('is-active');this.setAttribute('aria-selected','true');document.getElementById('pw-mode-images').classList.remove('is-active');document.getElementById('pw-mode-images').setAttribute('aria-selected','false');document.getElementById('pw-mode-sheet-size').classList.remove('is-active');document.getElementById('pw-mode-sheet-size').setAttribute('aria-selected','false');"><span class="pw-calc-tab-title">Calcular por altura da folha</span><span class="pw-calc-tab-desc">Calcular sabendo a altura total do arquivo</span></button>
              <button id="pw-mode-images" class="pw-calc-tab" type="button" role="tab" aria-selected="false" aria-controls="pw-images-calculator" onclick="document.getElementById('pw-height-calculator').style.display='none';document.getElementById('pw-images-calculator').style.display='block';document.getElementById('pw-size-calculator').style.display='none';this.classList.add('is-active');this.setAttribute('aria-selected','true');document.getElementById('pw-mode-total-height').classList.remove('is-active');document.getElementById('pw-mode-total-height').setAttribute('aria-selected','false');document.getElementById('pw-mode-sheet-size').classList.remove('is-active');document.getElementById('pw-mode-sheet-size').setAttribute('aria-selected','false');"><span class="pw-calc-tab-title">Quantos adesivos na folha</span><span class="pw-calc-tab-desc">Sei a medida e quantidade dos adesivos, quero saber qual a medida terá a folha</span></button>
              <button id="pw-mode-sheet-size" class="pw-calc-tab" type="button" role="tab" aria-selected="false" aria-controls="pw-size-calculator" onclick="document.getElementById('pw-height-calculator').style.display='none';document.getElementById('pw-images-calculator').style.display='none';document.getElementById('pw-size-calculator').style.display='block';this.classList.add('is-active');this.setAttribute('aria-selected','true');document.getElementById('pw-mode-total-height').classList.remove('is-active');document.getElementById('pw-mode-total-height').setAttribute('aria-selected','false');document.getElementById('pw-mode-images').classList.remove('is-active');document.getElementById('pw-mode-images').setAttribute('aria-selected','false');"><span class="pw-calc-tab-title">Altura da folha?</span><span class="pw-calc-tab-desc">Sei a medida dos adesivos e quero saber quantos cabem em X cm da folha</span></button>
            </div>

            <div id="pw-height-calculator" class="pw-grid pw-manual-grid">
              <div>
                <label class="pw-field-label">
                  <strong>Largura fixa</strong>
                </label>

                <div
                  style="
                    padding: 10px 12px;
                    border: 1px solid var(--muted);
                    border-radius: 8px;
                    background: var(--bg);
                  "
                >
                  28 cm
                </div>
              </div>

              <div>
                <label class="pw-field-label" for="pw-altura-step">
                  Altura (cm)
                </label>

                <div style="display: flex; gap: 8px; align-items: center">
                  <input
                    id="pw-altura-step"
                    class="pw-input"
                    type="text"
                    inputmode="decimal"
                    placeholder="Ex: 25,30"
                    style="flex: 1"
                  />

                  <button
                    id="btn-calc-manual"
                    class="pw-btn small"
                    type="button"
                  >
                    Calcular
                  </button>
                </div>
              </div>
            </div>

            <div id="pw-images-calculator" class="pw-images-calculator">
              <p class="muted" style="margin:0 0 12px">Informe a quantidade e o tamanho de cada imagem. O sistema testará as duas orientações e usará a que ocupar menos altura no material de 28 cm.</p>
              <div class="pw-images-fields">
                <div><label class="pw-visible-label" for="pw-image-quantity">Quantidade</label><input id="pw-image-quantity" class="pw-input" type="number" inputmode="numeric" min="1" max="1000" step="1" placeholder="Ex.: 15" /></div>
                <div><label class="pw-visible-label" for="pw-image-width">Largura de cada imagem (cm)</label><input id="pw-image-width" class="pw-input" type="number" inputmode="decimal" min="0.50" max="28" step="0.50" aria-describedby="pw-layout-guidance" placeholder="0,50 a 28,00" /></div>
                <div><label class="pw-visible-label" for="pw-image-height">Altura de cada imagem (cm)</label><input id="pw-image-height" class="pw-input" type="number" inputmode="decimal" min="0.50" max="10000" step="0.50" aria-describedby="pw-layout-guidance" placeholder="0,50 a 10.000,00" /></div>
                <div><label class="pw-visible-label" for="pw-image-gap">Espaço entre imagens (cm)</label><input id="pw-image-gap" class="pw-input" type="number" inputmode="decimal" min="0.50" max="28" step="0.50" aria-describedby="pw-layout-guidance" value="0.50" /></div>
                <div class="pw-images-calc-action"><button id="btn-calc-images" class="pw-btn small" type="button">Calcular disposição</button></div>
              </div>
              <div id="pw-layout-guidance" class="pw-cutting-note" aria-live="polite"></div>
              <div id="pw-layout-result" class="pw-layout-result">
                <div class="pw-layout-preview-wrap"><div class="pw-layout-preview-title">Prévia aproximada da página</div><div id="pw-layout-preview" class="pw-layout-preview" aria-label="Prévia da disposição das imagens"></div></div>
                <div id="pw-layout-summary" class="pw-layout-summary"></div>
              </div>
            </div>

            <div id="pw-size-calculator" class="pw-images-calculator" style="display:none">
              <p class="muted" style="margin:0 0 12px">Informe o tamanho do adesivo e a altura de folha que pretende usar. O sistema testa as duas orientações e usa a que encaixa mais adesivos naquela altura.</p>
              <div class="pw-images-fields">
                <div><label class="pw-visible-label" for="pw-size-width">Largura do adesivo (cm)</label><input id="pw-size-width" class="pw-input" type="number" inputmode="decimal" min="0.10" max="28" step="0.10" placeholder="Ex.: 5" /></div>
                <div><label class="pw-visible-label" for="pw-size-height">Altura do adesivo (cm)</label><input id="pw-size-height" class="pw-input" type="number" inputmode="decimal" min="0.10" max="10000" step="0.10" placeholder="Ex.: 5" /></div>
                <div><label class="pw-visible-label" for="pw-size-length">Altura de folha desejada (cm)</label><input id="pw-size-length" class="pw-input" type="number" inputmode="decimal" min="1" step="0.1" placeholder="Ex.: 30" /></div>
                <div><label class="pw-visible-label" for="pw-size-gap">Espaço entre adesivos (cm)</label><input id="pw-size-gap" class="pw-input" type="number" inputmode="decimal" min="0.50" max="28" step="0.50" value="0.50" /></div>
                <div class="pw-images-calc-action"><button id="btn-calc-size" class="pw-btn small" type="button">Calcular</button></div>
              </div>
              <div id="pw-size-guidance" class="pw-cutting-note" aria-live="polite"></div>
              <div id="pw-size-result" class="pw-layout-info-cards" style="display:none"></div>
              <div id="pw-size-layout-preview" class="pw-dtf-preview" hidden aria-label="Prévia da disposição dos adesivos"></div>
            </div>

            <div
              id="manual-warning"
              class="pw-warning"
              style="display: none"
            ></div>

          </div>

          <div class="pw-actions-bottom">
            <button id="btn-back-2" class="pw-btn ghost" type="button">
              Voltar
            </button>

            <button id="btn-next-2" class="pw-btn" type="button" disabled>
              Avançar
            </button>
          </div>
        </div>

        <!-- =========================================================
     ETAPA 3
     ========================================================= -->

        <div id="step-3" class="step" style="display: none">
          <h3 style="margin: 0 0 8px; color: var(--primary)">
            Resumo e pagamento
          </h3>

          <div class="pw-payment-summary-grid">
            <div class="valor-total-box">
              <div class="label">Valor total</div>

              <div class="value" id="valor-total-dtf">R$ 0,00</div>
            </div>

            <div class="summary-box">
              <div id="summary-text" class="muted">
                Nenhum cálculo realizado ainda.
              </div>
            </div>

          </div>

          <div id="valor-produto-dtf" style="display: none">0,00</div>

          <div style="margin-top: 12px">
            <div class="pw-payment-details-grid">
            <div id="pw-points-area" class="pw-proof-box" style="display:none;margin-bottom:18px">
              <div style="font-weight:700;margin-bottom:5px">Usar pontos neste pedido</div>
              <div class="muted">Saldo disponível: <strong id="pw-points-balance">0</strong> pontos. Você pode usar até <strong id="pw-points-limit">0</strong> pontos neste pedido.</div>
              <div style="display:flex;gap:8px;align-items:center;margin-top:10px;flex-wrap:wrap">
                <input id="pw-points-use" class="pw-input" type="number" min="0" step="1" value="0" style="max-width:180px" aria-label="Pontos a usar">
                <button id="btn-apply-points" class="pw-btn small" type="button">Aplicar pontos</button>
              </div>
              <div id="pw-points-status" class="muted" style="margin-top:8px"></div>
              <div id="pw-points-earn-message" class="muted" style="display:none;margin-top:8px"></div>
            </div>
            <div id="pw-points-spacer" style="display:none;height:24px;clear:both" aria-hidden="true"></div>

            <div id="pw-delivery-area" class="pw-proof-box">
              <div style="font-weight:700;margin-bottom:5px">Como deseja receber o pedido?</div>
              <div class="muted">Escolha uma opção para continuar. Nenhuma opção vem selecionada automaticamente.</div>
              <div class="pw-radio-options" role="radiogroup" aria-label="Como deseja receber o pedido?">
                <label class="pw-radio-option">
                  <input type="radio" name="pw-delivery-method" value="retirada_local">
                  <span>Irei retirar no local</span>
                </label>
                <label class="pw-radio-option">
                  <input type="radio" name="pw-delivery-method" value="entrega_taxa">
                  <span>Entregar mediante taxa de entrega que irei pagar</span>
                </label>
              </div>
              <div id="pw-delivery-status" class="muted" style="margin-top:8px">Selecione como deseja receber o pedido.</div>
            </div>
            </div>

            <div id="pw-payment-choice" style="display:flex;gap:10px;flex-wrap:wrap;margin:18px 0 12px">
              <button id="btn-pay-now" class="pw-btn" type="button">Pagar agora (Pix - QR Code)</button>
              <button id="btn-pay-later" class="pw-btn ghost" type="button" style="display:none">Pagar depois</button>
            </div>

            <div id="pw-payment-lock-notice" class="pw-proof-status ok" style="display:none;margin:0 0 12px">
              Comprovante validado. Os pontos e a forma de pagamento foram bloqueados para proteger este pedido.
            </div>

            <div id="pw-pay-later-area" class="pw-proof-box" style="display:none">
              <div style="font-weight:700;margin-bottom:5px">Código liberação (Pagar depois)</div>
              <div class="muted">Disponível somente para usuários logados e autorizados.</div>
              <div style="display:flex;gap:8px;align-items:center;margin-top:10px;flex-wrap:wrap">
                <input id="pw-pay-later-code" class="pw-input" type="text" autocomplete="off" style="max-width:260px" placeholder="Digite seu código">
                <button id="btn-validate-pay-later" class="pw-btn small" type="button">Validar código</button>
              </div>
              <div id="pw-pay-later-status" class="pw-proof-status wait"></div>
              <div id="pw-pay-later-options" style="display:none;margin-top:12px">
                <label class="pw-field-label" for="pw-payment-option">Como deseja realizar o pagamento?</label>
                <select id="pw-payment-option" class="pw-select"><option value="">Selecione</option><option value="cartao_credito">Cartão de crédito</option><option value="cartao_debito">Cartão de débito</option><option value="dinheiro">Dinheiro</option><option value="pix">Pix</option><option value="transferencia_bancaria">Transferência bancária</option></select>
              </div>
            </div>

            <button id="btn-generate-qr" class="pw-btn" type="button" style="display:none" disabled>
              Gerar QR
            </button>

            <div id="payment-msg" style="margin-top: 10px"></div>

            <div id="pw-pix-qrcode" style="margin-top: 12px"></div>

            <!-- COPIAR PIX -->

            <div id="pw-pix-copy-area" class="pw-copy-area">
              <button id="btn-copy-pix" class="pw-btn ghost" type="button">
                Copiar chave Pix
              </button>

              <span id="pix-copy-status" class="pw-copy-status"></span>
            </div>

            <!-- COMPROVANTE -->

            <div id="pw-proof-area" class="pw-proof-box" style="display: none">
              <div style="font-weight: 700; margin-bottom: 5px">
                Comprovante de pagamento
              </div>

              <div class="muted">
                Após realizar o Pix, envie o comprovante em PDF, JPG, PNG ou
                WEBP.
              </div>

              <input
                id="pw-proof-file"
                class="pw-file-hidden"
                type="file"
                accept="image/jpeg,image/png,image/webp,application/pdf"
              />

              <div style="margin-top: 10px">
                <button id="btn-proof-upload" class="pw-btn" type="button">
                  Enviar comprovante
                </button>
              </div>

              <div id="proof-status" class="pw-proof-status wait"></div>

              <div id="proof-admin-debug" class="pw-admin-debug"></div>

              <div
                id="proof-notice"
                class="pw-proof-notice"
                style="display: none"
              >
                <strong>Importante:</strong>
                a validação realizada nesta página não confirma que o dinheiro
                foi recebido. A gráfica fará a conferência real do pagamento
                antes de iniciar a produção.
              </div>
            </div>

          </div>

          <div class="pw-actions-bottom">
            <button id="btn-back-3" class="pw-btn ghost" type="button">
              Voltar
            </button>

            <button id="btn-next-3" class="pw-btn" type="button" disabled>
              Avançar
            </button>
          </div>
        </div>

        <!-- =========================================================
     ETAPA 4
     ========================================================= -->

        <div id="step-4" class="step" style="display: none">
          <h3 style="margin: 0 0 8px; color: var(--primary)">
            Finalizar solicitação
          </h3>

          <div class="pw-final-box">
            Após clicar em <strong>Finalizar</strong>, seus dados, o arquivo
            PDF, o comprovante e as informações complementares serão enviados
            para a gráfica.

            <br /><br />

            A equipe verificará se está tudo correto, fará a conferência real do
            pagamento e, estando tudo certo, iniciará a produção do arquivo PDF
            enviado.
          </div>

          <div class="pw-final-file" id="final-pdf-box" style="display: none">
            <strong>Arquivo para produção:</strong>

            <a
              href="#"
              id="final-pdf-link"
              title="Clique para abrir e baixar o PDF"
            ></a>

            <div class="muted" style="margin-top: 5px">
              Clique no nome para abrir o PDF para conferência e baixar uma
              cópia.
            </div>
          </div>

          <div id="pw-send-progress" class="pw-send-progress" aria-live="polite">
            <span id="pw-send-progress-label" class="pw-send-progress-label">Preparando envio…</span>
            <div class="pw-send-progress-track" aria-hidden="true">
              <div id="pw-send-progress-bar" class="pw-send-progress-bar"></div>
            </div>
          </div>

          <div id="send-status" class="muted" style="margin-top: 12px"></div>

          <div class="pw-actions-bottom">
            <button id="btn-back-4" class="pw-btn ghost" type="button">
              Voltar
            </button>

            <button id="btn-finish" class="pw-btn" type="button">
              Finalizar
            </button>
          </div>

          <div id="pw-order-tracking" class="pw-success-actions" style="display: none">
            <a
              class="pw-btn"
              href="/minha-conta/orders/?tipo=dtf-uv"
            >Acompanhe seu pedido</a>
            <button id="btn-new-order" class="pw-btn ghost" type="button">Fazer novo pedido</button>
          </div>
        </div>
      </div>
    </div>

    <!-- =========================================================
     FORMULÁRIO ANTIGO - COMPATIBILIDADE COM PHP/JS EXISTENTE
     ========================================================= -->

    <div id="pw-sender-modal">
      <div class="pw-modal-backdrop" id="pw-sender-backdrop">
        <div
          class="pw-modal"
          role="dialog"
          aria-modal="true"
          aria-labelledby="pw-sender-title"
        >
          <h3 id="pw-sender-title">Dados do remetente</h3>

          <p>Preencha os dados de quem está enviando.</p>

          <label class="pw-field-label" for="sender-name">
            Nome completo
          </label>

          <input id="sender-name" class="pw-input" type="text" />

          <label class="pw-field-label" for="sender-whatsapp"> WhatsApp </label>

          <input id="sender-whatsapp" class="pw-input" type="text" />

          <label class="pw-field-label" for="sender-email"> E-mail </label>

          <input id="sender-email" class="pw-input" type="email" />

          <div class="pw-error" id="pw-sender-error"></div>

          <button id="pw-sender-cancel" type="button">Cancelar</button>

          <button id="pw-sender-send" type="button">Enviar</button>
        </div>
      </div>
    </div>