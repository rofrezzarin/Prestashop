<?php
/**
 * Módulo: PrintWay DTF UV — Calculadora (front-end)
 *
 * Antes esse HTML/CSS/JS inteiro (quase 200 KB, tudo numa linha só) era
 * colado direto num widget HTML do Elementor — isso deixava o editor do
 * Elementor lento e travando, porque ele precisa processar o bloco inteiro
 * toda vez que a página é editada. Aqui o CSS e o JS viram arquivos
 * próprios (carregados normalmente pelo navegador, com cache), e a página
 * só precisa do shortcode [printway_dtf_uv] no lugar do bloco de HTML.
 *
 * @package PrintWay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'PW_DTF_UV_MODULE_LOADED' ) ) {
	return;
}
define( 'PW_DTF_UV_MODULE_LOADED', true );

define( 'PW_DTF_UV_VERSION', '1.2.0' );
define( 'PW_DTF_UV_DIR', plugin_dir_path( __FILE__ ) );
define( 'PW_DTF_UV_URL', plugin_dir_url( __FILE__ ) );
define( 'PW_DTF_UV_SHORTCODE', 'printway_dtf_uv' );

/**
 * filemtime() do próprio arquivo como versão do asset — assim o navegador
 * baixa a versão nova sozinho a cada atualização por FTP, sem precisar
 * mudar um número de versão à mão.
 */
function pw_dtf_uv_asset_version( $relative_path ) {
	$path = PW_DTF_UV_DIR . ltrim( $relative_path, '/\\' );
	return file_exists( $path ) ? (string) filemtime( $path ) : PW_DTF_UV_VERSION;
}

/**
 * O CSS/JS só é carregado nas páginas que realmente usam o shortcode —
 * mesmo critério já usado pelo módulo de pedidos (has_shortcode no
 * conteúdo do post), pra não pesar o site inteiro à toa.
 */
function pw_dtf_uv_maybe_enqueue_assets() {
	global $post;
	if ( $post instanceof WP_Post && has_shortcode( (string) $post->post_content, PW_DTF_UV_SHORTCODE ) ) {
		pw_dtf_uv_enqueue_assets();
	}
}
add_action( 'wp_enqueue_scripts', 'pw_dtf_uv_maybe_enqueue_assets', 20 );

function pw_dtf_uv_enqueue_assets() {
	wp_enqueue_style(
		'pw-dtf-uv',
		PW_DTF_UV_URL . 'assets/dtf-uv.css',
		array(),
		pw_dtf_uv_asset_version( 'assets/dtf-uv.css' )
	);

	// Bibliotecas externas que a calculadora usa (leitura de PDF e OCR do
	// comprovante) — as mesmas já usadas quando o bloco vinha colado direto
	// no Elementor, só que agora carregadas como dependências de verdade.
	wp_register_script( 'pw-dtf-uv-pdfjs', 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.14.305/pdf.min.js', array(), '2.14.305', true );
	wp_register_script( 'pw-dtf-uv-tesseractjs', 'https://cdn.jsdelivr.net/npm/tesseract.js@6.0.1/dist/tesseract.min.js', array(), '6.0.1', true );

	wp_enqueue_script(
		'pw-dtf-uv',
		PW_DTF_UV_URL . 'assets/dtf-uv.js',
		array( 'pw-dtf-uv-pdfjs', 'pw-dtf-uv-tesseractjs' ),
		pw_dtf_uv_asset_version( 'assets/dtf-uv.js' ),
		true
	);
	// window.PW_SERVER_DATA (tabela de preços, nonce, dados do usuário
	// logado etc.) já é publicado em toda página pelo módulo "email"
	// (pw_dtf_expose_ajax_config, em wp_head/wp_footer) — não precisa
	// duplicar aqui.
}

/**
 * Shortcode [printway_dtf_uv] — imprime só a marcação (o HTML puro da
 * calculadora); CSS e JS chegam pelos arquivos enfileirados acima.
 */
function pw_dtf_uv_shortcode( $atts = array() ) {
	if ( '1' === get_option( 'pw_dtf_maintenance_mode' ) && ! current_user_can( 'manage_options' ) ) {
		$until = (int) get_option( 'pw_dtf_maintenance_until', 0 );

		/* Auto-desativação: se a previsão já passou, desliga manutenção. */
		if ( $until > 0 && time() >= $until ) {
			update_option( 'pw_dtf_maintenance_mode', '0', false );
			update_option( 'pw_dtf_maintenance_until', 0, false );
			/* Continua e mostra a calculadora normalmente. */
		} else {
			/* Monta o bloco de previsão + countdown (só se houver data). */
			$eta_block = '';
			if ( $until > 0 ) {
				$until_ms    = $until * 1000;
				$until_label = wp_date( 'd/m/Y \à\s H:i', $until );
				$eta_block =
					'<div id="pw-maint-eta" data-until="' . esc_attr( (string) $until_ms ) . '" style="margin:20px 0 0;padding:16px 22px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;max-width:420px;width:100%">' .
					'<p style="margin:0 0 6px;font-size:13px;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Previsão de retorno</p>' .
					'<p style="margin:0 0 4px;font-size:18px;color:#1e293b;font-weight:700">' . esc_html( $until_label ) . '</p>' .
					'<p id="pw-maint-remaining" style="margin:6px 0 0;font-size:14px;color:#f59e0b;font-weight:600">Calculando tempo restante…</p>' .
					'<p style="margin:8px 0 0;font-size:11px;color:#94a3b8;font-style:italic">⚠ Esta é uma previsão. O serviço poderá retornar antes ou depois desse horário.</p>' .
					'</div>' .
					'<script>(function(){' .
					'var eta=document.getElementById("pw-maint-eta");' .
					'if(!eta)return;' .
					'var until=parseInt(eta.getAttribute("data-until"),10);' .
					'if(!until)return;' .
					'var rem=document.getElementById("pw-maint-remaining");' .
					'function fmt(ms){' .
					'if(ms<=0){return "Recarregando a calculadora…";}' .
					'var s=Math.floor(ms/1000),m=Math.floor(s/60),h=Math.floor(m/60),d=Math.floor(h/24);' .
					's%=60;m%=60;h%=24;' .
					'var p=[];' .
					'if(d>0)p.push(d+(d===1?" dia":" dias"));' .
					'if(h>0)p.push(h+(h===1?" hora":" horas"));' .
					'if(m>0||h>0||d>0)p.push(m+(m===1?" minuto":" minutos"));' .
					'p.push(s+(s===1?" segundo":" segundos"));' .
					'return "Faltam: "+p.join(", ");' .
					'}' .
					'function tick(){' .
					'var diff=until-Date.now();' .
					'if(rem)rem.textContent=fmt(diff);' .
					'if(diff<=0){setTimeout(function(){location.reload();},2000);return;}' .
					'setTimeout(tick,1000);' .
					'}' .
					'tick();' .
					'})();</script>';
			}

			return
				'<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:320px;padding:48px 24px;text-align:center;background:#f8fafc;border-radius:16px;border:1px solid #e2e8f0;margin:24px 0">' .
				'<div style="font-size:52px;margin:0 0 18px" role="img" aria-label="Em manutenção">🔧</div>' .
				'<h2 style="margin:0 0 10px;font-size:22px;color:#1e293b;font-weight:700">Estamos em manutenção</h2>' .
				'<p style="margin:0 0 8px;color:#64748b;font-size:15px;max-width:420px;line-height:1.6">A calculadora DTF UV está temporariamente indisponível enquanto realizamos melhorias.</p>' .
				$eta_block .
				'<p style="margin:' . ( $until > 0 ? '16px' : '16px' ) . ' 0 0;color:#94a3b8;font-size:13px">Em caso de dúvidas, entre em contato conosco pelo WhatsApp.</p>' .
				'</div>';
		}
	}

	$markup_path = PW_DTF_UV_DIR . 'templates/dtf-uv-markup.php';
	if ( ! is_file( $markup_path ) ) {
		return '<p>Calculadora DTF UV indisponível no momento.</p>';
	}
	ob_start();
	include $markup_path;
	return ob_get_clean();
}
add_shortcode( PW_DTF_UV_SHORTCODE, 'pw_dtf_uv_shortcode' );
