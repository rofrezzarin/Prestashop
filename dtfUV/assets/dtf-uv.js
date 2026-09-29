      /* ============================================================
   CONFIGURAÇÃO
   ============================================================ */

      const XML_PATH = "/dtfuv/precos.xml";
      const LARGURA_ESPERADA_CM = 28;
      const ESPACAMENTO_VERTICAL_PADRAO_CM = 0.5;
      const TOLERANCIA_CM = 0.05;
      const MAX_PROOF_SIZE = 10 * 1024 * 1024;

      /* ============================================================
   ESTADO
   ============================================================ */

      let productionFile = null;
      let proofFile = null;

      let lastComputed = null;
      let lastHeightCm = null;
      let lastSource = null;

      let QR_GENERATED = false;
      let PROOF_VALIDATED = false;
      let PAYMENT_METHOD = "pix";
      let PAY_LATER_VALIDATED = false;
      let POINTS_SETTINGS = null;
      let POINTS_BALANCE = 0;
      let POINTS_USED = 0;
      let POINTS_DISCOUNT = 0;
      let PAYMENT_SESSION_ID = "";
      let PAYMENT_CONTROLS_LOCKED = false;
      let CALCULATION_SESSION_ID = "";
      let CALCULATION_RECORD_ID = 0;
      let CURRENT_STEP = 1;

      let qrGenerationSequence = 0;
      let proofValidationSequence = 0;
      let paymentSessionSequence = 0;

      let finalPdfObjectUrl = null;
      let finalSendComplete = false;
      let sendProgressTimer = null;

      let LAST_PIX_COPY_VALUE = "";

      function setSendProgress(percent, label) {
        const box = document.getElementById("pw-send-progress");
        const bar = document.getElementById("pw-send-progress-bar");
        const text = document.getElementById("pw-send-progress-label");

        if (!box || !bar || !text) {
          return;
        }

        box.style.display = "block";
        bar.style.width = Math.max(0, Math.min(100, Number(percent) || 0)) + "%";
        text.textContent = label || "Enviando solicitação…";
      }

      function startSendProgress() {
        let progress = 18;

        if (sendProgressTimer) {
          clearInterval(sendProgressTimer);
        }

        setSendProgress(progress, "Preparando dados e arquivos…");

        sendProgressTimer = setInterval(function () {
          progress = Math.min(90, progress + (progress < 55 ? 8 : 3));
          setSendProgress(progress, "Enviando dados para a gráfica…");
        }, 700);
      }

      function stopSendProgress(success) {
        if (sendProgressTimer) {
          clearInterval(sendProgressTimer);
          sendProgressTimer = null;
        }

        if (success) {
          setSendProgress(100, "Envio concluído com sucesso.");
        }
      }

      function getPayableAmount() {
        const gross = lastComputed ? Number(lastComputed.precoFinal || 0) : 0;
        return Math.max(0, gross - Number(POINTS_DISCOUNT || 0));
      }

      function ensureValidPixCrc(payload) {
        const base = String(payload || "").replace(/6304[A-Fa-f0-9]{4}$/, "6304");
        if (!base.endsWith("6304")) {
          throw new Error("O servidor retornou um código Pix inválido.");
        }
        let crc = 0xffff;
        for (let index = 0; index < base.length; index++) {
          crc ^= base.charCodeAt(index) << 8;
          for (let bit = 0; bit < 8; bit++) {
            crc = (crc & 0x8000) ? ((crc << 1) ^ 0x1021) & 0xffff : (crc << 1) & 0xffff;
          }
        }
        return base + crc.toString(16).toUpperCase().padStart(4, "0");
      }

      const SENDER_COOKIE_NAME = "pw_sender";
      const SENDER_COOKIE_DAYS = 365;

      /* ============================================================
   COOKIE
   ============================================================ */

      function setCookie(name, value, days) {
        try {
          const expires = new Date(Date.now() + days * 864e5).toUTCString();

          document.cookie =
            name +
            "=" +
            encodeURIComponent(value) +
            "; expires=" +
            expires +
            "; path=/";

          return true;
        } catch (e) {
          return false;
        }
      }

      function getCookie(name) {
        const safe = name.replace(/([.*+?^${}()|[\]\\])/g, "\\$1");

        const match = document.cookie.match(
          new RegExp("(^|; )" + safe + "=([^;]*)"),
        );

        return match ? decodeURIComponent(match[2]) : null;
      }

      function persistSender(data) {
        const json = JSON.stringify(data);

        if (!setCookie(SENDER_COOKIE_NAME, json, SENDER_COOKIE_DAYS)) {
          try {
            localStorage.setItem(SENDER_COOKIE_NAME, json);
          } catch (e) {}
        }
      }

      function readPersistedSender() {
        try {
          const cookie = getCookie(SENDER_COOKIE_NAME);

          if (cookie) {
            return JSON.parse(cookie);
          }

          const local = localStorage.getItem(SENDER_COOKIE_NAME);

          if (local) {
            return JSON.parse(local);
          }
        } catch (e) {}

        return null;
      }

      /* ============================================================
   HELPERS
   ============================================================ */

      function formatBR(value) {
        return Number(value).toLocaleString("pt-BR", {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        });
      }

      function formatCm(value) {
        return Number(value).toLocaleString("pt-BR", {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        });
      }

      function normalizeNumberInput(value) {
        if (value === null || value === undefined) {
          return NaN;
        }

        let str = String(value).trim();

        if (!str) {
          return NaN;
        }

        if (str.includes(".") && str.includes(",")) {
          str = str.replace(/\./g, "").replace(",", ".");
        } else {
          str = str.replace(",", ".");
        }

        return Number(str);
      }

      function pontosParaCm(pt) {
        return (pt * 2.54) / 72;
      }

      function normalizeText(text) {
        return String(text || "")
          .normalize("NFD")
          .replace(/[\u0300-\u036f]/g, "")
          .toLowerCase()
          .replace(/\u00a0/g, " ")
          .replace(/\s+/g, " ")
          .trim();
      }

      function isLoggedAdmin() {
        return !!(
          window.PW_SERVER_DATA &&
          window.PW_SERVER_DATA.currentUser &&
          window.PW_SERVER_DATA.currentUser.is_admin
        );
      }

      function setProofAdminDiagnostics(lines) {
        const box = document.getElementById("proof-admin-debug");

        if (!box) {
          return;
        }

        if (!isLoggedAdmin()) {
          box.style.display = "none";
          box.textContent = "";
          return;
        }

        const cleanLines = (Array.isArray(lines) ? lines : [lines]).filter(
          Boolean,
        );

        box.textContent = cleanLines.length
          ? "Diagnóstico do administrador:\n• " + cleanLines.join("\n• ")
          : "";
        box.style.display = cleanLines.length ? "block" : "none";
      }

      function appendProofAdminDiagnostic(line) {
        const box = document.getElementById("proof-admin-debug");

        if (!box || !isLoggedAdmin() || !line) {
          return;
        }

        const prefix = box.textContent
          ? box.textContent + "\n• "
          : "Diagnóstico do administrador:\n• ";

        box.textContent = prefix + line;
        box.style.display = "block";
      }

      /* ============================================================
   PDF
   ============================================================ */

      async function lerDimensoesPDF(file) {
        if (!window.pdfjsLib) {
          throw new Error("PDF.js não carregado.");
        }

        const buffer = await file.arrayBuffer();

        const task = pdfjsLib.getDocument({
          data: buffer,
        });

        const pdf = await task.promise;

        const page = await pdf.getPage(1);

        const viewport = page.getViewport({
          scale: 1,
        });

        const result = {
          larguraCm: pontosParaCm(viewport.width),

          alturaCm: pontosParaCm(viewport.height),
        };

        try {
          await page.cleanup();
        } catch (e) {}

        try {
          await pdf.cleanup();
        } catch (e) {}

        return result;
      }

      async function validarPdfProducao(file) {
        if (!file) {
          throw new Error("Nenhum PDF selecionado.");
        }

        const name = String(file.name || "").toLowerCase();

        if (file.type !== "application/pdf" && !name.endsWith(".pdf")) {
          throw new Error("Selecione um arquivo PDF válido.");
        }

        const dims = await lerDimensoesPDF(file);

        if (Math.abs(dims.larguraCm - LARGURA_ESPERADA_CM) > TOLERANCIA_CM) {
          throw new Error(
            "O PDF possui " +
              formatCm(dims.larguraCm) +
              " cm de largura. Para produção ele deve possuir 28 cm de largura.",
          );
        }

        return dims;
      }

      async function renderPdfThumbnail(file) {
        const wrap = document.getElementById("pdf-thumb-wrap");

        try {
          const buffer = await file.arrayBuffer();

          const pdf = await pdfjsLib.getDocument({
            data: buffer,
          }).promise;

          const page = await pdf.getPage(1);

          const viewport = page.getViewport({
            scale: 1.5,
          });

          const canvas = document.createElement("canvas");

          const context = canvas.getContext("2d");

          canvas.width = Math.floor(viewport.width);

          canvas.height = Math.floor(viewport.height);

          await page.render({
            canvasContext: context,
            viewport: viewport,
          }).promise;

          wrap.innerHTML = "";

          const box = document.createElement("div");

          box.className = "pdf-thumb";

          box.appendChild(canvas);

          const caption = document.createElement("div");

          caption.className = "muted";

          caption.style.marginTop = "8px";

          caption.textContent = file.name;

          box.appendChild(caption);

          wrap.appendChild(box);
        } catch (error) {
          console.warn(error);

          wrap.innerHTML = '<div class="muted">Miniatura indisponível.</div>';
        }
      }

      /* ============================================================
   XML / PREÇOS
   ============================================================ */

      function createPriceXml(savedTable) {
          const escapeXml = function(value) {
            return String(value ?? "").replace(/&/g, "&amp;").replace(/"/g, "&quot;").replace(/</g, "&lt;");
          };
          let markup = "<precos>";
          savedTable.forEach(function(row) {
            const hasMax = !(row.max === "" || row.max === null || row.max === undefined);
            ["direto", "revenda"].forEach(function(type) {
              markup += '<faixa tipo="' + type + '" medida="' + escapeXml(row.medida) + '" minaltura="' + escapeXml(row.min) + '"' + (hasMax ? ' maxaltura="' + escapeXml(row.max) + '"' : '') + ' valor="' + escapeXml(row[type]) + '" />';
            });
          });
          markup += "</precos>";
          return new DOMParser().parseFromString(markup, "application/xml");
      }

      async function fetchXML() {
        const savedTable = window.PW_SERVER_DATA && window.PW_SERVER_DATA.price_table;
        if (Array.isArray(savedTable) && savedTable.length) {
          return createPriceXml(savedTable);
        }

        const form = new URLSearchParams();
        form.append("action", "printway_dtf_get_price_table");
        const response = await fetch((window.PW_SERVER_DATA && window.PW_SERVER_DATA.ajax_url) || "/wp-admin/admin-ajax.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
          body: form.toString(),
          cache: "no-store"
        });

        if (!response.ok) {
          throw new Error("Não foi possível carregar a tabela de preços do plugin.");
        }

        const json = await response.json();
        const priceTable = json && json.success && json.data && json.data.price_table;
        if (!Array.isArray(priceTable) || !priceTable.length) {
          throw new Error("A tabela de preços do plugin não está disponível.");
        }
        return createPriceXml(priceTable);
      }

      function parseFaixas(xml, tipo) {
        const faixas = Array.from(xml.getElementsByTagName("faixa"))
          .filter((node) => node.getAttribute("tipo") === tipo)
          .map((node) => {
            const maxAttr = node.getAttribute("maxaltura");
            return {
              medida: (node.getAttribute("medida") || "").trim(),

              minAltura: parseFloat(node.getAttribute("minaltura") || "0"),

              // Faixa aberta (sem teto, ex.: ">10m") quando o atributo não existe.
              maxAltura: maxAttr === null || maxAttr === "" ? null : parseFloat(maxAttr),

              valor: parseFloat(node.getAttribute("valor") || "0"),
            };
          })
          .filter(
            (item) =>
              Number.isFinite(item.minAltura) && Number.isFinite(item.valor),
          );

        faixas.sort((a, b) => a.minAltura - b.minAltura);

        return faixas;
      }

      /**
       * Preço DTF UV por altura — mesma fórmula final usada no plugin de
       * pedidos (confirmada em 2026-09-14): interpolação linear, contínua em
       * todas as transições entre faixas.
       *
       * A PRIMEIRA faixa (ex.: A4) é sempre um preço fixo — qualquer altura
       * até o teto dela mantém aquele valor, sem cálculo nenhum.
       *
       * As DEMAIS faixas: o preço cadastrado é o valor exato NO TETO daquela
       * faixa. Dentro da faixa, o preço cresce em linha reta a partir de onde
       * a faixa anterior terminou (contínuo, sem saltos) até bater
       * exatamente nesse valor ao alcançar o teto:
       *   preço = valorAnterior + (altura−mínimo)/(máximo−mínimo) × (valorDaFaixa−valorAnterior)
       */
      // Usa window.pwDtfCalcPrice (função central injetada pelo PHP com PW_SERVER_DATA).
      // Para alterar a fórmula, edite apenas pw_dtf_calculate_server_price() e
      // pwDtfCalcPrice no PHP — esta função não precisa ser alterada.
      async function calcularComAltura(alturaCm, tipo) {
        alturaCm = Number(alturaCm);

        if (!Number.isFinite(alturaCm) || alturaCm <= 0) {
          throw new Error("Altura inválida.");
        }

        if (typeof window.pwDtfCalcPrice !== "function") {
          throw new Error("Função de cálculo DTF UV não disponível. Recarregue a página.");
        }

        // Usa a tabela já carregada em PW_SERVER_DATA para evitar requisição extra.
        let table = window.PW_SERVER_DATA && window.PW_SERVER_DATA.price_table;
        if (!Array.isArray(table) || !table.length) {
          // Fallback: busca via AJAX se a tabela ainda não estiver disponível.
          const form = new URLSearchParams();
          form.append("action", "printway_dtf_get_price_table");
          const resp = await fetch(
            (window.PW_SERVER_DATA && window.PW_SERVER_DATA.ajax_url) || "/wp-admin/admin-ajax.php",
            { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" }, body: form.toString(), cache: "no-store" }
          );
          const json = await resp.json();
          table = json && json.success && json.data && json.data.price_table || null;
        }

        if (!Array.isArray(table) || !table.length) {
          throw new Error("Tabela de preços DTF UV não disponível.");
        }

        const rounding = window.PW_SERVER_DATA && window.PW_SERVER_DATA.rounding || {};
        if (rounding.round_cm) alturaCm = Math.ceil(alturaCm);

        const result = window.pwDtfCalcPrice(alturaCm, table, tipo);
        if (!result) {
          throw new Error("Nenhuma faixa encontrada para a altura informada.");
        }

        let precoFinal = result.price;
        if (rounding.round_price) precoFinal = Math.ceil(Math.round(precoFinal * 100) / 10) / 10;

        return {
          precoFinal: precoFinal,

          detalhe: result.detail,

          expression: "R$ " + formatBR(result.price),

          alturaOriginal: alturaCm,

          alturaUsada: result.effectiveHeight,

          tipo: tipo,
        };
      }

      /* ============================================================
   STEPPER
   ============================================================ */

      function setActiveStep(n) {
        const previousStep = CURRENT_STEP;
        CURRENT_STEP = n;
        for (let i = 1; i <= 4; i++) {
          document.getElementById("step-" + i).style.display =
            i === n ? "block" : "none";
        }

        document.querySelectorAll(".pw-step").forEach((step) => {
          const current = Number(step.getAttribute("data-step"));

          step.classList.remove("active", "done");

          if (current < n) {
            step.classList.add("done");
          }

          if (current === n) {
            step.classList.add("active");
          }
        });

        for (let i = 1; i <= 3; i++) {
          document
            .getElementById("connector-" + i)
            .classList.toggle("active", i < n);
        }

        if (n > previousStep) {
          window.requestAnimationFrame(function () {
            const formTop = document.getElementById("pw-calc");
            if (formTop) {
              formTop.scrollIntoView({ behavior: "smooth", block: "start" });
            }
          });
        }

        if (lastComputed && lastSource) recordAbandonedCalculation();
      }

      /* ============================================================
   PIX - LOCALIZA CÓDIGO/COPIA E COLA
   ============================================================ */

      function looksLikePixPayload(value) {
        const v = String(value || "").trim();

        if (v.length < 20) {
          return false;
        }

        return /^000201/i.test(v) || /BR\.GOV\.BCB\.PIX/i.test(v);
      }

      function getPixCopyValue() {
        if (LAST_PIX_COPY_VALUE) {
          return LAST_PIX_COPY_VALUE;
        }

        const container = document.getElementById("pw-pix-qrcode");

        if (!container) {
          return "";
        }

        const candidates = [];

        const elements = [container, ...container.querySelectorAll("*")];

        const attributes = [
          "data-pix-payload",
          "data-payload",
          "data-pix",
          "data-code",
          "data-key",
          "title",
          "alt",
          "value",
        ];

        elements.forEach((element) => {
          attributes.forEach((attr) => {
            let value = "";

            if (attr === "value" && "value" in element) {
              value = element.value || "";
            } else {
              value = element.getAttribute && element.getAttribute(attr);
            }

            if (value && String(value).trim()) {
              candidates.push(String(value).trim());
            }
          });
        });

        const text = container.textContent.trim();

        if (text) {
          candidates.push(text);
        }

        /*
         * Primeiro procura o Pix copia-e-cola EMV.
         */

        for (const candidate of candidates) {
          if (looksLikePixPayload(candidate)) {
            LAST_PIX_COPY_VALUE = candidate;

            return candidate;
          }
        }

        /*
         * Se o plugin colocou somente a chave no title,
         * permite copiar essa chave.
         */

        for (const candidate of candidates) {
          const value = String(candidate).trim();

          if (
            value.length >= 5 &&
            !/^(qr code|qrcode|pix|imagem|codigo qr)$/i.test(value)
          ) {
            LAST_PIX_COPY_VALUE = value;

            return value;
          }
        }

        return "";
      }

      async function copyTextToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
          await navigator.clipboard.writeText(text);

          return true;
        }

        const textarea = document.createElement("textarea");

        textarea.value = text;

        textarea.style.position = "fixed";

        textarea.style.left = "-999999px";

        textarea.style.top = "-999999px";

        document.body.appendChild(textarea);

        textarea.focus();
        textarea.select();

        const result = document.execCommand("copy");

        textarea.remove();

        return result;
      }

      /* ============================================================
   QR
   ============================================================ */

      function markQrGenerated(message) {
        QR_GENERATED = true;

        PROOF_VALIDATED = false;

        document.getElementById("pw-proof-area").style.display = "block";

        document.getElementById("btn-next-3").disabled = true;

        document.getElementById("pw-pix-copy-area").style.display = "flex";

        const msg = document.getElementById("payment-msg");

        msg.style.color = "#2b7a2b";

        msg.textContent =
          message || "QR gerado. Após pagar, envie o comprovante.";

        /*
         * Dá tempo para o plugin adicionar title/data ao QR.
         */

        setTimeout(function () {
          getPixCopyValue();
        }, 100);

        recordAbandonedCalculation();
      }

      function containerHasQr(container) {
        return !!(
          container && container.querySelector("img,canvas,svg,.qrcode")
        );
      }

      function observeQrContainer() {
        const container = document.getElementById("pw-pix-qrcode");

        if (!container || container._pw_observer) {
          return;
        }

        const observer = new MutationObserver(function () {
          if (containerHasQr(container) && !QR_GENERATED) {
            markQrGenerated(
              "QR gerado. Efetue o pagamento e envie o comprovante.",
            );
          }
        });

        observer.observe(container, {
          childList: true,
          subtree: true,
          characterData: true,
          attributes: true,
        });

        container._pw_observer = observer;
      }

      function generateQrUsingPluginByRef() {
        const old = document.getElementById("pw-temp-printway-btn");

        if (old) {
          old.remove();
        }

        const button = document.createElement("button");

        button.type = "button";

        button.id = "pw-temp-printway-btn";

        button.setAttribute("data-printway-pix-id", "valor-produto-dtf");

        button.style.position = "absolute";

        button.style.left = "-99999px";

        button.style.opacity = "0";

        document.body.appendChild(button);

        observeQrContainer();

        try {
          button.click();

          setTimeout(function () {
            if (button.parentNode) {
              button.remove();
            }
          }, 3000);

          return true;
        } catch (error) {
          console.error(error);

          button.remove();

          return false;
        }
      }

      function ensureQRCodeLib(callback) {
        if (window.QRCode) {
          callback();

          return;
        }

        const existing = document.getElementById("pw-qrcodejs-library");

        if (existing) {
          existing.addEventListener("load", callback, {
            once: true,
          });

          return;
        }

        const script = document.createElement("script");

        script.id = "pw-qrcodejs-library";

        script.src =
          "https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js";

        script.onload = callback;

        script.onerror = callback;

        document.head.appendChild(script);
      }

      /* ============================================================
   COMPROVANTE
   ============================================================ */

      async function readFileHeader(file, length) {
        const buffer = await file.slice(0, length).arrayBuffer();

        return new Uint8Array(buffer);
      }

      function matchesBytes(bytes, expected, offset) {
        offset = offset || 0;

        if (bytes.length < offset + expected.length) {
          return false;
        }

        for (let i = 0; i < expected.length; i++) {
          if (bytes[offset + i] !== expected[i]) {
            return false;
          }
        }

        return true;
      }

      async function detectProofFileType(file) {
        const bytes = await readFileHeader(file, 16);

        if (matchesBytes(bytes, [0x25, 0x50, 0x44, 0x46, 0x2d])) {
          return "pdf";
        }

        if (matchesBytes(bytes, [0xff, 0xd8, 0xff])) {
          return "jpeg";
        }

        if (
          matchesBytes(bytes, [0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a])
        ) {
          return "png";
        }

        const riff = matchesBytes(bytes, [0x52, 0x49, 0x46, 0x46], 0);

        const webp = matchesBytes(bytes, [0x57, 0x45, 0x42, 0x50], 8);

        if (riff && webp) {
          return "webp";
        }

        return null;
      }

      /* ============================================================
   TEXTO DO PDF
   ============================================================ */

      async function extractPdfText(file) {
        const buffer = await file.arrayBuffer();

        const pdf = await pdfjsLib.getDocument({
          data: buffer,
        }).promise;

        if (!pdf.numPages || pdf.numPages < 1) {
          throw new Error("O comprovante PDF não possui páginas válidas.");
        }

        let text = "";

        const pages = Math.min(pdf.numPages, 3);

        for (let number = 1; number <= pages; number++) {
          const page = await pdf.getPage(number);

          const content = await page.getTextContent();

          text += " " + content.items.map((item) => item.str || "").join(" ");

          try {
            await page.cleanup();
          } catch (e) {}
        }

        try {
          await pdf.cleanup();
        } catch (e) {}

        return text.trim();
      }

      /* ============================================================
   VALOR NO COMPROVANTE
   ============================================================ */

      function parseMoneyString(value) {
        let str = String(value).replace(/[^\d.,]/g, "");

        if (str.includes(",") && str.includes(".")) {
          if (str.lastIndexOf(",") > str.lastIndexOf(".")) {
            str = str.replace(/\./g, "").replace(",", ".");
          } else {
            str = str.replace(/,/g, "");
          }
        } else if (str.includes(",")) {
          str = /^\d{1,3}(?:,\d{3})+$/.test(str)
            ? str.replace(/,/g, "")
            : str.replace(",", ".");
        } else if (str.includes(".") && /^\d{1,3}(?:\.\d{3})+$/.test(str)) {
          str = str.replace(/\./g, "");
        }

        const number = Number(str);

        return Number.isFinite(number) ? number : NaN;
      }

      function extractMoneyValues(text) {
        const regex =
          /r\$\s*(?:\d{1,3}(?:\.\d{3})+(?:,\d{2})?|\d{1,3}(?:,\d{3})+(?:\.\d{2})?|\d+(?:[.,]\d{2})?)|(?<![\d./-])(?:\d{1,3}(?:\.\d{3})*,\d{2}|\d+\.\d{2})(?![\d./-])/gi;

        const matches = text.match(regex) || [];

        return matches
          .map((match) => parseMoneyString(match))
          .filter((value) => Number.isFinite(value));
      }

      function textContainsExpectedAmount(text, expectedAmount) {
        const values = extractMoneyValues(text);

        const expected = Number(expectedAmount);

        return values.some((value) => Math.abs(value - expected) < 0.009);
      }

      function getReceiptEvidence(text) {
        const checks = [
          [
            "termos de transação",
            /comprovante|pagamento|transferencia|transacao|operacao/,
          ],
          [
            "pagador ou recebedor",
            /pagador|recebedor|destinatario|favorecido|beneficiario|quem pagou|quem recebeu/,
          ],
          ["dados bancários", /cpf|cnpj|conta|agencia|instituicao|banco/],
          [
            "identificador da transação",
            /end.?to.?end|e2e|txid|identificador|id da transacao|id da operacao|autenticacao/,
          ],
          ["data", /\b\d{2}\/\d{2}\/\d{2,4}\b|\b\d{2}-\d{2}-\d{2,4}\b/],
          ["horário", /\b\d{1,2}:\d{2}(?::\d{2})?\b/],
        ];

        const found = checks
          .filter((check) => check[1].test(text))
          .map((check) => check[0]);

        return {
          score: found.length,
          found: found,
        };
      }

      /* ============================================================
   ANALISA O TEXTO DO COMPROVANTE
   ============================================================ */

      function extractReceiptDateTime(rawText) {
        const source = String(rawText || "");
        const dateMatch = source.match(
          /\b(\d{2}[\/-]\d{2}[\/-]\d{2,4})\b/,
        );
        const timeMatch = source.match(/\b([01]\d|2[0-3]):([0-5]\d)(?::\d{2})?\b/);

        return {
          date: dateMatch ? dateMatch[1].replace(/-/g, "/") : "",
          time: timeMatch ? timeMatch[1] + ":" + timeMatch[2] : "",
        };
      }

      function calcularDisposicaoImagens(quantidade, largura, altura, espacamento) {
        function testar(itemLargura, itemAltura, girada) {
          const colunas = Math.floor((LARGURA_ESPERADA_CM + espacamento) / (itemLargura + espacamento));
          if (colunas < 1) return null;
          const linhas = Math.ceil(quantidade / colunas);
          const itensNaLinhaMaisCheia = Math.min(quantidade, colunas);
          return {
            colunas: colunas,
            linhas: linhas,
            larguraUsada: itensNaLinhaMaisCheia * itemLargura + Math.max(0, itensNaLinhaMaisCheia - 1) * espacamento,
            alturaUsada: linhas * itemAltura + Math.max(0, linhas - 1) * ESPACAMENTO_VERTICAL_PADRAO_CM,
            itemLargura: itemLargura,
            itemAltura: itemAltura,
            espacamentoHorizontal: espacamento,
            espacamentoVertical: ESPACAMENTO_VERTICAL_PADRAO_CM,
            girada: girada
          };
        }
        const normal = testar(largura, altura, false);
        const girada = testar(altura, largura, true);
        const opcoes = [normal, girada].filter(Boolean);
        if (!opcoes.length) throw new Error("A imagem não cabe na largura de 28 cm, nem mesmo girada.");
        const ordenadas = opcoes.slice().sort(function(a,b){ return Math.abs(a.alturaUsada-b.alturaUsada)>0.000001 ? a.alturaUsada-b.alturaUsada : b.colunas-a.colunas; });
        const inicial = normal || girada;
        inicial.alternativas = opcoes;
        inicial.melhor = ordenadas[0];
        return inicial;
      }

      function calcularLarguraIdealProxima(quantidade, larguraAtual, espacamento) {
        const colunasAtuais = Math.floor(
          (LARGURA_ESPERADA_CM + espacamento) / (larguraAtual + espacamento),
        );

        if (colunasAtuais < 1 || quantidade <= colunasAtuais) {
          return null;
        }

        const novasColunas = Math.min(quantidade, colunasAtuais + 1);
        const larguraExata =
          (LARGURA_ESPERADA_CM - Math.max(0, novasColunas - 1) * espacamento) /
          novasColunas;

        /*
         * Sugere medidas comerciais de 0,50 em 0,50 cm e arredonda para
         * baixo. Assim a sugestão continua simples e nunca ultrapassa 28 cm.
         */
        const larguraSegura = Math.floor((larguraExata + 0.000000001) * 2) / 2;
        const larguraOcupada =
          novasColunas * larguraSegura +
          Math.max(0, novasColunas - 1) * espacamento;

        if (
          larguraSegura < 0.1 ||
          larguraSegura >= larguraAtual - 0.000001 ||
          larguraOcupada > LARGURA_ESPERADA_CM + 0.000001
        ) {
          return null;
        }

        return {
          colunasAtuais: colunasAtuais,
          novasColunas: novasColunas,
          largura: larguraSegura,
          larguraOcupada: larguraOcupada,
        };
      }

      function calcularLarguraParaPreencherFolha(
        quantidade,
        larguraAtual,
        espacamento,
        disposicaoAtual,
      ) {
        if (
          !disposicaoAtual ||
          !Number.isFinite(larguraAtual) ||
          disposicaoAtual.colunas < 1
        ) {
          return null;
        }

        const colunasUsadas = Math.min(
          quantidade,
          disposicaoAtual.colunas,
        );
        if (colunasUsadas < 1) return null;

        const larguraExata =
          (LARGURA_ESPERADA_CM -
            Math.max(0, colunasUsadas - 1) * espacamento) /
          colunasUsadas;

        /* Usa passos de 0,50 cm e nunca ultrapassa os 28 cm. */
        const larguraSegura =
          Math.floor((larguraExata + 0.000000001) * 2) / 2;
        const larguraOcupada =
          colunasUsadas * larguraSegura +
          Math.max(0, colunasUsadas - 1) * espacamento;

        if (
          larguraSegura <= larguraAtual + 0.000001 ||
          larguraSegura > LARGURA_ESPERADA_CM ||
          larguraOcupada > LARGURA_ESPERADA_CM + 0.000001
        ) {
          return null;
        }

        return {
          colunas: colunasUsadas,
          largura: larguraSegura,
          larguraOcupada: larguraOcupada,
        };
      }

      function calcularEspacamentoParaPreencherFolha(
        quantidade,
        larguraImagem,
        espacamentoAtual,
        disposicaoAtual,
      ) {
        if (
          !disposicaoAtual ||
          !Number.isFinite(larguraImagem) ||
          !Number.isFinite(espacamentoAtual)
        ) {
          return null;
        }

        const colunasUsadas = Math.min(
          quantidade,
          disposicaoAtual.colunas,
        );
        if (colunasUsadas < 2) return null;

        const espacoExato =
          (LARGURA_ESPERADA_CM - colunasUsadas * larguraImagem) /
          (colunasUsadas - 1);

        /*
         * Mantém os ajustes em passos de 0,50 cm e arredonda para baixo,
         * garantindo que a linha nunca ultrapasse os 28 cm disponíveis.
         */
        const espacoSeguro =
          Math.floor((espacoExato + 0.000000001) * 2) / 2;
        const larguraOcupada =
          colunasUsadas * larguraImagem +
          (colunasUsadas - 1) * espacoSeguro;

        if (
          espacoSeguro < 0.5 ||
          espacoSeguro <= espacamentoAtual + 0.000001 ||
          espacoSeguro > LARGURA_ESPERADA_CM ||
          larguraOcupada > LARGURA_ESPERADA_CM + 0.000001
        ) {
          return null;
        }

        return {
          colunas: colunasUsadas,
          espacamento: espacoSeguro,
          larguraOcupada: larguraOcupada,
        };
      }

      function calcularQuantidadeParaCompletarDisposicao(quantidade, disposicao) {
        if (!disposicao || disposicao.colunas < 1 || disposicao.linhas < 1) {
          return null;
        }

        const capacidade = disposicao.colunas * disposicao.linhas;
        const adicionais = capacidade - quantidade;

        if (adicionais < 1) {
          return null;
        }

        return {
          quantidade: capacidade,
          adicionais: adicionais,
          colunas: disposicao.colunas,
          linhas: disposicao.linhas,
        };
      }

      function formatarMedidaParaCampo(valor) {
        return Number(valor)
          .toLocaleString("pt-BR", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 3,
          });
      }

      function renderizarPreviaImagens(disposicao, quantidade, espacamento, aoAlterarDisposicao) {
        const preview = document.getElementById("pw-layout-preview");
        const summary = document.getElementById("pw-layout-summary");
        const result = document.getElementById("pw-layout-result");
        const alternativas = disposicao.alternativas && disposicao.alternativas.length ? disposicao.alternativas : [disposicao];
        let indiceAtual = 0;

        function desenhar(opcao) {
          const melhor = disposicao.melhor || alternativas.slice().sort(function(a,b){ return Math.abs(a.alturaUsada-b.alturaUsada)>0.000001 ? a.alturaUsada-b.alturaUsada : b.colunas-a.colunas; })[0];
          const exibindoMelhor = opcao === melhor;
          const larguraInternaPreview = 266;
          const escalaPreview = larguraInternaPreview / LARGURA_ESPERADA_CM;
          const gapHorizontalPixels = espacamento * escalaPreview;
          const gapVerticalPixels = ESPACAMENTO_VERTICAL_PADRAO_CM * escalaPreview;
          const larguraItemPixels = opcao.itemLargura * escalaPreview;
          const alturaItemPixels = opcao.itemAltura * escalaPreview;
          const alturaProporcionalPapel = opcao.alturaUsada * escalaPreview + 14;
          preview.innerHTML = "";
          preview.style.gridTemplateColumns = "repeat(" + opcao.colunas + "," + larguraItemPixels + "px)";
          preview.style.gridAutoRows = alturaItemPixels + "px";
          preview.style.height = Math.max(30, alturaProporcionalPapel) + "px";
          preview.style.columnGap = gapHorizontalPixels + "px";
          preview.style.rowGap = gapVerticalPixels + "px";
          for (let i=0; i<quantidade; i+=1) {
            const item=document.createElement("div"); item.className="pw-layout-item";
            item.textContent=i+1; preview.appendChild(item);
          }
          summary.innerHTML=
            '<div class="pw-layout-toolbar" role="toolbar" aria-label="Ferramentas da disposição">'+
              '<button id="pw-layout-undo" class="pw-layout-tool" type="button" data-tooltip="Desfazer a última alteração aplicada" aria-label="Desfazer a última alteração"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 7H5V3"/><path d="M5 7c2.1-2.2 5.2-3.3 8.2-2.7a7.8 7.8 0 1 1-7 12.9"/></svg></button>'+
              '<button id="pw-layout-reset" class="pw-layout-tool" type="button" data-tooltip="Restaurar os dados originalmente informados" aria-label="Restaurar os dados originais"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 11.5 12 4l9 7.5"/><path d="M5.5 10.5V20h13v-9.5"/><path d="M9.5 20v-6h5v6"/></svg></button>'+
              '<button id="pw-rotate-preview" class="pw-layout-tool" type="button" data-tooltip="Girar as imagens em 90 graus e recalcular" aria-label="Girar as imagens em 90 graus"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="3" width="7" height="13" rx="1"/><rect x="10" y="9" width="10.5" height="7" rx="1"/><path d="M7 20h10"/></svg></button>'+
            '</div>'+
            '<div class="pw-layout-info-cards">'+
              '<div class="pw-layout-info-card is-primary">'+
                '<span class="pw-layout-info-label">Largura × altura do papel</span>'+
                '<div class="pw-layout-info-value"><strong>'+formatCm(LARGURA_ESPERADA_CM)+' cm × '+formatCm(opcao.alturaUsada)+' cm</strong></div>'+
              '</div>'+
              '<div class="pw-layout-info-card">'+
                '<span class="pw-layout-info-label">Largura × altura de cada imagem</span>'+
                '<div class="pw-layout-info-value"><strong>'+formatCm(opcao.itemLargura)+' cm × '+formatCm(opcao.itemAltura)+' cm</strong></div>'+
              '</div>'+
              '<div class="pw-layout-info-card">'+
                '<span class="pw-layout-info-label">Colunas × linhas de imagens</span>'+
                '<div class="pw-layout-info-value"><strong>'+opcao.colunas+' coluna(s) × '+opcao.linhas+' linha(s)</strong></div>'+
              '</div>'+
              '<div class="pw-layout-info-card">'+
                '<span class="pw-layout-info-label">Total de imagens</span>'+
                '<div class="pw-layout-info-value"><strong>'+quantidade+' imagem(ns)</strong></div>'+
              '</div>'+
            '</div>';
          document.getElementById("pw-rotate-preview").addEventListener("click", async function(){
            indiceAtual = (indiceAtual + 1) % alternativas.length;
            const novaDisposicao = alternativas[indiceAtual];
            desenhar(novaDisposicao);
            if (typeof aoAlterarDisposicao === "function") {
              await aoAlterarDisposicao(novaDisposicao);
            }
          });
          document.getElementById("pw-layout-undo").addEventListener("click", function(){
            document.dispatchEvent(new CustomEvent("pw:image-layout-command", { detail:{ command:"undo" } }));
          });
          document.getElementById("pw-layout-reset").addEventListener("click", function(){
            document.dispatchEvent(new CustomEvent("pw:image-layout-command", { detail:{ command:"reset" } }));
          });
          document.dispatchEvent(new CustomEvent("pw:image-toolbar-ready"));
        }

        desenhar(alternativas[0]);
        result.style.display="grid";
      }

      function validatePixReceiptText(rawText, expectedAmount, sourceLabel) {
        const text = normalizeText(rawText);
        const pixDetected = /\bpix\b/.test(text);
        const values = extractMoneyValues(rawText);
        const expectedAmountDetected = textContainsExpectedAmount(
          rawText,
          expectedAmount,
        );
        const scheduled =
          /comprovante de agendamento|pix agendado|transferencia agendada|pagamento agendado/.test(
            text,
          );
        const completed =
          /realizado|realizada|efetuado|efetuada|concluido|concluida|sucesso|pago|pagamento realizado|transferencia realizada/.test(
            text,
          );
        const evidence = getReceiptEvidence(text);
        const textPreview = rawText.replace(/\s+/g, " ").trim().slice(0, 1200);

        appendProofAdminDiagnostic("Origem analisada: " + sourceLabel + ".");
        appendProofAdminDiagnostic(
          "Texto extraído: " + (textPreview || "nenhum texto reconhecido"),
        );
        appendProofAdminDiagnostic(
          "Palavra Pix reconhecida: " + (pixDetected ? "sim" : "não") + ".",
        );
        appendProofAdminDiagnostic(
          "Valores reconhecidos: " +
            (values.length
              ? values.map((value) => "R$ " + formatBR(value)).join(", ")
              : "nenhum") +
            ". Valor esperado: R$ " +
            formatBR(expectedAmount) +
            ".",
        );
        appendProofAdminDiagnostic(
          "Pagamento concluído: " +
            (completed ? "sim" : "não") +
            "; agendamento detectado: " +
            (scheduled ? "sim" : "não") +
            ".",
        );
        appendProofAdminDiagnostic(
          "Evidências encontradas: " +
            evidence.score +
            "/6" +
            (evidence.found.length
              ? " (" + evidence.found.join(", ") + ")"
              : "") +
            ".",
        );

        if (text.length < 20) {
          throw new Error(
            "Não foi possível ler informações suficientes no arquivo (" +
              sourceLabel +
              "). Envie um comprovante original e legível.",
          );
        }

        if (!pixDetected) {
          throw new Error(
            "O arquivo é válido, mas não foi identificado como um comprovante Pix.",
          );
        }

        if (!expectedAmountDetected) {
          throw new Error(
            "O arquivo parece ser um comprovante Pix, porém não contém o valor desta cobrança: R$ " +
              formatBR(expectedAmount) +
              ".",
          );
        }

        if (scheduled && !completed) {
          throw new Error(
            "O arquivo parece ser um comprovante de Pix agendado e não de pagamento realizado.",
          );
        }

        if (evidence.score < 2) {
          throw new Error(
            "O arquivo contém Pix e o valor correto, mas não possui informações suficientes para ser identificado como comprovante.",
          );
        }

        return extractReceiptDateTime(rawText);
      }

      async function validatePixReceiptPdf(file, expectedAmount) {
        const rawText = await extractPdfText(file);

        return validatePixReceiptText(rawText, expectedAmount, "PDF");
      }

      /* ============================================================
   IMAGENS
   ============================================================ */

      async function validateImageReadable(file) {
        return new Promise(function (resolve, reject) {
          const url = URL.createObjectURL(file);

          const image = new Image();

          image.onload = function () {
            const width = image.naturalWidth;

            const height = image.naturalHeight;

            URL.revokeObjectURL(url);

            if (width < 100 || height < 100) {
              reject(
                new Error(
                  "A imagem enviada é pequena demais para ser considerada legível.",
                ),
              );

              return;
            }

            resolve(true);
          };

          image.onerror = function () {
            URL.revokeObjectURL(url);

            reject(
              new Error("O arquivo de imagem está inválido ou corrompido."),
            );
          };

          image.src = url;
        });
      }

      async function extractImageText(file) {
        if (!window.Tesseract || !window.Tesseract.createWorker) {
          throw new Error(
            "O analisador de imagens não pôde ser carregado. Tente novamente.",
          );
        }

        const worker = await Tesseract.createWorker("por");

        try {
          const result = await worker.recognize(file, {
            rotateAuto: true,
          });

          return String(result.data.text || "").trim();
        } finally {
          await worker.terminate();
        }
      }

      async function validatePixReceiptImage(file, expectedAmount) {
        await validateImageReadable(file);

        const rawText = await extractImageText(file);

        return validatePixReceiptText(rawText, expectedAmount, "imagem");
      }

      /* ============================================================
   VALIDAÇÃO COMPLETA DO COMPROVANTE
   ============================================================ */

      async function validateProofFile(file, expectedAmount) {
        setProofAdminDiagnostics([]);

        if (!file) {
          throw new Error("Nenhum comprovante selecionado.");
        }

        appendProofAdminDiagnostic(
          "Arquivo recebido: " +
            String(file.name || "sem nome") +
            " (" +
            formatBR(file.size / 1024) +
            " KB).",
        );

        if (file.size <= 0) {
          throw new Error("O arquivo está vazio.");
        }

        if (file.size > MAX_PROOF_SIZE) {
          throw new Error("O comprovante deve ter no máximo 10 MB.");
        }

        const type = await detectProofFileType(file);

        appendProofAdminDiagnostic(
          "Tipo detectado pela assinatura do arquivo: " +
            (type || "não reconhecido") +
            ".",
        );

        if (!type) {
          throw new Error("Arquivo inválido. Envie PDF, JPG, PNG ou WEBP.");
        }

        if (type === "pdf") {
          const receiptDetails = await validatePixReceiptPdf(file, expectedAmount);

          return {
            type: "pdf",
            contentChecked: true,
            receiptDate: receiptDetails.date,
            receiptTime: receiptDetails.time,
          };
        }

        const receiptDetails = await validatePixReceiptImage(file, expectedAmount);

        return {
          type: type,
          contentChecked: true,
          receiptDate: receiptDetails.date,
          receiptTime: receiptDetails.time,
        };
      }

      /* ============================================================
   LINK DO PDF FINAL
   ============================================================ */

      function updateFinalPdfLink() {
        const box = document.getElementById("final-pdf-box");

        const link = document.getElementById("final-pdf-link");

        if (finalPdfObjectUrl) {
          try {
            URL.revokeObjectURL(finalPdfObjectUrl);
          } catch (e) {}
        }

        if (!productionFile) {
          box.style.display = "none";

          return;
        }

        finalPdfObjectUrl = URL.createObjectURL(productionFile);

        link.textContent = productionFile.name;

        link.href = finalPdfObjectUrl;

        box.style.display = "block";

        link.onclick = function (event) {
          event.preventDefault();

          window.open(finalPdfObjectUrl, "_blank");

          setTimeout(function () {
            const download = document.createElement("a");

            download.href = finalPdfObjectUrl;

            download.download = productionFile.name;

            download.style.display = "none";

            document.body.appendChild(download);

            download.click();

            download.remove();
          }, 150);
        };
      }

      /* ============================================================
   SINCRONIZA PDF COM INPUT ORIGINAL
   ============================================================ */

      function syncProductionFileWithLegacyInput() {
        if (!productionFile) {
          return;
        }

        const input = document.getElementById("pw-pdf");

        try {
          const transfer = new DataTransfer();

          transfer.items.add(productionFile);

          input.files = transfer.files;
        } catch (error) {
          console.warn("Falha ao sincronizar PDF:", error);
        }
      }

      /* ============================================================
   DADOS DO PEDIDO
   ============================================================ */

      function getOrderData() {
        return {
          name: document.getElementById("sender-name-main").value.trim(),

          whatsapp: document
            .getElementById("sender-whatsapp-main")
            .value.trim(),

          email: document.getElementById("sender-email-main").value.trim(),

          send_customer_copy: document.getElementById("pw-send-customer-copy")?.checked ? "1" : "0",

          instructions: document
            .getElementById("sender-instructions-main")
            .value.trim(),

          amount: Number(getPayableAmount()).toFixed(2),

          original_amount: Number(lastComputed.precoFinal).toFixed(2),

          points_used: String(POINTS_USED || 0),

          points_discount: Number(POINTS_DISCOUNT || 0).toFixed(2),

          payment_session: PAYMENT_SESSION_ID,

          payment_method: PAYMENT_METHOD,

          payment_option:
            document.getElementById("pw-payment-option")?.value || "",

          pay_later_code:
            document.getElementById("pw-pay-later-code")?.value.trim() || "",

          delivery_method:
            document.querySelector(
              'input[name="pw-delivery-method"]:checked',
            )?.value || "",

          height_cm: Number(lastComputed.alturaOriginal).toFixed(2),

          customer_type: lastComputed.tipo,

          calculation_source: lastSource || "",

          calculation_detail: lastComputed.detalhe || "",

          calculation_expression: lastComputed.expression || "",

          calculation_id: String(CALCULATION_RECORD_ID || ""),

          calculation_session: CALCULATION_SESSION_ID,
        };
      }

      function formatWhatsapp(value) {
        const digits = String(value || "").replace(/\D/g, "").slice(0, 11);
        if (!digits) return "";
        if (digits.length <= 2) return "(" + digits;
        if (digits.length <= 7) return "(" + digits.slice(0, 2) + ") " + digits.slice(2);
        return "(" + digits.slice(0, 2) + ") " + digits.slice(2, 7) + "-" + digits.slice(7);
      }

      function getCalculationSessionId() {
        if (CALCULATION_SESSION_ID) return CALCULATION_SESSION_ID;
        const key = "pw_dtf_calculation_session";
        CALCULATION_SESSION_ID = sessionStorage.getItem(key) || "";
        if (!CALCULATION_SESSION_ID) {
          CALCULATION_SESSION_ID = "calc-" + Date.now() + "-" + Math.random().toString(36).slice(2, 12);
          sessionStorage.setItem(key, CALCULATION_SESSION_ID);
        }
        return CALCULATION_SESSION_ID;
      }

      async function recordAbandonedCalculation() {
        if (!lastComputed || !lastSource) return;
        const form = new URLSearchParams();
        form.append("action", "printway_dtf_save_calculation");
        form.append("nonce", window.printway_dtf_nonce || "");
        form.append("session", getCalculationSessionId());
        form.append("source", lastSource);
        form.append("height_cm", Number(lastComputed.alturaOriginal || lastHeightCm || 0).toFixed(2));
        form.append("amount", Number(lastComputed.precoFinal || 0).toFixed(2));
        form.append("customer_type", lastComputed.tipo || "direto");
        form.append("last_step", String(CURRENT_STEP || 1));
        form.append("pix_qr_generated", QR_GENERATED ? "1" : "0");
        form.append("proof_uploaded", proofFile ? "1" : "0");
        form.append("name", document.getElementById("sender-name-main").value.trim());
        form.append("whatsapp", document.getElementById("sender-whatsapp-main").value.replace(/\D/g, ""));
        form.append("email", document.getElementById("sender-email-main").value.trim());
        try {
          const response = await fetch((window.PW_SERVER_DATA && window.PW_SERVER_DATA.ajax_url) || "/wp-admin/admin-ajax.php", { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" }, body: form.toString() });
          const json = await response.json();
          if (json && json.success && json.data && json.data.calculation_id) CALCULATION_RECORD_ID = Number(json.data.calculation_id) || 0;
        } catch (error) { console.warn("Não foi possível registrar o cálculo.", error); }
      }

      /* ============================================================
   PROCURA CONFIGURAÇÃO DE ENVIO JÁ EXISTENTE
   ============================================================ */

      function findExistingBackendConfig() {
        const server = window.PW_SERVER_DATA || {};

        let url =
          window.PW_DTF_UPLOAD_URL ||
          server.dtf_upload_url ||
          server.upload_url ||
          server.production_url ||
          server.send_url ||
          server.email_url ||
          "";

        let action =
          window.PW_DTF_UPLOAD_ACTION ||
          server.dtf_upload_action ||
          server.upload_action ||
          server.production_action ||
          server.send_action ||
          server.email_action ||
          "";

        let nonce =
          window.printway_dtf_nonce ||
          server.dtf_nonce ||
          server.upload_nonce ||
          server.production_nonce ||
          server.nonce ||
          "";

        /*
         * Procura action escondida já criada por outro
         * plugin/script da página.
         */

        if (!action) {
          const actionInputs = Array.from(
            document.querySelectorAll('input[name="action"]'),
          );

          for (const input of actionInputs) {
            const value = String(input.value || "");

            if (
              value &&
              value !== "printway_pix_generate_payload" &&
              /dtf|production|producao|send|upload|email|pdf|printway/i.test(
                value,
              )
            ) {
              action = value;

              break;
            }
          }
        }

        /*
         * Se há uma action, utiliza admin-ajax padrão.
         */

        if (action && !url) {
          url =
            server.ajax_url ||
            server.ajaxUrl ||
            window.ajaxurl ||
            "/wp-admin/admin-ajax.php";
        }

        if (!url) {
          return null;
        }

        return {
          url: url,
          action: action,
          nonce: nonce,
        };
      }

      /* ============================================================
   ENVIA PELO BACKEND DESCOBERTO
   ============================================================ */

      async function sendWithDiscoveredBackend() {
        const config = findExistingBackendConfig();

        if (!config) {
          return false;
        }

        const data = getOrderData();

        const form = new FormData();

        if (config.action) {
          form.append("action", config.action);
        }

        if (config.nonce) {
          form.append("nonce", config.nonce);
        }

        form.append("pdf", productionFile, productionFile.name);

        if (PAYMENT_METHOD === "pix" && proofFile) {
          form.append("receipt", proofFile, proofFile.name);
        }

        Object.keys(data).forEach((key) => {
          form.append(key, data[key]);
        });

        form.append("receipt_file_validated", "1");

        form.append("payment_confirmation_pending", "1");

        form.append("terms_accepted", "1");

        form.append("production_authorized", "1");

        const response = await fetch(config.url, {
          method: "POST",
          body: form,
        });

        const text = await response.text();

        let json = null;

        try {
          json = JSON.parse(text);
        } catch (e) {}

        if (!response.ok) {
          throw new Error("O servidor respondeu HTTP " + response.status + ".");
        }

        if (json && json.success === false) {
          let message = "O servidor recusou o envio.";

          if (typeof json.data === "string") {
            message = json.data;
          } else if (json.data && json.data.message) {
            message = json.data.message;
          }

          throw new Error(message);
        }

        if (json && (json.success === true || json.ok === true)) {
          return true;
        }

        if (text.trim() && text.trim() !== "0" && text.trim() !== "-1") {
          return true;
        }

        return false;
      }

      /* ============================================================
   MONITORA O ENVIO ANTIGO
   ============================================================ */

      function formDataLooksLikeProduction(body) {
        if (!(body instanceof FormData)) {
          return false;
        }

        let hasFile = false;

        for (const pair of body.entries()) {
          const value = pair[1];

          if (value instanceof File && value.size > 0) {
            hasFile = true;
          }
        }

        return hasFile;
      }

      function installLegacyNetworkMonitor(onSuccess, onError) {
        const originalFetch = window.fetch;

        const originalOpen = XMLHttpRequest.prototype.open;

        const originalSend = XMLHttpRequest.prototype.send;

        let restored = false;

        function restore() {
          if (restored) {
            return;
          }

          restored = true;

          window.fetch = originalFetch;

          XMLHttpRequest.prototype.open = originalOpen;

          XMLHttpRequest.prototype.send = originalSend;
        }

        window.fetch = async function () {
          const args = Array.from(arguments);

          const options = args[1] || {};

          const candidate =
            String(options.method || "GET").toUpperCase() === "POST" &&
            formDataLooksLikeProduction(options.body);

          try {
            const response = await originalFetch.apply(this, args);

            if (candidate) {
              const copy = response.clone();

              const text = await copy.text().catch(function () {
                return "";
              });

              if (response.ok && text.trim() !== "0" && text.trim() !== "-1") {
                restore();

                onSuccess();
              } else {
                onError(
                  new Error("O envio existente foi recusado pelo servidor."),
                );
              }
            }

            return response;
          } catch (error) {
            if (candidate) {
              onError(error);
            }

            throw error;
          }
        };

        XMLHttpRequest.prototype.open = function (method, url) {
          this._pwMethod = method;

          return originalOpen.apply(this, arguments);
        };

        XMLHttpRequest.prototype.send = function (body) {
          const xhr = this;

          const candidate =
            String(xhr._pwMethod || "").toUpperCase() === "POST" &&
            formDataLooksLikeProduction(body);

          if (candidate) {
            xhr.addEventListener(
              "loadend",
              function () {
                if (
                  xhr.status >= 200 &&
                  xhr.status < 300 &&
                  String(xhr.responseText || "").trim() !== "0" &&
                  String(xhr.responseText || "").trim() !== "-1"
                ) {
                  restore();

                  onSuccess();
                } else {
                  onError(new Error("O servidor recusou o envio."));
                }
              },
              {
                once: true,
              },
            );
          }

          return originalSend.apply(this, arguments);
        };

        return restore;
      }

      /* ============================================================
   PREPARA MECANISMO ANTIGO
   ============================================================ */

      function prepareLegacySend() {
        const data = getOrderData();

        document.getElementById("sender-name").value = data.name;

        document.getElementById("sender-whatsapp").value = data.whatsapp;

        document.getElementById("sender-email").value = data.email;

        syncProductionFileWithLegacyInput();

        /*
         * Disponibiliza também os dados completos para
         * o código PHP/JS existente da página.
         */

        window.PW_DTF_ORDER = {
          name: data.name,

          whatsapp: data.whatsapp,

          email: data.email,

          instructions: data.instructions,

          amount: data.amount,

          height_cm: data.height_cm,

          customer_type: data.customer_type,

          calculation_source: data.calculation_source,

          calculation_detail: data.calculation_detail,

          calculation_expression: data.calculation_expression,

          pdf: productionFile,

          receipt: proofFile,

          receiptValidated: true,

          productionAuthorized: true,
        };
      }

      /* ============================================================
   SUCESSO DO ENVIO
   ============================================================ */

      function markProductionSent() {
        if (finalSendComplete) {
          return;
        }

        finalSendComplete = true;

        stopSendProgress(true);

        const status = document.getElementById("send-status");

        const button = document.getElementById("btn-finish");

        const backButton = document.getElementById("btn-back-4");

        const tracking = document.getElementById("pw-order-tracking");

        status.style.color = "#2b7a2b";

        status.textContent =
          "Solicitação enviada com sucesso. A gráfica irá conferir o arquivo e o pagamento antes de iniciar a produção.";

        button.style.display = "none";

        backButton.style.display = "none";

        tracking.style.display = "flex";
      }

      /*
       * Compatibilidade com o callback antigo.
       */

      const previousMarkSent = window._pw_markSent;

      window._pw_markSent = function () {
        if (typeof previousMarkSent === "function") {
          try {
            previousMarkSent();
          } catch (e) {}
        }

        markProductionSent();
      };

      /* ============================================================
   INTERFACE
   ============================================================ */

      document.addEventListener("DOMContentLoaded", async function () {
        document
          .getElementById("btn-new-order")
          .addEventListener("click", function () {
            window.location.reload();
          });

        const senderName = document.getElementById("sender-name-main");

        const senderWhatsapp = document.getElementById("sender-whatsapp-main");

        const senderEmail = document.getElementById("sender-email-main");

        const instructions = document.getElementById(
          "sender-instructions-main",
        );

        const identError = document.getElementById("ident-error");

        let currentUser =
          window.PW_SERVER_DATA &&
          window.PW_SERVER_DATA.currentUser &&
          Number(window.PW_SERVER_DATA.currentUser.id) > 0
            ? window.PW_SERVER_DATA.currentUser
            : null;

        if (currentUser) {
          try {
            const server = window.PW_SERVER_DATA || {};
            const form = new URLSearchParams();
            form.append(
              "action",
              server.dtf_current_user_action ||
                "printway_dtf_get_current_user_profile",
            );
            form.append(
              "nonce",
              window.printway_dtf_nonce || server.dtf_nonce || "",
            );

            const response = await fetch(
              server.ajax_url || "/wp-admin/admin-ajax.php",
              {
                method: "POST",
                credentials: "same-origin",
                headers: {
                  "Content-Type":
                    "application/x-www-form-urlencoded; charset=UTF-8",
                },
                body: form.toString(),
              },
            );
            const json = await response.json();

            if (response.ok && json && json.success && json.data) {
              currentUser = Object.assign({}, currentUser, json.data);
            }
          } catch (error) {
            console.error("Não foi possível atualizar os dados cadastrais.", error);
          }
        }

        const tipo = document.getElementById("pw-tipo-step");

        const fileInput = document.getElementById("pw-pdf");


        const proofInput = document.getElementById("pw-proof-file");

        const pdfArea = document.getElementById("pdf-area");

        const manualArea = document.getElementById("manual-area");

        const pdfStatus = document.getElementById("pdf-status");

        const manualWarning = document.getElementById("manual-warning");


        const alturaInput = document.getElementById("pw-altura-step");
        const heightCalculator = document.getElementById("pw-height-calculator");
        const imagesCalculator = document.getElementById("pw-images-calculator");
        const modeTotalHeight = document.getElementById("pw-mode-total-height");
        const modeImages = document.getElementById("pw-mode-images");

        const btnNext2 = document.getElementById("btn-next-2");

        const btnGenerateQr = document.getElementById("btn-generate-qr");

        const btnNext3 = document.getElementById("btn-next-3");

        const paymentMsg = document.getElementById("payment-msg");

        const qrContainer = document.getElementById("pw-pix-qrcode");

        const copyArea = document.getElementById("pw-pix-copy-area");

        const copyStatus = document.getElementById("pix-copy-status");

        const proofArea = document.getElementById("pw-proof-area");

        const proofStatus = document.getElementById("proof-status");

        const proofNotice = document.getElementById("proof-notice");

        const summaryText = document.getElementById("summary-text");

        const sendStatus = document.getElementById("send-status");

        const btnFinish = document.getElementById("btn-finish");

        /* ========================================================
       DADOS SALVOS
       ======================================================== */

        setActiveStep(1);

        const saved = readPersistedSender();

        const formDataSource = {
          name: "",
          whatsapp: "",
          email: "",
        };

        if (currentUser) {
          const user = currentUser;
          const fullName = String(
            user.full_name ||
              [user.first_name, user.last_name]
            .filter(Boolean)
            .join(" ")
              .trim(),
          ).trim();

          if (fullName) {
            senderName.value = fullName;
            formDataSource.name = "login";
          } else {
            senderName.value = "";
            formDataSource.name = "cadastro sem Nome/Sobrenome";
          }

          if (user.email) {
            senderEmail.value = user.email || "";
            formDataSource.email = "login";
          }

          if (user.whatsapp) {
            senderWhatsapp.value = user.whatsapp || "";
            formDataSource.whatsapp = "login";
          }
        }

        /* === Campos extras de cadastro (CPF/CNPJ, CEP, endereço) === */
        const extraProfileFields  = document.getElementById("pw-extra-profile-fields");
        const addressFieldsBlock  = document.getElementById("pw-address-fields");
        const cpfCnpjInput        = document.getElementById("sender-cpfcnpj");
        const cepInput            = document.getElementById("sender-cep");
        const streetInput         = document.getElementById("sender-street");
        const numberInput         = document.getElementById("sender-number");
        const complementInput     = document.getElementById("sender-complement");
        const neighborhoodInput   = document.getElementById("sender-neighborhood");
        const cityInput           = document.getElementById("sender-city");
        const stateInput          = document.getElementById("sender-state");

        function fmtCpfCnpj(v) {
          var d = v.replace(/\D/g, "").slice(0, 14);
          if (d.length <= 11) {
            return d.replace(/(\d{3})(\d)/, "$1.$2")
                    .replace(/(\d{3})(\d)/, "$1.$2")
                    .replace(/(\d{3})(\d{1,2})$/, "$1-$2");
          }
          return d.replace(/^(\d{2})(\d)/, "$1.$2")
                  .replace(/^(\d{2})\.(\d{3})(\d)/, "$1.$2.$3")
                  .replace(/\.(\d{3})(\d)/, ".$1/$2")
                  .replace(/(\d{4})(\d)/, "$1-$2");
        }

        function fmtCep(v) {
          var d = v.replace(/\D/g, "").slice(0, 8);
          return d.length > 5 ? d.slice(0, 5) + "-" + d.slice(5) : d;
        }

        if (currentUser) {
          if (currentUser.cpf_cnpj && cpfCnpjInput)        cpfCnpjInput.value        = fmtCpfCnpj(currentUser.cpf_cnpj);
          if (currentUser.cep && cepInput)                  cepInput.value            = fmtCep(currentUser.cep);
          if (currentUser.address_street && streetInput)    streetInput.value         = currentUser.address_street;
          if (currentUser.address_number && numberInput)    numberInput.value         = currentUser.address_number;
          if (currentUser.address_complement && complementInput) complementInput.value = currentUser.address_complement;
          if (currentUser.address_neighborhood && neighborhoodInput) neighborhoodInput.value = currentUser.address_neighborhood;
          if (currentUser.address_city && cityInput)        cityInput.value           = currentUser.address_city;
          if (currentUser.address_state && stateInput)      stateInput.value          = currentUser.address_state;

          var extraRequired = ["cpf_cnpj","cep","street","number","neighborhood","city","state"];
          var missingExtra  = (currentUser.missing_required || []).filter(function(f){ return extraRequired.indexOf(f) >= 0; });
          if (extraProfileFields && missingExtra.length > 0) {
            extraProfileFields.style.display = "block";
            if (addressFieldsBlock && (currentUser.cep || currentUser.address_street)) {
              addressFieldsBlock.style.display = "block";
            }
          }

          /* Tipo de cliente — oculta o campo para não-admins e define o valor */
          if (!currentUser.is_admin) {
            var tipoEl = document.getElementById("pw-tipo-step");
            if (tipoEl && tipoEl.parentElement) tipoEl.parentElement.style.display = "none";
            if (tipoEl && currentUser.client_type) tipoEl.value = currentUser.client_type;
          }
        }

        /* ViaCEP */
        async function lookupViaCep(rawCep) {
          var d = rawCep.replace(/\D/g, "");
          if (d.length !== 8) return;
          if (addressFieldsBlock) addressFieldsBlock.style.display = "block";
          try {
            var res  = await fetch("https://viacep.com.br/ws/" + d + "/json/");
            var data = await res.json();
            if (data && !data.erro) {
              if (streetInput && !streetInput.value.trim())         streetInput.value       = data.logradouro  || "";
              if (neighborhoodInput && !neighborhoodInput.value.trim()) neighborhoodInput.value = data.bairro  || "";
              if (cityInput && !cityInput.value.trim())             cityInput.value         = data.localidade  || "";
              if (stateInput && !stateInput.value.trim())           stateInput.value        = data.uf          || "";
              setTimeout(function(){ if (numberInput && !numberInput.value) numberInput.focus(); }, 60);
            }
          } catch(e) { /* ViaCEP offline */ }
        }

        if (cepInput) {
          cepInput.addEventListener("input",  function(){ cepInput.value = fmtCep(cepInput.value); });
          cepInput.addEventListener("keydown", function(e){ if (e.key === "Enter"){ e.preventDefault(); lookupViaCep(cepInput.value); } });
          cepInput.addEventListener("blur",    function(){ lookupViaCep(cepInput.value); });
        }
        if (cpfCnpjInput) {
          cpfCnpjInput.addEventListener("input", function(){
            var start = cpfCnpjInput.selectionStart;
            var before = cpfCnpjInput.value.slice(0, start).replace(/\D/g,"").length;
            cpfCnpjInput.value = fmtCpfCnpj(cpfCnpjInput.value);
            var ct = 0, ni = 0;
            for (ni = 0; ni < cpfCnpjInput.value.length && ct < before; ni++) {
              if (/\d/.test(cpfCnpjInput.value[ni])) ct++;
            }
            cpfCnpjInput.setSelectionRange(ni, ni);
          });
        }

        if (saved && !currentUser) {
          if (!senderName.value && saved.name) {
            senderName.value = saved.name;
            formDataSource.name = "cache local";
          }

          if (!senderWhatsapp.value && saved.whatsapp) {
            senderWhatsapp.value = saved.whatsapp;
            formDataSource.whatsapp = "cache local";
          }

          if (!senderEmail.value && saved.email) {
            senderEmail.value = saved.email;
            formDataSource.email = "cache local";
          }
        }

        const info = document.getElementById("pw-user-info");

        if (currentUser) {
          const user = currentUser;

          info.textContent =
            "Usuário: " +
            (user.display || user.login || "—") +
            " | Função: " +
            (user.is_admin ? "Administrador" : "Usuário");
        } else {
          info.innerHTML =
            '<a href="https://printway.com.br/minha-conta/">Crie seu login com CNPJ e torne-se um revendedor com preços especiais - clique aqui</a><span class="pw-login-note">Após o cadastro, sua conta ficará como Cliente. Para obter as vantagens de Revendedor, solicite a mudança para Usuário Revendedor (essa opção estará localizada dentro da sua área de usuário em Painel de Controle). O programa de pontos é exclusivo para pedidos realizados com login.</span>';
        }

        if (
          currentUser &&
          currentUser.is_admin
        ) {
          const adminUser = currentUser;

          info.textContent +=
            " | Login: " +
            (adminUser.login || "—") +
            " | ID WordPress: " +
            (adminUser.id || "—") +
             " | C\u00f3digo de libera\u00e7\u00e3o: " +
            (adminUser.unlock_code || "n\u00e3o cadastrado") +
            " | Origem do preenchimento: Nome (" +
            (formDataSource.name || "não preenchido") +
            "), WhatsApp (" +
            (formDataSource.whatsapp || "não preenchido") +
            "), E-mail (" +
            (formDataSource.email || "não preenchido") +
            ") | Vers\u00e3o HTML: 2.8.9 | Gerado em: 21/08/2026 16:40";

          info.style.color = "#2271b1";
          info.style.fontStyle = "italic";
        }

        info.style.display = "inline-block";

        /* Exibe tipo de cliente no Step 1 */
        var clientTypeDisplay = document.getElementById("pw-client-type-display");
        var clientTypeLabel   = document.getElementById("pw-client-type-label");
        if (clientTypeDisplay && clientTypeLabel && currentUser) {
          clientTypeLabel.textContent = currentUser.client_type === "revenda" ? "Revendedor" : "Cliente direto";
          clientTypeDisplay.style.display = "block";
        }

        const btnPayNow = document.getElementById("btn-pay-now");
        const btnPayLater = document.getElementById("btn-pay-later");
        const payLaterArea = document.getElementById("pw-pay-later-area");
        const payLaterCode = document.getElementById("pw-pay-later-code");
        const btnValidatePayLater = document.getElementById("btn-validate-pay-later");
        const payLaterStatus = document.getElementById("pw-pay-later-status");
        const payLaterOptions = document.getElementById("pw-pay-later-options");
        const paymentOption = document.getElementById("pw-payment-option");
        const deliveryMethods = Array.from(
          document.querySelectorAll('input[name="pw-delivery-method"]'),
        );
        const deliveryStatus = document.getElementById("pw-delivery-status");
        const pointsArea = document.getElementById("pw-points-area");
        const pointsBalanceText = document.getElementById("pw-points-balance");
        const pointsLimitText = document.getElementById("pw-points-limit");
        const pointsInput = document.getElementById("pw-points-use");
        const pointsStatus = document.getElementById("pw-points-status");
        const btnApplyPoints = document.getElementById("btn-apply-points");
        const pointsEarnMessage = document.getElementById("pw-points-earn-message");
        const paymentLockNotice = document.getElementById("pw-payment-lock-notice");

        if (!currentUser) {
          tipo.value = "direto";
          tipo.disabled = true;
          tipo.title = "Faça login para selecionar o tipo de cliente.";
        }

        function userCanPayLater() {
          return !!(
            currentUser &&
            (typeof currentUser.can_pay_later !== "boolean" ||
              currentUser.can_pay_later)
          );
        }

        function setPaymentControlsLocked(locked) {
          PAYMENT_CONTROLS_LOCKED = !!locked;
          paymentLockNotice.style.display = PAYMENT_CONTROLS_LOCKED
            ? "block"
            : "none";

          btnPayNow.disabled = PAYMENT_CONTROLS_LOCKED;
          btnPayLater.disabled = PAYMENT_CONTROLS_LOCKED || !userCanPayLater();

          if (PAYMENT_CONTROLS_LOCKED) {
            pointsInput.disabled = true;
            btnApplyPoints.disabled = true;
            btnPayNow.title = "Forma de pagamento bloqueada após a validação do comprovante.";
            btnPayLater.title = "Forma de pagamento bloqueada após a validação do comprovante.";
            btnApplyPoints.title = "Os pontos foram bloqueados após a validação do comprovante.";
          } else {
            btnPayNow.removeAttribute("title");
            btnApplyPoints.removeAttribute("title");
            if (userCanPayLater()) {
              btnPayLater.removeAttribute("title");
            }
          }
        }

        senderWhatsapp.value = formatWhatsapp(senderWhatsapp.value);
        senderWhatsapp.addEventListener("input", function () {
          this.value = formatWhatsapp(this.value);
        });

        function invalidatePaymentSession() {
          paymentSessionSequence++;
          PAYMENT_SESSION_ID = "";
        }

        async function preparePaymentSession() {
          if (!lastComputed) {
            throw new Error("Calcule o pedido antes de preparar o pagamento.");
          }

          const sequence = ++paymentSessionSequence;
          PAYMENT_SESSION_ID = "";

          const form = new URLSearchParams();
          form.append("action", "printway_dtf_prepare_payment");
          form.append("nonce", window.printway_dtf_nonce || "");
          form.append("height_cm", Number(lastComputed.alturaOriginal).toFixed(2));
          form.append("customer_type", lastComputed.tipo || "");
          form.append("points_used", String(POINTS_USED || 0));

          const response = await fetch(
            window.PW_DTF_UPLOAD_URL || "/wp-admin/admin-ajax.php",
            {
              method: "POST",
              headers: {
                "Content-Type":
                  "application/x-www-form-urlencoded; charset=UTF-8",
              },
              body: form.toString(),
            },
          );
          const json = await response.json();

          if (
            sequence !== paymentSessionSequence ||
            !response.ok ||
            !json ||
            !json.success ||
            !json.data ||
            !json.data.payment_session
          ) {
            throw new Error(
              (json && json.data && json.data.message) ||
                "Não foi possível proteger os dados deste pagamento.",
            );
          }

          const serverOriginal = Number(json.data.original_amount || 0);
          if (
            !Number.isFinite(serverOriginal) ||
            serverOriginal <= 0 ||
            Math.abs(serverOriginal - Number(lastComputed.precoFinal || 0)) > 0.01
          ) {
            throw new Error(
              "A tabela de preços foi atualizada. Recalcule o pedido antes de pagar.",
            );
          }

          PAYMENT_SESSION_ID = String(json.data.payment_session);
          POINTS_USED = Number(json.data.points_used || 0);
          POINTS_DISCOUNT = Number(json.data.points_discount || 0);
          pointsInput.value = String(POINTS_USED);
          updateSummary();

          return json.data;
        }

        function getSelectedDeliveryMethod() {
          const selected = deliveryMethods.find(function(input) {
            return input.checked;
          });
          return selected ? selected.value : "";
        }

        function refreshAdvanceAvailability() {
          const hasDelivery = getSelectedDeliveryMethod() !== "";
          let canAdvance = false;

          if (PAYMENT_METHOD === "pix") {
            canAdvance =
              QR_GENERATED &&
              PROOF_VALIDATED &&
              PAYMENT_SESSION_ID !== "" &&
              hasDelivery;
          } else if (PAYMENT_METHOD === "points") {
            canAdvance = PAYMENT_SESSION_ID !== "" && hasDelivery;
          } else if (PAYMENT_METHOD === "alternative") {
            canAdvance =
              PAY_LATER_VALIDATED &&
              paymentOption.value !== "" &&
              hasDelivery;
          }

          btnNext3.disabled = !canAdvance;
          deliveryStatus.textContent = hasDelivery
            ? "Forma de recebimento selecionada."
            : "Selecione como deseja receber o pedido.";

          if (PAYMENT_METHOD === "alternative" && PAY_LATER_VALIDATED) {
            payLaterStatus.className = canAdvance
              ? "pw-proof-status ok"
              : "pw-proof-status wait";
            payLaterStatus.textContent = canAdvance
              ? "Pagar depois configurado. Você pode avançar."
              : "Selecione a forma de pagamento e como deseja receber o pedido.";
          }

          return canAdvance;
        }

        function refreshPointsArea() {
          const gross = lastComputed ? Number(lastComputed.precoFinal || 0) : 0;
          const enabled = !!(POINTS_SETTINGS && POINTS_SETTINGS.enabled && currentUser);
          const pointsPerReal = Number(POINTS_SETTINGS?.points_per_real || 0);
          const percentage = Math.max(0, Math.min(100, Number(POINTS_SETTINGS?.max_redemption_percent || 0)));
          const maxDiscount = gross * percentage / 100;
          const maxPoints = pointsPerReal > 0 ? Math.floor(Math.min(POINTS_BALANCE, maxDiscount * pointsPerReal)) : 0;

          pointsArea.style.display = enabled ? "block" : "none";
          document.getElementById("pw-points-spacer").style.display = enabled ? "block" : "none";
          pointsBalanceText.textContent = String(POINTS_BALANCE);
          pointsLimitText.textContent = String(maxPoints);
          pointsInput.max = String(maxPoints);
          pointsInput.disabled = PAYMENT_CONTROLS_LOCKED || !enabled || maxPoints <= 0;
          btnApplyPoints.disabled = PAYMENT_CONTROLS_LOCKED || !enabled || maxPoints <= 0;
          btnPayNow.disabled = PAYMENT_CONTROLS_LOCKED;
          btnPayLater.disabled = PAYMENT_CONTROLS_LOCKED || !userCanPayLater();

          const earnRate = Number(POINTS_SETTINGS?.earn_points_per_real || 0);
          const pointsToEarn = Math.max(0, Math.floor(getPayableAmount() * earnRate));
          const pointValue = Number(POINTS_SETTINGS?.redemption_reais || 0) / Number(POINTS_SETTINGS?.redemption_points || 1);
          const earningReais = Number(POINTS_SETTINGS?.earning_reais || 0);
          const earningPoints = Number(POINTS_SETTINGS?.earning_points || 0);
          pointsEarnMessage.style.display = enabled && lastComputed ? "block" : "none";
          if (enabled && lastComputed) {
            pointsEarnMessage.innerHTML = "<ul style='margin:8px 0 0;padding-left:20px;line-height:1.6'><li>Este pedido gerará <strong>" + pointsToEarn + " ponto" + (pointsToEarn === 1 ? "" : "s") + "</strong>.</li><li>A cada <strong>R$ " + formatBR(earningReais) + "</strong> pago, você ganha <strong>" + earningPoints + " ponto" + (earningPoints === 1 ? "" : "s") + "</strong>.</li><li>Nos próximos pedidos, cada ponto poderá ser trocado por <strong>R$ " + formatBR(pointValue) + "</strong>.</li><li>Os pontos serão liberados após a confirmação ou conclusão do pedido pela empresa.</li></ul>";
          }
        }

        function applyPoints() {
          if (PAYMENT_CONTROLS_LOCKED) {
            return;
          }

          qrGenerationSequence++;
          invalidatePaymentSession();

          const gross = lastComputed ? Number(lastComputed.precoFinal || 0) : 0;
          const pointsPerReal = Number(POINTS_SETTINGS?.points_per_real || 0);
          const percentage = Math.max(0, Math.min(100, Number(POINTS_SETTINGS?.max_redemption_percent || 0)));
          const maxPoints = pointsPerReal > 0 ? Math.floor(Math.min(POINTS_BALANCE, gross * percentage / 100 * pointsPerReal)) : 0;
          const rawPoints = String(pointsInput.value || "").trim();
          let requested = Math.max(0, Math.floor(Number(rawPoints || 0)));
          if (rawPoints === "" && maxPoints > 0) {
            requested = maxPoints;
          }
          POINTS_USED = Math.min(requested, maxPoints);
          POINTS_DISCOUNT = pointsPerReal > 0 ? Math.min(gross * percentage / 100, POINTS_USED / pointsPerReal) : 0;
          pointsInput.value = String(POINTS_USED);
          pointsStatus.textContent = POINTS_USED > 0 ? ("Desconto aplicado: R$ " + formatBR(POINTS_DISCOUNT) + ". Restante para Pix: R$ " + formatBR(getPayableAmount()) + ".") : "Nenhum ponto será usado neste pedido.";
          QR_GENERATED = false;
          PROOF_VALIDATED = false;
          proofFile = null;
          proofInput.value = "";
          qrContainer.innerHTML = "";
          copyArea.style.display = "none";
          proofArea.style.display = "none";
          if (getPayableAmount() <= 0.001 && POINTS_USED > 0) {
            PAYMENT_METHOD = "points";
            btnGenerateQr.style.display = "none";
            btnNext3.disabled = true;
            paymentMsg.style.color = "#666";
            paymentMsg.textContent = "Protegendo o uso dos pontos...";
            preparePaymentSession()
              .then(function(data) {
                if (data.payment_method !== "points") {
                  throw new Error("O valor do pedido ainda exige pagamento por Pix.");
                }
                paymentMsg.style.color = "#2b7a2b";
                paymentMsg.textContent = getSelectedDeliveryMethod()
                  ? "O pedido será pago integralmente com pontos. Você pode avançar."
                  : "O pedido será pago integralmente com pontos. Selecione como deseja recebê-lo.";
                refreshAdvanceAvailability();
              })
              .catch(function(error) {
                PAYMENT_SESSION_ID = "";
                btnNext3.disabled = true;
                paymentMsg.style.color = "var(--danger)";
                paymentMsg.textContent = error.message || "Não foi possível validar os pontos.";
              });
          } else {
            PAYMENT_METHOD = "pix";
            btnNext3.disabled = true;
          }
          updateSummary();
        }

        btnApplyPoints.addEventListener("click", applyPoints);

        if (currentUser) {
          POINTS_SETTINGS = (window.PW_SERVER_DATA && window.PW_SERVER_DATA.points) || null;
          POINTS_BALANCE = Number(currentUser.points_balance || 0);
          refreshPointsArea();
          const pointsForm = new URLSearchParams();
          pointsForm.append("action", "printway_dtf_get_points_access");
          fetch(window.PW_DTF_UPLOAD_URL || "/wp-admin/admin-ajax.php", { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" }, body: pointsForm.toString() })
            .then(function(response) { return response.json(); })
            .then(function(json) {
              if (json && json.success && json.data) {
                POINTS_SETTINGS = json.data.settings || POINTS_SETTINGS;
                POINTS_BALANCE = Number(json.data.balance || 0);
                refreshPointsArea();
              }
            })
            .catch(function() {});
        }

        if (currentUser) {
          btnPayLater.style.display = "inline-flex";

          const hasPayLaterPermission =
            typeof currentUser.can_pay_later === "boolean"
              ? currentUser.can_pay_later
              : true;

          if (!hasPayLaterPermission) {
            btnPayLater.disabled = true;
            btnPayLater.title =
              "Você não possui um código de liberação para Pagar depois.";
          }

          const accessForm = new URLSearchParams();
          accessForm.append("action", "printway_dtf_get_pay_later_access");

          fetch(window.PW_DTF_UPLOAD_URL || "/wp-admin/admin-ajax.php", {
            method: "POST",
            headers: {
              "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
            },
            body: accessForm.toString(),
          })
            .then(function (response) {
              return response.json();
            })
            .then(function (json) {
              if (!json || !json.success || !json.data) {
                return;
              }

              currentUser.can_pay_later = !!json.data.can_pay_later;
              currentUser.pay_later_code = json.data.pay_later_code || "";

              if (currentUser.can_pay_later && !PAYMENT_CONTROLS_LOCKED) {
                btnPayLater.disabled = false;
                btnPayLater.removeAttribute("title");
              } else {
                btnPayLater.disabled = true;
                btnPayLater.title =
                  "Você não possui um código de liberação para Pagar depois.";
              }

              if (currentUser.is_admin) {
                info.textContent = info.textContent.replace(
                  /Código de liberação: [^|]*/,
                  "Código de liberação: " +
                    (currentUser.pay_later_code || "não cadastrado"),
                );
              }
            })
            .catch(function () {
              /* Mantém a informação disponível no carregamento da página. */
            });
        }

        btnPayNow.addEventListener("click", function () {
          if (PAYMENT_CONTROLS_LOCKED) {
            return;
          }

          PAYMENT_METHOD = "pix";
          PAY_LATER_VALIDATED = false;
          payLaterArea.style.display = "none";
          paymentMsg.textContent = "";
          refreshPointsArea();
          refreshAdvanceAvailability();
          btnGenerateQr.click();
        });

        btnPayLater.addEventListener("click", function () {
          if (PAYMENT_CONTROLS_LOCKED) {
            return;
          }

          qrGenerationSequence++;
          invalidatePaymentSession();
          PAYMENT_METHOD = "alternative";
          PAY_LATER_VALIDATED = false;

          if (!payLaterCode.value && currentUser && currentUser.pay_later_code) {
            payLaterCode.value = currentUser.pay_later_code;
          }

          qrContainer.innerHTML = "";
          pointsArea.style.display = "none";
          copyArea.style.display = "none";
          proofArea.style.display = "none";
          btnNext3.disabled = true;
          payLaterArea.style.display = "block";
          payLaterStatus.textContent = "Digite seu código para liberar as opções.";
          payLaterStatus.className = "pw-proof-status wait";
          refreshAdvanceAvailability();
        });

        btnValidatePayLater.addEventListener("click", async function () {
          const code = payLaterCode.value.trim();
          if (!code) {
            payLaterStatus.className = "pw-proof-status error";
            payLaterStatus.textContent = "Digite o código de liberação.";
            return;
          }
          payLaterStatus.className = "pw-proof-status wait";
          payLaterStatus.textContent = "Validando código...";
          const form = new URLSearchParams();
          form.append("action", "printway_dtf_validate_pay_later");
          form.append("nonce", window.printway_dtf_nonce || "");
          form.append("code", code);
          try {
            const response = await fetch(window.PW_DTF_UPLOAD_URL || "/wp-admin/admin-ajax.php", { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" }, body: form.toString() });
            const json = await response.json();
            if (!response.ok || !json.success) { throw new Error((json.data && json.data.message) || "Código inválido."); }
            PAY_LATER_VALIDATED = true;
            payLaterStatus.className = "pw-proof-status ok";
            payLaterStatus.textContent = "Código validado. Selecione a forma de pagamento e como deseja receber o pedido.";
            payLaterOptions.style.display = "block";
            refreshAdvanceAvailability();
          } catch (error) {
            PAY_LATER_VALIDATED = false;
            payLaterOptions.style.display = "none";
            payLaterStatus.className = "pw-proof-status error";
            payLaterStatus.textContent = error.message || "Não foi possível validar o código.";
            refreshAdvanceAvailability();
          }
        });

        paymentOption.addEventListener("change", function() {
          refreshAdvanceAvailability();
        });

        deliveryMethods.forEach(function(input) {
          input.addEventListener("change", function() {
            const ready = refreshAdvanceAvailability();
            if (ready && PAYMENT_METHOD === "pix") {
              paymentMsg.style.color = "#2b7a2b";
              paymentMsg.textContent =
                "Comprovante e forma de recebimento confirmados. Você pode avançar.";
            } else if (ready && PAYMENT_METHOD === "points") {
              paymentMsg.style.color = "#2b7a2b";
              paymentMsg.textContent =
                "Pagamento com pontos e forma de recebimento confirmados. Você pode avançar.";
            }
          });
        });

        /* ========================================================
       RESET PAGAMENTO
       ======================================================== */

        function resetPaymentState() {
          qrGenerationSequence++;

          proofValidationSequence++;

          invalidatePaymentSession();

          setPaymentControlsLocked(false);

          QR_GENERATED = false;

          PROOF_VALIDATED = false;

          PAYMENT_METHOD = "pix";

          PAY_LATER_VALIDATED = false;

          POINTS_USED = 0;

          POINTS_DISCOUNT = 0;

          proofFile = null;

          LAST_PIX_COPY_VALUE = "";

          proofInput.value = "";

          btnNext3.disabled = true;

          paymentMsg.textContent = "";

          qrContainer.innerHTML = "";

          copyArea.style.display = "none";

          copyStatus.textContent = "";

          proofArea.style.display = "none";

          proofNotice.style.display = "none";

          proofStatus.className = "pw-proof-status wait";

          proofStatus.textContent = "";

          pointsInput.value = "0";

          pointsStatus.textContent = "";

          paymentOption.value = "";

          payLaterArea.style.display = "none";

          payLaterOptions.style.display = "none";

          deliveryMethods.forEach(function(input) {
            input.checked = false;
          });

          refreshAdvanceAvailability();

          setProofAdminDiagnostics([]);

        }

        /* ========================================================
       RESUMO
       ======================================================== */

        function updateSummary() {
          if (!lastComputed) {
            summaryText.textContent = "Nenhum cálculo realizado ainda.";

            document.getElementById("valor-total-dtf").textContent = "R$ 0,00";

            document.getElementById("valor-produto-dtf").textContent = "0,00";

            btnGenerateQr.disabled = true;

            return;
          }

          document.getElementById("valor-total-dtf").textContent =
            "R$ " + formatBR(getPayableAmount());

          document.getElementById("valor-produto-dtf").textContent = formatBR(
            getPayableAmount(),
          ).replace(/\s/g, "");

          const lines = [
            "Largura: " +
              formatCm(LARGURA_ESPERADA_CM) +
              " cm × Altura: " +
              formatCm(lastComputed.alturaOriginal) +
              " cm",
            "Tipo: " +
              (lastComputed.tipo === "revenda"
                ? "Revendedor"
                : "Cliente Direto"),
          ];

          summaryText.innerText = lines.join("\n");

          btnGenerateQr.disabled = false;
          refreshPointsArea();
        }

        /* ========================================================
       IDENTIFICAÇÃO
       ======================================================== */

        document
          .getElementById("btn-start-next")
          .addEventListener("click", async function () {
            const name     = senderName.value.trim();
            const whatsapp = senderWhatsapp.value.trim();
            const email    = senderEmail.value.trim();
            const digits   = whatsapp.replace(/\D/g, "");
            const emailRx  = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            const showErr = function(msg){ identError.style.display = "block"; identError.textContent = msg; };

            if (name.length < 2)                         { showErr("Nome obrigatório."); return; }
            if (!/^[1-9]{2}9\d{8}$/.test(digits))       { showErr("WhatsApp inválido."); return; }
            if (!emailRx.test(email))                    { showErr("E-mail inválido."); return; }

            /* Validação dos campos extras (se visíveis) */
            var needSaveProfile = false;
            if (extraProfileFields && extraProfileFields.style.display !== "none") {
              var cpfCnpjDigits = cpfCnpjInput ? cpfCnpjInput.value.replace(/\D/g,"") : "";
              if (cpfCnpjDigits.length !== 11 && cpfCnpjDigits.length !== 14) { showErr("CPF ou CNPJ inválido."); return; }
              var cepDigits = cepInput ? cepInput.value.replace(/\D/g,"") : "";
              if (cepDigits.length !== 8) { showErr("CEP inválido. Preencha e pressione Enter para buscar."); return; }
              if (addressFieldsBlock && addressFieldsBlock.style.display !== "none") {
                if (!streetInput || !streetInput.value.trim())           { showErr("Informe o logradouro."); return; }
                if (!numberInput || !numberInput.value.trim())           { showErr("Informe o número do endereço (ou S/N se não houver)."); return; }
                if (!neighborhoodInput || !neighborhoodInput.value.trim()){ showErr("Informe o bairro."); return; }
                if (!cityInput || !cityInput.value.trim())               { showErr("Informe a cidade."); return; }
                if (!stateInput || !stateInput.value.trim())             { showErr("Informe o estado (UF)."); return; }
              }
              needSaveProfile = true;
            }

            identError.style.display = "none";

            /* Salva no servidor (não bloqueia se falhar) */
            if (needSaveProfile) {
              try {
                var server2 = window.PW_SERVER_DATA || {};
                var f2 = new URLSearchParams();
                f2.append("action", "printway_dtf_save_user_profile");
                f2.append("nonce",  window.printway_dtf_nonce || server2.dtf_nonce || "");
                f2.append("name",   name);
                f2.append("whatsapp", digits);
                f2.append("email",  email);
                f2.append("cpf_cnpj",    cpfCnpjInput    ? cpfCnpjInput.value.replace(/\D/g,"")    : "");
                f2.append("cep",         cepInput        ? cepInput.value.replace(/\D/g,"")         : "");
                f2.append("street",      streetInput     ? streetInput.value.trim()                 : "");
                f2.append("number",      numberInput     ? numberInput.value.trim()                 : "");
                f2.append("complement",  complementInput ? complementInput.value.trim()              : "");
                f2.append("neighborhood",neighborhoodInput ? neighborhoodInput.value.trim()         : "");
                f2.append("city",        cityInput       ? cityInput.value.trim()                   : "");
                f2.append("state",       stateInput      ? stateInput.value.trim().toUpperCase()    : "");
                await fetch(server2.ajax_url || "/wp-admin/admin-ajax.php", {
                  method: "POST", credentials: "same-origin",
                  headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
                  body: f2.toString()
                });
              } catch(e) { /* não bloqueia */ }
            }

            persistSender({ name, whatsapp: formatWhatsapp(digits), email });
            setActiveStep(2);
          });

        /* ========================================================
       JÁ TENHO PDF
       ======================================================== */

        var cardPdf    = document.getElementById("choose-pdf");
        var cardManual = document.getElementById("choose-manual");

        function selectSourceCard(selected, other) {
          if (selected) selected.classList.add("is-selected");
          if (other)    other.classList.remove("is-selected");
        }

        cardPdf.addEventListener("click", function () {
            selectSourceCard(cardPdf, cardManual);

            pdfArea.style.display = "block";

            manualArea.style.display = "none";

            btnNext2.disabled = true;

            pdfStatus.textContent = "";

            document.getElementById("pdf-thumb-wrap").innerHTML = "";

            fileInput.value = "";

            fileInput.click();
          });

        /* ========================================================
       MANUAL
       ======================================================== */

        cardManual.addEventListener("click", function () {
            selectSourceCard(cardManual, cardPdf);

            manualArea.style.display = "block";

            pdfArea.style.display = "none";

            btnNext2.disabled = true;

            manualWarning.style.display = "none";

          });

        /* ========================================================
       PDF AUTOMÁTICO
       ======================================================== */

        fileInput.addEventListener("change", async function () {
          const file = fileInput.files && fileInput.files[0];

          if (!file) {
            return;
          }

          try {
            const dims = await validarPdfProducao(file);

            const calc = await calcularComAltura(dims.alturaCm, tipo.value);

            productionFile = file;

            lastHeightCm = dims.alturaCm;

            lastSource = "pdf";

            lastComputed = calc;

            resetPaymentState();

            pdfStatus.style.color = "#2b7a2b";

            pdfStatus.textContent =
              "PDF válido — Largura: " +
              formatCm(LARGURA_ESPERADA_CM) +
              " cm x Altura: " +
              formatCm(dims.alturaCm) +
              " cm — Total: R$ " +
              formatBR(calc.precoFinal);

            btnNext2.disabled = false;

            updateSummary();

            recordAbandonedCalculation();

            renderPdfThumbnail(file);
          } catch (error) {
            productionFile = null;

            lastComputed = null;

            lastHeightCm = null;

            lastSource = null;

            btnNext2.disabled = true;

            pdfStatus.style.color = "var(--danger)";

            pdfStatus.textContent = error.message || "Erro ao processar PDF.";
          }
        });

        /* ========================================================
       CÁLCULO MANUAL
       ======================================================== */

        function selecionarModoCalculo(modo) {
          const usarImagens = modo === "images";
          heightCalculator.style.display = usarImagens ? "none" : "grid";
          imagesCalculator.style.display = usarImagens ? "block" : "none";
          modeTotalHeight.classList.toggle("is-active", !usarImagens);
          modeTotalHeight.classList.toggle("ghost", usarImagens);
          modeImages.classList.toggle("is-active", usarImagens);
          modeImages.classList.toggle("ghost", !usarImagens);
          manualWarning.style.display = "none";
          btnNext2.disabled = true;
        }
        modeTotalHeight.addEventListener("click", function(){ selecionarModoCalculo("height"); });
        modeImages.addEventListener("click", function(){ selecionarModoCalculo("images"); });

        document
          .getElementById("btn-calc-manual")
          .addEventListener("click", async function () {
            const altura = normalizeNumberInput(alturaInput.value);

            if (!Number.isFinite(altura) || altura <= 0) {
              manualWarning.style.display = "block";

              manualWarning.style.color = "var(--danger)";

              manualWarning.textContent = "Digite uma altura válida.";

              return;
            }

            try {
              const calc = await calcularComAltura(altura, tipo.value);

              lastComputed = calc;

              lastHeightCm = altura;

              lastSource = "manual";

              productionFile = null;

              resetPaymentState();

              manualWarning.style.display = "block";

              manualWarning.style.color = "#2b7a2b";

              manualWarning.textContent =
                "Total: R$ " + formatBR(calc.precoFinal);

              btnNext2.disabled = false;

              updateSummary();
              recordAbandonedCalculation();
            } catch (error) {
              manualWarning.style.display = "block";

              manualWarning.style.color = "var(--danger)";

              manualWarning.textContent = error.message || "Erro ao calcular.";

              btnNext2.disabled = true;
            }
          });

        const appliedLayoutImprovements = new Set();
        let preserveAppliedImprovementsOnNextCalculation = false;
        let originalImageCalculatorState = null;
        const imageCalculatorHistory = [];

        function captureImageCalculatorState() {
          return {
            quantity: document.getElementById("pw-image-quantity").value,
            width: document.getElementById("pw-image-width").value,
            height: document.getElementById("pw-image-height").value,
            gap: document.getElementById("pw-image-gap").value,
            applied: Array.from(appliedLayoutImprovements),
          };
        }

        function restoreImageCalculatorState(state) {
          if (!state) return;
          document.getElementById("pw-image-quantity").value = state.quantity;
          document.getElementById("pw-image-width").value = state.width;
          document.getElementById("pw-image-height").value = state.height;
          document.getElementById("pw-image-gap").value = state.gap;
          appliedLayoutImprovements.clear();
          (state.applied || []).forEach(function (item) {
            appliedLayoutImprovements.add(item);
          });
        }

        function imageCalculatorStateMatches(a, b) {
          return !!a && !!b &&
            a.quantity === b.quantity &&
            a.width === b.width &&
            a.height === b.height &&
            a.gap === b.gap;
        }

        function updateImageToolbarState() {
          const undoButton = document.getElementById("pw-layout-undo");
          const resetButton = document.getElementById("pw-layout-reset");
          if (undoButton) undoButton.disabled = imageCalculatorHistory.length === 0;
          if (resetButton) {
            resetButton.disabled = !originalImageCalculatorState ||
              imageCalculatorStateMatches(
                captureImageCalculatorState(),
                originalImageCalculatorState,
              );
          }
        }

        function saveImageCalculatorHistory() {
          imageCalculatorHistory.push(captureImageCalculatorState());
          if (imageCalculatorHistory.length > 30) imageCalculatorHistory.shift();
        }

        function recalculateImagesPreservingAppliedImprovements() {
          preserveAppliedImprovementsOnNextCalculation = true;
          document.getElementById("btn-calc-images").click();
        }

        document.addEventListener("pw:image-toolbar-ready", updateImageToolbarState);
        document.addEventListener("pw:image-layout-command", function (event) {
          const command = event.detail && event.detail.command;
          if (command === "undo" && imageCalculatorHistory.length) {
            restoreImageCalculatorState(imageCalculatorHistory.pop());
            recalculateImagesPreservingAppliedImprovements();
          }
          if (command === "reset" && originalImageCalculatorState) {
            if (!imageCalculatorStateMatches(captureImageCalculatorState(), originalImageCalculatorState)) {
              saveImageCalculatorHistory();
            }
            restoreImageCalculatorState(originalImageCalculatorState);
            recalculateImagesPreservingAppliedImprovements();
          }
        });

        document.getElementById("btn-calc-images").addEventListener("click", async function () {
          const preservingInternalChanges = preserveAppliedImprovementsOnNextCalculation;
          if (!preservingInternalChanges) {
            appliedLayoutImprovements.clear();
          }
          preserveAppliedImprovementsOnNextCalculation = false;

          const larguraInput = document.getElementById("pw-image-width");
          const alturaInput = document.getElementById("pw-image-height");
          const espacamentoInput = document.getElementById("pw-image-gap");
          const orientacaoAviso = document.getElementById("pw-layout-guidance");
          const quantidadeInput = document.getElementById("pw-image-quantity");
          const quantidade = Number.parseInt(quantidadeInput.value, 10);
          const largura = normalizeNumberInput(larguraInput.value);
          const altura = normalizeNumberInput(alturaInput.value);
          const espacamento = normalizeNumberInput(espacamentoInput.value);
          if (!Number.isInteger(quantidade) || quantidade < 1 || quantidade > 1000) { manualWarning.style.display="block"; manualWarning.style.color="var(--danger)"; manualWarning.textContent="Informe uma quantidade entre 1 e 1.000 imagens."; return; }
          if (!Number.isFinite(largura) || largura < 0.1 || largura > LARGURA_ESPERADA_CM) { manualWarning.style.display="block"; manualWarning.style.color="var(--danger)"; manualWarning.textContent="A largura de cada imagem deve ficar entre 0,10 cm e 28,00 cm, que é a largura útil máxima da página."; return; }
          if (!Number.isFinite(altura) || altura < 0.1 || altura > 10000) { manualWarning.style.display="block"; manualWarning.style.color="var(--danger)"; manualWarning.textContent="A altura de cada imagem deve ficar entre 0,10 cm e 10.000,00 cm."; return; }
          if (!Number.isFinite(espacamento) || espacamento < 0.5 || espacamento > LARGURA_ESPERADA_CM) { manualWarning.style.display="block"; manualWarning.style.color="var(--danger)"; manualWarning.textContent="O espaço entre as imagens deve ficar entre 0,50 cm (5 mm) e 28,00 cm."; return; }
          if (!preservingInternalChanges) {
            originalImageCalculatorState = captureImageCalculatorState();
            imageCalculatorHistory.length = 0;
          }
          try {
            const disposicao=calcularDisposicaoImagens(quantidade,largura,altura,espacamento);
            const calc=await calcularComAltura(disposicao.alturaUsada,tipo.value);
            const melhorDisposicao = disposicao.melhor || disposicao;

            async function aplicarRotacaoDaPrevia(novaDisposicao) {
              try {
                saveImageCalculatorHistory();
                const larguraAnterior = larguraInput.value;
                larguraInput.value = alturaInput.value;
                alturaInput.value = larguraAnterior;
                const novoCalculo = await calcularComAltura(novaDisposicao.alturaUsada, tipo.value);
                lastComputed = novoCalculo;
                lastHeightCm = novaDisposicao.alturaUsada;
                lastSource = "images";
                productionFile = null;
                resetPaymentState();
                manualWarning.style.display = "block";
                manualWarning.style.color = "#2b7a2b";
                manualWarning.textContent =
                  "Largura ocupada: " + formatCm(novaDisposicao.larguraUsada) +
                  " cm (largura máxima da folha: " + formatCm(LARGURA_ESPERADA_CM) +
                  " cm) × Altura total: " + formatCm(novaDisposicao.alturaUsada) +
                  " cm — Total: R$ " + formatBR(novoCalculo.precoFinal);
                btnNext2.disabled = false;
                updateSummary();
                recordAbandonedCalculation();
                await atualizarOrientacaoAviso(novaDisposicao, novoCalculo);
                updateImageToolbarState();
              } catch (error) {
                manualWarning.style.display = "block";
                manualWarning.style.color = "var(--danger)";
                manualWarning.textContent = error.message || "Erro ao recalcular a orientação.";
                btnNext2.disabled = true;
              }
            }

            function renderizarDisposicaoAplicada(disposicaoAplicada, quantidadeAplicada, espacamentoAplicado) {
              const result = document.getElementById("pw-layout-result");
              if (result) result.classList.remove("is-temporary-preview");
              renderizarPreviaImagens(
                disposicaoAplicada || disposicao,
                Number.isFinite(quantidadeAplicada) ? quantidadeAplicada : quantidade,
                Number.isFinite(espacamentoAplicado) ? espacamentoAplicado : espacamento,
                aplicarRotacaoDaPrevia,
              );
            }

            async function atualizarOrientacaoAviso(disposicaoAtual, calculoAtual) {
              const melhorias = [];

              if (
                melhorDisposicao !== disposicaoAtual
              ) {
                try {
                  const calculoMelhor = await calcularComAltura(melhorDisposicao.alturaUsada, tipo.value);
                  if (calculoMelhor.precoFinal < calculoAtual.precoFinal - 0.004) {
                    melhorias.push(
                      {
                        id: "orientation",
                        title: "Orientação mais econômica",
                        description: "A posição girada ocupa menos altura e reduz o valor.",
                        width: melhorDisposicao.itemLargura,
                        height: melhorDisposicao.itemAltura,
                      },
                    );
                  }
                } catch (error) {
                  console.error("Não foi possível comparar o preço da orientação alternativa.", error);
                }
              }

              if (
                espacamento > 1
              ) {
                melhorias.push(
                  {
                    id: "spacing",
                    title: "Espaçamento recomendado",
                    description: "Retorna o espaço lateral para 0,50 cm; o espaço vertical permanece fixo em 0,50 cm.",
                    gap: 0.5,
                  },
                );
              }

              const larguraAtualDoCampo = normalizeNumberInput(larguraInput.value);
              const dicaLargura = Number.isFinite(larguraAtualDoCampo)
                ? calcularLarguraIdealProxima(
                    quantidade,
                    larguraAtualDoCampo,
                    espacamento,
                  )
                : null;
              const dicaPreencherLargura = Number.isFinite(larguraAtualDoCampo)
                ? calcularLarguraParaPreencherFolha(
                    quantidade,
                    larguraAtualDoCampo,
                    espacamento,
                    disposicaoAtual,
                  )
                : null;

              if (
                dicaLargura || dicaPreencherLargura
              ) {
                if (dicaLargura) {
                  melhorias.push({
                    id: "width-reduce",
                    title: "Mais imagens por linha",
                    description: "Reduz a largura para acomodar " + dicaLargura.novasColunas + " imagens por linha.",
                    width: dicaLargura.largura,
                  });
                }
                if (dicaPreencherLargura) {
                  melhorias.push({
                    id: "width-fill",
                    title: "Preencher toda a largura",
                    description: "Amplia igualmente as imagens, mantendo " + dicaPreencherLargura.colunas + " por linha.",
                    width: dicaPreencherLargura.largura,
                  });
                }
              }

              const espacamentoAtualDoCampo = normalizeNumberInput(
                espacamentoInput.value,
              );
              const dicaEspacamentoMaior =
                Number.isFinite(larguraAtualDoCampo) &&
                Number.isFinite(espacamentoAtualDoCampo)
                  ? calcularEspacamentoParaPreencherFolha(
                      quantidade,
                      larguraAtualDoCampo,
                      espacamentoAtualDoCampo,
                      disposicaoAtual,
                    )
                  : null;

              if (dicaEspacamentoMaior) {
                melhorias.push({
                  id: "gap-fill",
                  title: "Distribuir melhor na largura",
                  description: "Aumenta somente o espaço lateral, mantendo 0,50 cm entre as linhas e " +
                    dicaEspacamentoMaior.colunas +
                    " imagens por linha, sem alterar o tamanho delas.",
                  gap: dicaEspacamentoMaior.espacamento,
                });
              }

              const dicaQuantidade = calcularQuantidadeParaCompletarDisposicao(
                quantidade,
                disposicaoAtual,
              );

              if (
                dicaQuantidade
              ) {
                melhorias.push(
                  {
                    id: "quantity",
                    title: "Completar a última linha",
                    description: "Adiciona " + dicaQuantidade.adicionais + " imagem(ns) sem aumentar a altura atual.",
                    quantity: dicaQuantidade.quantidade,
                  },
                );
              }

              function renderAttributeIcon(field, applied) {
                if (applied) {
                  return '<svg class="pw-attribute-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.6 2.7L16.5 9"/></svg>';
                }

                const icons = {
                  quantity: '<svg class="pw-attribute-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="10" height="10" rx="1"/><path d="M9 17h8V9"/><path d="M12 20h8V12"/></svg>',
                  width: '<svg class="pw-attribute-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7v10M20 7v10M4 12h16"/><path d="m8 9-4 3 4 3M16 9l4 3-4 3"/></svg>',
                  height: '<svg class="pw-attribute-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4h10M7 20h10M12 4v16"/><path d="m9 8 3-4 3 4M9 16l3 4 3-4"/></svg>',
                  gap: '<svg class="pw-attribute-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="5" height="14" rx="1"/><rect x="16" y="5" width="5" height="14" rx="1"/><path d="M9.5 12h5"/><path d="m11 10-2 2 2 2M13 10l2 2-2 2"/></svg>',
                };

                return icons[field] || '';
              }

              function renderAttributeButton(rowId, field, value, suffix) {
                if (!Number.isFinite(value)) return '<span class="pw-attribute-empty">—</span>';
                const key = rowId + "-" + field;
                /*
                 * Completar a última linha deve voltar sempre habilitado caso
                 * uma nova disposição deixe espaços vazios. Os demais ajustes
                 * continuam marcados como aplicados até um novo cálculo manual.
                 */
                const applied = field !== "quantity" &&
                  appliedLayoutImprovements.has(key);
                const label = field === "quantity"
                  ? String(Math.round(value)) + " imagens"
                  : formatarMedidaParaCampo(value) + " " + suffix;
                return '<button class="pw-attribute-button" type="button" data-key="' + key + '" data-field="' + field + '" data-value="' + value + '"' + (applied ? ' disabled' : '') + '>' + renderAttributeIcon(field, applied) + '<span>' + (applied ? 'Aplicado' : label) + '</span></button>';
              }

              /*
               * Exibe somente as colunas que realmente possuem alguma dica.
               * Quantidade vem antes das medidas para manter a leitura natural.
               */
              const improvementColumns = [
                { field: "quantity", label: "Quantidade", suffix: "" },
                { field: "width", label: "Largura da imagem", suffix: "cm" },
                { field: "height", label: "Altura da imagem", suffix: "cm" },
                { field: "gap", label: "Espaço entre imagens", suffix: "cm" },
              ].filter(function (column) {
                return melhorias.some(function (item) {
                  return Number.isFinite(item[column.field]);
                });
              });

              /*
               * Algumas melhorias são alternativas para o mesmo objetivo.
               * Mantê-las no mesmo grupo deixa claro que o cliente deve
               * escolher apenas uma delas, sem impedir ajustes de outros grupos.
               */
              const improvementGroupById = {
                orientation: "orientation",
                spacing: "spacing",
                "width-reduce": "lateral-fit",
                "width-fill": "lateral-fit",
                "gap-fill": "lateral-fit",
                quantity: "quantity",
              };
              const improvementGroupLabels = {
                "lateral-fit": "Aproveitamento lateral — escolha uma opção",
              };
              const improvementGroupCounts = melhorias.reduce(function (counts, item) {
                const group = improvementGroupById[item.id] || item.id;
                counts[group] = (counts[group] || 0) + 1;
                return counts;
              }, {});

              orientacaoAviso.innerHTML = melhorias.length
                ? '<table class="pw-improvement-table"><thead><tr>'+
                    '<th>Melhoria</th>'+
                    improvementColumns.map(function (column) {
                      return '<th>' + column.label + '</th>';
                    }).join("")+
                  '</tr></thead><tbody>'+
                  melhorias.map(function (item, index) {
                    const group = improvementGroupById[item.id] || item.id;
                    const previousItem = melhorias[index - 1];
                    const nextItem = melhorias[index + 1];
                    const previousGroup = previousItem
                      ? (improvementGroupById[previousItem.id] || previousItem.id)
                      : "";
                    const nextGroup = nextItem
                      ? (improvementGroupById[nextItem.id] || nextItem.id)
                      : "";
                    const isGroupStart = group !== previousGroup;
                    const isGroupEnd = group !== nextGroup;
                    const isChoiceGroup = improvementGroupCounts[group] > 1;
                    const rowClasses = [
                      isGroupStart ? "pw-improvement-group-start" : "",
                      isGroupEnd ? "pw-improvement-group-end" : "",
                      isChoiceGroup ? "pw-improvement-choice" : "",
                    ].filter(Boolean).join(" ");
                    const choiceLabel = isChoiceGroup && isGroupStart
                      ? '<span class="pw-improvement-choice-label">' + improvementGroupLabels[group] + '</span>'
                      : "";
                    return '<tr class="' + rowClasses + '"><td>' + choiceLabel + '<span class="pw-improvement-title">' + item.title + '</span><span class="pw-improvement-description">' + item.description + '</span></td>'+
                      improvementColumns.map(function (column) {
                        return '<td>' + renderAttributeButton(item.id, column.field, item[column.field], column.suffix) + '</td>';
                      }).join("")+
                      '</tr>';
                  }).join("") + '</tbody></table>'
                : "";
              orientacaoAviso.classList.toggle(
                "has-improvements",
                melhorias.length > 0,
              );

              let temporaryPreviewSequence = 0;

              function restoreAppliedPreview(button) {
                if (button && button.dataset.previewCommitted === "true") return;
                temporaryPreviewSequence += 1;
                renderizarDisposicaoAplicada(disposicaoAtual, quantidade, espacamento);
                manualWarning.style.display = "block";
                manualWarning.style.color = "#2b7a2b";
                manualWarning.textContent =
                  "Largura ocupada: " + formatCm(disposicaoAtual.larguraUsada) +
                  " cm (largura máxima da folha: " + formatCm(LARGURA_ESPERADA_CM) +
                  " cm) × Altura total: " + formatCm(disposicaoAtual.alturaUsada) +
                  " cm — Total: R$ " + formatBR(calculoAtual.precoFinal);
              }

              async function showTemporaryImprovementPreview(button) {
                const field = button.dataset.field;
                const value = Number(button.dataset.value);
                if (!field || !Number.isFinite(value)) return;

                let temporaryQuantity = Number.parseInt(quantidadeInput.value, 10);
                let temporaryWidth = normalizeNumberInput(larguraInput.value);
                let temporaryHeight = normalizeNumberInput(alturaInput.value);
                let temporaryGap = normalizeNumberInput(espacamentoInput.value);

                if (field === "quantity") temporaryQuantity = Math.round(value);
                if (field === "width") temporaryWidth = value;
                if (field === "height") temporaryHeight = value;
                if (field === "gap") temporaryGap = value;

                try {
                  const sequence = ++temporaryPreviewSequence;
                  const temporaryDisposition = calcularDisposicaoImagens(
                    temporaryQuantity,
                    temporaryWidth,
                    temporaryHeight,
                    temporaryGap,
                  );
                  renderizarPreviaImagens(
                    temporaryDisposition,
                    temporaryQuantity,
                    temporaryGap,
                    null,
                  );
                  const result = document.getElementById("pw-layout-result");
                  if (result) result.classList.add("is-temporary-preview");

                  const temporaryCalculation = await calcularComAltura(
                    temporaryDisposition.alturaUsada,
                    tipo.value,
                  );
                  if (sequence !== temporaryPreviewSequence) return;
                  manualWarning.style.display = "block";
                  manualWarning.style.color = "#9a6200";
                  manualWarning.textContent =
                    "Prévia temporária — Largura ocupada: " +
                    formatCm(temporaryDisposition.larguraUsada) +
                    " cm × Altura total: " +
                    formatCm(temporaryDisposition.alturaUsada) +
                    " cm — Total: R$ " +
                    formatBR(temporaryCalculation.precoFinal) +
                    ". Clique para aplicar.";
                } catch (error) {
                  console.error("Não foi possível exibir a prévia temporária.", error);
                }
              }

              orientacaoAviso.querySelectorAll(".pw-attribute-button:not(:disabled)").forEach(function (button) {
                button.addEventListener("mouseenter", function () {
                  showTemporaryImprovementPreview(button);
                });
                button.addEventListener("mouseleave", function () {
                  restoreAppliedPreview(button);
                });
                button.addEventListener("focus", function () {
                  showTemporaryImprovementPreview(button);
                });
                button.addEventListener("blur", function () {
                  restoreAppliedPreview(button);
                });
                button.addEventListener("click", function () {
                  const field = button.dataset.field;
                  const value = Number(button.dataset.value);
                  if (!field || !Number.isFinite(value)) return;
                  button.dataset.previewCommitted = "true";
                  temporaryPreviewSequence += 1;
                  saveImageCalculatorHistory();
                  appliedLayoutImprovements.add(button.dataset.key);
                  if (field === "width") larguraInput.value = String(value);
                  if (field === "height") alturaInput.value = String(value);
                  if (field === "gap") espacamentoInput.value = String(value);
                  if (field === "quantity") quantidadeInput.value = String(Math.round(value));
                  button.disabled = true;
                  button.innerHTML = renderAttributeIcon(field, true) + '<span>Aplicado</span>';
                  recalculateImagesPreservingAppliedImprovements();
                });
              });
              updateImageToolbarState();
            }

            lastComputed=calc; lastHeightCm=disposicao.alturaUsada; lastSource="images"; productionFile=null; resetPaymentState();
            renderizarDisposicaoAplicada(disposicao, quantidade, espacamento);
            manualWarning.style.display="block"; manualWarning.style.color="#2b7a2b";
            manualWarning.textContent="Largura ocupada: "+formatCm(disposicao.larguraUsada)+" cm (largura máxima da folha: "+formatCm(LARGURA_ESPERADA_CM)+" cm) × Altura total: "+formatCm(disposicao.alturaUsada)+" cm — Total: R$ "+formatBR(calc.precoFinal);
            btnNext2.disabled=false; updateSummary(); recordAbandonedCalculation();
            await atualizarOrientacaoAviso(disposicao, calc);
          } catch(error) { manualWarning.style.display="block"; manualWarning.style.color="var(--danger)"; manualWarning.textContent=error.message||"Erro ao calcular a disposição."; btnNext2.disabled=true; }
        });

        (function configurarLimitesDaCalculadoraDeImagens() {
          const larguraInput = document.getElementById("pw-image-width");
          const alturaInput = document.getElementById("pw-image-height");
          const espacamentoInput = document.getElementById("pw-image-gap");
          const calcularButton = document.getElementById("btn-calc-images");
          const resultado = document.getElementById("pw-layout-result");

          function limitarValor(input, minimo, maximo, nome) {
            input.addEventListener("input", function () {
              const valor = normalizeNumberInput(input.value);
              if (Number.isFinite(valor) && valor > maximo) {
                input.value = "";
                resultado.style.display = "none";
                lastComputed = null;
                lastHeightCm = 0;
                manualWarning.style.display = "block";
                manualWarning.style.color = "var(--danger)";
                manualWarning.textContent = nome + " não pode ultrapassar " + formatCm(maximo) + " cm.";
                btnNext2.disabled = true;
              }
            });
            input.addEventListener("change", function () {
              const valor = normalizeNumberInput(input.value);
              if (!Number.isFinite(valor)) return;
              if (valor < minimo || valor > maximo) {
                input.value = "";
                resultado.style.display = "none";
                lastComputed = null;
                lastHeightCm = 0;
                manualWarning.style.display = "block";
                manualWarning.style.color = "var(--danger)";
                manualWarning.textContent = nome + " deve ficar entre " + formatCm(minimo) + " cm e " + formatCm(maximo) + " cm.";
                btnNext2.disabled = true;
              }
            });
          }

          limitarValor(larguraInput, 0.5, LARGURA_ESPERADA_CM, "A largura de cada imagem");
          limitarValor(alturaInput, 0.5, 10000, "A altura de cada imagem");
          limitarValor(espacamentoInput, 0.5, LARGURA_ESPERADA_CM, "O espaço lateral entre as imagens");

          espacamentoInput.addEventListener("change", function () {
            if (resultado.style.display === "grid" && espacamentoInput.value.trim()) {
              recalculateImagesPreservingAppliedImprovements();
            }
          });

          [larguraInput, alturaInput, espacamentoInput].forEach(function (input) {
            input.addEventListener("keydown", function (event) {
              if (event.key === "Enter") {
                event.preventDefault();
                calcularButton.click();
              }
            });
          });
        })();

        /* ========================================================
       ALTURA DA FOLHA — melhorOrientacaoParaAltura + btn-calc-size
       ======================================================== */

        function melhorOrientacaoParaAltura(largura, altura, espacamentoH, alturaDesejada) {
          return window.DTF.bestOrientationForHeight(largura, altura, espacamentoH, alturaDesejada, LARGURA_ESPERADA_CM, ESPACAMENTO_VERTICAL_PADRAO_CM);
        }

        function renderizarPreviaTamanho(best) {
          var sizeResult = document.getElementById("pw-size-result");
          if (sizeResult) {
            sizeResult.innerHTML =
              '<div class="pw-layout-info-card is-primary">' +
                '<span class="pw-layout-info-label">Medida da folha</span>' +
                '<div class="pw-layout-info-value"><strong>' + formatCm(best.sheetWidth) + ' cm × ' + formatCm(best.heightCm) + ' cm</strong></div>' +
              '</div>' +
              '<div class="pw-layout-info-card">' +
                '<span class="pw-layout-info-label">Dimensão do adesivo</span>' +
                '<div class="pw-layout-info-value"><strong>' + formatCm(best.itemW) + ' cm × ' + formatCm(best.itemH) + ' cm' + (best.rotated ? ' (girada 90°)' : '') + '</strong></div>' +
              '</div>' +
              '<div class="pw-layout-info-card">' +
                '<span class="pw-layout-info-label">Adesivos coluna × linhas</span>' +
                '<div class="pw-layout-info-value"><strong>' + best.columns + ' × ' + best.rows + '</strong></div>' +
              '</div>' +
              '<div class="pw-layout-info-card">' +
                '<span class="pw-layout-info-label">Total de adesivos</span>' +
                '<div class="pw-layout-info-value"><strong>' + best.totalFit + ' adesivo(s)</strong></div>' +
              '</div>';
            sizeResult.style.display = "flex";
          }
          window.DTF.renderLayoutPreview("pw-size-layout-preview", best);
        }

        (function () {
          var btnCalcSize = document.getElementById("btn-calc-size");
          if (!btnCalcSize) return;

          async function executarCalcSize() {
            var largura = normalizeNumberInput(document.getElementById("pw-size-width").value);
            var altura = normalizeNumberInput(document.getElementById("pw-size-height").value);
            var alturaDesejada = normalizeNumberInput(document.getElementById("pw-size-length").value);
            var espacamento = normalizeNumberInput(document.getElementById("pw-size-gap").value);
            var guidance = document.getElementById("pw-size-guidance");

            var sizeResult = document.getElementById("pw-size-result");
            var sizePreview = document.getElementById("pw-size-layout-preview");
            function erroCalcSize(msg) {
              manualWarning.style.display = "block"; manualWarning.style.color = "var(--danger)";
              manualWarning.textContent = msg;
              if (guidance) guidance.textContent = "";
              if (sizeResult) sizeResult.style.display = "none";
              if (sizePreview) sizePreview.hidden = true;
            }
            if (!Number.isFinite(largura) || largura < 0.1 || largura > LARGURA_ESPERADA_CM) {
              erroCalcSize("A largura do adesivo deve ficar entre 0,10 cm e " + formatCm(LARGURA_ESPERADA_CM) + " cm."); return;
            }
            if (!Number.isFinite(altura) || altura < 0.1 || altura > 10000) {
              erroCalcSize("A altura do adesivo deve ficar entre 0,10 cm e 10.000,00 cm."); return;
            }
            if (!Number.isFinite(alturaDesejada) || alturaDesejada <= 0) {
              erroCalcSize("Informe a altura de folha desejada."); return;
            }
            var espacamentoVal = Number.isFinite(espacamento) && espacamento >= 0.5 ? espacamento : 0.5;

            try {
              var best = melhorOrientacaoParaAltura(largura, altura, espacamentoVal, alturaDesejada);
              if (!best) throw new Error("Nenhum adesivo inteiro cabe nessa altura de folha.");

              var calc = await calcularComAltura(best.heightCm, tipo.value);
              lastComputed = calc;
              lastHeightCm = best.heightCm;
              lastSource = "size";
              productionFile = null;
              resetPaymentState();

              if (guidance) guidance.textContent = "";
              renderizarPreviaTamanho(best);

              manualWarning.style.display = "block";
              manualWarning.style.color = "#2b7a2b";
              manualWarning.textContent = "Largura " + formatCm(LARGURA_ESPERADA_CM) + " cm × Altura " + formatCm(best.heightCm) + " cm — Total: R$ " + formatBR(calc.precoFinal);
              btnNext2.disabled = false;
              updateSummary();
              recordAbandonedCalculation();
            } catch (error) {
              manualWarning.style.display = "block";
              manualWarning.style.color = "var(--danger)";
              manualWarning.textContent = error.message || "Erro ao calcular.";
              if (guidance) guidance.textContent = "";
              if (sizeResult) sizeResult.style.display = "none";
              if (sizePreview) sizePreview.hidden = true;
              btnNext2.disabled = true;
            }
          }

          btnCalcSize.addEventListener("click", executarCalcSize);

          ["pw-size-width", "pw-size-height", "pw-size-length", "pw-size-gap"].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener("keydown", function(e) { if (e.key === "Enter") { e.preventDefault(); btnCalcSize.click(); } });
          });
        })();

        /* ========================================================
       TIPO CLIENTE
       ======================================================== */

        tipo.addEventListener("change", async function () {
          const imageCalculator = document.getElementById(
            "pw-images-calculator",
          );
          const imageCalculateButton = document.getElementById(
            "btn-calc-images",
          );
          const sizeCalculator = document.getElementById("pw-size-calculator");
          const sizeCalculateButton = document.getElementById("btn-calc-size");

          const imageCalculatorIsVisible =
            imageCalculator &&
            window.getComputedStyle(imageCalculator).display !== "none";

          const sizeCalculatorIsVisible =
            sizeCalculator &&
            window.getComputedStyle(sizeCalculator).display !== "none";

          /*
           * A calculadora por quantidade possui seu próprio fluxo completo:
           * disposição, medidas, preço, dicas e prévia. Quando ela já tiver
           * sido calculada, reutilizamos o mesmo botão para não deixar nenhum
           * desses dados com o tipo de cliente anterior.
           */
          if (
            imageCalculatorIsVisible &&
            lastSource === "images" &&
            imageCalculateButton
          ) {
            recalculateImagesPreservingAppliedImprovements();
            return;
          }

          /*
           * A calculadora de altura de folha também precisa recalcular com o
           * novo tipo de cliente, mantendo os campos preenchidos.
           */
          if (
            sizeCalculatorIsVisible &&
            lastSource === "size" &&
            sizeCalculateButton
          ) {
            sizeCalculateButton.click();
            return;
          }

          if (!lastHeightCm || !lastComputed) {
            return;
          }

          try {
            lastComputed = await calcularComAltura(lastHeightCm, tipo.value);

            resetPaymentState();

            updateSummary();
            recordAbandonedCalculation();

            if (lastSource === "manual" || lastSource === "images" || lastSource === "size") {
              manualWarning.style.display = "block";

              manualWarning.style.color = "#2b7a2b";

              manualWarning.textContent =
                "Total: R$ " + formatBR(lastComputed.precoFinal);
            } else {
              pdfStatus.style.color = "#2b7a2b";

              pdfStatus.textContent =
                "PDF válido — Largura: " +
                formatCm(LARGURA_ESPERADA_CM) +
                " cm x Altura: " +
                formatCm(lastHeightCm) +
                " cm — Total: R$ " +
                formatBR(lastComputed.precoFinal);
            }
          } catch (error) {
            console.error(error);
          }
        });

        /* ========================================================
       VOLTAR ETAPA 2
       ======================================================== */

        document
          .getElementById("btn-back-2")
          .addEventListener("click", function () {
            setActiveStep(1);
          });

        /* ========================================================
       AVANÇAR ETAPA 2
       ======================================================== */

        btnNext2.addEventListener("click", function () {
          if (!lastComputed) {
            return;
          }

          const calculationNeedsProductionPdf =
            lastSource === "manual" || lastSource === "images" || lastSource === "size";

          if (calculationNeedsProductionPdf && !productionFile) {
            const selectNow = window.confirm(
              "A calculadora fornece apenas uma estimativa. Para continuar, é necessário selecionar o PDF final que será produzido e cobrado.\n\nDeseja selecionar o PDF agora?",
            );

            if (selectNow) {
              document.getElementById("choose-pdf").click();
            }

            return;
          }

          setActiveStep(3);

          updateSummary();
        });

        /* ========================================================
       GERAR QR
       ======================================================== */

        observeQrContainer();

        btnGenerateQr.addEventListener("click", function () {
          if (!lastComputed) {
            return;
          }

          const sequence = ++qrGenerationSequence;

          proofValidationSequence++;

          QR_GENERATED = false;

          PROOF_VALIDATED = false;

          proofFile = null;

          LAST_PIX_COPY_VALUE = "";

          proofInput.value = "";

          copyArea.style.display = "none";

          copyStatus.textContent = "";

          proofArea.style.display = "none";

          proofNotice.style.display = "none";

          btnNext3.disabled = true;

          paymentMsg.textContent = "";

          qrContainer.innerHTML = "";

          btnGenerateQr.disabled = true;

          btnGenerateQr.textContent = "Gerando QR...";

          setTimeout(async function () {
            if (sequence !== qrGenerationSequence) {
              return;
            }

            if (QR_GENERATED) {
              btnGenerateQr.disabled = false;

              btnGenerateQr.textContent = "Gerar QR";

              return;
            }

            try {
              const paymentData = await preparePaymentSession();

              if (
                sequence !== qrGenerationSequence ||
                paymentData.payment_method !== "pix"
              ) {
                throw new Error("Este pagamento não precisa gerar um QR Pix.");
              }

              const form = new URLSearchParams();

              form.append("action", "printway_pix_generate_payload");

              if (window.printway_pix_nonce) {
                form.append("nonce", window.printway_pix_nonce);
              }

              form.append("payment_session", PAYMENT_SESSION_ID);
              form.append("amount", String(paymentData.amount));

              const response = await fetch("/wp-admin/admin-ajax.php", {
                method: "POST",

                headers: {
                  "Content-Type":
                    "application/x-www-form-urlencoded; charset=UTF-8",
                },

                body: form.toString(),
              });

              const json = await response.json();

              if (sequence !== qrGenerationSequence) {
                return;
              }

              let payload = json.payload || (json.data && json.data.payload);

              if (!json.success || !payload) {
                throw new Error(
                  (json.errors && json.errors.join(" ")) ||
                    "Não foi possível gerar o QR Pix.",
                );
              }

              payload = ensureValidPixCrc(payload);

              /*
               * Guarda o código Pix exatamente como
               * veio do plugin.
               */

              LAST_PIX_COPY_VALUE = payload;

              ensureQRCodeLib(function () {
                qrContainer.innerHTML = "";

                new QRCode(qrContainer, payload);

                markQrGenerated(
                  "QR gerado. Efetue o pagamento e envie o comprovante.",
                );
              });
            } catch (error) {
              paymentMsg.style.color = "var(--danger)";

              paymentMsg.textContent = error.message || "Erro ao gerar QR.";
            } finally {
              btnGenerateQr.disabled = false;

              btnGenerateQr.textContent = "Gerar QR";
            }
          }, 0);
        });

        /* ========================================================
       COPIAR PIX
       ======================================================== */

        document
          .getElementById("btn-copy-pix")
          .addEventListener("click", async function () {
            copyStatus.textContent = "";

            let value = getPixCopyValue();

            if (!value) {
              /*
               * Às vezes o plugin adiciona o title um
               * pouco depois da imagem.
               */

              await new Promise((resolve) => setTimeout(resolve, 150));

              value = getPixCopyValue();
            }

            if (!value) {
              copyStatus.style.color = "var(--danger)";

              copyStatus.textContent =
                "Não foi possível localizar a chave Pix.";

              return;
            }

            try {
              await copyTextToClipboard(value);

              copyStatus.style.color = "#2b7a2b";

              copyStatus.textContent = "Chave Pix copiada!";
            } catch (error) {
              console.error(error);

              copyStatus.style.color = "var(--danger)";

              copyStatus.textContent =
                "Não foi possível copiar automaticamente.";
            }
          });

        /* ========================================================
       ENVIAR COMPROVANTE
       ======================================================== */

        document
          .getElementById("btn-proof-upload")
          .addEventListener("click", function () {
            if (!QR_GENERATED) {
              proofStatus.className = "pw-proof-status error";

              proofStatus.textContent =
                "Gere o QR antes de enviar o comprovante.";

              return;
            }

            proofInput.value = "";

            proofInput.click();
          });

        /* ========================================================
       VALIDAR COMPROVANTE
       ======================================================== */

        proofInput.addEventListener("change", async function () {
          const file = proofInput.files && proofInput.files[0];

          if (!file) {
            return;
          }

          const sequence = ++proofValidationSequence;

          const button = document.getElementById("btn-proof-upload");

          PROOF_VALIDATED = false;

          setPaymentControlsLocked(false);

          proofFile = null;

          btnNext3.disabled = true;

          proofNotice.style.display = "none";

          button.disabled = true;

          proofStatus.className = "pw-proof-status wait";

          proofStatus.textContent = "Analisando comprovante...";

          try {
            const result = await validateProofFile(
              file,
              getPayableAmount(),
            );

            if (sequence !== proofValidationSequence) {
              return;
            }

            proofFile = file;

            PROOF_VALIDATED = true;

            setPaymentControlsLocked(true);

            proofStatus.className = "pw-proof-status ok";

            if (result.contentChecked) {
              const receiptDate = result.receiptDate || "data não identificada";
              const receiptTime = result.receiptTime || "horário não identificado";

              proofStatus.textContent =
                "Comprovante Pix validado no valor de R$ " +
                formatBR(getPayableAmount()) +
                " na data de " +
                receiptDate +
                " às " +
                receiptTime +
                ".";
            }

            proofNotice.style.display = "block";

            recordAbandonedCalculation();

            if (!getSelectedDeliveryMethod()) {
              paymentMsg.style.color = "#666";
              paymentMsg.textContent =
                "Comprovante validado. Agora selecione como deseja receber o pedido.";
            }

            refreshAdvanceAvailability();
          } catch (error) {
            if (sequence !== proofValidationSequence) {
              return;
            }

            proofFile = null;

            PROOF_VALIDATED = false;

            setPaymentControlsLocked(false);

            proofStatus.className = "pw-proof-status error";

            proofStatus.textContent =
              error.message || "O comprovante não pôde ser validado.";

            proofNotice.style.display = "none";

            refreshAdvanceAvailability();
          } finally {
            button.disabled = false;
          }
        });

        /* ========================================================
       VOLTAR ETAPA 3
       ======================================================== */

        document
          .getElementById("btn-back-3")
          .addEventListener("click", function () {
            setActiveStep(2);
          });

        /* ========================================================
       AVANÇAR ETAPA 3
       ======================================================== */

        btnNext3.addEventListener("click", function () {
          if (!getSelectedDeliveryMethod()) {
            paymentMsg.style.color = "var(--danger)";
            paymentMsg.textContent =
              "Selecione como deseja receber o pedido antes de continuar.";
            return;
          }

          if (PAYMENT_METHOD === "pix" && !QR_GENERATED) {
            paymentMsg.style.color = "var(--danger)";

            paymentMsg.textContent = "Gere o QR antes de continuar.";

            return;
          }

          if (PAYMENT_METHOD === "pix" && !PROOF_VALIDATED) {
            paymentMsg.style.color = "var(--danger)";

            paymentMsg.textContent =
              "Envie um comprovante válido antes de continuar.";

            return;
          }

          if (
            PAYMENT_METHOD === "alternative" &&
            (!PAY_LATER_VALIDATED || !paymentOption.value)
          ) {
            paymentMsg.style.color = "var(--danger)";

            paymentMsg.textContent = "Valide o código de Pagar depois antes de continuar.";

            return;
          }

          if (!productionFile) {
            paymentMsg.style.color = "var(--danger)";

            paymentMsg.textContent =
              "Nenhum PDF válido foi selecionado para produção.";

            return;
          }

          setActiveStep(4);

          updateFinalPdfLink();

          sendStatus.textContent = "";
        });

        /* ========================================================
       VOLTAR ETAPA 4
       ======================================================== */

        document
          .getElementById("btn-back-4")
          .addEventListener("click", function () {
            setActiveStep(3);
          });

        /* ========================================================
       FINALIZAR
       ======================================================== */

        btnFinish.addEventListener("click", async function () {
          if (finalSendComplete) {
            return;
          }

          if (
            !productionFile ||
            !getSelectedDeliveryMethod() ||
            (PAYMENT_METHOD === "pix" && (!proofFile || !PROOF_VALIDATED)) ||
            (PAYMENT_METHOD === "alternative" && !PAY_LATER_VALIDATED)
          ) {
            sendStatus.style.color = "var(--danger)";

            sendStatus.textContent = "O pedido não está completo para envio.";

            return;
          }

          await recordAbandonedCalculation();

          startSendProgress();

          btnFinish.disabled = true;

          btnFinish.textContent = "Enviando...";

          sendStatus.style.color = "#666";

          sendStatus.textContent =
            "Enviando dados, PDF e comprovante para a gráfica...";

          try {
            /*
             * 1) Tenta usar uma configuração PHP de envio
             * já exposta pelo site.
             */

            const directResult = await sendWithDiscoveredBackend();

            if (directResult) {
              setSendProgress(92, "Confirmando o envio…");
              markProductionSent();

              return;
            }

            /*
             * 2) Caso o backend não esteja explicitamente
             * configurado no JS, reutiliza exatamente o
             * botão/formulário antigo.
             */

            prepareLegacySend();

            setSendProgress(55, "Enviando arquivos para a gráfica…");

            const oldButton = document.getElementById("pw-sender-send");

            if (!oldButton) {
              throw new Error(
                "O mecanismo antigo de envio não foi encontrado.",
              );
            }

            let completed = false;

            const cleanup = installLegacyNetworkMonitor(
              function () {
                if (completed) {
                  return;
                }

                completed = true;

                markProductionSent();
              },

              function (error) {
                console.error(error);
              },
            );

            /*
             * Aciona exatamente o botão utilizado
             * pelo fluxo antigo, sem mostrar o formulário.
             */

            oldButton.click();

            /*
             * O PHP/JS antigo também pode chamar
             * window._pw_markSent().
             */

            setTimeout(function () {
              cleanup();

              if (finalSendComplete) {
                return;
              }

              btnFinish.disabled = false;

              stopSendProgress(false);

              btnFinish.textContent = "Finalizar";

              sendStatus.style.color = "var(--danger)";

              sendStatus.textContent =
                "Não foi localizada uma requisição de envio do PDF no mecanismo antigo. Neste caso, preciso do código PHP que fazia o envio para ligar o botão Finalizar diretamente a ele.";
            }, 15000);
          } catch (error) {
            console.error(error);

            btnFinish.disabled = false;

            stopSendProgress(false);

            btnFinish.textContent = "Finalizar";

            sendStatus.style.color = "var(--danger)";

            sendStatus.textContent =
              error.message || "Erro ao enviar a solicitação.";
          }
        });
      });