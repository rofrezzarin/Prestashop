<?php
/**
 * Plugin Name: PrintWay
 * Description: Carregador principal dos módulos PrintWay: DTF UV, Pedidos, Backup, Pix, Editor de imagens e Mercado Livre.
 * Version: 2.2.79
 * Author: PrintWay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Carrega o Editor de imagens antes da proteção do núcleo. Isso garante que
 * o shortcode continue registrado mesmo quando uma cópia antiga do PrintWay
 * já tiver definido PW_PRINTWAY_BOOTSTRAPPED durante uma atualização.
 */
$pw_printway_embedded_editor = plugin_dir_path( __FILE__ ) . 'editor-de-imagens/dtf-uv-editor.php';
if ( ! function_exists( 'dtf_uv_editor_shortcode' ) && file_exists( $pw_printway_embedded_editor ) ) {
	require_once $pw_printway_embedded_editor;
}

/* Evita erro fatal se uma instalação antiga do pacote ainda carregar este
 * núcleo por outro caminho durante uma atualização. */
if ( defined( 'PW_PRINTWAY_BOOTSTRAPPED' ) ) {
	return;
}
define( 'PW_PRINTWAY_BOOTSTRAPPED', true );

define( 'PW_PRINTWAY_DIR', plugin_dir_path( __FILE__ ) );
define( 'PW_PRINTWAY_VERSION', '2.2.27' );

require_once PW_PRINTWAY_DIR . 'printway-logger.php';

/**
 * Registra somente informações técnicas necessárias para diagnosticar a
 * ativação. O arquivo não contém senhas, tokens nem dados de clientes.
 */
function pw_printway_activation_log( $event, $context = array() ) {
	$path = PW_PRINTWAY_DIR . 'activation.log';
	if ( file_exists( $path ) && filesize( $path ) > 262144 ) {
		@rename( $path, PW_PRINTWAY_DIR . 'activation-previous.log' );
	}
	$record = array_merge(
		array(
			'time_utc' => gmdate( 'Y-m-d H:i:s' ),
			'event'    => (string) $event,
			'version'  => PW_PRINTWAY_VERSION,
			'php'      => PHP_VERSION,
		),
		(array) $context
	);
	@file_put_contents( $path, wp_json_encode( $record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . PHP_EOL, FILE_APPEND | LOCK_EX );
}

/* Registra falhas fatais que ocorram depois da ativação, durante uma página,
 * AJAX ou cron. Assim o mesmo activation.log também diagnostica erros de
 * execução sem exibir detalhes técnicos ao visitante. */
register_shutdown_function(
	static function() {
		$error = error_get_last();
		$fatal_types = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR );
		if ( ! is_array( $error ) || ! in_array( (int) $error['type'], $fatal_types, true ) ) {
			return;
		}
		pw_printway_activation_log(
			'runtime_fatal',
			array(
				'message' => isset( $error['message'] ) ? $error['message'] : '',
				'file'    => isset( $error['file'] ) ? $error['file'] : '',
				'line'    => isset( $error['line'] ) ? (int) $error['line'] : 0,
			)
		);
		if ( function_exists( 'pw_printway_log' ) ) {
			pw_printway_log(
				'core',
				'error',
				isset( $error['message'] ) ? $error['message'] : 'Erro fatal sem mensagem',
				array(
					'file' => isset( $error['file'] ) ? $error['file'] : '',
					'line' => isset( $error['line'] ) ? (int) $error['line'] : 0,
				)
			);
		}
	}
);

function pw_printway_activation_completed() {
	/* Uma reativacao depois de uma transferencia interrompida deve liberar
	 * imediatamente os arquivos parciais. Backups concluidos nunca sao tocados. */
	$cleaned_bytes = 0;
	$directories = array(
		trailingslashit( dirname( ABSPATH ) ) . 'printway-backups',
		trailingslashit( WP_CONTENT_DIR ) . 'uploads/printway-backups',
	);
	foreach ( array_unique( $directories ) as $directory ) {
		foreach ( array( '.upload-*.tmp', '.upload-auth-*.json', '*.importing-*' ) as $pattern ) {
			foreach ( (array) glob( trailingslashit( $directory ) . $pattern ) as $partial ) {
				if ( ! is_file( $partial ) ) { continue; }
				$cleaned_bytes += max( 0, (int) @filesize( $partial ) );
				@unlink( $partial );
			}
		}
	}
	pw_printway_activation_log(
		'activation_success',
		array(
			'wordpress'   => get_bloginfo( 'version' ),
			'woocommerce' => defined( 'WC_VERSION' ) ? WC_VERSION : 'not-active',
			'plugin_path' => plugin_basename( __FILE__ ),
			'partial_upload_bytes_cleaned' => $cleaned_bytes,
		)
	);
}
register_activation_hook( __FILE__, 'pw_printway_activation_completed' );

function pw_printway_require_module( $path, $guard_function, $label ) {
	if ( function_exists( $guard_function ) ) {
		return;
	}
	if ( ! file_exists( $path ) ) {
		pw_printway_activation_log( 'module_missing', array( 'module' => $label, 'path' => $path ) );
		return;
	}
	try {
		require_once $path;
	} catch ( Throwable $error ) {
		pw_printway_activation_log(
			'module_error',
			array(
				'module'  => $label,
				'message' => $error->getMessage(),
				'file'    => $error->getFile(),
				'line'    => $error->getLine(),
			)
		);
		throw $error;
	}
}

add_action( 'admin_menu', 'pw_printway_register_admin_menu', 1 );
add_action( 'admin_menu', 'pw_printway_register_editor_admin_menu', 1000 );
add_action( 'admin_menu', 'pw_printway_group_pix_menu', 999 );

function pw_printway_register_admin_menu() {
	add_menu_page(
		'PrintWay',
		'PrintWay',
		'manage_options',
		'pw-printway',
		'pw_printway_render_dashboard',
		'dashicons-admin-generic',
		56
	);
}

function pw_printway_render_dashboard() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	echo '<div class="wrap"><h1>PrintWay</h1><p>Escolha um módulo no menu lateral.</p></div>';
}

/**
 * Editor de imagens — módulo interno do PrintWay.
 * Estrutura:
 * /wp-content/plugins/printway/editor-de-imagens/
 */
function pw_printway_register_editor_admin_menu() {
	if ( ! current_user_can( 'manage_options' ) || ! function_exists( 'dtf_uv_editor_render_admin_page' ) ) {
		return;
	}

	global $submenu;
	$exists = false;

	if ( isset( $submenu['pw-printway'] ) && is_array( $submenu['pw-printway'] ) ) {
		foreach ( $submenu['pw-printway'] as $item ) {
			if ( isset( $item[2] ) && 'dtf-uv-editor' === $item[2] ) {
				$exists = true;
				break;
			}
		}
	}

	if ( ! $exists ) {
		add_submenu_page(
			'pw-printway',
			'Editor de imagens',
			'Editor de imagens',
			'manage_options',
			'dtf-uv-editor',
			'dtf_uv_editor_render_admin_page'
		);
	}
}

function pw_printway_group_pix_menu() {
	global $menu;

	if ( ! is_array( $menu ) ) {
		return;
	}

	foreach ( $menu as $item ) {
		if ( empty( $item[0] ) || empty( $item[2] ) ) {
			continue;
		}

		$label = trim( wp_strip_all_tags( $item[0] ) );

		if ( 0 !== strcasecmp( $label, 'PrintWay Pix' ) ) {
			continue;
		}

		$pix_slug = $item[2];
		$capability = ! empty( $item[1] ) ? $item[1] : 'manage_options';
		remove_menu_page( $pix_slug );
		add_submenu_page(
			'pw-printway',
			'Pix',
			'Pix',
			$capability,
			admin_url( 'admin.php?page=' . rawurlencode( $pix_slug ) )
		);
		break;
	}

	remove_submenu_page( 'pw-printway', 'pw-printway' );
}

$email_module = PW_PRINTWAY_DIR . 'email/printway-dtf-email.php';
$backup_module = PW_PRINTWAY_DIR . 'backup/printway-backup.php';
$pedidos_module = PW_PRINTWAY_DIR . 'pedidos/printway-pedidos.php';

/* Editor de imagens como módulo interno, junto aos demais aplicativos. */
$editor_module = PW_PRINTWAY_DIR . 'editor-de-imagens/dtf-uv-editor.php';

/* Calculadora DTF UV (front-end) — shortcode [printway_dtf_uv], antes colada
 * inteira num widget HTML do Elementor. */
$dtf_uv_module = PW_PRINTWAY_DIR . 'dtfUV/printway-dtf-uv.php';

/* Mercado Livre — conexão OAuth do aplicativo (Client ID/Secret + tokens),
 * base para futura vinculação dos pedidos do marketplace. */
$mercadolivre_module = PW_PRINTWAY_DIR . 'mercadolivre/printway-mercadolivre.php';

/* Shopee — conexão com a Shopee Open Platform (Partner ID/Key + tokens da
 * loja), mesmo padrão do módulo Mercado Livre acima. */
$shopee_module = PW_PRINTWAY_DIR . 'shopee/printway-shopee.php';

pw_printway_require_module( $email_module, 'pw_dtf_disable_calculator_cache', 'email' );
pw_printway_require_module( $backup_module, 'pw_printway_backup_components', 'backup' );
pw_printway_require_module( $pedidos_module, 'pw_personalizados_storage_keys', 'pedidos' );
pw_printway_require_module( $editor_module, 'dtf_uv_editor_shortcode', 'editor-de-imagens' );
pw_printway_require_module( $dtf_uv_module, 'pw_dtf_uv_shortcode', 'dtfUV' );
pw_printway_require_module( $mercadolivre_module, 'pw_ml_get_settings', 'mercadolivre' );
pw_printway_require_module( $shopee_module, 'pw_shopee_get_settings', 'shopee' );

$pix_modules = glob( PW_PRINTWAY_DIR . 'pix-qrcode/*.php' );

if ( is_array( $pix_modules ) ) {
	foreach ( $pix_modules as $pix_module ) {
		pw_printway_require_module( $pix_module, 'pw_bytes_len', 'pix' );
	}
}

/*
 * Minha conta: concentra os dados do cliente em "Detalhes da conta".
 * Os mesmos formulários nativos do WooCommerce continuam sendo usados,
 * portanto endereço, CNPJ e senha são salvos exatamente como antes.
 */
add_filter( 'woocommerce_account_menu_items', 'pw_printway_simplify_account_menu', 99 );
/* Executa por último: o plugin da lista de desejos inclui seu item depois dos demais. */
add_filter( 'woocommerce_account_menu_items', 'pw_printway_simplify_account_menu', 9999 );
function pw_printway_simplify_account_menu( $items ) {
	unset( $items['edit-address'] );
	if ( isset( $items['edit-account'] ) ) {
		$items['edit-account'] = 'Dados cadastrais';
	}

	if ( is_user_logged_in() ) {
		$user_id = get_current_user_id();
		if ( isset( $items['orders'] ) ) {
			$count = pw_printway_get_active_order_count( $user_id );
			$items['orders'] = 'Pedidos' . ( $count > 0 ? ' (' . $count . ')' : '' );
		}
		if ( isset( $items['wishlist'] ) ) {
			$count = pw_printway_get_wishlist_item_count( $user_id );
			$items['wishlist'] = 'Lista de desejos' . ( $count > 0 ? ' (' . $count . ')' : '' );
		}
		if ( isset( $items['points'] ) ) {
			$points = function_exists( 'pw_dtf_get_user_points_balance' ) ? pw_dtf_get_user_points_balance( $user_id ) : (int) get_user_meta( $user_id, 'wps_wpr_points', true );
			$items['points'] = 'Pontos' . ( $points > 0 ? ' (' . number_format_i18n( $points ) . ')' : '' );
		}
		if ( isset( $items['downloads'] ) ) {
			$downloads = function_exists( 'wc_get_customer_available_downloads' ) ? wc_get_customer_available_downloads( $user_id ) : array();
			$count = count( $downloads );
			$items['downloads'] = 'Downloads' . ( $count > 0 ? ' (' . $count . ')' : '' );
		}
	}

	$order = array( 'dashboard', 'edit-account', 'orders', 'points', 'wishlist', 'downloads', 'customer-logout' );
	$sorted = array();
	foreach ( $order as $endpoint ) {
		if ( isset( $items[ $endpoint ] ) ) {
			$sorted[ $endpoint ] = $items[ $endpoint ];
		}
	}
	foreach ( $items as $endpoint => $label ) {
		if ( ! isset( $sorted[ $endpoint ] ) ) {
			$sorted[ $endpoint ] = $label;
		}
	}

	return $sorted;
}

/* Login e cadastro em um único painel organizado por abas. */
add_action( 'woocommerce_before_customer_login_form', 'pw_printway_render_login_tabs', 5 );
function pw_printway_render_login_tabs() {
	$default_tab = ! empty( $_POST['register'] ) || ( isset( $_GET['acao'] ) && 'cadastro' === sanitize_key( wp_unslash( $_GET['acao'] ) ) ) ? 'register' : 'login';
	echo '<nav class="pw-auth-tabs" data-default="' . esc_attr( $default_tab ) . '" aria-label="Acesso à conta">';
	echo '<button type="button" class="pw-auth-tab ' . ( 'login' === $default_tab ? 'is-active' : '' ) . '" data-target="login" aria-selected="' . ( 'login' === $default_tab ? 'true' : 'false' ) . '">Entrar</button>';
	echo '<button type="button" class="pw-auth-tab ' . ( 'register' === $default_tab ? 'is-active' : '' ) . '" data-target="register" aria-selected="' . ( 'register' === $default_tab ? 'true' : 'false' ) . '">Cadastre-se</button>';
	echo '</nav>';
	echo '<noscript><style>#customer_login .u-column1,#customer_login .u-column2{display:block!important;visibility:visible!important;opacity:1!important}</style></noscript>';
}

/* Recuperação de senha: evita reenvios repetidos e informa o prazo ao cliente. */
const PW_PRINTWAY_PASSWORD_RESET_COOLDOWN = 300;

function pw_printway_password_reset_key( $user_id ) {
	return 'pw_printway_password_reset_' . absint( $user_id );
}

function pw_printway_limit_password_reset_request( $errors, $user_data ) {
	if ( ! $user_data || empty( $user_data->ID ) ) {
		return;
	}

	$expires_at = (int) get_transient( pw_printway_password_reset_key( $user_data->ID ) );
	if ( $expires_at > time() ) {
		$remaining = max( 1, (int) ceil( ( $expires_at - time() ) / 60 ) );
		$errors->add( 'pw_password_reset_cooldown', sprintf( 'Aguarde %d minuto(s) antes de solicitar outro e-mail de redefinição.', $remaining ) );
		return;
	}

	$expires_at = time() + PW_PRINTWAY_PASSWORD_RESET_COOLDOWN;
	set_transient( pw_printway_password_reset_key( $user_data->ID ), $expires_at, PW_PRINTWAY_PASSWORD_RESET_COOLDOWN );
	$payload = wp_json_encode( array( 'email' => $user_data->user_email, 'sent_at' => time(), 'expires_at' => $expires_at ) );
	setcookie( 'pw_printway_password_reset_notice', rawurlencode( base64_encode( $payload ) ), $expires_at, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
}
add_action( 'lostpassword_post', 'pw_printway_limit_password_reset_request', 10, 2 );

function pw_printway_password_reset_message( $message ) {
	return 'Um e-mail de redefinição de senha foi enviado para o endereço de e-mail da sua conta, mas pode levar alguns minutos para aparecer na sua caixa de entrada. Aguarde pelo menos 5 minutos antes de tentar novamente ou verifique sua caixa de spam.';
}
add_filter( 'woocommerce_lost_password_message', 'pw_printway_password_reset_message' );

function pw_printway_get_password_reset_notice() {
	if ( empty( $_COOKIE['pw_printway_password_reset_notice'] ) ) {
		return array();
	}
	$raw = base64_decode( rawurldecode( wp_unslash( $_COOKIE['pw_printway_password_reset_notice'] ) ), true );
	$data = $raw ? json_decode( $raw, true ) : array();
	return is_array( $data ) && ! empty( $data['email'] ) && ! empty( $data['expires_at'] ) ? $data : array();
}

/* Cabeçalho próprio para a recuperação de senha. */
add_action( 'woocommerce_before_lost_password_form', 'pw_printway_render_lost_password_header', 5 );
function pw_printway_render_lost_password_header() {
	echo '<div class="pw-lost-password-header"><span>RECUPERAÇÃO DE ACESSO</span><h2>Redefina sua senha</h2><p>Informe seu usuário ou e-mail. Enviaremos um link seguro para criar uma nova senha.</p></div>';
	$notice = pw_printway_get_password_reset_notice();
	if ( empty( $notice ) || (int) $notice['expires_at'] <= time() ) {
		return;
	}
	$sent_at = wp_date( 'd/m/Y \à\s H:i', (int) $notice['sent_at'], wp_timezone() );
	echo '<div class="pw-password-reset-wait" data-expires="' . esc_attr( (int) $notice['expires_at'] * 1000 ) . '"><strong>Solicitação enviada</strong><p>Um e-mail de redefinição de senha foi enviado para <b>' . esc_html( $notice['email'] ) . '</b> em <b>' . esc_html( $sent_at ) . '</b>.</p><p>Ele pode levar alguns minutos para aparecer. Verifique também a caixa de spam.</p><p>Aguarde <b class="pw-password-reset-countdown">05:00</b> para solicitar novamente.</p></div>';
	echo '<script>(function(){function start(){var box=document.querySelector(".pw-password-reset-wait"),form=document.querySelector(".woocommerce-ResetPassword");if(!box||!form){return;}var input=form.querySelector("input[name=user_login]"),button=form.querySelector("button[type=submit]");function update(){var left=Math.max(0,Math.ceil((Number(box.dataset.expires)-Date.now())/1000)),minutes=Math.floor(left/60),seconds=left%60,label=box.querySelector(".pw-password-reset-countdown");if(label){label.textContent=String(minutes).padStart(2,"0")+":"+String(seconds).padStart(2,"0");}if(input){input.disabled=left>0;}if(button){button.disabled=left>0;}if(left>0){setTimeout(update,1000);}else{box.remove();}}update();}if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",start);}else{start();}})();</script>';
}

/* Direciona o cliente ao cadastro quando abrir o painel com dados pendentes. */
function pw_printway_redirect_incomplete_account() {
	if (
		is_admin() ||
		! is_user_logged_in() ||
		! function_exists( 'is_account_page' ) ||
		! is_account_page() ||
		( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url() )
	) {
		return;
	}

	$user_id = get_current_user_id();
	if ( pw_printway_account_setup_is_pending( $user_id ) ) {
		return;
	}

	if ( ! pw_printway_account_data_is_incomplete( $user_id ) ) {
		return;
	}

	wp_safe_redirect( wc_get_endpoint_url( 'edit-account', '', wc_get_page_permalink( 'myaccount' ) ) );
	exit;
}

function pw_printway_account_setup_is_pending( $user_id ) {
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return true;
	}

	/* O WordPress usa essa chave durante a ativação/redefinição de senha. */
	if ( '' !== trim( (string) $user->user_activation_key ) ) {
		return true;
	}

	/* Compatibilidade com plugins de confirmação de e-mail. */
	$pending_meta_patterns = array(
		'/email.*(confirm|verify|verif|activ|ativ)/i',
		'/(confirm|verify|verif|activ|ativ).*email/i',
		'/(account|user).*(pending|activation|ativacao|ativação)/i',
	);
	$pending_values = array( '0', 'false', 'no', 'pending', 'unverified', 'not_verified', 'unconfirmed', 'awaiting', 'inactive' );

	foreach ( get_user_meta( $user_id ) as $meta_key => $values ) {
		$is_relevant = false;
		foreach ( $pending_meta_patterns as $pattern ) {
			if ( preg_match( $pattern, (string) $meta_key ) ) {
				$is_relevant = true;
				break;
			}
		}
		if ( ! $is_relevant ) {
			continue;
		}

		foreach ( (array) $values as $value ) {
			if ( in_array( strtolower( trim( (string) $value ) ), $pending_values, true ) ) {
				return true;
			}
		}
	}

	return (bool) apply_filters( 'pw_printway_account_setup_is_pending', false, $user_id );
}

function pw_printway_account_data_is_incomplete( $user_id ) {
	$user = get_userdata( $user_id );
	if ( ! $user || '' === trim( (string) $user->user_email ) ) {
		return true;
	}

	$required_fields = array(
		'first_name',
		'last_name',
		'billing_phone',
		'billing_postcode',
		'billing_address_1',
		'billing_number',
		'billing_neighborhood',
		'billing_city',
		'billing_state',
	);

	foreach ( $required_fields as $field ) {
		$value = get_user_meta( $user_id, $field, true );
		if ( '' === trim( (string) $value ) ) {
			return true;
		}
	}

	$person_type = strtolower( (string) get_user_meta( $user_id, 'billing_persontype', true ) );
	$cnpj        = get_user_meta( $user_id, 'billing_cnpj', true );
	$is_company  = in_array( $person_type, array( '2', 'juridica', 'jurídica', 'pessoa juridica', 'pessoa jurídica', 'cnpj' ), true ) || '' !== trim( (string) $cnpj );
	$document    = $is_company
		? $cnpj
		: get_user_meta( $user_id, 'billing_cpf', true );

	return '' === trim( (string) $document );
}

/* Painel inicial da conta com indicadores úteis para o cliente. */
add_filter( 'gettext', 'pw_printway_remove_default_dashboard_text', 20, 3 );
function pw_printway_remove_default_dashboard_text( $translated, $text, $domain ) {
	if ( 'woocommerce' === $domain && false !== strpos( $text, 'From your account dashboard' ) ) {
		return '';
	}
	if ( 'woocommerce' === $domain ) {
		$password_labels = array(
			'Current password (leave blank to leave unchanged)' => 'Senha atual',
			'New password (leave blank to leave unchanged)'     => 'Nova senha',
			'Confirm new password'                              => 'Confirmar nova senha',
		);
		if ( isset( $password_labels[ $text ] ) ) {
			return $password_labels[ $text ];
		}
	}
	return $translated;
}

/* Guarda o último login para exibição no resumo da conta. */
add_action( 'wp_login', 'pw_printway_record_last_login', 10, 2 );
function pw_printway_record_last_login( $user_login, $user ) {
	if ( $user instanceof WP_User ) {
		/* Timestamp UTC: a visualização será sempre convertida para São Paulo. */
		update_user_meta( $user->ID, '_pw_printway_last_login', time() );
		update_user_meta( $user->ID, '_pw_printway_last_login_utc', '1' );
		$reminders = pw_printway_get_dashboard_reminders();
		$previous  = absint( get_user_meta( $user->ID, '_pw_printway_login_reminder', true ) );
		$choices   = array_diff( array_keys( $reminders ), array( $previous ) );
		$choices   = $choices ? array_values( $choices ) : array_keys( $reminders );
		update_user_meta( $user->ID, '_pw_printway_login_reminder', $choices[ array_rand( $choices ) ] );
	}
}

function pw_printway_get_dashboard_reminders() {
	return array(
		'Os pontos de pedidos DTF UV são liberados após a confirmação ou conclusão do pedido pela empresa.',
		'Confira os dados e o arquivo antes de finalizar um novo pedido.',
		'Mantenha seu telefone e endereço atualizados para facilitar o atendimento.',
		'Acompanhe os pedidos para saber quando houver uma atualização de status.',
		'Você pode consultar seus produtos salvos na Lista de Desejos.',
		'Use seus pontos em pedidos elegíveis, respeitando o limite disponível.',
	);
}

function pw_printway_get_last_login( $user_id ) {
	$last_login = absint( get_user_meta( $user_id, '_pw_printway_last_login', true ) );
	$is_utc     = '1' === get_user_meta( $user_id, '_pw_printway_last_login_utc', true );
	/* Converte o primeiro registro criado pelas versões anteriores para UTC. */
	if ( $last_login && ! $is_utc && $user_id === get_current_user_id() ) {
		$last_login = time();
		update_user_meta( $user_id, '_pw_printway_last_login', $last_login );
		update_user_meta( $user_id, '_pw_printway_last_login_utc', '1' );
	}
	if ( ! $last_login && $user_id === get_current_user_id() ) {
		$last_login = time();
		update_user_meta( $user_id, '_pw_printway_last_login', $last_login );
		update_user_meta( $user_id, '_pw_printway_last_login_utc', '1' );
	}
	return $last_login;
}

function pw_printway_format_brazil_datetime( $timestamp ) {
	if ( ! $timestamp ) {
		return '';
	}
	return wp_date( 'd/m/Y \\à\\s H:i', $timestamp, new DateTimeZone( 'America/Sao_Paulo' ) );
}

add_action( 'template_redirect', 'pw_printway_handle_reseller_request', 30 );
function pw_printway_handle_reseller_request() {
	if ( empty( $_POST['pw_printway_reseller_request'] ) || ! is_user_logged_in() || ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
		return;
	}

	check_admin_referer( 'pw_printway_reseller_request', 'pw_printway_reseller_nonce' );
	$user_id = get_current_user_id();
	if ( ! pw_printway_can_request_reseller( $user_id ) ) {
		wc_add_notice( 'Para solicitar a conta de Revendedor, cadastre um CNPJ válido como Pessoa Jurídica.', 'error' );
		wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
		exit;
	}

	if ( 'pending' === get_user_meta( $user_id, '_pw_printway_reseller_request_status', true ) ) {
		wc_add_notice( 'Sua solicitação de conta Revendedor já está em análise.', 'notice' );
		wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
		exit;
	}

	$user      = get_userdata( $user_id );
	$recipient = function_exists( 'pw_dtf_get_recipient_email' ) ? pw_dtf_get_recipient_email() : get_option( 'admin_email' );
	$cnpj      = get_user_meta( $user_id, 'billing_cnpj', true );
	$subject   = 'Solicitação de conta Revendedor — ' . $user->user_login;
	$rows      = array(
		'Usuário WordPress' => $user->user_login,
		'ID do usuário'     => $user_id,
		'Cliente'           => $user->display_name,
		'E-mail'            => $user->user_email,
		'CNPJ'              => $cnpj,
		'Ação necessária'   => 'Acesse PrintWay → DTF UV → Usuários e códigos e altere a função deste usuário após a conferência.',
	);
	$body = function_exists( 'pw_dtf_render_email_html' )
		? pw_dtf_render_email_html( 'Solicitação de conta Revendedor', 'Um cliente solicitou alteração de Cliente para Revendedor.', $rows )
		: '<h2>Solicitação de conta Revendedor</h2><p>Usuário: ' . esc_html( $user->user_login ) . '</p><p>CNPJ: ' . esc_html( $cnpj ) . '</p>';
	$sent = wp_mail( $recipient, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );

	if ( $sent ) {
		update_user_meta( $user_id, '_pw_printway_reseller_request_status', 'pending' );
		update_user_meta( $user_id, '_pw_printway_reseller_request_date', current_time( 'mysql' ) );
		wc_add_notice( 'Solicitação enviada. A equipe irá conferir seu cadastro e avaliar a alteração para Revendedor.', 'success' );
	} else {
		wc_add_notice( 'Não foi possível enviar a solicitação agora. Tente novamente mais tarde.', 'error' );
	}

	wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
	exit;
}

function pw_printway_can_request_reseller( $user_id ) {
	$user = get_userdata( $user_id );
	if ( ! $user || ! in_array( 'customer', (array) $user->roles, true ) ) {
		return false;
	}
	$person_type = strtolower( (string) get_user_meta( $user_id, 'billing_persontype', true ) );
	$is_company  = in_array( $person_type, array( '2', 'juridica', 'jurídica', 'pessoa juridica', 'pessoa jurídica', 'cnpj' ), true );
	$cnpj        = get_user_meta( $user_id, 'billing_cnpj', true );
	return $is_company && pw_printway_is_valid_cnpj( $cnpj );
}

function pw_printway_get_wishlist_item_count( $user_id ) {
	$meta_items = get_user_meta( $user_id, 'yith_wcwl_products', true );
	if ( is_array( $meta_items ) ) {
		return count( $meta_items );
	}

	global $wpdb;
	$lists_table = $wpdb->prefix . 'yith_wcwl_lists';
	$items_table = $wpdb->prefix . 'yith_wcwl_items';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $lists_table ) ) !== $lists_table || $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $items_table ) ) !== $items_table ) {
		return 0;
	}
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(items.ID) FROM {$items_table} AS items INNER JOIN {$lists_table} AS lists ON lists.ID = items.wishlist_id WHERE lists.user_id = %d", $user_id ) );
}

function pw_printway_get_active_order_count( $user_id ) {
	$site_orders = function_exists( 'wc_get_orders' ) ? wc_get_orders(
		array(
			'customer_id' => $user_id,
			'limit'       => -1,
			'return'      => 'ids',
			'status'      => array( 'wc-pending', 'wc-failed', 'wc-on-hold', 'wc-processing' ),
		)
	) : array();

	$dtf_orders = get_posts(
		array(
			'post_type'      => 'pw_dtf_order',
			'post_status'    => 'publish',
			'author'         => $user_id,
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);
	$active_dtf = 0;
	foreach ( $dtf_orders as $order_id ) {
		$status = (string) get_post_meta( $order_id, '_pw_dtf_status', true );
		if ( ! in_array( $status, array( 'Concluído', 'Cancelado' ), true ) ) {
			$active_dtf++;
		}
	}

	return count( $site_orders ) + $active_dtf;
}

/**
 * Lê a equivalência configurada no Points and Rewards for WooCommerce.
 * Retorna pontos e reais somente quando ambos estiverem definidos pelo plugin.
 */
function pw_printway_get_site_points_exchange() {
	$settings = get_option( 'wps_wpr_settings_gallery', array() );
	if ( ! is_array( $settings ) ) {
		return array( 'points' => 0.0, 'reais' => 0.0 );
	}

	$points_keys = array( 'wps_wpr_redeem_points', 'wps_wpr_redeem_point', 'redeem_points', 'redeem_point' );
	$reais_keys  = array( 'wps_wpr_redeem_price', 'wps_wpr_redeem_value', 'redeem_price', 'redeem_value' );
	$values      = array();
	$iterator    = new RecursiveIteratorIterator( new RecursiveArrayIterator( $settings ) );
	foreach ( $iterator as $key => $value ) {
		if ( is_scalar( $value ) ) {
			$values[ (string) $key ] = $value;
		}
	}

	$points = 0.0;
	$reais  = 0.0;
	foreach ( $points_keys as $key ) {
		if ( isset( $values[ $key ] ) && is_numeric( $values[ $key ] ) ) {
			$points = (float) $values[ $key ];
			break;
		}
	}
	foreach ( $reais_keys as $key ) {
		if ( isset( $values[ $key ] ) && is_numeric( $values[ $key ] ) ) {
			$reais = (float) $values[ $key ];
			break;
		}
	}

	return array( 'points' => $points, 'reais' => $reais );
}

add_action( 'woocommerce_account_dashboard', 'pw_printway_render_account_dashboard', 20 );
function pw_printway_render_account_dashboard() {
	$user_id      = get_current_user_id();
	$user         = wp_get_current_user();
	$wc_orders    = function_exists( 'wc_get_orders' ) ? wc_get_orders( array( 'customer_id' => $user_id, 'limit' => -1, 'return' => 'objects' ) ) : array();
	$dtf_orders   = get_posts( array( 'post_type' => 'pw_dtf_order', 'post_status' => 'publish', 'author' => $user_id, 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'DESC' ) );
	$latest_site  = null;
	$latest_dtf   = null;

	foreach ( $wc_orders as $order ) {
		$order_time = $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0;
		if ( ! $latest_site || $order_time > $latest_site['time'] ) {
			$latest_site = array(
				'time'   => $order_time,
				'label'  => 'Pedido #' . $order->get_order_number(),
				'status' => wc_get_order_status_name( 'wc-' . $order->get_status() ),
			);
		}
	}

	foreach ( $dtf_orders as $order ) {
		$order_time = strtotime( $order->post_date );
		$status     = (string) get_post_meta( $order->ID, '_pw_dtf_status', true );
		$reference  = (string) get_post_meta( $order->ID, '_pw_dtf_reference', true );
		if ( ! $latest_dtf || $order_time > $latest_dtf['time'] ) {
			$latest_dtf = array( 'time' => $order_time, 'label' => $reference ? $reference : 'Pedido DTF UV', 'status' => $status ? $status : 'Enviado para análise' );
		}
	}

	$points       = function_exists( 'pw_dtf_get_user_points_balance' ) ? pw_dtf_get_user_points_balance( $user_id ) : (int) get_user_meta( $user_id, 'wps_wpr_points', true );
	$site_exchange = pw_printway_get_site_points_exchange();
	$dtf_point_value  = 0.0;
	$dtf_points_enabled = false;
	if ( function_exists( 'pw_dtf_get_points_settings' ) ) {
		$point_settings = pw_dtf_get_points_settings();
		$dtf_points_enabled = ! empty( $point_settings['enabled'] );
		$dtf_point_value    = ! empty( $point_settings['redemption_points'] ) ? (float) $point_settings['redemption_reais'] / (float) $point_settings['redemption_points'] : 0.0;
	}
	$billing_ready = ! pw_printway_account_data_is_incomplete( $user_id );
	$shipping_ready = true;
	if ( '1' === get_user_meta( $user_id, '_pw_printway_delivery_different', true ) ) {
		foreach ( array( 'shipping_address_1', 'shipping_number', 'shipping_neighborhood', 'shipping_city', 'shipping_state', 'shipping_postcode' ) as $field ) {
			if ( '' === trim( (string) get_user_meta( $user_id, $field, true ) ) ) {
				$shipping_ready = false;
				break;
			}
		}
	}
	$roles        = (array) $user->roles;
	$role_key     = reset( $roles );
	$all_roles    = wp_roles();
	$role_label   = ( $role_key && isset( $all_roles->roles[ $role_key ]['name'] ) ) ? translate_user_role( $all_roles->roles[ $role_key ]['name'] ) : 'Cliente';
	/* A instalação usa "Cliente" como nome comercial da função padrão customer. */
	if ( 'customer' === $role_key ) {
		$role_label = 'Cliente';
	}
	$wishlist_count = pw_printway_get_wishlist_item_count( $user_id );
	$can_request_reseller = pw_printway_can_request_reseller( $user_id );
	$reseller_request     = get_user_meta( $user_id, '_pw_printway_reseller_request_status', true );
	$myaccount_url        = wc_get_page_permalink( 'myaccount' );
	$account_url          = wc_get_endpoint_url( 'edit-account', '', $myaccount_url );
	$billing_url          = wc_get_endpoint_url( 'edit-address', 'billing', $myaccount_url );
	$shipping_url         = wc_get_endpoint_url( 'edit-address', 'shipping', $myaccount_url );
	$orders_url           = wc_get_endpoint_url( 'orders', '', $myaccount_url );
	$dtf_orders_url       = add_query_arg( 'tipo', 'dtf-uv', $orders_url );
	$logout_url           = wp_logout_url( $myaccount_url );
	$last_login           = pw_printway_get_last_login( $user_id );
	$reminders            = pw_printway_get_dashboard_reminders();
	$reminder_index       = array_rand( $reminders );
	$missing_items        = array();
	if ( '' === trim( (string) $user->first_name ) || '' === trim( (string) $user->last_name ) ) {
		$missing_items[] = array( 'Dados pessoais', $account_url );
	}
	if ( '' === trim( (string) get_user_meta( $user_id, 'billing_phone', true ) ) ) {
		$missing_items[] = array( 'Celular / WhatsApp', $account_url );
	}
	if ( ! $billing_ready ) {
		$missing_items[] = array( 'Endereço principal ou CPF / CNPJ', $billing_url );
	}
	if ( ! $shipping_ready ) {
		$missing_items[] = array( 'Endereço de entrega', $shipping_url );
	}

	echo '<section class="pw-account-dashboard">';
	echo '<div class="pw-account-dashboard__welcome">';
	echo '<span class="pw-account-dashboard__eyebrow">RESUMO DA SUA CONTA</span>';
	echo '<h2>Olá, ' . esc_html( $user->display_name ) . '!</h2>';
	echo '<div class="pw-account-dashboard__session">(não é ' . esc_html( $user->display_name ) . '? <a href="' . esc_url( $logout_url ) . '">Sair</a>)' . ( $last_login ? '<span>Último acesso: ' . esc_html( pw_printway_format_brazil_datetime( $last_login ) ) . '</span>' : '' ) . '</div>';
	echo '</div>';
	echo '<div class="pw-account-dashboard__metrics">';
	echo '<article class="pw-account-dashboard__last-orders"><span>ÚLTIMOS PEDIDOS</span>';
	echo '<div><b>Pedido do site</b>' . ( $latest_site ? '<a href="' . esc_url( $orders_url ) . '">' . esc_html( $latest_site['label'] ) . ' — ' . esc_html( $latest_site['status'] ) . '</a><small>' . esc_html( wp_date( 'd/m/Y \à\s H:i', $latest_site['time'] ) ) . '</small>' : '<small>Nenhum pedido do site registrado.</small>' ) . '</div>';
	echo '<div><b>Pedido DTF UV</b>' . ( $latest_dtf ? '<a href="' . esc_url( $dtf_orders_url ) . '">' . esc_html( $latest_dtf['label'] ) . ' — ' . esc_html( $latest_dtf['status'] ) . '</a><small>' . esc_html( wp_date( 'd/m/Y \à\s H:i', $latest_dtf['time'] ) ) . '</small>' : '<small>Nenhum pedido DTF UV registrado.</small>' ) . '</div>';
	echo '</article>';
	echo '<article class="pw-account-dashboard__points"><span>PONTOS DISPONÍVEIS</span>';
	echo '<strong class="pw-account-dashboard__points-total">' . esc_html( number_format_i18n( $points ) ) . ' ponto(s)</strong>';
	echo '<div><b>Compra no site</b>';
	if ( $site_exchange['points'] > 0 && $site_exchange['reais'] > 0 ) {
		echo '<small><span class="pw-account-dashboard__points-value">' . esc_html( number_format_i18n( $site_exchange['points'] ) ) . ' ponto(s) = ' . esc_html( wp_strip_all_tags( wc_price( $site_exchange['reais'] ) ) ) . '</span> para troca em compras no site.</small>';
	} else {
		echo '<small>A equivalência de troca é definida no programa de pontos do site.</small>';
	}
	echo '</div>';
	echo '<div><b>DTF UV</b>';
	if ( $dtf_point_value > 0 ) {
		$dtf_exchange_points = ! empty( $point_settings['redemption_points'] ) ? (float) $point_settings['redemption_points'] : 1.0;
		$dtf_exchange_reais  = ! empty( $point_settings['redemption_reais'] ) ? (float) $point_settings['redemption_reais'] : $dtf_point_value;
		$dtf_enabled_note    = $dtf_points_enabled ? '' : ' A troca está desativada no momento.';
		echo '<small><span class="pw-account-dashboard__points-value">' . esc_html( number_format_i18n( $dtf_exchange_points ) ) . ' ponto(s) = ' . esc_html( wp_strip_all_tags( wc_price( $dtf_exchange_reais ) ) ) . '</span> na calculadora DTF UV.' . esc_html( $dtf_enabled_note ) . '</small>';
	} else {
		echo '<small>A equivalência de troca para DTF UV ainda não foi configurada.</small>';
	}
	echo '</div></article>';
	echo '<article class="pw-account-dashboard__account-type"><span>TIPO DE CONTA</span><strong>' . esc_html( $role_label ) . '</strong>';
	if ( 'pending' === $reseller_request ) {
		echo '<small>Sua solicitação para Revendedor está em análise pela equipe.</small>';
	} elseif ( $can_request_reseller ) {
		echo '<small>Com seu CNPJ válido, você pode solicitar conta Revendedor e acessar melhores preços.</small>';
		echo '<form method="post"><input type="hidden" name="pw_printway_reseller_request" value="1">' . wp_nonce_field( 'pw_printway_reseller_request', 'pw_printway_reseller_nonce', true, false ) . '<button type="submit" class="pw-request-reseller">Solicitar conta Revendedor</button></form>';
	} else {
		echo '<small>Benefícios e preços aplicados conforme sua categoria de cliente.</small>';
	}
	echo '</article>';
	echo '<article class="pw-account-dashboard__status"><span>SITUAÇÃO DA CONTA</span>';
	if ( empty( $missing_items ) ) {
		echo '<strong><i class="pw-dashboard-status is-ok">✓</i>Cadastro completo</strong><small>Seus dados essenciais estão atualizados.</small>';
	} else {
		echo '<strong><i class="pw-dashboard-status is-pending">×</i>Complete seu cadastro</strong><ul>';
		foreach ( $missing_items as $missing_item ) {
			echo '<li><a href="' . esc_url( $missing_item[1] ) . '">' . esc_html( $missing_item[0] ) . '</a></li>';
		}
		echo '</ul>';
	}
	echo '</article>';
	if ( $wishlist_count > 0 ) {
		echo '<article class="pw-account-dashboard__wishlist"><span>LISTA DE DESEJOS</span><strong>' . esc_html( number_format_i18n( $wishlist_count ) ) . ' item(ns) salvo(s)</strong><small>Você tem produtos guardados para consultar quando quiser.</small></article>';
	}
	echo '</div>';
	echo '<div class="pw-account-dashboard__reminder"><b>✓ Lembrete</b><span>' . esc_html( $reminders[ $reminder_index ] ) . '</span></div>';
	echo '<script>(function(){function pwHideWooGreeting(){document.querySelectorAll(".woocommerce-MyAccount-content p").forEach(function(p){var t=(p.textContent||"").toLowerCase(),logout=p.querySelector("a[href*=logout]");if(logout||((t.indexOf("não é")!==-1||t.indexOf("nao e")!==-1)&&t.indexOf("confirm")===-1&&t.indexOf("verifi")===-1&&t.indexOf("senha")===-1)){p.style.display="none";}});}if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",pwHideWooGreeting);}else{pwHideWooGreeting();}setTimeout(pwHideWooGreeting,250);})();</script>';
	echo '</section>';
}

add_filter( 'body_class', 'pw_printway_account_body_classes' );
function pw_printway_account_body_classes( $classes ) {
	if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
		return $classes;
	}

	if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'edit-account' ) ) {
		$tab       = isset( $_GET['aba'] ) ? sanitize_key( wp_unslash( $_GET['aba'] ) ) : 'dados';
		$classes[] = 'pw-account-view-' . ( 'senha' === $tab ? 'password' : 'personal' );
	}

	if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'edit-address' ) ) {
		$address_value = (string) get_query_var( 'edit-address' );
		$request_path  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$is_shipping   = in_array( $address_value, array( 'shipping', 'entrega' ), true ) || false !== strpos( $request_path, '/shipping' ) || false !== strpos( $request_path, '/entrega' );
		$classes[]     = 'pw-account-view-address';
		$classes[]     = $is_shipping ? 'pw-address-shipping' : 'pw-address-billing';
	}

	return $classes;
}

add_action( 'woocommerce_before_edit_account_form', 'pw_printway_account_tabs_personal', 5 );
function pw_printway_account_tabs_personal() {
	pw_printway_render_account_tabs( 'personal' );
}

add_action( 'woocommerce_before_edit_account_address_form', 'pw_printway_account_tabs_address', 5 );
function pw_printway_account_tabs_address() {
	pw_printway_render_account_tabs( 'address' );
	pw_printway_render_address_mode_notice();
}

function pw_printway_is_shipping_address_request() {
	$address_value = (string) get_query_var( 'edit-address' );
	$request_path  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	return in_array( $address_value, array( 'shipping', 'entrega' ), true ) || false !== strpos( $request_path, '/shipping' ) || false !== strpos( $request_path, '/entrega' );
}

/** Um único endereço principal é usado por padrão; entrega diferente é opcional. */
function pw_printway_render_address_mode_notice() {
	if ( ! function_exists( 'wc_get_endpoint_url' ) || ! function_exists( 'wc_get_page_permalink' ) ) {
		return;
	}

	$my_account_url = wc_get_page_permalink( 'myaccount' );
	$billing_url    = add_query_arg( 'aba', 'endereco', wc_get_endpoint_url( 'edit-address', 'billing', $my_account_url ) );
	$shipping_url   = add_query_arg( 'aba', 'endereco', wc_get_endpoint_url( 'edit-address', 'shipping', $my_account_url ) );

	if ( pw_printway_is_shipping_address_request() ) {
		echo '<div class="pw-address-mode"><strong>Endereço de entrega diferente</strong><span>Preencha este formulário somente se o pedido precisar ser entregue em outro local.</span><form method="post"><input type="hidden" name="pw_printway_use_primary_address" value="1">' . wp_nonce_field( 'pw_printway_use_primary_address', 'pw_printway_address_nonce', true, false ) . '<button type="submit">Usar o mesmo endereço principal</button></form></div>';
		return;
	}

	echo '<div class="pw-address-mode"><strong>Endereço principal</strong><span>Este endereço será usado para cobrança e entrega.</span><a href="' . esc_url( $shipping_url ) . '">Meu endereço de entrega é diferente</a></div>';
}

function pw_printway_copy_billing_to_shipping( $user_id ) {
	$fields = array( 'first_name', 'last_name', 'company', 'country', 'address_1', 'number', 'address_2', 'neighborhood', 'city', 'state', 'postcode' );
	foreach ( $fields as $field ) {
		update_user_meta( $user_id, 'shipping_' . $field, get_user_meta( $user_id, 'billing_' . $field, true ) );
	}
}

add_action( 'woocommerce_customer_save_address', 'pw_printway_sync_primary_and_delivery_addresses', 20, 2 );
function pw_printway_sync_primary_and_delivery_addresses( $user_id, $address_type ) {
	$user_id      = absint( $user_id );
	$address_type = sanitize_key( $address_type );
	if ( ! $user_id ) {
		return;
	}

	if ( 'billing' === $address_type && '1' !== get_user_meta( $user_id, '_pw_printway_delivery_different', true ) ) {
		pw_printway_copy_billing_to_shipping( $user_id );
	}

	if ( 'shipping' === $address_type ) {
		update_user_meta( $user_id, '_pw_printway_delivery_different', '1' );
	}
}

add_action( 'template_redirect', 'pw_printway_handle_use_primary_address', 25 );
function pw_printway_handle_use_primary_address() {
	if ( ! is_user_logged_in() || empty( $_POST['pw_printway_use_primary_address'] ) || ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
		return;
	}

	check_admin_referer( 'pw_printway_use_primary_address', 'pw_printway_address_nonce' );
	pw_printway_copy_billing_to_shipping( get_current_user_id() );
	delete_user_meta( get_current_user_id(), '_pw_printway_delivery_different' );
	wc_add_notice( 'O endereço de entrega voltou a usar o mesmo endereço principal.', 'success' );
	$my_account_url = wc_get_page_permalink( 'myaccount' );
	wp_safe_redirect( add_query_arg( 'aba', 'endereco', wc_get_endpoint_url( 'edit-address', 'billing', $my_account_url ) ) );
	exit;
}

function pw_printway_render_account_tabs( $active_tab ) {
	if ( ! function_exists( 'wc_get_endpoint_url' ) || ! function_exists( 'wc_get_page_permalink' ) ) {
		return;
	}

	$my_account_url = wc_get_page_permalink( 'myaccount' );
	$personal_url   = add_query_arg( 'aba', 'dados', wc_get_endpoint_url( 'edit-account', '', $my_account_url ) );
	$password_url   = add_query_arg( 'aba', 'senha', wc_get_endpoint_url( 'edit-account', '', $my_account_url ) );
	$address_url    = add_query_arg( 'aba', 'endereco', wc_get_endpoint_url( 'edit-address', 'billing', $my_account_url ) );
	if ( 'personal' === $active_tab && isset( $_GET['aba'] ) && 'senha' === sanitize_key( wp_unslash( $_GET['aba'] ) ) ) {
		$active_tab = 'password';
	}

	echo '<nav class="pw-account-tabs" aria-label="Dados da conta">';
	echo '<a class="' . ( 'personal' === $active_tab ? 'is-active' : '' ) . '" href="' . esc_url( $personal_url ) . '">Dados pessoais</a>';
	echo '<a class="' . ( 'address' === $active_tab ? 'is-active' : '' ) . '" href="' . esc_url( $address_url ) . '">Endereço</a>';
	echo '<a class="' . ( 'password' === $active_tab ? 'is-active' : '' ) . '" href="' . esc_url( $password_url ) . '">Senha</a>';
	echo '</nav>';
	if ( 'password' === $active_tab && function_exists( 'wc_lostpassword_url' ) ) {
		echo '<p class="pw-password-recovery">Não lembra sua senha? <a href="' . esc_url( wc_lostpassword_url() ) . '">Recupere sua senha aqui</a>.</p>';
	}
}

function pw_printway_render_address_type_tabs() {
	if ( ! function_exists( 'wc_get_endpoint_url' ) || ! function_exists( 'wc_get_page_permalink' ) ) {
		return;
	}

	$my_account_url = wc_get_page_permalink( 'myaccount' );
	$billing_url    = add_query_arg( 'aba', 'endereco', wc_get_endpoint_url( 'edit-address', 'billing', $my_account_url ) );
	$shipping_url   = add_query_arg( 'aba', 'endereco', wc_get_endpoint_url( 'edit-address', 'shipping', $my_account_url ) );
	$current        = (string) get_query_var( 'edit-address' );
	$request_path   = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$current        = ( in_array( $current, array( 'shipping', 'entrega' ), true ) || false !== strpos( $request_path, '/shipping' ) || false !== strpos( $request_path, '/entrega' ) ) ? 'shipping' : 'billing';

	echo '<nav class="pw-address-tabs" aria-label="Tipo de endereço">';
	echo '<a class="' . ( 'billing' === $current ? 'is-active' : '' ) . '" href="' . esc_url( $billing_url ) . '">Endereço de cobrança</a>';
	echo '<a class="' . ( 'shipping' === $current ? 'is-active' : '' ) . '" href="' . esc_url( $shipping_url ) . '">Endereço de entrega</a>';
	echo '</nav>';
}

/* CPF/CNPJ: validação no servidor para não depender apenas do navegador. */
add_action( 'woocommerce_after_save_address_validation', 'pw_printway_validate_billing_document', 10, 4 );
function pw_printway_validate_billing_document( $user_id, $address_type, $address, $customer ) {
	if ( 'billing' !== $address_type ) {
		return;
	}

	$person_type = '';
	foreach ( array( 'billing_persontype', 'billing_person_type', 'billing_tipo_pessoa', 'billing_tipo_de_pessoa' ) as $field ) {
		if ( isset( $_POST[ $field ] ) ) {
			$person_type = strtolower( sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
			break;
		}
	}

	$is_company = in_array( $person_type, array( '2', 'juridica', 'jurídica', 'pessoa juridica', 'pessoa jurídica', 'cnpj' ), true );
	if ( ! $is_company && ! empty( $_POST['billing_cnpj'] ) ) {
		$is_company = true;
	}
	$document   = '';
	$document_type = $is_company ? 'CNPJ' : 'CPF';
	$fields = $is_company ? array( 'billing_cnpj', 'cnpj', 'billing_cpfcnpj', 'billing_cpf_cnpj' ) : array( 'billing_cpf', 'cpf', 'billing_cpfcnpj', 'billing_cpf_cnpj' );

	foreach ( $fields as $field ) {
		if ( isset( $_POST[ $field ] ) && '' !== trim( (string) $_POST[ $field ] ) ) {
			$document = preg_replace( '/\\D+/', '', (string) wp_unslash( $_POST[ $field ] ) );
			break;
		}
	}

	if ( '' === $document ) {
		wc_add_notice( 'Informe o ' . $document_type . ' para salvar o endereço.', 'error' );
		return;
	}

	$valid = $is_company ? pw_printway_is_valid_cnpj( $document ) : pw_printway_is_valid_cpf( $document );
	if ( ! $valid ) {
		wc_add_notice( 'O ' . $document_type . ' informado é inválido. Verifique os números e tente novamente.', 'error' );
	}
}

function pw_printway_is_valid_cpf( $cpf ) {
	$cpf = preg_replace( '/\\D+/', '', (string) $cpf );
	if ( 11 !== strlen( $cpf ) || preg_match( '/^(\\d)\\1{10}$/', $cpf ) ) {
		return false;
	}
	for ( $position = 9; $position <= 10; $position++ ) {
		$sum = 0;
		for ( $index = 0; $index < $position; $index++ ) {
			$sum += (int) $cpf[ $index ] * ( ( $position + 1 ) - $index );
		}
		$digit = ( $sum * 10 ) % 11;
		if ( 10 === $digit ) {
			$digit = 0;
		}
		if ( $digit !== (int) $cpf[ $position ] ) {
			return false;
		}
	}
	return true;
}

function pw_printway_is_valid_cnpj( $cnpj ) {
	$cnpj = preg_replace( '/\\D+/', '', (string) $cnpj );
	if ( 14 !== strlen( $cnpj ) || preg_match( '/^(\\d)\\1{13}$/', $cnpj ) ) {
		return false;
	}
	$weights = array( array( 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2 ), array( 6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2 ) );
	for ( $digit_index = 0; $digit_index < 2; $digit_index++ ) {
		$sum = 0;
		foreach ( $weights[ $digit_index ] as $index => $weight ) {
			$sum += (int) $cnpj[ $index ] * $weight;
		}
		$digit = $sum % 11;
		$digit = $digit < 2 ? 0 : 11 - $digit;
		if ( $digit !== (int) $cnpj[ 12 + $digit_index ] ) {
			return false;
		}
	}
	return true;
}

/* O celular pertence aos dados pessoais, não precisa ser repetido no endereço. */
add_action( 'woocommerce_edit_account_form', 'pw_printway_render_account_phone_field' );
function pw_printway_render_account_phone_field() {
	$user_id = get_current_user_id();
	woocommerce_form_field(
		'billing_phone',
		array(
			'type'         => 'tel',
			'label'        => 'Celular (preferencialmente WhatsApp)',
			'required'     => true,
			'input_class'  => array( 'woocommerce-Input', 'woocommerce-Input--text', 'input-text' ),
			'custom_attributes' => array(
				'inputmode' => 'numeric',
				'maxlength' => '15',
				'placeholder' => '(19) 99999-9999',
			),
		),
		$user_id ? get_user_meta( $user_id, 'billing_phone', true ) : ''
	);
}

/* Tipo de pessoa e documento pertencem aos Dados pessoais, não aos endereços. */
add_action( 'woocommerce_edit_account_form', 'pw_printway_render_account_document_fields', 15 );
function pw_printway_render_account_document_fields() {
	$user_id     = get_current_user_id();
	$person_type = (string) get_user_meta( $user_id, 'billing_persontype', true );
	$is_company  = in_array( strtolower( $person_type ), array( '2', 'juridica', 'jurídica', 'pessoa juridica', 'pessoa jurídica', 'cnpj' ), true );
	if ( ! $is_company && '' === $person_type && '' !== get_user_meta( $user_id, 'billing_cnpj', true ) ) {
		$is_company = true;
	}
	$document    = $is_company ? get_user_meta( $user_id, 'billing_cnpj', true ) : get_user_meta( $user_id, 'billing_cpf', true );

	if ( '' === $document ) {
		$document = get_user_meta( $user_id, 'billing_cpfcnpj', true );
	}

	echo '<div class="pw-person-document-fields">';
	woocommerce_form_field(
		'pw_person_type',
		array(
			'type'     => 'select',
			'label'    => 'Tipo de pessoa',
			'required' => true,
			'options'  => array( '1' => 'Pessoa Física', '2' => 'Pessoa Jurídica' ),
		),
		$is_company ? '2' : '1'
	);
	woocommerce_form_field(
		'pw_document',
		array(
			'type'              => 'text',
			'label'             => $is_company ? 'CNPJ' : 'CPF',
			'required'          => true,
			'input_class'       => array( 'woocommerce-Input', 'woocommerce-Input--text', 'input-text' ),
			'custom_attributes' => array( 'inputmode' => 'numeric', 'maxlength' => $is_company ? '18' : '14' ),
		),
		$document
	);
	echo '</div>';
}

add_action( 'woocommerce_save_account_details', 'pw_printway_save_account_phone_field', 20 );
function pw_printway_save_account_phone_field( $user_id ) {
	if ( isset( $_POST['billing_phone'] ) ) {
		update_user_meta( $user_id, 'billing_phone', wc_clean( wp_unslash( $_POST['billing_phone'] ) ) );
	}
	if ( isset( $_POST['pw_person_type'], $_POST['pw_document'] ) ) {
		$is_company = '2' === (string) wp_unslash( $_POST['pw_person_type'] );
		$document   = preg_replace( '/\D+/', '', (string) wp_unslash( $_POST['pw_document'] ) );
		update_user_meta( $user_id, 'billing_persontype', $is_company ? '2' : '1' );
		update_user_meta( $user_id, 'billing_person_type', $is_company ? 'juridica' : 'fisica' );
		update_user_meta( $user_id, 'billing_cpfcnpj', $document );
		if ( $is_company ) {
			update_user_meta( $user_id, 'billing_cnpj', $document );
		} else {
			update_user_meta( $user_id, 'billing_cpf', $document );
		}
	}
}

add_action( 'woocommerce_save_account_details_errors', 'pw_printway_validate_account_phone_field', 10, 2 );
function pw_printway_validate_account_phone_field( $errors, $user ) {
	/* Trocar a senha não deve exigir nova validação dos dados pessoais. */
	if ( ! empty( $_POST['password_1'] ) || ! empty( $_POST['password_2'] ) || ! empty( $_POST['password_current'] ) ) {
		return;
	}
	$phone = isset( $_POST['billing_phone'] ) ? preg_replace( '/\\D+/', '', (string) wp_unslash( $_POST['billing_phone'] ) ) : '';
	if ( '' === $phone ) {
		$errors->add( 'billing_phone_required', 'Informe seu celular, preferencialmente um número de WhatsApp.' );
		return;
	}
	if ( 11 !== strlen( $phone ) || ! preg_match( '/^[1-9]{2}9[0-9]{8}$/', $phone ) ) {
		$errors->add( 'billing_phone_invalid', 'Informe um celular válido no formato (XX) 99999-9999.' );
	}
	$person_type = isset( $_POST['pw_person_type'] ) ? (string) wp_unslash( $_POST['pw_person_type'] ) : '';
	$document    = isset( $_POST['pw_document'] ) ? preg_replace( '/\D+/', '', (string) wp_unslash( $_POST['pw_document'] ) ) : '';
	if ( ! in_array( $person_type, array( '1', '2' ), true ) ) {
		$errors->add( 'pw_person_type_required', 'Selecione o tipo de pessoa.' );
	} elseif ( '' === $document ) {
		$errors->add( 'pw_document_required', 'Informe o ' . ( '2' === $person_type ? 'CNPJ' : 'CPF' ) . '.' );
	} elseif ( ( '2' === $person_type && ! pw_printway_is_valid_cnpj( $document ) ) || ( '1' === $person_type && ! pw_printway_is_valid_cpf( $document ) ) ) {
		$errors->add( 'pw_document_invalid', 'O ' . ( '2' === $person_type ? 'CNPJ' : 'CPF' ) . ' informado é inválido.' );
	}
}

/* Mantém a pessoa na aba Senha depois de salvar. */
add_filter( 'woocommerce_get_endpoint_url', 'pw_printway_keep_password_tab_after_save', 20, 4 );
function pw_printway_keep_password_tab_after_save( $url, $endpoint, $value, $permalink ) {
	if ( 'edit-account' === $endpoint && ! empty( $_POST['save_account_details'] ) && ( ! empty( $_POST['password_1'] ) || ! empty( $_POST['password_2'] ) || ! empty( $_POST['password_current'] ) ) ) {
		return add_query_arg( 'aba', 'senha', $url );
	}
	return $url;
}

/*
 * Processa a aba Senha antes do manipulador padrão do WooCommerce.
 * O sucesso só é exibido depois de conferir o hash realmente salvo.
 */
add_action( 'wp_loaded', 'pw_printway_save_password_tab', 5 );
function pw_printway_save_password_tab() {
	if (
		! is_user_logged_in() ||
		'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? (string) $_SERVER['REQUEST_METHOD'] : '' ) ||
		empty( $_POST['save_account_details'] ) ||
		! isset( $_GET['aba'] ) ||
		'senha' !== sanitize_key( wp_unslash( $_GET['aba'] ) )
	) {
		return;
	}

	$nonce = isset( $_POST['save-account-details-nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['save-account-details-nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'save_account_details' ) ) {
		wp_die( 'Não foi possível validar esta solicitação. Atualize a página e tente novamente.' );
	}

	$user_id          = get_current_user_id();
	$user             = get_userdata( $user_id );
	$current_password = isset( $_POST['password_current'] ) ? (string) wp_unslash( $_POST['password_current'] ) : '';
	$new_password     = isset( $_POST['password_1'] ) ? (string) wp_unslash( $_POST['password_1'] ) : '';
	$confirm_password = isset( $_POST['password_2'] ) ? (string) wp_unslash( $_POST['password_2'] ) : '';
	$redirect_url     = add_query_arg( 'aba', 'senha', wc_get_endpoint_url( 'edit-account', '', wc_get_page_permalink( 'myaccount' ) ) );

	if ( '' === $current_password || '' === $new_password || '' === $confirm_password ) {
		wc_add_notice( 'Preencha a senha atual, a nova senha e a confirmação.', 'error' );
		wp_safe_redirect( $redirect_url );
		exit;
	}
	if ( ! $user || ! wp_check_password( $current_password, $user->user_pass, $user_id ) ) {
		wc_add_notice( 'A senha atual está incorreta.', 'error' );
		wp_safe_redirect( $redirect_url );
		exit;
	}
	if ( $new_password !== $confirm_password ) {
		wc_add_notice( 'A nova senha e a confirmação não são iguais.', 'error' );
		wp_safe_redirect( $redirect_url );
		exit;
	}
	if ( strlen( $new_password ) < 8 ) {
		wc_add_notice( 'A nova senha deve ter pelo menos 8 caracteres.', 'error' );
		wp_safe_redirect( $redirect_url );
		exit;
	}
	if ( wp_check_password( $new_password, $user->user_pass, $user_id ) ) {
		wc_add_notice( 'Escolha uma senha diferente da senha atual.', 'error' );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	wp_set_password( $new_password, $user_id );
	clean_user_cache( $user_id );
	$updated_user = get_userdata( $user_id );
	if ( ! $updated_user || ! wp_check_password( $new_password, $updated_user->user_pass, $user_id ) ) {
		wc_add_notice( 'A senha não pôde ser confirmada após o salvamento. Tente novamente.', 'error' );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/* Mantém a sessão atual válida depois da alteração confirmada. */
	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id, true, is_ssl() );
	wc_add_notice( 'Senha alterada e confirmada com sucesso.', 'success' );
	wp_safe_redirect( add_query_arg( 'senha_atualizada', '1', $redirect_url ) );
	exit;
}

add_action( 'wp_enqueue_scripts', 'pw_printway_account_tabs_assets', 30 );
function pw_printway_account_tabs_assets() {
	if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
		return;
	}

	$css = '
		body.woocommerce-lost-password .woocommerce-MyAccount-navigation{display:none!important}
		body.woocommerce-lost-password .woocommerce-MyAccount-content{float:none!important;width:100%!important;margin:0!important}
		body.woocommerce-lost-password .woocommerce{max-width:620px!important;margin:52px auto 70px!important;padding:0 18px;box-sizing:border-box}
		body.woocommerce-lost-password .woocommerce-ResetPassword,body.woocommerce-lost-password .pw-lost-password-header{display:block!important;float:none!important;width:640px!important;max-width:calc(100vw - 36px)!important;margin-left:auto!important;margin-right:auto!important;box-sizing:border-box!important}
		body.woocommerce-lost-password .woocommerce-ResetPassword{margin-top:0!important;margin-bottom:0!important;padding:0 34px 34px!important;border:1px solid #e4d5c9!important;border-top:0!important;border-radius:0 0 14px 14px!important;background:#fff!important;box-shadow:0 7px 22px rgba(0,0,0,.11)!important}
		body.woocommerce-lost-password .pw-lost-password-header{margin-top:0!important;margin-bottom:0!important;padding:32px 34px 20px;border:1px solid #e4d5c9;border-bottom:0;border-radius:14px 14px 0 0;background:linear-gradient(135deg,#fff8f1,#fff)}
		body.woocommerce-lost-password .pw-lost-password-header span{display:block;margin-bottom:8px;color:#963d00;font-size:12px;font-weight:800;letter-spacing:.08em}
		body.woocommerce-lost-password .pw-lost-password-header h2{margin:0 0 9px;color:#242424;font-size:28px}
		body.woocommerce-lost-password .pw-lost-password-header p{margin:0;color:#666;line-height:1.55}
		body.woocommerce-lost-password .pw-password-reset-wait{position:fixed;top:50%;left:50%;z-index:99999;width:560px;max-width:calc(100vw - 32px);box-sizing:border-box;margin:0;padding:17px 20px;border:1px solid #efbd5d;border-radius:11px;background:#fffaf0;color:#5b3b00;box-shadow:0 14px 32px rgba(79,48,7,.24);transform:translate(-50%,-50%)}
		body.woocommerce-lost-password .pw-password-reset-wait strong{display:block;color:#963d00;font-size:16px}body.woocommerce-lost-password .pw-password-reset-wait p{margin:8px 0 0;line-height:1.45}
		body.woocommerce-lost-password .woocommerce-ResetPassword button[disabled],body.woocommerce-lost-password .woocommerce-ResetPassword input[disabled]{opacity:.55;cursor:not-allowed}
		body.woocommerce-lost-password .woocommerce-ResetPassword>p:first-child{display:none}
		body.woocommerce-lost-password .woocommerce-ResetPassword .form-row{margin:0 0 21px!important}
		body.woocommerce-lost-password .woocommerce-ResetPassword label{display:block;margin-bottom:8px;color:#4a4a4a;font-weight:700}
		body.woocommerce-lost-password .woocommerce-ResetPassword input.input-text{display:block!important;width:100%!important;min-height:52px!important;padding:12px 14px!important;border:1px solid #c9c9c9!important;border-radius:8px!important;background:#fff!important;box-sizing:border-box!important}
		body.woocommerce-lost-password .woocommerce-ResetPassword button[type=submit]{min-height:48px;padding:11px 25px!important;border-radius:8px!important;background:#963d00!important;color:#fff!important;font-weight:800!important}
		body.woocommerce-lost-password .woocommerce-ResetPassword button[type=submit]:hover{background:#6f2d00!important}
		body.woocommerce-account:not(.logged-in) .woocommerce{max-width:900px;margin-left:auto;margin-right:auto}
		body.woocommerce-account:not(.logged-in) .pw-auth-tabs{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin:28px 0 0;padding:6px;border:1px solid #e4d5c9;border-radius:12px 12px 0 0;background:#f5eee8}
		body.woocommerce-account:not(.logged-in) .pw-auth-tab{min-height:52px;margin:0!important;padding:12px 18px!important;border:0!important;border-radius:8px!important;background:transparent!important;box-shadow:none!important;color:#643000!important;font-family:inherit!important;font-size:18px!important;font-weight:800!important;line-height:1.2!important;cursor:pointer}
		body.woocommerce-account:not(.logged-in) .pw-auth-tab.is-active{background:#963d00!important;color:#fff!important;box-shadow:0 3px 9px rgba(103,44,0,.22)!important}
		body.woocommerce-account:not(.logged-in) #customer_login{display:block!important;margin:0!important;padding:0!important;border:1px solid #e4d5c9;border-top:0;border-radius:0 0 12px 12px;background:#fff;box-shadow:0 5px 18px rgba(0,0,0,.08)}
		body.woocommerce-account:not(.logged-in) #customer_login:before,body.woocommerce-account:not(.logged-in) #customer_login:after{display:none!important}
		body.woocommerce-account:not(.logged-in) #customer_login>.u-column1,body.woocommerce-account:not(.logged-in) #customer_login>.u-column2{display:none!important;float:none!important;width:100%!important;margin:0!important;padding:30px!important;box-sizing:border-box;visibility:hidden;opacity:0}
		body.woocommerce-account:not(.logged-in) #customer_login>.is-active{display:block!important;visibility:visible;opacity:1;animation:pwAuthFade .18s ease}
		body.woocommerce-account:not(.logged-in) #customer_login h2{display:none!important}
		body.woocommerce-account:not(.logged-in) #customer_login form.login,body.woocommerce-account:not(.logged-in) #customer_login form.register{max-width:100%;margin:0!important;padding:0!important;border:0!important;background:transparent!important;box-shadow:none!important}
		body.woocommerce-account:not(.logged-in) #customer_login .form-row{margin-bottom:18px}
		body.woocommerce-account:not(.logged-in) #customer_login input.input-text{min-height:52px;border-radius:8px;box-sizing:border-box}
		body.woocommerce-account:not(.logged-in) #customer_login button[type=submit]{min-height:48px;padding:11px 25px!important;border-radius:8px!important;background:#963d00!important;color:#fff!important;font-weight:800!important}
		body.woocommerce-account:not(.logged-in) #customer_login button[type=submit]:hover{background:#6f2d00!important}
		@keyframes pwAuthFade{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:none}}
		.pw-account-tabs{display:flex;gap:10px;flex-wrap:wrap;margin:0 0 26px;padding:0;border-bottom:1px solid #dedede}
		.pw-account-tabs a{display:block;padding:12px 18px;margin:0 0 -1px;border:1px solid #dedede;border-bottom:0;border-radius:8px 8px 0 0;background:#f6f6f6;color:#643000;text-decoration:none;font-weight:700}
		.pw-account-tabs a.is-active{background:#963d00;color:#fff;border-color:#963d00}
		.pw-account-tabs a:hover{background:#6f2d00;color:#fff}
		.pw-password-recovery{margin:-10px 0 20px;color:#666;font-size:14px}
		.pw-password-recovery a{color:#963d00;font-weight:700;text-decoration:none}
		.pw-password-recovery a:hover{text-decoration:underline}
		.pw-address-tabs{display:flex;gap:10px;flex-wrap:wrap;margin:-10px 0 22px}
		.pw-address-tabs a{padding:9px 14px;border:1px solid #cfcfcf;border-radius:7px;background:#fff;color:#643000;text-decoration:none;font-weight:700}
		.pw-address-tabs a.is-active,.pw-address-tabs a:hover{background:#f4e4d7;border-color:#963d00;color:#643000}
		.pw-address-mode{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:0 0 20px;padding:12px 14px;border:1px solid #ead6c8;border-radius:9px;background:#fff9f5;color:#643000}.pw-address-mode strong{font-weight:800}.pw-address-mode span{color:#6b625d;font-size:13px}.pw-address-mode a,.pw-address-mode button{margin-left:auto;padding:9px 13px;border:1px solid #963d00;border-radius:7px;background:#fff;color:#963d00;text-decoration:none;font:inherit;font-size:13px;font-weight:800;cursor:pointer}.pw-address-mode a:hover,.pw-address-mode button:hover{background:#963d00;color:#fff}.pw-address-mode form{margin:0 0 0 auto}
		.pw-copy-billing-address{display:inline-flex;align-items:center;gap:8px;margin:0 0 20px;padding:11px 15px;border:1px solid #963d00;border-radius:7px;background:#fff;color:#963d00;font-weight:700;cursor:pointer}
		.pw-copy-billing-address:hover{background:#963d00;color:#fff}
		.pw-account-dashboard{max-width:980px;margin:0 auto;padding:4px 0 18px}
		.pw-account-dashboard__welcome{padding:26px 28px;margin-bottom:20px;border:1px solid #f0d5bd;border-radius:12px;background:linear-gradient(135deg,#fff8f1,#fff)}
		.pw-account-dashboard__eyebrow{display:block;margin-bottom:7px;color:#963d00;font-size:12px;font-weight:800;letter-spacing:.08em}
		.pw-account-dashboard__welcome h2{margin:0 0 7px;color:#202020;font-size:29px}
		.pw-account-dashboard__welcome p{margin:0;color:#5d5d5d}
		.pw-account-dashboard__session{display:flex;flex-wrap:wrap;gap:5px 11px;color:#777;font-size:11px;line-height:1.45}
		.pw-account-dashboard__session a{display:inline!important;margin:0!important;padding:0!important;border:0!important;border-radius:0!important;background:transparent!important;box-shadow:none!important;color:#963d00!important;font-family:inherit!important;font-size:11px!important;font-weight:700!important;line-height:1.45!important;text-decoration:none!important;text-transform:none!important}
		.pw-account-dashboard__session a:hover{text-decoration:underline}
		.pw-account-dashboard__session span{color:#777}
		.pw-account-dashboard__metrics{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px}
		.pw-account-dashboard__metrics article{min-height:122px;padding:18px;border:1px solid #e4e4e4;border-radius:10px;background:#fff;box-shadow:0 2px 7px rgba(0,0,0,.05)}
		.pw-account-dashboard__metrics span,.pw-account-dashboard__metrics strong,.pw-account-dashboard__metrics small{display:block}
		.pw-account-dashboard__metrics span{margin-bottom:9px;color:#963d00;font-size:11px;font-weight:800;letter-spacing:.06em}
		.pw-account-dashboard__metrics strong{margin-bottom:7px;color:#292929;font-size:17px;line-height:1.35}
		.pw-account-dashboard__metrics small{color:#686868;font-size:13px;line-height:1.4}
		.pw-account-dashboard__last-orders{min-height:0!important}
		.pw-account-dashboard__last-orders>div+div,.pw-account-dashboard__points>div+div{margin-top:12px;padding-top:12px;border-top:1px solid #eeeeee}
		.pw-account-dashboard__last-orders b,.pw-account-dashboard__points b{display:block;margin-bottom:3px;color:#4a4a4a;font-size:13px}
		.pw-account-dashboard__points-total{margin:-2px 0 12px!important;color:#292929!important;font-size:20px!important}
		.pw-account-dashboard__points-value{display:inline-block!important;white-space:nowrap;font-weight:700;color:#963d00}
		.pw-account-dashboard__last-orders a,.pw-account-dashboard__status a{color:#963d00;font-weight:700;text-decoration:none}
		.pw-account-dashboard__last-orders a:hover,.pw-account-dashboard__status a:hover{text-decoration:underline}
		.pw-account-dashboard__status ul{margin:8px 0 0 20px;color:#686868}
		.pw-account-dashboard__status li{margin:4px 0}
		.pw-dashboard-status{display:inline-grid;place-items:center;width:22px;height:22px;margin:0 8px 0 0;border-radius:50%;font-size:15px;font-style:normal;line-height:1;vertical-align:1px}
		.pw-dashboard-status.is-ok{background:#dff3e3;color:#1f7135}
		.pw-dashboard-status.is-pending{background:#fde4e1;color:#b43a2d}
		.pw-account-dashboard__account-type{border-left:4px solid #963d00!important}
		.pw-account-dashboard__wishlist{border-left:4px solid #d19a00!important}
		.pw-account-dashboard__account-type form{margin:12px 0 0}
		.pw-request-reseller{width:auto!important;min-height:40px!important;padding:9px 13px!important;border:0!important;border-radius:7px!important;background:#963d00!important;color:#fff!important;font-size:13px!important;font-weight:700!important;line-height:1.2!important;box-shadow:none!important;cursor:pointer}
		.pw-request-reseller:hover{background:#6f2d00!important;color:#fff!important}
		.pw-account-dashboard__reminder{display:flex;gap:10px;align-items:flex-start;margin-top:16px;padding:15px 18px;border-radius:10px;background:#eff8f1;border:1px solid #c9e6ce;color:#285c32}
		.pw-account-dashboard__reminder b{white-space:nowrap}
		.woocommerce-account .woocommerce-MyAccount-navigation ul{margin:0;padding:8px;overflow:hidden;border:1px solid #e3d8d0;border-radius:12px;background:#fff;box-shadow:0 2px 9px rgba(0,0,0,.06);list-style:none}
		.woocommerce-account .woocommerce-MyAccount-navigation li{margin:0!important;padding:0!important;border:0!important}
		.woocommerce-account .woocommerce-MyAccount-navigation li+li{margin-top:3px!important}
		.woocommerce-account .woocommerce-MyAccount-navigation li a{display:block!important;padding:13px 15px!important;border:1px solid transparent!important;border-radius:8px!important;background:transparent!important;color:#4c2b17!important;font-weight:700!important;line-height:1.25!important;text-decoration:none!important;transition:background .15s ease,color .15s ease,border-color .15s ease!important}
		.woocommerce-account .woocommerce-MyAccount-navigation li a:hover{border-color:#eed7c7!important;background:#fff4eb!important;color:#963d00!important}
		.woocommerce-account .woocommerce-MyAccount-navigation li.is-active a{border-color:#963d00!important;background:#963d00!important;color:#fff!important;box-shadow:0 3px 7px rgba(103,44,0,.2)!important}
		.woocommerce-account .woocommerce-MyAccount-navigation li a:before{display:inline-block;width:24px;margin-right:8px;font-size:16px;text-align:center;vertical-align:-1px}
		.woocommerce-account .woocommerce-MyAccount-navigation-link--dashboard a:before{content:"⌂"}.woocommerce-account .woocommerce-MyAccount-navigation-link--edit-account a:before{content:"✎"}.woocommerce-account .woocommerce-MyAccount-navigation-link--orders a:before{content:"▣"}.woocommerce-account .woocommerce-MyAccount-navigation-link--points a:before{content:"★"}.woocommerce-account .woocommerce-MyAccount-navigation-link--wishlist a:before{content:"♡"}.woocommerce-account .woocommerce-MyAccount-navigation-link--downloads a:before{content:"⇩"}.woocommerce-account .woocommerce-MyAccount-navigation-link--customer-logout a:before{content:"↪"}.woocommerce-account .woocommerce-MyAccount-navigation-link--printway-system a:before{content:"🖥️"}
		body.pw-account-view-password .woocommerce-MyAccount-content,body.pw-account-view-personal .woocommerce-MyAccount-content,body.pw-account-view-address .woocommerce-MyAccount-content{position:relative;min-height:190px}
		body.pw-account-view-password:not(.pw-account-form-ready) .woocommerce-MyAccount-content:after,body.pw-account-view-personal:not(.pw-account-form-ready) .woocommerce-MyAccount-content:after,body.pw-account-view-address:not(.pw-account-form-ready) .woocommerce-MyAccount-content:after{content:"Processando…";position:absolute;top:16px;left:50%;display:flex;align-items:center;justify-content:center;min-width:155px;min-height:42px;padding:0 16px;border:1px solid #e4d5c9;border-radius:9px;background:#fff;color:#963d00;font-size:13px;font-weight:700;box-shadow:0 8px 22px rgba(71,42,18,.18);transform:translateX(-50%);z-index:5}
		body.pw-account-view-password:not(.pw-account-form-ready) .woocommerce-MyAccount-content:before,body.pw-account-view-personal:not(.pw-account-form-ready) .woocommerce-MyAccount-content:before,body.pw-account-view-address:not(.pw-account-form-ready) .woocommerce-MyAccount-content:before{content:"";position:absolute;top:29px;left:calc(50% - 58px);width:13px;height:13px;border:2px solid #e9c2a7;border-top-color:#963d00;border-radius:50%;animation:pwAccountSpinner .8s linear infinite;z-index:6}
		@keyframes pwAccountSpinner{to{transform:rotate(360deg)}}
		body.pw-account-view-password .woocommerce-MyAccount-content form,body.pw-account-view-personal .woocommerce-MyAccount-content form,body.pw-account-view-address .woocommerce-MyAccount-content form{visibility:hidden!important;opacity:0!important}
		body.pw-account-form-ready .woocommerce-MyAccount-content form{visibility:visible!important;opacity:1!important;transition:opacity .12s ease}
		body.pw-account-view-password .woocommerce-EditAccountForm fieldset{display:grid!important;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:15px 18px}
		body.pw-account-view-personal .woocommerce-EditAccountForm fieldset{display:none}
		body.pw-account-view-password .woocommerce-EditAccountForm fieldset legend{grid-column:1/-1;width:100%}
		body.pw-account-view-password .woocommerce-EditAccountForm fieldset .pw-pass-current{grid-column:1/-1}
		body.pw-account-view-password .woocommerce-EditAccountForm fieldset .pw-pass-new{grid-column:1}
		body.pw-account-view-password .woocommerce-EditAccountForm fieldset .pw-pass-confirm{grid-column:2}
		body.pw-account-view-password .woocommerce-EditAccountForm fieldset .pw-pass-current,body.pw-account-view-password .woocommerce-EditAccountForm fieldset .pw-pass-new,body.pw-account-view-password .woocommerce-EditAccountForm fieldset .pw-pass-confirm{float:none!important;width:100%!important;margin:0!important}
		body.pw-account-view-password .woocommerce-EditAccountForm fieldset input{width:100%!important;min-height:52px;box-sizing:border-box}
		body.pw-account-view-password #billing_phone_field{display:none!important}
		body.pw-account-view-password .pw-person-document-fields{display:none!important}
		.pw-person-document-fields{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:0 18px;margin-top:18px}
		.pw-person-document-fields .form-row{float:none!important;width:100%!important}
		body.pw-account-view-address #billing_first_name_field,body.pw-account-view-address #billing_last_name_field,body.pw-account-view-address #billing_email_field,body.pw-account-view-address #billing_phone_field{display:none!important}
		body.pw-account-view-address #billing_persontype_field,body.pw-account-view-address #billing_person_type_field,body.pw-account-view-address #billing_tipo_pessoa_field,body.pw-account-view-address #billing_tipo_de_pessoa_field,body.pw-account-view-address #billing_cpf_field,body.pw-account-view-address #billing_cnpj_field,body.pw-account-view-address #billing_cpfcnpj_field,body.pw-account-view-address #billing_cpf_cnpj_field{display:none!important}
		body.pw-address-shipping #shipping_first_name_field,body.pw-address-shipping #shipping_last_name_field{display:none!important}
		@media (max-width:600px){body.woocommerce-lost-password .woocommerce{margin-top:28px!important}body.woocommerce-lost-password .pw-lost-password-header,body.woocommerce-lost-password .woocommerce-ResetPassword{padding-left:22px!important;padding-right:22px!important}body.woocommerce-lost-password .pw-lost-password-header h2{font-size:24px}body.woocommerce-account:not(.logged-in) .pw-auth-tab{font-size:15px!important}body.woocommerce-account:not(.logged-in) #customer_login>.u-column1,body.woocommerce-account:not(.logged-in) #customer_login>.u-column2{padding:22px 18px!important}.pw-account-tabs{gap:6px}.pw-account-tabs a,.pw-address-tabs a{flex:1 1 100%;text-align:center;border:1px solid #dedede;border-radius:7px;margin:0}.pw-account-tabs a.is-active{border-color:#963d00}.pw-account-dashboard__welcome{padding:22px 18px}.pw-account-dashboard__welcome h2{font-size:24px}.pw-account-dashboard__metrics{grid-template-columns:1fr}.pw-account-dashboard__reminder{display:block}.pw-account-dashboard__reminder b{display:block;margin-bottom:5px}.woocommerce-account .woocommerce-MyAccount-navigation ul{margin-bottom:18px}.woocommerce-account .woocommerce-MyAccount-navigation li a{text-align:center!important}body.pw-account-view-password .woocommerce-EditAccountForm fieldset{grid-template-columns:1fr}body.pw-account-view-password .woocommerce-EditAccountForm fieldset .pw-pass-current,body.pw-account-view-password .woocommerce-EditAccountForm fieldset .pw-pass-new,body.pw-account-view-password .woocommerce-EditAccountForm fieldset .pw-pass-confirm{grid-column:1}.pw-person-document-fields{grid-template-columns:1fr}.pw-address-mode a,.pw-address-mode form{width:100%;margin-left:0}.pw-address-mode a,.pw-address-mode button{display:block;box-sizing:border-box;width:100%;text-align:center}}
	';
	wp_register_style( 'pw-printway-account-tabs', false, array(), '1.4.3' );
	wp_enqueue_style( 'pw-printway-account-tabs' );
	wp_add_inline_style( 'pw-printway-account-tabs', $css );

	$script = "document.addEventListener('DOMContentLoaded',function(){var form=document.querySelector('form.woocommerce-EditAccountForm');var tab=(new URLSearchParams(window.location.search)).get('aba');if(form){var passwordFields=form.querySelectorAll('fieldset input');if(tab==='senha'){document.body.classList.add('pw-account-password-view');passwordFields.forEach(function(field){field.disabled=false;});['account_first_name','account_last_name','account_display_name','account_email'].forEach(function(id){var field=document.getElementById(id);var row=field&&field.closest('.form-row');if(row){row.style.display='none';}});var title=document.querySelector('.woocommerce-MyAccount-content > h2');if(title){title.textContent='Alterar senha';}}else{document.body.classList.add('pw-account-personal-view');passwordFields.forEach(function(field){field.disabled=true;});var personalTitle=document.querySelector('.woocommerce-MyAccount-content > h2');if(personalTitle){personalTitle.textContent='Dados pessoais';}}}var cep=document.querySelector('#billing_postcode,[name=billing_postcode]');if(!cep){return;}['billing_first_name','billing_last_name','billing_email'].forEach(function(id){var field=document.getElementById(id);var row=field&&field.closest('.form-row');if(row){row.style.display='none';}});var street=document.querySelector('#billing_address_1,[name=billing_address_1]');if(street){street.setAttribute('placeholder','Ex.: Rua das Flores');var streetRow=street.closest('.form-row');var hint=streetRow&&streetRow.querySelector('.description');if(hint){hint.textContent='Informe somente o nome da rua. Preencha o número no campo ao lado.';}}function setValue(selector,value){var field=document.querySelector(selector);if(!field||!value){return;}field.value=value;field.dispatchEvent(new Event('change',{bubbles:true}));}function buscarCep(){var numero=String(cep.value||'').replace(/\\D/g,'');if(numero.length!==8){return;}cep.setAttribute('aria-busy','true');fetch('https://viacep.com.br/ws/'+numero+'/json/').then(function(response){return response.json();}).then(function(data){if(!data||data.erro){return;}setValue('#billing_address_1,[name=billing_address_1]',data.logradouro);setValue('#billing_neighborhood,[name=billing_neighborhood]',data.bairro);setValue('#billing_city,[name=billing_city]',data.localidade);setValue('#billing_state,[name=billing_state]',data.uf);var number=document.querySelector('#billing_number,[name=billing_number]');if(number&&!number.value){number.focus();}}).catch(function(){}).finally(function(){cep.removeAttribute('aria-busy');});}cep.addEventListener('keydown',function(event){if(event.key==='Enter'){event.preventDefault();buscarCep();}});});";
	wp_register_script( 'pw-printway-account-tabs', false, array(), '1.4.3', true );
	wp_enqueue_script( 'pw-printway-account-tabs' );
	$billing_data = array();
	if ( get_current_user_id() ) {
		foreach ( array( 'first_name', 'last_name', 'company', 'country', 'address_1', 'number', 'address_2', 'neighborhood', 'city', 'state', 'postcode', 'phone' ) as $field ) {
			$billing_data[ $field ] = get_user_meta( get_current_user_id(), 'billing_' . $field, true );
		}
	}
	wp_add_inline_script( 'pw-printway-account-tabs', 'window.pwPrintwayBilling = ' . wp_json_encode( $billing_data ) . ';', 'before' );
	$cep_confirmation_script = "document.addEventListener('DOMContentLoaded',function(){function getField(prefix,key){return document.querySelector('#'+prefix+'_'+key+', [name='+prefix+'_'+key+']');}function setValue(field,value){if(!field||!value){return;}field.value=value;field.dispatchEvent(new Event('change',{bubbles:true}));}function setupCep(prefix){var cep=getField(prefix,'postcode');if(!cep){return;}var row=cep.closest('.form-row');var label=row&&row.querySelector('label');if(label){var required=label.querySelector('.required');label.textContent='CEP (digite e pressione ENTER para preencher os campos automaticamente)';if(required){label.appendChild(document.createTextNode(' '));label.appendChild(required);}}var hint=row&&row.querySelector('.description');if(hint){hint.textContent='A busca preencherá Endereço, Bairro, Cidade e Estado.';}function search(){var number=String(cep.value||'').replace(/\\D/g,'');if(number.length!==8){return;}var updates=[{key:'address_1',label:'Endereço',value:''},{key:'neighborhood',label:'Bairro',value:''},{key:'city',label:'Cidade',value:''},{key:'state',label:'Estado',value:''}];cep.setAttribute('aria-busy','true');fetch('https://viacep.com.br/ws/'+number+'/json/').then(function(response){return response.json();}).then(function(data){if(!data||data.erro){return;}updates[0].value=data.logradouro;updates[1].value=data.bairro;updates[2].value=data.localidade;updates[3].value=data.uf;var occupied=updates.filter(function(item){var field=getField(prefix,item.key);return field&&String(field.value||'').trim()!=='';}).map(function(item){return item.label;});if(occupied.length&&!window.confirm('O novo CEP preencherá: Endereço, Bairro, Cidade e Estado.\\n\\nCampos que já possuem dados e serão substituídos: '+occupied.join(', ')+'.\\n\\nDeseja continuar?')){return;}updates.forEach(function(item){setValue(getField(prefix,item.key),item.value);});var houseNumber=getField(prefix,'number');if(houseNumber&&!houseNumber.value){houseNumber.focus();}}).catch(function(){}).finally(function(){cep.removeAttribute('aria-busy');});}document.addEventListener('keydown',function(event){if(event.target!==cep||event.key!=='Enter'){return;}event.preventDefault();event.stopImmediatePropagation();search();},true);}setupCep('billing');setupCep('shipping');});";
	wp_add_inline_script( 'pw-printway-account-tabs', $cep_confirmation_script );
	wp_add_inline_script( 'pw-printway-account-tabs', $script );
	/* Organiza a senha e revela qualquer uma das três abas apenas ao fim da carga. */
	wp_add_inline_script( 'pw-printway-account-tabs', "window.addEventListener('load',function(){[['password_current','pw-pass-current'],['password_1','pw-pass-new'],['password_2','pw-pass-confirm']].forEach(function(item){var field=document.getElementById(item[0]);var row=field&&field.closest('.form-row');if(row){row.classList.add(item[1]);}});});" );
	$password_fields_script = "window.addEventListener('load',function(){var fields=[['password_current','Senha atual'],['password_1','Nova senha'],['password_2','Confirmar nova senha']];function clearPasswords(){fields.forEach(function(item){var field=document.getElementById(item[0]);if(field){field.value='';field.setAttribute('autocomplete','new-password');}});}fields.forEach(function(item){var field=document.getElementById(item[0]);if(!field){return;}field.setAttribute('autocomplete','new-password');var row=field.closest('.form-row');var label=row&&row.querySelector('label');if(label){Array.prototype.forEach.call(label.childNodes,function(node){if(node.nodeType===3&&node.nodeValue.trim()){node.nodeValue=item[1]+' ';}});}});clearPasswords();document.body.classList.add('pw-account-form-ready');setTimeout(clearPasswords,150);setTimeout(clearPasswords,600);});";
	wp_add_inline_script( 'pw-printway-account-tabs', $password_fields_script );
	$phone_script = "document.addEventListener('DOMContentLoaded',function(){var phone=document.querySelector('#billing_phone,[name=billing_phone]');if(!phone){return;}function mask(value){var digits=String(value||'').replace(/\\D/g,'').slice(0,11);if(digits.length<3){return digits;}if(digits.length<8){return '('+digits.slice(0,2)+') '+digits.slice(2);}return '('+digits.slice(0,2)+') '+digits.slice(2,7)+'-'+digits.slice(7);}phone.value=mask(phone.value);phone.addEventListener('input',function(){phone.value=mask(phone.value);});phone.addEventListener('blur',function(){var digits=phone.value.replace(/\\D/g,'');phone.setCustomValidity(digits.length===11&&/^[1-9]{2}9[0-9]{8}$/.test(digits)?'':'Informe um celular no formato (XX) 99999-9999.');});});";
	wp_add_inline_script( 'pw-printway-account-tabs', $phone_script );
	$document_script = "document.addEventListener('DOMContentLoaded',function(){var type=document.querySelector('#pw_person_type'),documentField=document.querySelector('#pw_document');if(!type||!documentField){return;}function digits(value){return String(value||'').replace(/\\D/g,'');}function maskCpf(value){var v=digits(value).slice(0,11);return v.replace(/(\\d{3})(\\d)/,'$1.$2').replace(/(\\d{3})(\\d)/,'$1.$2').replace(/(\\d{3})(\\d{1,2})$/,'$1-$2');}function maskCnpj(value){var v=digits(value).slice(0,14);return v.replace(/^(\\d{2})(\\d)/,'$1.$2').replace(/^(\\d{2})\\.(\\d{3})(\\d)/,'$1.$2.$3').replace(/\\.(\\d{3})(\\d)/,'.$1/$2').replace(/(\\d{4})(\\d)/,'$1-$2');}function refresh(){var company=type.value==='2';var row=documentField.closest('.form-row'),label=row&&row.querySelector('label');if(label){label.childNodes.forEach(function(node){if(node.nodeType===3&&node.nodeValue.trim()){node.nodeValue=(company?'CNPJ':'CPF')+' ';}});}documentField.setAttribute('maxlength',company?'18':'14');documentField.value=company?maskCnpj(documentField.value):maskCpf(documentField.value);}type.addEventListener('change',refresh);documentField.addEventListener('input',refresh);refresh();});";
	wp_add_inline_script( 'pw-printway-account-tabs', $document_script );
	wp_add_inline_script( 'pw-printway-account-tabs', "document.addEventListener('DOMContentLoaded',function(){if((new URLSearchParams(window.location.search)).get('aba')!=='senha'){return;}['pw_person_type','pw_document'].forEach(function(id){var field=document.getElementById(id);if(field){field.disabled=true;}});});" );
	$address_script = "document.addEventListener('DOMContentLoaded',function(){var billingForm=document.querySelector('#billing_postcode,[name=billing_postcode]');var shippingField=document.querySelector('#shipping_postcode,[name=shipping_postcode]');function rowOf(field){return field&&field.closest('.form-row');}if(billingForm){['billing_first_name','billing_last_name','billing_email','billing_phone','billing_persontype','billing_person_type','billing_tipo_pessoa','billing_tipo_de_pessoa','billing_cpf','billing_cnpj','billing_cpfcnpj','billing_cpf_cnpj'].forEach(function(id){var row=rowOf(document.getElementById(id)||document.querySelector('[name='+id+']'));if(row){row.style.display='none';}});var billingTitle=document.querySelector('.woocommerce-MyAccount-content > h3');if(billingTitle){billingTitle.textContent='Endereço principal';}}if(shippingField){['shipping_first_name','shipping_last_name'].forEach(function(id){var row=rowOf(document.getElementById(id));if(row){row.style.display='none';}});var shippingTitle=document.querySelector('.woocommerce-MyAccount-content > h3');if(shippingTitle){shippingTitle.textContent='Endereço de entrega diferente';}}});";
	wp_add_inline_script( 'pw-printway-account-tabs', $address_script );
	$auth_tabs_script = "document.addEventListener('DOMContentLoaded',function(){var nav=document.querySelector('.pw-auth-tabs');var wrapper=document.getElementById('customer_login');if(!nav||!wrapper){return;}var login=wrapper.querySelector('.u-column1');var register=wrapper.querySelector('.u-column2');var buttons=nav.querySelectorAll('.pw-auth-tab');if(!login||!register){return;}login.setAttribute('role','tabpanel');register.setAttribute('role','tabpanel');function activate(target,focus){var showRegister=target==='register';login.classList.toggle('is-active',!showRegister);register.classList.toggle('is-active',showRegister);login.setAttribute('aria-hidden',showRegister?'true':'false');register.setAttribute('aria-hidden',showRegister?'false':'true');buttons.forEach(function(button){var active=button.getAttribute('data-target')===target;button.classList.toggle('is-active',active);button.setAttribute('aria-selected',active?'true':'false');});if(focus){var panel=showRegister?register:login;var field=panel.querySelector('input:not([type=hidden])');if(field){field.focus();}}}buttons.forEach(function(button){button.addEventListener('click',function(){activate(button.getAttribute('data-target'),true);});});activate(nav.getAttribute('data-default')||'login',false);});";
	wp_add_inline_script( 'pw-printway-account-tabs', $auth_tabs_script );
	$wishlist_menu_script = "document.addEventListener('DOMContentLoaded',function(){function organizeWishlist(){var nav=document.querySelector('.woocommerce-MyAccount-navigation');if(!nav){return;}var links=Array.prototype.slice.call(nav.querySelectorAll('a'));var wishLink=links.find(function(link){return /lista\\s+de\\s+desejos|wishlist/i.test((link.textContent||'').trim())||/wishlist/i.test(link.getAttribute('href')||'');});var pointsLink=nav.querySelector('.woocommerce-MyAccount-navigation-link--points a')||links.find(function(link){return /^pontos(?:\\s|\\(|$)/i.test((link.textContent||'').trim());});var wishItem=wishLink&&wishLink.closest('li'),pointsItem=pointsLink&&pointsLink.closest('li');if(!wishItem||!pointsItem||!pointsItem.parentNode){return;}wishItem.classList.add('woocommerce-MyAccount-navigation-link--wishlist');if(wishItem.parentNode!==pointsItem.parentNode||wishItem.previousElementSibling!==pointsItem){pointsItem.parentNode.insertBefore(wishItem,pointsItem.nextSibling);}}organizeWishlist();var nav=document.querySelector('.woocommerce-MyAccount-navigation');if(nav&&window.MutationObserver){new MutationObserver(organizeWishlist).observe(nav,{childList:true,subtree:true});}setTimeout(organizeWishlist,250);setTimeout(organizeWishlist,1000);});";
	wp_add_inline_script( 'pw-printway-account-tabs', $wishlist_menu_script );
}



