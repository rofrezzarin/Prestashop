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

define( 'PW_DTF_UV_VERSION', '1.0.0' );
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
	$markup_path = PW_DTF_UV_DIR . 'templates/dtf-uv-markup.php';
	if ( ! is_file( $markup_path ) ) {
		return '<p>Calculadora DTF UV indisponível no momento.</p>';
	}
	ob_start();
	include $markup_path;
	return ob_get_clean();
}
add_shortcode( PW_DTF_UV_SHORTCODE, 'pw_dtf_uv_shortcode' );
