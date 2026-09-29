<?php
/**
 * Módulo PrintWay — Pedidos de personalizados.
 *
 * IMPORTANTE PARA IA / DEV: leia NOTAS-IA.md na raiz do plugin antes de
 * fazer qualquer alteração. Esse arquivo contém todas as regras de versão,
 * formato de entrega ZIP, convenções de código e histórico de decisões.
 * A IA deve atualizar NOTAS-IA.md ao final de cada sessão.
 *
 * @package PrintWay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'PW_PERSONALIZADOS_MODULE_LOADED' ) ) {
	return;
}

define( 'PW_PERSONALIZADOS_MODULE_LOADED', true );
define( 'PW_PERSONALIZADOS_VERSION', '1.32.404' );
define( 'PW_PERSONALIZADOS_DB_VERSION', '1.2.0' );
define( 'PW_PERSONALIZADOS_DIR', plugin_dir_path( __FILE__ ) );
define( 'PW_PERSONALIZADOS_URL', plugin_dir_url( __FILE__ ) );

/** Chaves permitidas no armazenamento compartilhado do sistema. */
function pw_personalizados_storage_keys() {
	return array(
		'pw_personalizados_order_sequence',
		'pw_personalizados_client_sequence',
		'pw_personalizados_product_sequence',
		'pw_personalizados_category_sequence',
		'pw_personalizados_unit_sequence',
		'pw_personalizados_supplier_sequence',
		'pw_personalizados_payment_method_sequence',
		'pw_personalizados_order_origin_sequence',
		'pw_personalizados_clients',
		'pw_personalizados_products',
		'pw_personalizados_categories',
		'pw_personalizados_units',
		'pw_personalizados_suppliers',
		'pw_personalizados_payment_methods',
		'pw_personalizados_order_origins',
		'pw_personalizados_dtf_costs',
		'pw_personalizados_dtf_catalog',
		'pw_personalizados_dtf_expense_categories',
		'pw_personalizados_dtf_expense_units',
		'pw_personalizados_dtf_expense_movements',
		'pw_personalizados_expense_units_migrated',
		'pw_personalizados_orders',
		'pw_personalizados_trash',
		'pw_personalizados_audit',
		'pw_personalizados_settings',
		'pw_personalizados_nfe_settings',
		'pw_personalizados_case_correction_ignored',
		'pw_personalizados_pdf_rules',
		'pw_personalizados_melhorenvio_labels',
		'pw_personalizados_nav_stats',
		'pw_personalizados_mecolour_expenses_linked',
		'pw_personalizados_dtf_cost_supplier_type_migrated',
	);
}

function pw_personalizados_storage_key_allowed( $key ) {
	if ( in_array( $key, pw_personalizados_storage_keys(), true ) ) {
		return true;
	}
	return 1 === preg_match( '/^pw_personalizados_order_sequence_\d{6}$/', $key );
}

/** Relaciona cada conjunto lógico à sua tabela própria. */
function pw_personalizados_table_map() {
	global $wpdb;
	return array(
		'pw_personalizados_clients'    => $wpdb->prefix . 'pw_personalizados_clients',
		'pw_personalizados_products'   => $wpdb->prefix . 'pw_personalizados_products',
		'pw_personalizados_categories' => $wpdb->prefix . 'pw_personalizados_categories',
		'pw_personalizados_units'      => $wpdb->prefix . 'pw_personalizados_units',
		'pw_personalizados_suppliers'  => $wpdb->prefix . 'pw_personalizados_suppliers',
		'pw_personalizados_payment_methods' => $wpdb->prefix . 'pw_personalizados_payment_methods',
		'pw_personalizados_dtf_costs'  => $wpdb->prefix . 'pw_personalizados_dtf_costs',
		'pw_personalizados_orders'     => $wpdb->prefix . 'pw_personalizados_orders',
		'pw_personalizados_trash'      => $wpdb->prefix . 'pw_personalizados_trash',
		'pw_personalizados_audit'      => $wpdb->prefix . 'pw_personalizados_audit',
	);
}

function pw_personalizados_meta_table() {
	global $wpdb;
	return $wpdb->prefix . 'pw_personalizados_meta';
}

/** Cria as tabelas do módulo sem depender de dados armazenados no navegador. */
function pw_personalizados_install_tables() {
	global $wpdb;
	if ( get_option( 'pw_personalizados_db_version' ) === PW_PERSONALIZADOS_DB_VERSION ) {
		return;
	}

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();
	foreach ( pw_personalizados_table_map() as $table ) {
		$sql = "CREATE TABLE {$table} (
			row_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			object_id varchar(191) NOT NULL,
			code varchar(100) NOT NULL DEFAULT '',
			name varchar(255) NOT NULL DEFAULT '',
			status varchar(100) NOT NULL DEFAULT '',
			event_date datetime NULL,
			total decimal(18,2) NOT NULL DEFAULT 0,
			payload longtext NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (row_id),
			UNIQUE KEY object_id (object_id),
			KEY code (code),
			KEY name (name(100)),
			KEY status (status),
			KEY event_date (event_date)
		) {$charset};";
		dbDelta( $sql );
	}

	$meta = pw_personalizados_meta_table();
	dbDelta( "CREATE TABLE {$meta} (
		meta_key varchar(191) NOT NULL,
		meta_value longtext NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (meta_key)
	) {$charset};" );
	update_option( 'pw_personalizados_db_version', PW_PERSONALIZADOS_DB_VERSION, false );
}
add_action( 'plugins_loaded', 'pw_personalizados_install_tables', 30 );

function pw_personalizados_is_collection_key( $key ) {
	return isset( pw_personalizados_table_map()[ $key ] );
}

function pw_personalizados_record_identity( $key, $record, $index ) {
	$candidates = array( 'id', 'orderNumber', 'trashId', 'recordId', 'code' );
	foreach ( $candidates as $field ) {
		if ( isset( $record[ $field ] ) && '' !== (string) $record[ $field ] ) {
			return (string) $record[ $field ];
		}
	}
	return $key . '-' . $index . '-' . wp_generate_uuid4();
}

function pw_personalizados_record_columns( $record ) {
	$name = '';
	foreach ( array( 'name', 'description', 'item', 'customerName', 'clientName', 'action' ) as $field ) {
		if ( ! empty( $record[ $field ] ) ) { $name = (string) $record[ $field ]; break; }
	}
	$code = '';
	foreach ( array( 'code', 'orderNumber', 'recordId' ) as $field ) {
		if ( ! empty( $record[ $field ] ) ) { $code = (string) $record[ $field ]; break; }
	}
	$status = isset( $record['status'] ) ? (string) $record['status'] : '';
	$date = null;
	foreach ( array( 'deliveryForecast', 'date', 'createdAt', 'at' ) as $field ) {
		if ( ! empty( $record[ $field ] ) ) {
			$timestamp = strtotime( (string) $record[ $field ] );
			if ( $timestamp ) { $date = gmdate( 'Y-m-d H:i:s', $timestamp ); break; }
		}
	}
	$total = 0;
	foreach ( array( 'total', 'totalValue', 'grandTotal', 'value' ) as $field ) {
		if ( isset( $record[ $field ] ) && is_numeric( $record[ $field ] ) ) { $total = (float) $record[ $field ]; break; }
	}
	return compact( 'name', 'code', 'status', 'date', 'total' );
}

function pw_personalizados_get_storage() {
	global $wpdb;
	pw_personalizados_install_tables();
	$storage = array();
	foreach ( pw_personalizados_table_map() as $key => $table ) {
		$rows = $wpdb->get_col( "SELECT payload FROM {$table} ORDER BY row_id ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$storage[ $key ] = array_values( array_filter( array_map( static function( $json ) {
			$value = json_decode( $json, true );
			return is_array( $value ) ? $value : null;
		}, (array) $rows ) ) );
	}
	$meta_table = pw_personalizados_meta_table();
	$meta_rows = $wpdb->get_results( "SELECT meta_key, meta_value FROM {$meta_table}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	foreach ( (array) $meta_rows as $row ) {
		$value = json_decode( $row['meta_value'], true );
		$storage[ $row['meta_key'] ] = JSON_ERROR_NONE === json_last_error() ? $value : null;
	}
	return $storage;
}

function pw_personalizados_current_user_data( $user = null ) {
	$user = $user instanceof WP_User ? $user : wp_get_current_user();
	if ( ! $user || ! $user->exists() ) {
		return null;
	}

	$roles     = (array) $user->roles;
	$role_slug = $roles ? reset( $roles ) : '';
	$role      = 'administrator' === $role_slug ? 'Administrador' : ( 'colaborador' === $role_slug ? 'Colaborador' : translate_user_role( wp_roles()->roles[ $role_slug ]['name'] ?? ucfirst( $role_slug ) ) );
	$full_name = trim( $user->first_name . ' ' . $user->last_name );
	if ( '' === $full_name ) {
		$full_name = $user->display_name;
	}

	return array(
		'id'         => (int) $user->ID,
		'login'      => $user->user_login,
		'display'    => $user->display_name,
		'full_name'  => $full_name,
		'email'      => $user->user_email,
		'role'       => $role,
		'role_slug'  => $role_slug,
		'is_admin'   => user_can( $user, 'manage_options' ),
	);
}

/** Permite o sistema somente aos perfis Administrador e Colaborador. */
function pw_personalizados_user_can_access( $user = null ) {
	$user = $user instanceof WP_User ? $user : wp_get_current_user();
	if ( ! $user || ! $user->exists() ) {
		return false;
	}
	if ( user_can( $user, 'manage_options' ) ) {
		return true;
	}
	$allowed_roles = array( 'contributor', 'colaborador', 'collaborator' );
	return (bool) array_intersect( $allowed_roles, array_map( 'sanitize_key', (array) $user->roles ) );
}

/** Interrompe chamadas internas feitas por perfis sem acesso ao sistema. */
function pw_personalizados_require_ajax_access() {
	if ( ! pw_personalizados_user_can_access() ) {
		wp_send_json_error( array( 'message' => 'Acesso permitido somente para Administradores e Colaboradores.' ), 403 );
	}
}

/**
 * Junta em uma chamada só o par que se repetia em cada handler AJAX do
 * sistema: checar permissão de acesso e validar o nonce. Variante "forte"
 * (die automático do check_ajax_referer quando o nonce falha).
 */
function pw_personalizados_ajax_guard( $nonce_action = 'pw_personalizados_storage' ) {
	pw_personalizados_require_ajax_access();
	check_ajax_referer( $nonce_action, 'nonce' );
}

/**
 * Mesma checagem, mas com mensagem amigável de "sessão expirou" em vez do
 * die padrão do WordPress — usada nos handlers que já respondiam assim.
 */
function pw_personalizados_ajax_guard_soft( $message = 'Sua sessão expirou.', $nonce_action = 'pw_personalizados_storage' ) {
	pw_personalizados_require_ajax_access();
	if ( ! check_ajax_referer( $nonce_action, 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => $message ), 403 );
	}
}

function pw_personalizados_asset_version( $relative_path ) {
	$path = PW_PERSONALIZADOS_DIR . ltrim( $relative_path, '/\\' );
	return file_exists( $path ) ? (string) filemtime( $path ) : PW_PERSONALIZADOS_VERSION;
}

/**
 * Arquivos que precisam estar íntegros antes de anunciar uma atualização.
 * Cada um começa com um comentário "PW_BUILD_VERSION: X.Y.Z". Em vez de
 * exigir que os três marcadores batam com a constante PW_PERSONALIZADOS_VERSION
 * (o que forçava reenviar pedidos.js/pedidos.css/pedidos-app.php em TODO
 * release, mesmo quando só um arquivo PHP puramente de backend mudou — só
 * pra manter o marcador em dia), agora exigimos que os três batam ENTRE SI.
 * Isso ainda pega o caso real que importa (um envio por FTP incompleto,
 * onde só parte dos arquivos do pacote chegou — os marcadores ficariam
 * diferentes um do outro) sem exigir reenviar os três toda vez que só o
 * PHP muda. Quando uma release realmente mexe em algum desses três
 * arquivos, seu marcador é bumped igual antes — só não é mais obrigatório
 * nos releases que não tocam neles.
 */
function pw_personalizados_release_manifest() {
	return array(
		'assets/pedidos.js',
		'assets/pedidos.css',
		'templates/pedidos-app.php',
	);
}

function pw_personalizados_release_ready() {
	$seen_marker = null;
	foreach ( pw_personalizados_release_manifest() as $relative_path ) {
		$path = PW_PERSONALIZADOS_DIR . ltrim( $relative_path, '/\\' );
		if ( ! is_readable( $path ) || ! is_file( $path ) ) {
			return false;
		}
		$head = file_get_contents( $path, false, null, 0, 4096 );
		if ( false === $head || ! preg_match( '/PW_BUILD_VERSION:\s*([0-9][0-9.]*)/', $head, $match ) ) {
			return false;
		}
		$marker = trim( $match[1] );
		if ( null === $seen_marker ) {
			$seen_marker = $marker;
		} elseif ( 0 !== strcmp( $marker, $seen_marker ) ) {
			return false;
		}
	}
	return true;
}

require_once __DIR__ . '/includes/frenet.php';
require_once __DIR__ . '/includes/melhorenvio.php';
require_once __DIR__ . '/includes/pacotevicio.php';
require_once __DIR__ . '/includes/arts-tiff.php';
require_once __DIR__ . '/includes/arts-pdf.php';
require_once __DIR__ . '/includes/mercadolivre-report.php';
require_once __DIR__ . '/includes/shopee-report.php';
require_once __DIR__ . '/includes/nfe-marketplace.php';

/**
 * Usa a versão minificada de um asset quando ela existe e o site não está em
 * modo de depuração (SCRIPT_DEBUG). Isso reduz o peso de pedidos.js/pedidos.css
 * em produção sem exigir nenhum passo de build: basta os dois arquivos (fonte
 * e .min) estarem presentes na pasta assets/.
 */
function pw_personalizados_asset_relpath( $relative_path ) {
	if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
		return $relative_path;
	}
	$min_path = preg_replace( '/\.(js|css)$/', '.min.$1', $relative_path );
	return file_exists( PW_PERSONALIZADOS_DIR . $min_path ) ? $min_path : $relative_path;
}

function pw_personalizados_enqueue_assets() {
	$css_rel = pw_personalizados_asset_relpath( 'assets/pedidos.css' );
	$js_rel  = pw_personalizados_asset_relpath( 'assets/pedidos.js' );
	wp_enqueue_style(
		'pw-personalizados',
		PW_PERSONALIZADOS_URL . $css_rel,
		array(),
		pw_personalizados_asset_version( $css_rel )
	);
	// Funções compartilhadas com a calculadora DTF UV pública — carregadas
	// antes do script principal para que DTF.bestOrientation* e DTF.renderLayoutPreview
	// estejam disponíveis quando pedidos.js inicializar o Simulador.
	if ( defined( 'PW_DTF_UV_URL' ) && defined( 'PW_DTF_UV_DIR' ) ) {
		$shared_rel = 'assets/dtf-uv-shared.js';
		$shared_path = rtrim( PW_DTF_UV_DIR, '/\\' ) . '/' . $shared_rel;
		wp_enqueue_script(
			'pw-dtf-uv-shared',
			rtrim( PW_DTF_UV_URL, '/' ) . '/' . $shared_rel,
			array(),
			file_exists( $shared_path ) ? (string) filemtime( $shared_path ) : '1.0.0',
			true
		);
	}

	wp_enqueue_script(
		'pw-personalizados',
		PW_PERSONALIZADOS_URL . $js_rel,
		defined( 'PW_DTF_UV_URL' ) ? array( 'pw-dtf-uv-shared' ) : array(),
		pw_personalizados_asset_version( $js_rel ),
		true
	);
	// Mesma lib (e mesmo handle) que o módulo pix-qrcode já usa — o QR do Pix
	// no documento impresso do fechamento mensal é gerado com ela.
	wp_enqueue_script(
		'printway-qrcode-js',
		'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js',
		array(),
		'1.0.0',
		true
	);

	$collaborators = current_user_can( 'manage_options' ) ? array_map(
		function ( $user ) {
			return pw_personalizados_current_user_data( $user );
		},
		get_users(
			array(
				'role__in' => array( 'contributor', 'colaborador', 'collaborator', 'administrator' ),
				'orderby'  => 'display_name',
				'order'    => 'ASC',
			)
		)
	) : array();

	$config = array(
		'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
		'ajax_url'        => admin_url( 'admin-ajax.php' ),
		'nonce'           => wp_create_nonce( 'pw_personalizados_storage' ),
		'version'         => PW_PERSONALIZADOS_VERSION,
		'currentUser'     => pw_personalizados_current_user_data(),
		'collaborators'   => $collaborators,
		'dashboardLive'   => (bool) get_user_meta( get_current_user_id(), 'pw_personalizados_dashboard_live', true ),
		'dtfPriceTable'   => function_exists( 'pw_dtf_get_unified_price_table' ) ? pw_dtf_get_unified_price_table() : array(),
		'frenetConfigured' => (bool) pw_personalizados_frenet_credentials(),
		'melhorEnvioConfigured' => (bool) get_option( 'pw_personalizados_melhorenvio_secret', false ),
		'pacoteVicioConfigured' => (bool) get_option( 'pw_personalizados_pacotevicio_key', false ),
		'skipUserRefresh' => true,
	);
	wp_add_inline_script( 'pw-personalizados', 'window.PW_PERSONALIZADOS=' . wp_json_encode( $config ) . ';', 'before' );
}

/**
 * A leitura de todos os cadastros (clientes, pedidos, auditoria etc.) é pesada e só é
 * necessária pouco antes do script principal rodar. Adiar essa consulta para o rodapé da
 * página — em vez de fazê-la durante o <head> — deixa o restante do HTML (inclusive a
 * barra "Preparando o sistema") ser gerado e, quando a hospedagem não segura a resposta
 * inteira em buffer, exibido antes dessa consulta terminar.
 */
function pw_personalizados_enqueue_storage_data() {
	if ( ! wp_script_is( 'pw-personalizados', 'enqueued' ) ) { return; }
	if ( function_exists( 'ob_get_level' ) && ob_get_level() > 0 ) { @ob_flush(); } // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.Security.EscapeOutput
	if ( function_exists( 'flush' ) ) { @flush(); } // phpcs:ignore WordPress.PHP.NoSilencedErrors
	$storage = pw_personalizados_get_storage();
	wp_add_inline_script( 'pw-personalizados', 'window.PW_PERSONALIZADOS=Object.assign(window.PW_PERSONALIZADOS||{},{storage:' . wp_json_encode( $storage ) . '});', 'before' );
}
add_action( 'wp_footer', 'pw_personalizados_enqueue_storage_data', 5 );

function pw_personalizados_maybe_enqueue_assets() {
	global $post;
	if ( pw_personalizados_user_can_access() && $post instanceof WP_Post && has_shortcode( (string) $post->post_content, 'printway_pedidos_personalizados' ) ) {
		pw_personalizados_enqueue_assets();
	}
}
add_action( 'wp_enqueue_scripts', 'pw_personalizados_maybe_enqueue_assets', 20 );


function pw_personalizados_shortcode() {
	if ( ! is_user_logged_in() ) {
		return '<div class="pw-personalizados-login-required"><p>Entre em sua conta para acessar o sistema de pedidos personalizados.</p><p><a class="button" href="' . esc_url( wp_login_url( get_permalink() ) ) . '">Entrar</a></p></div>';
	}
	if ( ! pw_personalizados_user_can_access() ) {
		return '<div class="pw-personalizados-login-required"><p><strong>Acesso restrito.</strong></p><p>Somente usuários com perfil Administrador ou Colaborador podem acessar este sistema.</p></div>';
	}

	pw_personalizados_enqueue_assets();
	$template = PW_PERSONALIZADOS_DIR . 'templates/pedidos-app.php';
	if ( ! file_exists( $template ) ) {
		return '<p>O módulo de pedidos personalizados está incompleto. Reinstale o pacote PrintWay.</p>';
	}

	ob_start();
	include $template;
	return (string) ob_get_clean();
}
add_shortcode( 'printway_pedidos_personalizados', 'pw_personalizados_shortcode' );

/** Encontra a página publicada que contém o sistema de pedidos. */
function pw_personalizados_system_url() {
	global $wpdb;
	$page_id = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ('page','post') AND post_content LIKE %s ORDER BY post_type = 'page' DESC, ID ASC LIMIT 1",
			'%' . $wpdb->esc_like( '[printway_pedidos_personalizados' ) . '%'
		)
	);
	return $page_id ? get_permalink( $page_id ) : home_url( '/sistema/' );
}

/** Exibe o acesso ao Sistema logo abaixo de Dados cadastrais na área da conta, com ícone. */
function pw_personalizados_account_menu_link( $items ) {
	if ( ! pw_personalizados_user_can_access() ) {
		return $items;
	}
	unset( $items['printway-system'] );
	// Sem emoji no texto do rótulo: o ícone é aplicado via CSS (:before em
	// .woocommerce-MyAccount-navigation-link--printway-system, em printway.php),
	// igual aos demais itens do menu — mantém a mesma largura/espaçamento.
	$label     = 'Sistema';
	$organized = array();
	$inserted  = false;
	foreach ( (array) $items as $key => $existing_label ) {
		$organized[ $key ] = $existing_label;
		if ( 'edit-account' === $key ) {
			$organized['printway-system'] = $label;
			$inserted                     = true;
		}
	}
	if ( ! $inserted ) {
		// Não achou "Dados cadastrais" (tema pode ter renomeado a chave): insere logo após o primeiro item.
		$rebuilt = array();
		$done    = false;
		foreach ( $organized as $key => $existing_label ) {
			$rebuilt[ $key ] = $existing_label;
			if ( ! $done ) {
				$rebuilt['printway-system'] = $label;
				$done                       = true;
			}
		}
		$organized = $rebuilt ? $rebuilt : array( 'printway-system' => $label );
	}
	return $organized;
}
add_filter( 'woocommerce_account_menu_items', 'pw_personalizados_account_menu_link', PHP_INT_MAX );

function pw_personalizados_account_system_url( $url, $endpoint ) {
	if ( 'printway-system' === $endpoint && pw_personalizados_user_can_access() ) {
		return pw_personalizados_system_url();
	}
	return $url;
}
add_filter( 'woocommerce_get_endpoint_url', 'pw_personalizados_account_system_url', 30, 2 );

/** Localiza o cliente também entre os usuários WordPress e identifica sua tabela de preço. */
/** Localiza o usuário do WordPress correspondente a um cliente, pelos mesmos critérios usados no cadastro. */
function pw_personalizados_find_matching_wp_user( $email, $phone, $document, $name ) {
	$users = array();
	if ( $email ) {
		$user = get_user_by( 'email', $email );
		if ( $user ) { $users[ $user->ID ] = $user; }
	}
	$meta_searches = array(
		'billing_phone' => strlen( $phone ) >= 4 ? substr( $phone, -4 ) : '',
		'shipping_phone' => strlen( $phone ) >= 4 ? substr( $phone, -4 ) : '',
		'billing_cpf' => strlen( $document ) >= 6 ? substr( $document, -6 ) : '',
		'billing_cnpj' => strlen( $document ) >= 6 ? substr( $document, -6 ) : '',
	);
	foreach ( $meta_searches as $meta_key => $needle ) {
		if ( ! $needle ) { continue; }
		foreach ( get_users( array( 'number' => 25, 'meta_key' => $meta_key, 'meta_value' => $needle, 'meta_compare' => 'LIKE' ) ) as $user ) {
			$users[ $user->ID ] = $user;
		}
	}
	if ( $name ) {
		foreach ( get_users( array( 'number' => 25, 'search' => '*' . $name . '*', 'search_columns' => array( 'display_name', 'user_login' ) ) ) as $user ) {
			$users[ $user->ID ] = $user;
		}
	}
	$normalize = static function( $value ) { return strtolower( remove_accents( trim( (string) $value ) ) ); };
	$digits = static function( $value ) { return preg_replace( '/\D+/', '', (string) $value ); };
	foreach ( $users as $user ) {
		$user_phone = $digits( get_user_meta( $user->ID, 'billing_phone', true ) );
		$user_document = $digits( get_user_meta( $user->ID, 'billing_cpf', true ) . get_user_meta( $user->ID, 'billing_cnpj', true ) );
		if ( ( $email && strtolower( $user->user_email ) === strtolower( $email ) ) || ( $phone && $user_phone && $phone === $user_phone ) || ( $document && $user_document && false !== strpos( $user_document, $document ) ) || ( $name && $normalize( $user->display_name ) === $normalize( $name ) ) ) {
			return $user;
		}
	}
	return null;
}

function pw_personalizados_lookup_customer_type() {
	pw_personalizados_ajax_guard();
	$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$phone    = isset( $_POST['phone'] ) ? preg_replace( '/\D+/', '', wp_unslash( $_POST['phone'] ) ) : '';
	$document = isset( $_POST['document'] ) ? preg_replace( '/\D+/', '', wp_unslash( $_POST['document'] ) ) : '';
	$name     = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$matched  = pw_personalizados_find_matching_wp_user( $email, $phone, $document, $name );
	if ( ! $matched ) {
		wp_send_json_success( array( 'found' => false ) );
	}
	$roles = array_map( 'sanitize_key', (array) $matched->roles );
	$role_names = array();
	$wp_roles = wp_roles()->roles;
	foreach ( $roles as $role ) { $role_names[] = isset( $wp_roles[ $role ]['name'] ) ? sanitize_key( $wp_roles[ $role ]['name'] ) : $role; }
	$stored_type = sanitize_key( (string) get_user_meta( $matched->ID, '_pw_dtf_customer_type', true ) );
	$role_text = implode( ' ', array_merge( $roles, $role_names, array( $stored_type ) ) );
	$type = preg_match( '/revenda|revendedor|reseller|wholesale/', $role_text ) ? 'revenda' : 'direto';
	wp_send_json_success( array( 'found' => true, 'type' => $type, 'label' => 'revenda' === $type ? 'Revendedor' : 'Cliente direto', 'user_id' => (int) $matched->ID ) );
}
add_action( 'wp_ajax_pw_personalizados_lookup_customer_type', 'pw_personalizados_lookup_customer_type' );

/** Sincroniza o Tipo de cliente do cadastro com a função "Revendedor" do usuário do WordPress correspondente. Só mexe em contas de nível cliente (nunca em admin/editor/etc). */
function pw_personalizados_sync_customer_role() {
	pw_personalizados_ajax_guard();
	$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$phone    = isset( $_POST['phone'] ) ? preg_replace( '/\D+/', '', wp_unslash( $_POST['phone'] ) ) : '';
	$document = isset( $_POST['document'] ) ? preg_replace( '/\D+/', '', wp_unslash( $_POST['document'] ) ) : '';
	$name     = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$type     = isset( $_POST['customer_type'] ) && 'revenda' === $_POST['customer_type'] ? 'revenda' : 'direto';
	$matched  = pw_personalizados_find_matching_wp_user( $email, $phone, $document, $name );
	if ( ! $matched ) { wp_send_json_success( array( 'synced' => false, 'reason' => 'no_user' ) ); }
	$safe_roles = array( 'customer', 'subscriber', 'revendedor' );
	$current_roles = array_map( 'sanitize_key', (array) $matched->roles );
	$only_safe_roles = ! array_diff( $current_roles, $safe_roles );
	if ( ! $only_safe_roles ) { wp_send_json_success( array( 'synced' => false, 'reason' => 'privileged_account' ) ); }
	$wp_roles = wp_roles()->roles;
	if ( ! isset( $wp_roles['revendedor'] ) ) { wp_send_json_success( array( 'synced' => false, 'reason' => 'role_missing' ) ); }
	$desired_role = 'revenda' === $type ? 'revendedor' : 'customer';
	if ( ! in_array( $desired_role, $current_roles, true ) || 1 !== count( $current_roles ) ) {
		$matched->set_role( $desired_role );
	}
	wp_send_json_success( array( 'synced' => true, 'user_id' => (int) $matched->ID, 'role' => $desired_role ) );
}
add_action( 'wp_ajax_pw_personalizados_sync_customer_role', 'pw_personalizados_sync_customer_role' );

/** Rastreia último acesso e contador de logins de usuários. */
add_action( 'wp_login', function ( $user_login, $user ) {
	update_user_meta( $user->ID, 'pw_last_login', current_time( 'mysql' ) );
	update_user_meta( $user->ID, 'pw_login_count', (int) get_user_meta( $user->ID, 'pw_login_count', true ) + 1 );
}, 10, 2 );

/** Cria um login no WordPress para um cliente do sistema. */
function pw_personalizados_create_wp_login() {
	pw_personalizados_ajax_guard();
	$name    = isset( $_POST['name'] )    ? sanitize_text_field( wp_unslash( $_POST['name'] ) )   : '';
	$email   = isset( $_POST['email'] )   ? sanitize_email( wp_unslash( $_POST['email'] ) )        : '';
	$wp_role = isset( $_POST['wp_role'] ) && 'revendedor' === $_POST['wp_role'] ? 'revendedor' : 'customer';
	if ( ! $email ) {
		wp_send_json_error( array( 'reason' => 'no_email', 'message' => 'Informe o e-mail do cliente para criar o login.' ) );
	}
	$wp_roles = wp_roles()->roles;
	if ( ! isset( $wp_roles['revendedor'] ) ) {
		$cap = get_role( 'customer' );
		add_role( 'revendedor', 'Revendedor', $cap ? $cap->capabilities : array( 'read' => true ) );
	}
	$existing = get_user_by( 'email', $email );
	if ( $existing ) {
		$safe_roles   = array( 'customer', 'subscriber', 'revendedor' );
		$current_roles = array_map( 'sanitize_key', (array) $existing->roles );
		wp_send_json_success( array(
			'already_exists' => true,
			'wp_user_id'     => (int) $existing->ID,
			'login'          => $existing->user_login,
			'email'          => $existing->user_email,
			'display_name'   => $existing->display_name,
			'registered'     => $existing->user_registered,
			'last_login'     => (string) get_user_meta( $existing->ID, 'pw_last_login', true ),
			'login_count'    => (int) get_user_meta( $existing->ID, 'pw_login_count', true ),
			'role'           => count( array_diff( $current_roles, $safe_roles ) ) === 0 ? implode( ', ', $current_roles ) : implode( ', ', $current_roles ),
		) );
	}
	$user_login = $email;
	if ( username_exists( $user_login ) ) {
		wp_send_json_error( array( 'reason' => 'username_taken', 'message' => 'Já existe um usuário com este e-mail como nome de login.' ) );
	}
	$user_id = wp_insert_user( array(
		'user_login'   => $user_login,
		'user_email'   => $email,
		'display_name' => $name ?: $user_login,
		'role'         => $wp_role,
		'user_pass'    => wp_generate_password( 24 ),
	) );
	if ( is_wp_error( $user_id ) ) {
		wp_send_json_error( array( 'reason' => 'insert_failed', 'message' => $user_id->get_error_message() ) );
	}
	wp_new_user_notification( $user_id, null, 'user' );
	$user = get_userdata( $user_id );
	wp_send_json_success( array(
		'created'    => true,
		'wp_user_id' => (int) $user_id,
		'login'      => $user->user_login,
		'email'      => $user->user_email,
		'display_name' => $user->display_name,
		'registered' => $user->user_registered,
		'last_login' => '',
		'login_count' => 0,
	) );
}
add_action( 'wp_ajax_pw_personalizados_create_wp_login', 'pw_personalizados_create_wp_login' );

/** Retorna informações de login do WordPress para um usuário vinculado. */
function pw_personalizados_get_wp_login_info() {
	pw_personalizados_ajax_guard();
	$wp_user_id = isset( $_POST['wp_user_id'] ) ? (int) $_POST['wp_user_id'] : 0;
	if ( ! $wp_user_id ) { wp_send_json_error( array( 'reason' => 'no_id' ) ); }
	$user = get_userdata( $wp_user_id );
	if ( ! $user ) { wp_send_json_error( array( 'reason' => 'not_found' ) ); }
	wp_send_json_success( array(
		'wp_user_id'   => (int) $user->ID,
		'login'        => $user->user_login,
		'email'        => $user->user_email,
		'display_name' => $user->display_name,
		'registered'   => $user->user_registered,
		'last_login'   => (string) get_user_meta( $user->ID, 'pw_last_login', true ),
		'login_count'  => (int) get_user_meta( $user->ID, 'pw_login_count', true ),
	) );
}
add_action( 'wp_ajax_pw_personalizados_get_wp_login_info', 'pw_personalizados_get_wp_login_info' );

/** Gera um link de redefinição de senha para o primeiro acesso do usuário vinculado. */
function pw_personalizados_generate_login_link() {
	pw_personalizados_ajax_guard();
	$wp_user_id = isset( $_POST['wp_user_id'] ) ? (int) $_POST['wp_user_id'] : 0;
	if ( ! $wp_user_id ) { wp_send_json_error( array( 'reason' => 'no_id' ) ); }
	$user = get_userdata( $wp_user_id );
	if ( ! $user ) { wp_send_json_error( array( 'reason' => 'not_found', 'message' => 'Usuário não encontrado.' ) ); }
	$key = get_password_reset_key( $user );
	if ( is_wp_error( $key ) ) {
		wp_send_json_error( array( 'reason' => 'key_failed', 'message' => $key->get_error_message() ) );
	}
	$url = network_site_url( 'wp-login.php?action=rp&key=' . rawurlencode( $key ) . '&login=' . rawurlencode( $user->user_login ), 'login' );
	wp_send_json_success( array( 'url' => $url, 'email' => $user->user_email ) );
}
add_action( 'wp_ajax_pw_personalizados_generate_login_link', 'pw_personalizados_generate_login_link' );

/** Reenvia o e-mail de convite de acesso para o usuário WordPress vinculado. */
function pw_personalizados_resend_login_email() {
	pw_personalizados_ajax_guard();
	$wp_user_id = isset( $_POST['wp_user_id'] ) ? (int) $_POST['wp_user_id'] : 0;
	if ( ! $wp_user_id ) { wp_send_json_error( array( 'reason' => 'no_id' ) ); }
	$user = get_userdata( $wp_user_id );
	if ( ! $user ) { wp_send_json_error( array( 'reason' => 'not_found', 'message' => 'Usuário não encontrado.' ) ); }
	wp_new_user_notification( $wp_user_id, null, 'user' );
	wp_send_json_success( array( 'email' => $user->user_email ) );
}
add_action( 'wp_ajax_pw_personalizados_resend_login_email', 'pw_personalizados_resend_login_email' );

/**
 * Busca um usuário WP correspondente a um cliente do Pedidos por CPF/CNPJ (prioridade)
 * ou por e-mail. Retorna os dados do usuário e o nível de confiança do vínculo.
 */
function pw_personalizados_find_wp_user_for_client() {
	pw_personalizados_ajax_guard();
	$email    = isset( $_POST['email'] )    ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$document = isset( $_POST['document'] ) ? preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_POST['document'] ) ) ) : '';

	$build_result = function ( $user, $confidence ) {
		return array(
			'found'        => true,
			'confidence'   => $confidence,
			'wp_user_id'   => (int) $user->ID,
			'login'        => $user->user_login,
			'email'        => $user->user_email,
			'display_name' => $user->display_name,
			'registered'   => $user->user_registered,
			'last_login'   => (string) get_user_meta( $user->ID, 'pw_last_login', true ),
			'login_count'  => (int) get_user_meta( $user->ID, 'pw_login_count', true ),
		);
	};

	/* 1. CPF/CNPJ: confiança máxima — documento único por pessoa/empresa. */
	if ( $document && ( 11 === strlen( $document ) || 14 === strlen( $document ) ) ) {
		$by_doc = array_merge(
			get_users( array( 'meta_key' => 'billing_cpf',  'meta_value' => $document, 'number' => 2 ) ),
			get_users( array( 'meta_key' => 'billing_cnpj', 'meta_value' => $document, 'number' => 2 ) )
		);
		if ( 1 === count( $by_doc ) ) {
			wp_send_json_success( $build_result( $by_doc[0], 'document' ) );
		}
	}

	/* 2. E-mail: alta confiança. */
	if ( $email ) {
		$user = get_user_by( 'email', $email );
		if ( $user ) {
			wp_send_json_success( $build_result( $user, 'email' ) );
		}
	}

	wp_send_json_success( array( 'found' => false ) );
}
add_action( 'wp_ajax_pw_personalizados_find_wp_user_for_client', 'pw_personalizados_find_wp_user_for_client' );

/**
 * Ao registrar um novo usuário WP (via WooCommerce ou painel),
 * vincula automaticamente ao cliente do Pedidos que tiver o mesmo e-mail
 * e ainda não estiver vinculado.
 */
add_action( 'user_register', function ( $user_id ) {
	$user = get_userdata( $user_id );
	if ( ! $user || ! $user->user_email ) {
		return;
	}
	global $wpdb;
	$table = $wpdb->prefix . 'pw_personalizados_clients';
	if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
		return;
	}
	$like = '%' . $wpdb->esc_like( '"email":"' . $user->user_email ) . '%';
	$row  = $wpdb->get_row(
		$wpdb->prepare( "SELECT object_id, payload FROM {$table} WHERE payload LIKE %s LIMIT 1", $like ),
		ARRAY_A
	);
	if ( ! $row ) {
		return;
	}
	$payload = json_decode( $row['payload'], true );
	if ( ! is_array( $payload ) || ! empty( $payload['wpUserId'] ) ) {
		return;
	}
	$payload['wpUserId'] = $user_id;
	$wpdb->update(
		$table,
		array( 'payload' => wp_json_encode( $payload ) ),
		array( 'object_id' => (int) $row['object_id'] )
	);
} );

/** Calcula o dígito verificador da chave de acesso da NF-e (módulo 11). */
function pw_personalizados_nfe_check_digit( $base ) {
	$weight = 2;
	$sum    = 0;
	for ( $index = strlen( $base ) - 1; $index >= 0; $index-- ) {
		$sum   += (int) $base[ $index ] * $weight;
		$weight = 9 === $weight ? 2 : $weight + 1;
	}
	$remainder = $sum % 11;
	return (string) ( 0 === $remainder || 1 === $remainder ? 0 : 11 - $remainder );
}

/** Acrescenta uma assinatura apenas estrutural numa cópia usada pelo validador XSD. */
function pw_personalizados_nfe_append_schema_signature( DOMDocument $document, $reference_id ) {
	$namespace = 'http://www.w3.org/2000/09/xmldsig#';
	$signature = $document->createElementNS( $namespace, 'Signature' );
	$signed     = $signature->appendChild( $document->createElementNS( $namespace, 'SignedInfo' ) );
	$canonical  = $signed->appendChild( $document->createElementNS( $namespace, 'CanonicalizationMethod' ) );
	$canonical->setAttribute( 'Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315' );
	$method = $signed->appendChild( $document->createElementNS( $namespace, 'SignatureMethod' ) );
	$method->setAttribute( 'Algorithm', 'http://www.w3.org/2000/09/xmldsig#rsa-sha1' );
	$reference = $signed->appendChild( $document->createElementNS( $namespace, 'Reference' ) );
	$reference->setAttribute( 'URI', '#' . $reference_id );
	$transforms = $reference->appendChild( $document->createElementNS( $namespace, 'Transforms' ) );
	$transform  = $transforms->appendChild( $document->createElementNS( $namespace, 'Transform' ) );
	$transform->setAttribute( 'Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature' );
	$transform = $transforms->appendChild( $document->createElementNS( $namespace, 'Transform' ) );
	$transform->setAttribute( 'Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315' );
	$digest_method = $reference->appendChild( $document->createElementNS( $namespace, 'DigestMethod' ) );
	$digest_method->setAttribute( 'Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1' );
	$reference->appendChild( $document->createElementNS( $namespace, 'DigestValue', 'AA==' ) );
	$signature->appendChild( $document->createElementNS( $namespace, 'SignatureValue', 'AA==' ) );
	$key_info = $signature->appendChild( $document->createElementNS( $namespace, 'KeyInfo' ) );
	$x509     = $key_info->appendChild( $document->createElementNS( $namespace, 'X509Data' ) );
	$x509->appendChild( $document->createElementNS( $namespace, 'X509Certificate', 'AA==' ) );
	$document->documentElement->appendChild( $signature );
}

/** Valida o rascunho da NF-e com regras locais e com os esquemas XSD do leiaute 4.00. */
function pw_personalizados_validate_nfe_xml() {
	pw_personalizados_ajax_guard();
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Apenas administradores podem validar documentos fiscais.' ), 403 );
	}
	if ( ! class_exists( 'DOMDocument' ) || ! class_exists( 'DOMXPath' ) ) {
		wp_send_json_error( array( 'message' => 'O servidor precisa ter a extensão DOM/libxml do PHP habilitada.' ), 501 );
	}
	$xml = isset( $_POST['xml'] ) ? wp_unslash( $_POST['xml'] ) : '';
	if ( ! is_string( $xml ) || '' === trim( $xml ) ) {
		wp_send_json_error( array( 'message' => 'O XML da NF-e não foi recebido.' ), 400 );
	}
	if ( strlen( $xml ) > 2 * MB_IN_BYTES ) {
		wp_send_json_error( array( 'message' => 'O XML excede o limite de 2 MB.' ), 413 );
	}

	$previous = libxml_use_internal_errors( true );
	libxml_clear_errors();
	$document                     = new DOMDocument( '1.0', 'UTF-8' );
	$document->preserveWhiteSpace = false;
	$loaded                       = $document->loadXML( $xml, LIBXML_NONET | LIBXML_NOBLANKS );
	if ( ! $loaded ) {
		$errors = array_map(
			static function ( $error ) { return trim( $error->message ) . ( $error->line ? ' (linha ' . $error->line . ')' : '' ); },
			libxml_get_errors()
		);
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		wp_send_json_success( array( 'valid' => false, 'errors' => array_slice( $errors, 0, 20 ), 'warnings' => array(), 'engine' => 'DOM/libxml' ) );
	}

	$xpath = new DOMXPath( $document );
	$xpath->registerNamespace( 'nfe', 'http://www.portalfiscal.inf.br/nfe' );
	$xpath->registerNamespace( 'ds', 'http://www.w3.org/2000/09/xmldsig#' );
	$errors   = array();
	$warnings = array();
	$id       = (string) $xpath->evaluate( 'string(//nfe:infNFe/@Id)' );
	if ( ! preg_match( '/^NFe(\d{44})$/', $id, $key_match ) ) {
		$errors[] = 'A chave de acesso deve possuir 44 números.';
	} elseif ( pw_personalizados_nfe_check_digit( substr( $key_match[1], 0, 43 ) ) !== substr( $key_match[1], -1 ) ) {
		$errors[] = 'O dígito verificador da chave de acesso está incorreto.';
	}
	if ( '55' !== (string) $xpath->evaluate( 'string(//nfe:ide/nfe:mod)' ) ) { $errors[] = 'O modelo fiscal deve ser 55.'; }
	if ( 0 === $xpath->query( '//nfe:det' )->length ) { $errors[] = 'A NF-e precisa ter ao menos um item.'; }
	$emit_document = preg_replace( '/\D+/', '', (string) $xpath->evaluate( 'string(//nfe:emit/nfe:CNPJ)' ) );
	$dest_document = preg_replace( '/\D+/', '', (string) $xpath->evaluate( 'string(//nfe:dest/nfe:CNPJ | //nfe:dest/nfe:CPF)' ) );
	if ( 14 !== strlen( $emit_document ) ) { $errors[] = 'O CNPJ do emitente está incompleto.'; }
	if ( ! in_array( strlen( $dest_document ), array( 11, 14 ), true ) ) { $errors[] = 'O CPF/CNPJ do destinatário está incompleto.'; }
	$item_total = 0.0;
	foreach ( $xpath->query( '//nfe:det/nfe:prod/nfe:vProd' ) as $node ) { $item_total += (float) $node->nodeValue; }
	$declared_products = (float) $xpath->evaluate( 'string(//nfe:total/nfe:ICMSTot/nfe:vProd)' );
	if ( abs( $item_total - $declared_products ) > 0.01 ) { $errors[] = 'A soma dos itens não confere com o total dos produtos.'; }

	$has_signature = $xpath->query( '//ds:Signature' )->length > 0;
	$has_protocol  = $xpath->query( '//nfe:protNFe/nfe:infProt/nfe:nProt' )->length > 0;
	if ( ! $has_signature ) { $warnings[] = 'Assinatura digital pendente: o certificado A1 ainda não foi aplicado.'; }
	if ( ! $has_protocol ) { $warnings[] = 'Autorização pendente: ainda não existe protocolo de autorização da SEFAZ.'; }

	$schema = PW_PERSONALIZADOS_DIR . 'assets/nfe/schemas/PL_010_V1.30/nfe_v4.00.xsd';
	if ( ! is_readable( $schema ) ) {
		$errors[] = 'Os esquemas oficiais de validação da NF-e não foram encontrados no plugin.';
	} else {
		$schema_document = clone $document;
		if ( ! $has_signature && $id ) { pw_personalizados_nfe_append_schema_signature( $schema_document, $id ); }
		libxml_clear_errors();
		if ( ! $schema_document->schemaValidate( $schema ) ) {
			foreach ( array_slice( libxml_get_errors(), 0, 20 ) as $error ) {
				$errors[] = 'XSD: ' . trim( $error->message ) . ( $error->line ? ' (linha ' . $error->line . ')' : '' );
			}
		}
	}
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );
	wp_send_json_success(
		array(
			'valid'      => empty( $errors ),
			'errors'     => array_values( array_unique( $errors ) ),
			'warnings'   => $warnings,
			'engine'     => 'XSD NF-e 4.00 PL_010_V1.30 + verificações AntaSys',
			'signed'     => $has_signature,
			'authorized' => $has_protocol,
		)
	);
}
add_action( 'wp_ajax_pw_personalizados_validate_nfe_xml', 'pw_personalizados_validate_nfe_xml' );

/**
 * Calcula o DPI seguro pra renderizar uma página, dado o tamanho físico
 * (mm) medido no PDF. DTF UV tem largura fixa mas comprimento livre — uma
 * arte comprida a 900 DPI pode passar de 1 bilhão de pixels e estourar a
 * memória do PHP. Reduz o DPI só o necessário pra caber num limite seguro,
 * nunca abaixo de 150 (ainda bem acima da qualidade normal de impressão).
 * Usada tanto na conversão individual quanto no "Montar Tiff" em lote.
 */
function pw_personalizados_adaptive_render_dpi( $width_mm, $height_mm, $base_dpi = 900, $max_megapixels = 220 ) {
	if ( $width_mm <= 0 || $height_mm <= 0 ) {
		return $base_dpi;
	}
	$width_in = $width_mm / 25.4;
	$height_in = $height_mm / 25.4;
	$estimated_pixels = ( $width_in * $base_dpi ) * ( $height_in * $base_dpi );
	$budget = $max_megapixels * 1000000;
	if ( $estimated_pixels <= $budget ) {
		return $base_dpi;
	}
	$scale = sqrt( $budget / $estimated_pixels );
	return max( 150, (int) floor( $base_dpi * $scale ) );
}

/**
 * `wp_raise_memory_limit('image')` sozinho normalmente só chega a
 * 256–512 MB (o padrão do WordPress para edição de imagem), o que não é
 * suficiente pra renderizar folhas grandes de DTF a alta resolução.
 * Tenta subir ainda mais o limite pra esta requisição específica — se o
 * hosting bloquear `ini_set`, simplesmente não tem efeito, sem erro.
 */
function pw_personalizados_raise_memory_for_large_render() {
	if ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'image' );
	}
	$current = function_exists( 'wp_convert_hr_to_bytes' ) ? wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) ) : 0;
	if ( $current >= 0 && $current < 2147483648 ) { // 2048 MB
		@ini_set( 'memory_limit', '2048M' );
	}
	// Folha grande a alta resolução pode passar dos 30s padrão do PHP —
	// tenta esticar o tempo de execução também (melhor esforço; hosts com
	// max_execution_time travado no php.ini simplesmente ignoram isto).
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 180 );
	}
}

/**
 * Renderiza a primeira página do PDF preservando a transparência que o
 * próprio arquivo já carrega — a mesma que o CorelDRAW mostra ao abrir o
 * PDF direto — em vez de assumir fundo branco sólido e tentar "adivinhar"
 * o que remover depois com floodfill. Isso evita a perda de qualidade nas
 * bordas e as falhas brancas perto da arte que o floodfill causa.
 *
 * Retorna o Imagick já pronto quando o PDF tinha transparência real, ou
 * `false` quando a página veio praticamente opaca (PDF sem transparência
 * de verdade — arte antiga com fundo branco sólido) — nesse caso o
 * chamador deve cair no método antigo (renderizar com fundo branco e
 * remover com pw_personalizados_remove_connected_white_background()).
 */
function pw_personalizados_render_pdf_native_alpha( $source, $render_dpi ) {
	if ( ! class_exists( 'Imagick' ) ) {
		return new WP_Error( 'pdf_no_imagick', 'O servidor precisa ter o módulo Imagick habilitado.' );
	}
	try {
		$image = new Imagick();
		$image->setBackgroundColor( new ImagickPixel( 'transparent' ) );
		$image->setResolution( $render_dpi, $render_dpi );
		$image->readImage( $source . '[0]' );
		$image->setIteratorIndex( 0 );
		if ( defined( 'Imagick::ALPHACHANNEL_ACTIVATE' ) ) {
			$image->setImageAlphaChannel( Imagick::ALPHACHANNEL_ACTIVATE );
		}
		$alpha = $image->getImageChannelMean( Imagick::CHANNEL_ALPHA );
		$mean = is_array( $alpha ) && isset( $alpha['mean'] ) ? (float) $alpha['mean'] : 0;
		$quantum = method_exists( 'Imagick', 'getQuantumRange' ) ? Imagick::getQuantumRange() : array();
		$quantum_max = ! empty( $quantum['quantumRangeLong'] ) ? (float) $quantum['quantumRangeLong'] : 65535.0;
		$opaque_ratio = $quantum_max > 0 ? $mean / $quantum_max : 1;
		if ( $opaque_ratio > 0.985 ) {
			// Praticamente opaca em toda a página — o PDF não tinha
			// transparência de verdade, mesmo pedindo fundo transparente.
			$image->clear();
			return false;
		}
		return $image;
	} catch ( Throwable $error ) {
		return new WP_Error( 'pdf_render_failed', 'Não foi possível renderizar a página preservando a transparência: ' . $error->getMessage() );
	}
}

/**
 * Mede as páginas de um PDF em milímetros.
 *
 * O Imagick é a fonte preferencial. Quando ele não está disponível, a leitura
 * estrutural de MediaBox/CropBox mantém a validação independente de programas
 * instalados no computador do usuário.
 */
function pw_personalizados_pdf_geometry( $path ) {
	$result = array(
		'verified'        => false,
		'measurement'     => '',
		'pages'           => 0,
		'width_px'        => 0,
		'height_px'       => 0,
		'width_mm'        => 0,
		'height_mm'       => 0,
		'dpi'             => 72,
		'page_widths_mm'  => array(),
		'page_heights_mm' => array(),
	);
	if ( ! $path || ! is_file( $path ) ) {
		return $result;
	}
	// PDF, AI compatível com PDF (padrão do Illustrator/CorelDRAW ao exportar
	// .ai) e EPS de verdade são todos lidos aqui — o Imagick/Ghostscript
	// abaixo trata os três da mesma forma via delegate. Um .cdr (RIFF) ou um
	// .ai antigo sem compatibilidade PDF cai fora e devolve "não verificado".
	$header = file_get_contents( $path, false, null, 0, 5 );
	$is_eps = 0 === strpos( (string) $header, '%!PS' );
	if ( '%PDF-' !== $header && ! $is_eps ) {
		return $result;
	}

	try {
		if ( class_exists( 'Imagick' ) ) {
			$image = new Imagick();
			$image->setResolution( 72, 72 );
			$image->pingImage( $path );
			$widths = array();
			$heights = array();
			$width_pixels = array();
			$height_pixels = array();
			foreach ( $image as $page ) {
				$resolution = $page->getImageResolution();
				$dpi_x = isset( $resolution['x'] ) && (float) $resolution['x'] > 0 ? (float) $resolution['x'] : 72.0;
				$dpi_y = isset( $resolution['y'] ) && (float) $resolution['y'] > 0 ? (float) $resolution['y'] : $dpi_x;
				$width_px = (int) $page->getImageWidth();
				$height_px = (int) $page->getImageHeight();
				if ( $width_px > 0 && $height_px > 0 ) {
					$width_pixels[] = $width_px;
					$height_pixels[] = $height_px;
					$widths[] = round( $width_px / $dpi_x * 25.4, 2 );
					$heights[] = round( $height_px / $dpi_y * 25.4, 2 );
				}
			}
			$image->clear();
			if ( $widths && $heights ) {
				$result['verified'] = true;
				$result['measurement'] = 'Imagick/PDF';
				$result['pages'] = count( $widths );
				$result['width_px'] = max( $width_pixels );
				$result['height_px'] = array_sum( $height_pixels );
				$result['width_mm'] = max( $widths );
				$result['height_mm'] = round( array_sum( $heights ), 2 );
				$result['page_widths_mm'] = $widths;
				$result['page_heights_mm'] = $heights;
				return $result;
			}
		}
	} catch ( Exception $error ) {
		// A leitura estrutural abaixo é o segundo mecanismo de medição.
	}

	$source = file_get_contents( $path );
	if ( false === $source ) {
		return $result;
	}
	preg_match_all( '/\/Type\s*\/Page\b/', $source, $page_objects );
	$page_count = max( 1, count( $page_objects[0] ) );
	$number = '[-+]?(?:\d+(?:\.\d+)?|\.\d+)';
	$user_unit = 1.0;
	if ( preg_match( '/\/UserUnit\s+(' . $number . ')/', $source, $unit_match ) && (float) $unit_match[1] > 0 ) {
		$user_unit = (float) $unit_match[1];
	}
	$rotation = 0;
	if ( preg_match( '/\/Rotate\s+(-?\d+)/', $source, $rotation_match ) ) {
		$rotation = abs( (int) $rotation_match[1] ) % 360;
	}
	$boxes = array();
	foreach ( array( 'CropBox', 'MediaBox' ) as $box_name ) {
		$pattern = '/\/' . $box_name . '\s*\[\s*(' . $number . ')\s+(' . $number . ')\s+(' . $number . ')\s+(' . $number . ')\s*\]/';
		preg_match_all( $pattern, $source, $matches, PREG_SET_ORDER );
		if ( $matches ) {
			$boxes = $matches;
			break;
		}
	}
	if ( ! $boxes ) {
		return $result;
	}
	if ( 1 === count( $boxes ) && $page_count > 1 ) {
		$boxes = array_fill( 0, $page_count, $boxes[0] );
	} elseif ( count( $boxes ) > $page_count ) {
		$boxes = array_slice( $boxes, 0, $page_count );
	}
	$widths = array();
	$heights = array();
	foreach ( $boxes as $box ) {
		$width_points = abs( (float) $box[3] - (float) $box[1] ) * $user_unit;
		$height_points = abs( (float) $box[4] - (float) $box[2] ) * $user_unit;
		if ( in_array( $rotation, array( 90, 270 ), true ) ) {
			$rotated_width = $height_points;
			$height_points = $width_points;
			$width_points = $rotated_width;
		}
		if ( $width_points <= 0 || $height_points <= 0 ) {
			continue;
		}
		$widths[] = round( $width_points * 25.4 / 72, 2 );
		$heights[] = round( $height_points * 25.4 / 72, 2 );
	}
	if ( $widths && $heights ) {
		$result['verified'] = true;
		$result['measurement'] = 'Estrutura PDF (CropBox/MediaBox)';
		$result['pages'] = count( $widths );
		$result['width_px'] = (int) round( max( $widths ) / 25.4 * 72 );
		$result['height_px'] = (int) round( array_sum( $heights ) / 25.4 * 72 );
		$result['width_mm'] = max( $widths );
		$result['height_mm'] = round( array_sum( $heights ), 2 );
		$result['page_widths_mm'] = $widths;
		$result['page_heights_mm'] = $heights;
	}
	return $result;
}

/** Recebe a arte aprovada de um item do pedido e a guarda na biblioteca de mídia. */
function pw_personalizados_pdf_rules() {
	global $wpdb;
	$table = pw_personalizados_meta_table();
	$value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$table} WHERE meta_key = %s", 'pw_personalizados_pdf_rules' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rules = $value ? json_decode( $value, true ) : array();
	return is_array( $rules ) ? $rules : array();
}

function pw_personalizados_weather_coordinates() {
	$cached = get_transient( 'pw_personalizados_weather_coords' );
	if ( is_array( $cached ) && isset( $cached['lat'] ) ) { return $cached; }
	global $wpdb;
	$meta_table = pw_personalizados_meta_table();
	$raw = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$meta_table} WHERE meta_key = %s", 'pw_personalizados_nfe_settings' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$settings = $raw ? json_decode( $raw, true ) : array();
	$city = is_array( $settings ) ? trim( (string) ( $settings['city'] ?? '' ) ) : '';
	$state = is_array( $settings ) ? trim( (string) ( $settings['state'] ?? '' ) ) : '';
	if ( ! $city ) { return null; }
	$query = $city . ( $state ? ', ' . $state : '' ) . ', Brasil';
	$response = wp_remote_get( 'https://geocoding-api.open-meteo.com/v1/search?count=1&language=pt&format=json&name=' . rawurlencode( $query ), array( 'timeout' => 10 ) );
	if ( is_wp_error( $response ) ) { return null; }
	$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	$first = is_array( $data['results'][0] ?? null ) ? $data['results'][0] : null;
	if ( ! $first ) { return null; }
	$coords = array( 'lat' => (float) $first['latitude'], 'lon' => (float) $first['longitude'], 'city' => $city );
	set_transient( 'pw_personalizados_weather_coords', $coords, WEEK_IN_SECONDS );
	return $coords;
}

function pw_personalizados_weather_codes() {
	return array(
		0 => array( '☀️', 'Céu limpo' ), 1 => array( '🌤️', 'Poucas nuvens' ), 2 => array( '⛅', 'Parcialmente nublado' ), 3 => array( '☁️', 'Nublado' ),
		45 => array( '🌫️', 'Neblina' ), 48 => array( '🌫️', 'Neblina com geada' ),
		51 => array( '🌦️', 'Garoa fraca' ), 53 => array( '🌦️', 'Garoa' ), 55 => array( '🌦️', 'Garoa forte' ),
		61 => array( '🌧️', 'Chuva fraca' ), 63 => array( '🌧️', 'Chuva' ), 65 => array( '🌧️', 'Chuva forte' ),
		66 => array( '🌧️', 'Chuva congelante' ), 67 => array( '🌧️', 'Chuva congelante forte' ),
		71 => array( '🌨️', 'Neve fraca' ), 73 => array( '🌨️', 'Neve' ), 75 => array( '🌨️', 'Neve forte' ), 77 => array( '🌨️', 'Grãos de neve' ),
		80 => array( '🌧️', 'Pancadas de chuva fracas' ), 81 => array( '🌧️', 'Pancadas de chuva' ), 82 => array( '⛈️', 'Pancadas de chuva fortes' ),
		85 => array( '🌨️', 'Pancadas de neve fracas' ), 86 => array( '🌨️', 'Pancadas de neve fortes' ),
		95 => array( '⛈️', 'Trovoada' ), 96 => array( '⛈️', 'Trovoada com granizo fraco' ), 99 => array( '⛈️', 'Trovoada com granizo forte' ),
	);
}

function pw_personalizados_weather() {
	pw_personalizados_ajax_guard_soft();
	$cache_key = 'pw_personalizados_weather_data';
	$cached = get_transient( $cache_key );
	if ( is_array( $cached ) ) { wp_send_json_success( $cached ); }
	$coords = pw_personalizados_weather_coordinates();
	if ( ! $coords ) { wp_send_json_error( array( 'message' => 'Defina a cidade em Configuração fiscal (NF-e) para mostrar a previsão do tempo.' ), 422 ); }
	$response = wp_remote_get( 'https://api.open-meteo.com/v1/forecast?latitude=' . $coords['lat'] . '&longitude=' . $coords['lon'] . '&daily=weathercode,temperature_2m_max,temperature_2m_min&forecast_days=8&timezone=America%2FSao_Paulo', array( 'timeout' => 10 ) );
	if ( is_wp_error( $response ) ) { wp_send_json_error( array( 'message' => 'Não foi possível consultar a previsão do tempo agora.' ) ); }
	$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	$daily = is_array( $data['daily'] ?? null ) ? $data['daily'] : array();
	$dates = $daily['time'] ?? array();
	$codes = $daily['weathercode'] ?? array();
	$max = $daily['temperature_2m_max'] ?? array();
	$min = $daily['temperature_2m_min'] ?? array();
	$labels = pw_personalizados_weather_codes();
	$days = array();
	foreach ( $dates as $index => $date ) {
		$code = (int) ( $codes[ $index ] ?? 0 );
		$info = $labels[ $code ] ?? array( '❓', 'Sem informação' );
		$days[] = array(
			'date' => $date,
			'icon' => $info[0],
			'description' => $info[1],
			'max' => isset( $max[ $index ] ) ? round( (float) $max[ $index ] ) : null,
			'min' => isset( $min[ $index ] ) ? round( (float) $min[ $index ] ) : null,
		);
	}
	$result = array( 'city' => $coords['city'], 'days' => $days );
	set_transient( $cache_key, $result, 3 * HOUR_IN_SECONDS );
	wp_send_json_success( $result );
}
add_action( 'wp_ajax_pw_personalizados_weather', 'pw_personalizados_weather' );

function pw_personalizados_next_holiday() {
	pw_personalizados_ajax_guard_soft();
	$cache_key = 'pw_personalizados_next_holidays_v2';
	$cached = get_transient( $cache_key );
	if ( is_array( $cached ) ) { wp_send_json_success( $cached ); }
	$today = current_time( 'Y-m-d' );
	$year = (int) current_time( 'Y' );
	$holidays = array();
	foreach ( array( $year, $year + 1 ) as $y ) {
		$response = wp_remote_get( 'https://brasilapi.com.br/api/feriados/v1/' . $y, array( 'timeout' => 10 ) );
		if ( is_wp_error( $response ) ) { continue; }
		$list = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $list ) ) { continue; }
		foreach ( $list as $item ) {
			$date = (string) ( $item['date'] ?? '' );
			$name = sanitize_text_field( (string) ( $item['name'] ?? '' ) );
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) && $date >= $today && $name ) {
				$holidays[] = array( 'date' => $date, 'name' => $name );
			}
		}
		if ( count( $holidays ) >= 3 ) { break; }
	}
	usort( $holidays, static function ( $left, $right ) { return strcmp( (string) $left['date'], (string) $right['date'] ); } );
	$holidays = array_slice( $holidays, 0, 3 );
	if ( ! $holidays ) { wp_send_json_error( array( 'message' => 'Não foi possível localizar os próximos feriados.' ) ); }
	$result = array( 'holidays' => $holidays, 'date' => $holidays[0]['date'], 'name' => $holidays[0]['name'] );
	set_transient( $cache_key, $result, DAY_IN_SECONDS );
	wp_send_json_success( $result );
}
add_action( 'wp_ajax_pw_personalizados_next_holiday', 'pw_personalizados_next_holiday' );

/**
 * Prepara uma cópia local dos arquivos do OCR para que as leituras seguintes
 * não dependam de CDN. O primeiro aquecimento ainda precisa de internet para
 * buscar os arquivos; depois disso o navegador carrega tudo da mesma origem,
 * inclusive quando a conexão externa estiver indisponível.
 */
function pw_personalizados_order_ai_assets_manifest() {
	return array(
		'tesseract.min.js'       => 'https://cdn.jsdelivr.net/npm/tesseract.js@5.1.1/dist/tesseract.min.js',
		'worker.min.js'          => 'https://cdn.jsdelivr.net/npm/tesseract.js@5.1.1/dist/worker.min.js',
		'tesseract-core.wasm.js' => 'https://cdn.jsdelivr.net/npm/tesseract.js@5.1.1/dist/tesseract-core.wasm.js',
		'tesseract-core.wasm'    => 'https://cdn.jsdelivr.net/npm/tesseract.js@5.1.1/dist/tesseract-core.wasm',
		'por.traineddata.gz'     => 'https://tessdata.projectnaptha.com/4.0.0/por.traineddata.gz',
	);
}

function pw_personalizados_order_ai_assets_location() {
	$uploads = wp_upload_dir();
	return array(
		'dir' => trailingslashit( (string) ( $uploads['basedir'] ?? '' ) ) . 'pw-personalizados-order-ai',
		'url' => trailingslashit( (string) ( $uploads['baseurl'] ?? '' ) ) . 'pw-personalizados-order-ai',
	);
}

function pw_personalizados_order_ai_assets_status() {
	$location = pw_personalizados_order_ai_assets_location();
	$manifest = pw_personalizados_order_ai_assets_manifest();
	$files = array();
	$ready = true;
	foreach ( $manifest as $name => $remote ) {
		$path = trailingslashit( $location['dir'] ) . $name;
		$exists = is_readable( $path ) && is_file( $path ) && filesize( $path ) > 0;
		$files[ $name ] = $exists;
		if ( ! $exists ) { $ready = false; }
	}
	return array(
		'ready'  => $ready,
		'files'  => $files,
		'mainUrl' => trailingslashit( $location['url'] ) . 'tesseract.min.js',
		'workerPath' => trailingslashit( $location['url'] ) . 'worker.min.js',
		'corePath' => trailingslashit( $location['url'] ) . 'tesseract-core.wasm.js',
		'langPath' => rtrim( $location['url'], '/' ),
	);
}

function pw_personalizados_prepare_order_ai_assets() {
	pw_personalizados_ajax_guard_soft();
	$location = pw_personalizados_order_ai_assets_location();
	if ( empty( $location['dir'] ) || empty( $location['url'] ) ) {
		wp_send_json_error( array( 'message' => 'O diretório de uploads do WordPress não está disponível para o cache local do OCR.' ), 500 );
	}
	if ( ! wp_mkdir_p( $location['dir'] ) ) {
		wp_send_json_error( array( 'message' => 'Não foi possível criar o cache local do OCR.' ), 500 );
	}
	$manifest = pw_personalizados_order_ai_assets_manifest();
	$errors = array();
	foreach ( $manifest as $name => $remote ) {
		$path = trailingslashit( $location['dir'] ) . $name;
		if ( is_readable( $path ) && is_file( $path ) && filesize( $path ) > 0 ) { continue; }
		$response = wp_remote_get(
			$remote,
			array(
				'timeout'             => 45,
				'limit_response_size' => 40 * MB_IN_BYTES,
				'headers'             => array( 'Accept' => '*/*' ),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			$errors[] = $name;
			continue;
		}
		$body = wp_remote_retrieve_body( $response );
		if ( ! is_string( $body ) || '' === $body ) {
			$errors[] = $name;
			continue;
		}
		// O temporário fica no mesmo diretório do cache para que rename() seja
		// atômico também quando o diretório de temporários do PHP estiver em
		// outro volume do servidor.
		$temp = wp_tempnam( $name, $location['dir'] );
		if ( ! $temp || false === file_put_contents( $temp, $body, LOCK_EX ) || ! rename( $temp, $path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			if ( $temp && is_file( $temp ) ) { @unlink( $temp ); } // phpcs:ignore WordPress.PHP.NoSilencedErrors
			$errors[] = $name;
		}
	}
	$status = pw_personalizados_order_ai_assets_status();
	$status['downloaded'] = array_values( array_diff( array_keys( $manifest ), $errors ) );
	$status['errors'] = array_values( $errors );
	if ( ! $status['ready'] ) {
		wp_send_json_error( $status, 503 );
	}
	wp_send_json_success( $status );
}
add_action( 'wp_ajax_pw_personalizados_prepare_order_ai_assets', 'pw_personalizados_prepare_order_ai_assets' );

/**
 * Miniatura rápida (baixa resolução, fundo branco) da 1ª página de um PDF,
 * só pra pré-visualização — não é a arte de produção. Sempre renderiza com
 * fundo branco sólido (sem tentar preservar/remover transparência): duas
 * tentativas anteriores de mostrar essa miniatura sem fundo (transparência
 * nativa do PDF, depois floodfill de remoção) quebraram justamente pra
 * PDFs onde o Ghostscript não consegue rasterizar o conteúdo de verdade
 * nesses modos — o resultado saía todo transparente/em branco (sem nem a
 * arte visível), pior do que simplesmente mostrar com fundo branco.
 * Confirmado com o usuário: prefere sempre ver o conteúdo com fundo branco
 * a arriscar ficar em branco. Retorna '' se o Imagick não estiver
 * disponível ou a renderização falhar.
 */
function pw_personalizados_quick_pdf_thumbnail( $path, $max_width = 320 ) {
	if ( ! class_exists( 'Imagick' ) ) {
		return '';
	}
	try {
		$image = new Imagick();
		$image->setResolution( 100, 100 );
		$image->setBackgroundColor( new ImagickPixel( 'white' ) );
		$image->readImage( $path . '[0]' );
		$image->setImageFormat( 'png' );
		$image->setImageBackgroundColor( new ImagickPixel( 'white' ) );
		$flattened = $image->mergeImageLayers( Imagick::LAYERMETHOD_FLATTEN );
		if ( $flattened->getImageWidth() > $max_width ) {
			$flattened->scaleImage( $max_width, 0 );
		}
		$blob = $flattened->getImageBlob();
		$image->clear();
		$flattened->clear();
		return base64_encode( $blob );
	} catch ( Throwable $error ) {
		return '';
	}
}

/**
 * Miniatura sob demanda de um anexo de PDF já vinculado a um pedido — usada
 * pelo modal "Visualizar anexo" em Relatórios → Pedidos → Anexos. Só roda
 * quando o usuário abre a pré-visualização (não a cada carregamento da
 * listagem), reaproveitando a mesma renderização rápida do aviso de novo
 * pedido/simulador DTF UV.
 */
function pw_personalizados_get_order_art_thumbnail() {
	pw_personalizados_ajax_guard();
	$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
	if ( ! $attachment_id ) {
		wp_send_json_error( array( 'message' => 'Anexo inválido.' ), 400 );
	}
	$path = get_attached_file( $attachment_id );
	if ( ! $path || ! file_exists( $path ) || 'application/pdf' !== get_post_mime_type( $attachment_id ) ) {
		wp_send_json_error( array( 'message' => 'Pré-visualização não disponível para este arquivo.' ), 404 );
	}
	$thumbnail = pw_personalizados_quick_pdf_thumbnail( $path );
	if ( ! $thumbnail ) {
		wp_send_json_error( array( 'message' => 'Não foi possível gerar a pré-visualização.' ), 500 );
	}
	$alpha_check = pw_personalizados_render_pdf_native_alpha( $path, 72 );
	$has_native_alpha = ( ! is_wp_error( $alpha_check ) && false !== $alpha_check );
	if ( $has_native_alpha && $alpha_check instanceof Imagick ) { $alpha_check->clear(); }
	wp_send_json_success( array( 'thumbnail' => $thumbnail, 'has_native_alpha' => $has_native_alpha ) );
}
add_action( 'wp_ajax_pw_personalizados_get_order_art_thumbnail', 'pw_personalizados_get_order_art_thumbnail' );

/**
 * Simulador DTF UV: mede a altura/largura de um PDF enviado só para calcular
 * o valor — nunca cria anexo, nunca grava no pedido. O arquivo chega no
 * diretório temporário do próprio PHP e é apagado logo depois de medido,
 * tenha a medição dado certo ou não. Também devolve uma miniatura rápida
 * (base64) só pra exibição na tela do simulador e no orçamento em PDF.
 */
function pw_personalizados_dtf_simulator_measure() {
	pw_personalizados_ajax_guard();
	if ( empty( $_FILES['pdf'] ) || ! is_array( $_FILES['pdf'] ) ) {
		wp_send_json_error( array( 'message' => 'Nenhum arquivo foi recebido.' ), 400 );
	}
	$file = $_FILES['pdf'];
	$tmp_path = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
	if ( ! empty( $file['size'] ) && (int) $file['size'] > 60 * MB_IN_BYTES ) {
		if ( $tmp_path && is_file( $tmp_path ) ) { @unlink( $tmp_path ); }
		wp_send_json_error( array( 'message' => 'O arquivo deve ter no máximo 60 MB.' ), 413 );
	}
	$signature = $tmp_path && is_file( $tmp_path ) ? file_get_contents( $tmp_path, false, null, 0, 5 ) : '';
	if ( '%PDF-' !== $signature ) {
		if ( $tmp_path && is_file( $tmp_path ) ) { @unlink( $tmp_path ); }
		wp_send_json_error( array( 'message' => 'O arquivo selecionado não possui uma estrutura PDF válida.' ), 415 );
	}
	$geometry = pw_personalizados_pdf_geometry( $tmp_path );
	$thumbnail = $geometry['verified'] ? pw_personalizados_quick_pdf_thumbnail( $tmp_path ) : '';
	if ( $tmp_path && is_file( $tmp_path ) ) { @unlink( $tmp_path ); }
	if ( empty( $geometry['verified'] ) ) {
		wp_send_json_error( array( 'message' => 'Não foi possível medir este PDF com segurança. Tente outro arquivo ou informe a altura manualmente.' ), 422 );
	}
	wp_send_json_success( array( 'measurement' => $geometry, 'thumbnail' => $thumbnail ) );
}
add_action( 'wp_ajax_pw_personalizados_dtf_simulator_measure', 'pw_personalizados_dtf_simulator_measure' );

/**
 * Orçamento em PDF do Simulador DTF UV — gerado do zero com FPDF (a mesma
 * biblioteca já usada em pedidos/vendor/fpdf pro "Montar PDF" de artes).
 * Não grava nada em disco: monta o documento em memória e devolve como
 * download (Content-Disposition: attachment).
 */
function pw_personalizados_dtf_simulator_quote_pdf() {
	pw_personalizados_ajax_guard();

	$payload = isset( $_POST['payload'] ) ? json_decode( wp_unslash( $_POST['payload'] ), true ) : null;
	if ( ! is_array( $payload ) || empty( $payload['mode'] ) ) {
		wp_die( 'Dados do orçamento inválidos.' );
	}
	$client_name = isset( $_POST['client_name'] ) ? sanitize_text_field( wp_unslash( $_POST['client_name'] ) ) : '';

	$fpdf_path = PW_PERSONALIZADOS_DIR . 'vendor/fpdf/fpdf.php';
	if ( ! class_exists( 'FPDF' ) ) {
		if ( ! is_file( $fpdf_path ) ) {
			wp_die( 'Biblioteca de PDF não encontrada no servidor (pedidos/vendor/fpdf).' );
		}
		require_once $fpdf_path;
	}

	$mode = sanitize_key( $payload['mode'] );
	$customer_type = 'revenda' === ( $payload['customerType'] ?? '' ) ? 'revenda' : 'direto';
	$price = (float) ( $payload['price'] ?? 0 );
	$detail = sanitize_text_field( (string) ( $payload['detail'] ?? '' ) );
	$now = time();
	$issued_label = wp_date( 'd/m/Y \à\s H:i', $now );
	$valid_until = wp_date( 'd/m/Y', $now + 15 * DAY_IN_SECONDS );

	try {
	$pdf = new FPDF( 'P', 'mm', 'A4' );
	$pdf->SetMargins( 18, 16, 18 );
	$pdf->SetAutoPageBreak( true, 18 );
	$pdf->AddPage();
	$pdf->SetTextColor( 30, 30, 30 );

	// Cabeçalho: logo (se existir) + título + emissão/validade.
	$logo_candidates = array( 'assets/printway-logo.png', 'assets/antasys-logo-v2.png', 'assets/antasys-logo.png' );
	$logo_path = '';
	foreach ( $logo_candidates as $candidate ) {
		if ( is_file( PW_PERSONALIZADOS_DIR . $candidate ) ) { $logo_path = PW_PERSONALIZADOS_DIR . $candidate; break; }
	}
	$title_x = 18;
	if ( $logo_path ) {
		try {
			$pdf->Image( $logo_path, 18, 14, 0, 14 );
			$title_x = 18 + 14 * ( @getimagesize( $logo_path )[0] / max( 1, @getimagesize( $logo_path )[1] ) ) + 6;
		} catch ( Throwable $error ) {
			$title_x = 18;
		}
	}
	$pdf->SetXY( $title_x, 14 );
	$pdf->SetFont( 'Arial', '', 17 );
	$pdf->Cell( 0, 8, 'Orcamento - Impressao DTF UV', 0, 2 );
	$pdf->SetX( $title_x );
	$pdf->SetFont( 'Arial', '', 9 );
	$pdf->SetTextColor( 100, 100, 100 );
	$pdf->Cell( 0, 5, 'Emitido em ' . $issued_label, 0, 2 );
	$pdf->SetX( $title_x );
	$pdf->SetTextColor( 150, 40, 0 );
	$pdf->Cell( 0, 5, 'Valido por 15 dias - ate ' . $valid_until, 0, 2 );
	$pdf->SetTextColor( 30, 30, 30 );
	$pdf->Ln( 8 );
	$pdf->SetDrawColor( 200, 200, 200 );
	$pdf->Line( 18, $pdf->GetY(), 192, $pdf->GetY() );
	$pdf->Ln( 6 );

	$pdf->SetFont( 'Arial', '', 12 );
	$pdf->Cell( 0, 6, 'Cliente: ' . ( $client_name ? pw_personalizados_pdf_ascii( $client_name ) : 'Nao informado' ), 0, 1 );
	$pdf->SetFont( 'Arial', '', 10 );
	$pdf->Cell( 0, 6, 'Tipo de cliente: ' . ( 'revenda' === $customer_type ? 'Revenda' : 'Direto' ), 0, 1 );
	$pdf->Ln( 4 );

	$row = function( $label, $value ) use ( $pdf ) {
		$pdf->SetFont( 'Arial', '', 10.5 );
		$pdf->SetTextColor( 90, 90, 90 );
		$pdf->Cell( 60, 7, pw_personalizados_pdf_ascii( $label ), 0, 0 );
		$pdf->SetFont( 'Arial', '', 11.5 );
		$pdf->SetTextColor( 30, 30, 30 );
		$pdf->Cell( 0, 7, pw_personalizados_pdf_ascii( $value ), 0, 1 );
	};

	if ( 'pdf' === $mode ) {
		$row( 'Largura medida:', number_format_i18n( (float) ( $payload['widthCm'] ?? 0 ), 2 ) . ' cm' );
		$row( 'Altura medida:', number_format_i18n( (float) ( $payload['heightCm'] ?? 0 ), 2 ) . ' cm' );
		if ( ! empty( $payload['thumbnail'] ) ) {
			$pdf->Ln( 3 );
			require_once ABSPATH . 'wp-admin/includes/file.php';
			$tmp_thumb = wp_tempnam( 'pw-dtf-sim-thumb' );
			$binary = base64_decode( (string) $payload['thumbnail'], true );
			if ( $binary && $tmp_thumb ) {
				file_put_contents( $tmp_thumb, $binary );
				try {
					$size = @getimagesize( $tmp_thumb );
					$width_mm = 70;
					$height_mm = $size ? $width_mm * ( $size[1] / max( 1, $size[0] ) ) : 70;
					$pdf->Image( $tmp_thumb, 18, $pdf->GetY(), $width_mm, $height_mm, 'PNG' );
					$pdf->Ln( $height_mm + 4 );
				} catch ( Throwable $error ) {
					// Sem miniatura, segue só com os números.
				}
				@unlink( $tmp_thumb );
			}
		}
	} elseif ( 'height' === $mode ) {
		$row( 'Altura informada:', number_format_i18n( (float) ( $payload['heightCm'] ?? 0 ), 2 ) . ' cm' );
	} elseif ( in_array( $mode, array( 'quantity', 'size' ), true ) ) {
		$sticker_w = (float) ( $payload['stickerWidth'] ?? 0 );
		$sticker_h = (float) ( $payload['stickerHeight'] ?? 0 );
		$columns = max( 0, (int) ( $payload['columns'] ?? 0 ) );
		$rows = max( 0, (int) ( $payload['rows'] ?? 0 ) );
		$total_fit = max( 0, (int) ( $payload['totalFit'] ?? 0 ) );
		$sheet_height = (float) ( $payload['heightCm'] ?? ( $rows * $sticker_h ) );
		$row( 'Tamanho do adesivo:', number_format_i18n( $sticker_w, 1 ) . ' x ' . number_format_i18n( $sticker_h, 1 ) . ' cm' );
		$row( 'Adesivos por fileira:', (string) $columns );
		$row( 'Fileiras:', (string) $rows );
		$row( 'Quantidade de adesivos:', (string) $total_fit );
		$row( 'Largura da folha:', '28 cm' );
		$row( 'Altura da folha:', number_format_i18n( $sheet_height, 2 ) . ' cm' );
		if ( ! empty( $payload['rotated'] ) ) {
			$row( 'Orientacao usada:', 'Girada 90 graus' );
		}
		$pdf->Ln( 4 );
		$gap_cm = isset( $payload['gapCm'] ) && is_numeric( $payload['gapCm'] ) ? max( 0, (float) $payload['gapCm'] ) : 0.5;
		pw_personalizados_pdf_draw_sticker_grid( $pdf, $columns, $rows, $sticker_w, $sticker_h, $gap_cm );
	}

	$pdf->Ln( 4 );
	$pdf->SetDrawColor( 200, 200, 200 );
	$pdf->Line( 18, $pdf->GetY(), 192, $pdf->GetY() );
	$pdf->Ln( 6 );
	$row( 'Calculo aplicado:', $detail ?: '-' );
	$pdf->Ln( 2 );
	$pdf->SetFont( 'Arial', '', 17 );
	$pdf->SetTextColor( 150, 40, 0 );
	$pdf->Cell( 0, 10, 'Valor total: ' . pw_personalizados_pdf_ascii( 'R$ ' . number_format_i18n( $price, 2 ) ), 0, 1 );
	$pdf->SetTextColor( 30, 30, 30 );
	$pdf->Ln( 6 );
	$pdf->SetFont( 'Arial', '', 8.5 );
	$pdf->SetTextColor( 120, 120, 120 );
	$pdf->MultiCell( 0, 4.5, pw_personalizados_pdf_ascii( 'Orcamento sujeito a confirmacao da arte final antes da producao. Valores podem mudar apos essa data em caso de reajuste de custos.' ) );

	$filename = 'orcamento-dtf-uv-' . wp_date( 'Ymd-His', $now ) . '.pdf';
	while ( ob_get_level() > 0 ) { @ob_end_clean(); }
	$pdf->Output( 'D', $filename );
	} catch ( Throwable $error ) {
		// Nunca deixa a geração do orçamento derrubar a página com um erro
		// fatal cru — registra no log central e avisa de forma legível.
		if ( function_exists( 'pw_printway_log' ) ) {
			pw_printway_log( 'pedidos', 'error', 'Orçamento em PDF do simulador falhou: ' . $error->getMessage(), array( 'file' => $error->getFile(), 'line' => $error->getLine() ) );
		}
		while ( ob_get_level() > 0 ) { @ob_end_clean(); }
		wp_die( 'Não foi possível gerar o PDF do orçamento agora. O erro já foi registrado em PrintWay → Logs para conferência. Detalhe técnico: ' . esc_html( $error->getMessage() ) );
	}
	exit;
}
add_action( 'wp_ajax_pw_personalizados_dtf_simulator_quote_pdf', 'pw_personalizados_dtf_simulator_quote_pdf' );

/** FPDF (fonte core, sem UTF-8) só imprime Latin-1 — converte acentos/símbolos com segurança. */
function pw_personalizados_pdf_ascii( $text ) {
	$converted = @iconv( 'UTF-8', 'ISO-8859-1//TRANSLIT', (string) $text );
	return false === $converted ? (string) $text : $converted;
}

/**
 * Desenha a grade de adesivos no orçamento, com o mesmo critério da
 * miniatura da tela: folha sempre com 28cm de largura, o mesmo espaço
 * horizontal usado no cálculo (gap entre colunas) e 0,5cm fixo entre
 * fileiras, grade centralizada e cada adesivo numerado. Se a grade for
 * grande demais pra caber com clareza numa página, mostra só um aviso em
 * texto (sem tentar paginar o desenho).
 */
function pw_personalizados_pdf_draw_sticker_grid( $pdf, $columns, $rows, $sticker_w, $sticker_h, $gap_cm = 0.5 ) {
	if ( $columns <= 0 || $rows <= 0 || $sticker_w <= 0 || $sticker_h <= 0 ) {
		return;
	}
	$sheet_width_cm = 28;
	$vertical_gap_cm = 0.5;
	$sheet_height_cm = $rows * $sticker_h + max( 0, $rows - 1 ) * $vertical_gap_cm;
	$max_width_mm = 170; // largura útil da página (A4, margens de 18mm).
	$max_height_mm = 90; // reserva espaço pro restante do orçamento na mesma página.
	$scale = min( $max_width_mm / ( $sheet_width_cm * 10 ), $max_height_mm / max( 1, $sheet_height_cm * 10 ) );
	if ( $rows * $columns > 400 || $scale < 0.15 ) {
		$pdf->SetFont( 'Arial', '', 9 );
		$pdf->Cell( 0, 6, pw_personalizados_pdf_ascii( 'Disposicao com ' . ( $rows * $columns ) . ' adesivos - detalhe visual omitido neste orcamento.' ), 0, 1 );
		return;
	}
	$sheet_w_mm = $sheet_width_cm * 10 * $scale;
	$sheet_h_mm = $sheet_height_cm * 10 * $scale;
	$grid_w_mm = ( $columns * $sticker_w + max( 0, $columns - 1 ) * $gap_cm ) * 10 * $scale;
	$offset_x = max( 0, ( $sheet_w_mm - $grid_w_mm ) / 2 );
	$origin_x = $pdf->GetX();
	$origin_y = $pdf->GetY();
	$pdf->SetDrawColor( 150, 59, 0 );
	$pdf->SetFillColor( 246, 233, 224 );
	$pdf->Rect( $origin_x, $origin_y, $sheet_w_mm, $sheet_h_mm );
	$font_size = max( 5, min( $sticker_w, $sticker_h ) * 10 * $scale * 0.28 );
	$pdf->SetFont( 'Arial', '', $font_size );
	for ( $r = 0; $r < $rows; $r++ ) {
		for ( $c = 0; $c < $columns; $c++ ) {
			$x = $origin_x + $offset_x + $c * ( $sticker_w + $gap_cm ) * 10 * $scale;
			$y = $origin_y + $r * ( $sticker_h + $vertical_gap_cm ) * 10 * $scale;
			$w = max( 1, $sticker_w * 10 * $scale );
			$h = max( 1, $sticker_h * 10 * $scale );
			$pdf->Rect( $x, $y, $w, $h, 'DF' );
			$number = $r * $columns + $c + 1;
			$pdf->SetXY( $x, $y + $h / 2 - $font_size * 0.18 );
			$pdf->Cell( $w, $font_size * 0.4, (string) $number, 0, 0, 'C' );
		}
	}
	$pdf->SetXY( $origin_x, $origin_y + $sheet_h_mm + 3 );
}

function pw_personalizados_upload_order_art() {
	pw_personalizados_ajax_guard();
	if ( empty( $_FILES['art'] ) || ! is_array( $_FILES['art'] ) ) {
		wp_send_json_error( array( 'message' => 'Nenhum arquivo foi recebido.' ), 400 );
	}
	$file = $_FILES['art'];
	if ( ! empty( $file['size'] ) && (int) $file['size'] > 200 * MB_IN_BYTES ) {
		wp_send_json_error( array( 'message' => 'O arquivo deve ter no máximo 200 MB.' ), 413 );
	}
	$name = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : '';
	// Formatos aceitos como anexo de arte, além do PDF: AI e EPS têm tamanho
	// de página medível com o mesmo motor do PDF (AI moderno É um PDF por
	// dentro; EPS carrega BoundingBox). CDR é formato fechado da Corel — sem
	// biblioteca aberta confiável pra medir no servidor — então é aceito só
	// como anexo de referência, sem medir largura/altura automaticamente
	// (o fluxo real do usuário é abrir no próprio CorelDRAW e exportar o
	// PDF/TIFF definitivo a partir dele, que aí sim é medido normalmente).
	$mimes = array(
		'pdf' => 'application/pdf',
		'ai'  => 'application/postscript',
		'eps' => 'application/postscript',
		'cdr' => 'application/x-coreldraw',
	);
	$type = wp_check_filetype( $name, $mimes );
	$measurable_exts = array( 'pdf', 'ai', 'eps' );
	if ( empty( $type['ext'] ) || ! in_array( $type['ext'], array( 'pdf', 'ai', 'eps', 'cdr' ), true ) ) {
		wp_send_json_error( array( 'message' => 'Envie a arte em PDF, AI, EPS ou CDR.' ), 415 );
	}
	$tmp_path = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
	$header = $tmp_path && is_file( $tmp_path ) ? (string) file_get_contents( $tmp_path, false, null, 0, 16 ) : '';
	// Confere a assinatura de verdade do arquivo (não só a extensão) — cada
	// formato tem um jeito próprio de validar isso: PDF/AI-compatível-PDF
	// começam com "%PDF-", EPS de verdade começa com "%!PS", CDR é um
	// contêiner RIFF (assinatura "RIFF" nos 4 primeiros bytes).
	$signature_ok = false;
	if ( 'pdf' === $type['ext'] ) {
		$signature_ok = 0 === strpos( $header, '%PDF-' );
	} elseif ( 'ai' === $type['ext'] ) {
		// AI moderno (com "Create PDF Compatible File" ativo, que é o padrão
		// do Illustrator/CorelDRAW ao exportar .ai) é literalmente um PDF por
		// dentro. Um .ai sem essa compatibilidade é raro hoje em dia — nesse
		// caso a medição automática de tamanho não vai funcionar, mas ainda
		// aceitamos o anexo (mesma lógica do CDR: fica sem medida).
		$signature_ok = 0 === strpos( $header, '%PDF-' ) || 0 === strpos( $header, '%!PS' );
	} elseif ( 'eps' === $type['ext'] ) {
		$signature_ok = 0 === strpos( $header, '%!PS' );
	} elseif ( 'cdr' === $type['ext'] ) {
		$signature_ok = 0 === strpos( $header, 'RIFF' );
	}
	if ( ! $signature_ok ) {
		wp_send_json_error( array( 'message' => 'O arquivo selecionado não possui uma estrutura ' . strtoupper( $type['ext'] ) . ' válida.' ), 415 );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$code = isset( $_POST['code'] ) ? strtoupper( preg_replace( '/[^A-Za-z0-9-]/', '', sanitize_text_field( wp_unslash( $_POST['code'] ) ) ) ) : '';
	$code = substr( $code ?: '00000-X', 0, 24 );
	$_FILES['art']['name'] = sanitize_file_name( $code . '.' . $type['ext'] );
	add_filter( 'upload_mimes', function ( $mimes_allowed ) use ( $mimes ) { return array_merge( $mimes_allowed, $mimes ); } );
	$attachment_id = media_handle_upload( 'art', 0, array(), array( 'test_form' => false, 'mimes' => $mimes ) );
	if ( is_wp_error( $attachment_id ) ) {
		wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ), 400 );
	}
	wp_update_post( array( 'ID' => $attachment_id, 'post_title' => $code ) );
	$path = get_attached_file( $attachment_id );
	$measurable = in_array( $type['ext'], $measurable_exts, true );
	$geometry = $measurable ? pw_personalizados_pdf_geometry( $path ) : array( 'verified' => false, 'page_widths_mm' => array(), 'page_heights_mm' => array() );
	$is_dtf_uv = ! empty( $_POST['dtf_uv'] ) && '0' !== (string) wp_unslash( $_POST['dtf_uv'] );
	if ( $is_dtf_uv && $measurable ) {
		if ( empty( $geometry['verified'] ) ) {
			wp_delete_attachment( $attachment_id, true );
			wp_send_json_error( array( 'message' => 'Não foi possível confirmar com segurança a largura e a altura deste arquivo. Verifique-o e tente novamente.' ), 422 );
		}
		$invalid_widths = array_filter( $geometry['page_widths_mm'], static function ( $width_mm ) {
			return (float) $width_mm < 249.5 || (float) $width_mm > 280.5;
		} );
		if ( $invalid_widths ) {
			$measured = (float) reset( $invalid_widths ) / 10;
			wp_delete_attachment( $attachment_id, true );
			wp_send_json_error( array( 'message' => 'A largura medida no arquivo é ' . number_format_i18n( $measured, 2 ) . ' cm. Para DTF UV ela deve ficar entre 25 e 28 cm; o ideal é 28 cm.' ), 422 );
		}
	}
	$category_id = isset( $_POST['category_id'] ) ? sanitize_text_field( wp_unslash( $_POST['category_id'] ) ) : '';
	$rule = $category_id ? ( pw_personalizados_pdf_rules()[ $category_id ] ?? null ) : null;
	if ( is_array( $rule ) && isset( $rule['enabled'] ) && ! $rule['enabled'] ) {
		$rule = null; // regra desativada para esta categoria: não aplica limites extras.
	}
	if ( is_array( $rule ) ) {
		if ( ! empty( $rule['maxSizeMb'] ) && ! empty( $file['size'] ) && (int) $file['size'] > (float) $rule['maxSizeMb'] * MB_IN_BYTES ) {
			wp_delete_attachment( $attachment_id, true );
			wp_send_json_error( array( 'message' => 'O arquivo deve ter no máximo ' . number_format_i18n( (float) $rule['maxSizeMb'], 1 ) . ' MB para esta categoria.' ), 413 );
		}
		if ( $measurable && ( isset( $rule['minWidth'] ) || isset( $rule['maxWidth'] ) || isset( $rule['minHeight'] ) || isset( $rule['maxHeight'] ) ) && empty( $geometry['verified'] ) ) {
			wp_delete_attachment( $attachment_id, true );
			wp_send_json_error( array( 'message' => 'Não foi possível confirmar com segurança a largura e a altura deste arquivo. Verifique-o e tente novamente.' ), 422 );
		}
		if ( ! empty( $geometry['verified'] ) ) {
			foreach ( $geometry['page_widths_mm'] as $index => $width_mm ) {
				$height_mm = $geometry['page_heights_mm'][ $index ] ?? 0;
				$bad_width = ( isset( $rule['minWidth'] ) && null !== $rule['minWidth'] && (float) $width_mm < (float) $rule['minWidth'] )
					|| ( isset( $rule['maxWidth'] ) && null !== $rule['maxWidth'] && (float) $width_mm > (float) $rule['maxWidth'] );
				$bad_height = ( isset( $rule['minHeight'] ) && null !== $rule['minHeight'] && (float) $height_mm < (float) $rule['minHeight'] )
					|| ( isset( $rule['maxHeight'] ) && null !== $rule['maxHeight'] && (float) $height_mm > (float) $rule['maxHeight'] );
				if ( $bad_width || $bad_height ) {
					wp_delete_attachment( $attachment_id, true );
					wp_send_json_error( array( 'message' => 'O arquivo mede ' . number_format_i18n( (float) $width_mm / 10, 2 ) . ' × ' . number_format_i18n( (float) $height_mm / 10, 2 ) . ' cm, fora do intervalo aceito para esta categoria.' ), 422 );
				}
			}
		}
	}
	wp_send_json_success( array(
		'id'   => (int) $attachment_id,
		'code' => $code,
		'url'  => wp_get_attachment_url( $attachment_id ),
		'name' => get_the_title( $attachment_id ) ?: $name,
		'mime' => get_post_mime_type( $attachment_id ),
		'uploadedAt' => get_post_time( 'c', true, $attachment_id ) ?: gmdate( 'c' ),
		'measurement' => $geometry,
	) );
}
add_action( 'wp_ajax_pw_personalizados_upload_order_art', 'pw_personalizados_upload_order_art' );

/** Grava o recorte PNG produzido pela IA local e, quando solicitado, gera o TIFF mantendo a escala do PDF. */
function pw_personalizados_save_ai_order_art() {
	pw_personalizados_ajax_guard();
	if ( empty( $_FILES['art'] ) || ! is_array( $_FILES['art'] ) ) {
		wp_send_json_error( array( 'message' => 'A IA não devolveu um arquivo para salvar.' ), 400 );
	}
	$file = $_FILES['art'];
	if ( ! empty( $file['size'] ) && (int) $file['size'] > 128 * MB_IN_BYTES ) {
		wp_send_json_error( array( 'message' => 'O recorte gerado deve ter no máximo 128 MB.' ), 413 );
	}
	$tmp_path = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
	if ( ! $tmp_path || ! is_uploaded_file( $tmp_path ) ) {
		wp_send_json_error( array( 'message' => 'O recorte da IA não foi recebido como upload válido.' ), 400 );
	}
	$image_info = $tmp_path && is_file( $tmp_path ) ? @getimagesize( $tmp_path ) : false;
	$mime = is_array( $image_info ) && ! empty( $image_info['mime'] ) ? (string) $image_info['mime'] : '';
	if ( 'image/png' !== $mime ) {
		wp_send_json_error( array( 'message' => 'A IA deve devolver um recorte PNG transparente.' ), 415 );
	}
	$format = isset( $_POST['format'] ) ? sanitize_key( wp_unslash( $_POST['format'] ) ) : 'png';
	if ( ! in_array( $format, array( 'png', 'tiff' ), true ) ) {
		wp_send_json_error( array( 'message' => 'Formato de saída da IA inválido.' ), 400 );
	}
	if ( ! class_exists( 'Imagick' ) ) {
		wp_send_json_error( array( 'message' => 'O servidor precisa ter o módulo Imagick habilitado para finalizar a arte.' ), 501 );
	}
	$source_attachment_id = isset( $_POST['source_attachment_id'] ) ? absint( $_POST['source_attachment_id'] ) : 0;
	$source_path = $source_attachment_id ? get_attached_file( $source_attachment_id ) : '';
	$source_mime = $source_attachment_id ? get_post_mime_type( $source_attachment_id ) : '';
	$source_geometry = ( $source_path && 'application/pdf' === $source_mime ) ? pw_personalizados_pdf_geometry( $source_path ) : array();
	$uploads = wp_upload_dir();
	if ( ! empty( $uploads['error'] ) ) {
		wp_send_json_error( array( 'message' => $uploads['error'] ), 500 );
	}
	$code = isset( $_POST['code'] ) ? strtoupper( preg_replace( '/[^A-Za-z0-9-]/', '', sanitize_text_field( wp_unslash( $_POST['code'] ) ) ) ) : 'ARTE';
	$code = substr( $code ?: 'ARTE', 0, 24 );
	$is_tiff = 'tiff' === $format;
	$suffix = $is_tiff ? '-tiff-ai' : '-png-ai';
	$extension = $is_tiff ? '.tiff' : '.png';
	$mime_type = $is_tiff ? 'image/tiff' : 'image/png';
	$filename = wp_unique_filename( $uploads['path'], sanitize_file_name( $code . $suffix . $extension ) );
	$destination = trailingslashit( $uploads['path'] ) . $filename;
	try {
		$image = new Imagick();
		$image->readImage( $tmp_path );
		$image->setIteratorIndex( 0 );
		if ( method_exists( $image, 'setImageAlphaChannel' ) && defined( 'Imagick::ALPHACHANNEL_ACTIVATE' ) ) {
			$image->setImageAlphaChannel( Imagick::ALPHACHANNEL_ACTIVATE );
		}
		$image->setImageFormat( $is_tiff ? 'tiff' : 'png' );
		$target_width_mm = ! empty( $source_geometry['page_widths_mm'][0] ) ? (float) $source_geometry['page_widths_mm'][0] : (float) ( $source_geometry['width_mm'] ?? 0 );
		$target_height_mm = ! empty( $source_geometry['page_heights_mm'][0] ) ? (float) $source_geometry['page_heights_mm'][0] : (float) ( $source_geometry['height_mm'] ?? 0 );
		$width_px = (int) $image->getImageWidth();
		$height_px = (int) $image->getImageHeight();
		$image_resolution = method_exists( $image, 'getImageResolution' ) ? $image->getImageResolution() : array();
		$fallback_x = ! empty( $image_resolution['x'] ) ? (float) $image_resolution['x'] : 900;
		$fallback_y = ! empty( $image_resolution['y'] ) ? (float) $image_resolution['y'] : $fallback_x;
		$dpi_x = $target_width_mm > 0 && $width_px > 0 ? $width_px / ( $target_width_mm / 25.4 ) : $fallback_x;
		$dpi_y = $target_height_mm > 0 && $height_px > 0 ? $height_px / ( $target_height_mm / 25.4 ) : $fallback_y;
		if ( method_exists( $image, 'setImageUnits' ) && defined( 'Imagick::RESOLUTION_PIXELSPERINCH' ) ) {
			$image->setImageUnits( Imagick::RESOLUTION_PIXELSPERINCH );
		}
		if ( method_exists( $image, 'setImageResolution' ) ) {
			$image->setImageResolution( $dpi_x, $dpi_y );
		}
		if ( $is_tiff ) {
			$image->setImageCompression( Imagick::COMPRESSION_ZIP );
			$image->setImageCompressionQuality( 100 );
			$image->setOption( 'tiff:alpha', 'unassociated' );
		} else {
			$image->setImageCompression( Imagick::COMPRESSION_ZIP );
			$image->setImageCompressionQuality( 100 );
		}
		$image->writeImage( $destination );
		$image->clear();
	} catch ( Exception $error ) {
		wp_send_json_error( array( 'message' => 'O servidor não conseguiu salvar o recorte da IA: ' . $error->getMessage() ), 500 );
	}
	if ( ! file_exists( $destination ) ) {
		wp_send_json_error( array( 'message' => 'O arquivo final da IA não foi criado.' ), 500 );
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$generated_id = wp_insert_attachment( array(
		'post_mime_type' => $mime_type,
		'post_title'     => $code . $suffix,
		'post_status'    => 'inherit',
	), $destination );
	if ( is_wp_error( $generated_id ) ) {
		wp_send_json_error( array( 'message' => $generated_id->get_error_message() ), 500 );
	}
	wp_update_attachment_metadata( $generated_id, wp_generate_attachment_metadata( $generated_id, $destination ) );
	wp_send_json_success( array(
		'id'   => (int) $generated_id,
		'url'  => wp_get_attachment_url( $generated_id ),
		'name' => basename( $destination ),
		'mime' => $mime_type,
	) );
}
add_action( 'wp_ajax_pw_personalizados_save_ai_order_art', 'pw_personalizados_save_ai_order_art' );

/** Lê características técnicas do arquivo original de uma arte. */
function pw_personalizados_inspect_order_art() {
	pw_personalizados_ajax_guard();
	$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
	$path = $attachment_id ? get_attached_file( $attachment_id ) : '';
	if ( ! $path || ! is_file( $path ) ) {
		wp_send_json_error( array( 'message' => 'O arquivo original não foi localizado.' ), 404 );
	}
	$mime = (string) get_post_mime_type( $attachment_id );
	$data = array(
		'format'      => 'application/pdf' === $mime ? 'PDF' : strtoupper( pathinfo( $path, PATHINFO_EXTENSION ) ?: 'Arquivo' ),
		'mime'        => $mime,
		'bytes'       => (int) filesize( $path ),
		'width_px'    => 0,
		'height_px'   => 0,
		'width_mm'    => 0,
		'height_mm'   => 0,
		'pages'       => 'application/pdf' === $mime ? 1 : 0,
		'dpi'         => 0,
		'color_space' => '',
		'orientation' => '',
		'modified'    => wp_date( 'd/m/Y H:i', (int) filemtime( $path ) ),
		'font_status' => '',
		'font_count' => 0,
		'embedded_font_count' => 0,
		'font_names' => array(),
	);
	if ( 'application/pdf' === $mime ) {
		$pdf_source = file_get_contents( $path );
		if ( false !== $pdf_source ) {
			preg_match_all( '/\/Type\s*\/Font\b/', $pdf_source, $font_objects );
			preg_match_all( '/\/FontFile(?:2|3)?\b/', $pdf_source, $embedded_fonts );
			preg_match_all( '/\/BaseFont\s*\/([^\s\/<>\[\]()]+)/', $pdf_source, $font_names );
			$names = isset( $font_names[1] ) ? array_values( array_unique( array_map( static function ( $name ) {
				return preg_replace( '/^[A-Z]{6}\+/', '', sanitize_text_field( $name ) );
			}, $font_names[1] ) ) ) : array();
			$data['font_count'] = max( count( $font_objects[0] ), count( $names ) );
			$data['embedded_font_count'] = count( $embedded_fonts[0] );
			$data['font_names'] = array_slice( $names, 0, 12 );
			if ( 0 === $data['font_count'] ) {
				$data['font_status'] = 'Nenhuma fonte detectada. O conteúdo provavelmente está em curvas ou como imagem.';
			} elseif ( $data['embedded_font_count'] < $data['font_count'] ) {
				$data['font_status'] = 'Atenção: há fontes possivelmente não incorporadas e textos não convertidos em curvas.';
			} else {
				$data['font_status'] = 'Fontes incorporadas detectadas. O texto ainda pode estar editável e não convertido em curvas.';
			}
		}
	}
	if ( 'application/pdf' === $mime ) {
		$geometry = pw_personalizados_pdf_geometry( $path );
		$data = array_merge( $data, $geometry );
	} else {
		try {
			if ( class_exists( 'Imagick' ) ) {
				$image = new Imagick();
				$image->pingImage( $path );
				$data['pages'] = max( 1, (int) $image->getNumberImages() );
				$image->setIteratorIndex( 0 );
				$data['width_px'] = (int) $image->getImageWidth();
				$data['height_px'] = (int) $image->getImageHeight();
				$resolution = $image->getImageResolution();
				$dpi_x = isset( $resolution['x'] ) && (float) $resolution['x'] > 0 ? (float) $resolution['x'] : 72.0;
				$dpi_y = isset( $resolution['y'] ) && (float) $resolution['y'] > 0 ? (float) $resolution['y'] : $dpi_x;
				$data['dpi'] = (int) round( max( $dpi_x, $dpi_y ) );
				$data['width_mm'] = round( $data['width_px'] / $dpi_x * 25.4, 2 );
				$data['height_mm'] = round( $data['height_px'] / $dpi_y * 25.4, 2 );
				$image->clear();
			} elseif ( 'image/png' === $mime ) {
				$size = wp_getimagesize( $path );
				if ( is_array( $size ) ) {
					$data['width_px'] = isset( $size[0] ) ? (int) $size[0] : 0;
					$data['height_px'] = isset( $size[1] ) ? (int) $size[1] : 0;
				}
			}
		} catch ( Exception $error ) {
			// Mantém tamanho, formato e data mesmo quando o servidor não lê a geometria interna.
		}
	}
	if ( $data['width_px'] && $data['height_px'] ) {
		$data['orientation'] = $data['width_px'] > $data['height_px'] ? 'Paisagem' : ( $data['width_px'] < $data['height_px'] ? 'Retrato' : 'Quadrado' );
	}
	wp_send_json_success( $data );
}
add_action( 'wp_ajax_pw_personalizados_inspect_order_art', 'pw_personalizados_inspect_order_art' );

/** Reúne em ZIP as artes anexadas a um pedido para download pelo relatório. */
function pw_personalizados_download_order_arts() {
	pw_personalizados_ajax_guard();
	try {
		pw_personalizados_download_order_arts_run();
	} catch ( Throwable $error ) {
		if ( function_exists( 'pw_printway_log' ) ) {
			pw_printway_log( 'pedidos', 'error', 'Download de anexos em lote: erro inesperado — ' . get_class( $error ) . ': ' . $error->getMessage() . ' em ' . $error->getFile() . ':' . $error->getLine() );
		}
		wp_send_json_error( array(
			'message' => 'Erro inesperado ao compactar os anexos: ' . $error->getMessage() . ' (' . get_class( $error ) . ', linha ' . $error->getLine() . ' de ' . basename( $error->getFile() ) . ').',
		), 500 );
	}
}
add_action( 'wp_ajax_pw_personalizados_download_order_arts', 'pw_personalizados_download_order_arts' );

function pw_personalizados_download_order_arts_run() {
	if ( ! class_exists( 'ZipArchive' ) ) {
		wp_send_json_error( array( 'message' => 'O servidor não possui suporte para criar arquivos ZIP.' ), 501 );
	}
	$raw_ids = isset( $_POST['attachment_ids'] ) ? json_decode( wp_unslash( $_POST['attachment_ids'] ), true ) : array();
	$ids = array_values( array_unique( array_filter( array_map( 'absint', is_array( $raw_ids ) ? $raw_ids : array() ) ) ) );
	if ( count( $ids ) < 2 || count( $ids ) > 30 ) {
		wp_send_json_error( array( 'message' => 'Seleção de anexos inválida (' . count( $ids ) . ' item(ns); precisa de 2 a 30).' ), 400 );
	}
	$order_number = isset( $_POST['order_number'] ) ? sanitize_file_name( wp_unslash( $_POST['order_number'] ) ) : 'pedido';
	$uploads = wp_upload_dir();
	if ( ! empty( $uploads['error'] ) ) {
		wp_send_json_error( array( 'message' => $uploads['error'] ), 500 );
	}
	$filename = wp_unique_filename( $uploads['path'], sanitize_file_name( 'artes-' . $order_number . '.zip' ) );
	$destination = trailingslashit( $uploads['path'] ) . $filename;
	$zip = new ZipArchive();
	$open_result = $zip->open( $destination, ZipArchive::CREATE | ZipArchive::OVERWRITE );
	if ( true !== $open_result ) {
		wp_send_json_error( array( 'message' => 'Não foi possível iniciar a compactação (código ZipArchive: ' . $open_result . ') em "' . $destination . '".' ), 500 );
	}
	$added = 0;
	foreach ( $ids as $attachment_id ) {
		$path = get_attached_file( $attachment_id );
		if ( ! $path || ! is_file( $path ) ) {
			continue;
		}
		$extension = pathinfo( $path, PATHINFO_EXTENSION );
		$code = sanitize_file_name( get_the_title( $attachment_id ) ?: (string) $attachment_id );
		$entry = $code . ( $extension ? '.' . strtolower( $extension ) : '' );
		if ( $zip->addFile( $path, $entry ) ) {
			$added++;
		}
	}
	$zip->close();
	if ( $added < 1 || ! file_exists( $destination ) ) {
		if ( file_exists( $destination ) ) {
			wp_delete_file( $destination );
		}
		wp_send_json_error( array( 'message' => 'Nenhum dos ' . count( $ids ) . ' anexos selecionados foi encontrado no servidor.' ), 404 );
	}
	wp_send_json_success( array(
		'url'  => trailingslashit( $uploads['url'] ) . rawurlencode( $filename ),
		'name' => $filename,
		'count' => $added,
	) );
}

/** Exclui definitivamente as artes de pedidos removidos da Lixeira. */
function pw_personalizados_delete_order_arts() {
	pw_personalizados_ajax_guard();
	$raw_ids = isset( $_POST['attachment_ids'] ) ? json_decode( wp_unslash( $_POST['attachment_ids'] ), true ) : array();
	$ids = array_values( array_unique( array_filter( array_map( 'absint', is_array( $raw_ids ) ? $raw_ids : array() ) ) ) );
	if ( count( $ids ) > 100 ) {
		wp_send_json_error( array( 'message' => 'Há arquivos demais em uma única solicitação.' ), 400 );
	}
	$deleted = 0;
	$failed = 0;
	foreach ( $ids as $attachment_id ) {
		$post = get_post( $attachment_id );
		if ( ! $post ) {
			continue;
		}
		if ( 'attachment' !== $post->post_type || ! current_user_can( 'delete_post', $attachment_id ) ) {
			$failed++;
			continue;
		}
		if ( wp_delete_attachment( $attachment_id, true ) ) {
			$deleted++;
		} else {
			$failed++;
		}
	}
	if ( $failed ) {
		wp_send_json_error( array( 'message' => 'Alguns arquivos não puderam ser removidos. O pedido foi mantido na Lixeira para nova tentativa.' ), 500 );
	}
	wp_send_json_success( array( 'deleted' => $deleted ) );
}
add_action( 'wp_ajax_pw_personalizados_delete_order_arts', 'pw_personalizados_delete_order_arts' );

/** Remove somente o branco conectado às bordas, preservando os brancos internos da arte. */
/** Converte uma tolerância pensada na escala 0-255 (mais fácil de calibrar)
 * pro valor de "fuzz" que o Imagick espera de verdade — que é na escala do
 * QUANTUM do build (Q8 = 0-255, Q16 = 0-65535). Sem essa conversão, um fuzz
 * pensado pra Q8 sairia 256× fraco demais (ou sem nenhum efeito) num
 * servidor Q16, e vice-versa. */
function pw_personalizados_imagick_fuzz_from_255( $value_0_255 ) {
	$quantum_max = 255.0;
	if ( class_exists( 'Imagick' ) && method_exists( 'Imagick', 'getQuantumRange' ) ) {
		$range = Imagick::getQuantumRange();
		if ( isset( $range['quantumRangeLong'] ) && (float) $range['quantumRangeLong'] > 0 ) {
			$quantum_max = (float) $range['quantumRangeLong'];
		}
	}
	return ( max( 0, min( 255, (float) $value_0_255 ) ) / 255 ) * $quantum_max;
}

function pw_personalizados_remove_connected_white_background( $image ) {
	if ( ! is_object( $image ) || ! method_exists( $image, 'getImagePixelColor' ) ) {
		return $image;
	}
	if ( method_exists( $image, 'setImageAlphaChannel' ) && defined( 'Imagick::ALPHACHANNEL_ACTIVATE' ) ) {
		$image->setImageAlphaChannel( Imagick::ALPHACHANNEL_ACTIVATE );
	}
	$image->setImageFormat( 'png' );
	$corner = $image->getImagePixelColor( 0, 0 );
	$corner_color = $corner instanceof ImagickPixel ? $corner->getColor() : array();
	$corner_is_white = isset( $corner_color['r'], $corner_color['g'], $corner_color['b'] )
		&& min( (float) $corner_color['r'], (float) $corner_color['g'], (float) $corner_color['b'] ) >= 240;
	if ( $corner_is_white ) {
		$background = new ImagickPixel( sprintf( 'rgb(%d,%d,%d)', (int) $corner_color['r'], (int) $corner_color['g'], (int) $corner_color['b'] ) );
		// A borda garante que o preenchimento sempre comece no fundo real;
		// o shave devolve exatamente a largura e a altura originais. Um fuzz
		// pequeno (não mais 0.0 exato) é necessário pra também apagar o halo
		// de antialiasing entre o branco puro e a borda do desenho — sem
		// isso sobrava um anel branco fino contornando cada figura (relatado
		// pelo usuário com print). Só pega esse halo bem próximo do branco;
		// uma cor de verdade da arte (creme, laranja, verde) está muito
		// longe pra entrar nessa tolerância.
		$image->borderImage( $background, 1, 1 );
		$image->floodFillPaintImage( new ImagickPixel( 'transparent' ), pw_personalizados_imagick_fuzz_from_255( 18 ), $background, 0, 0, false );
		$image->shaveImage( 1, 1 );
	}
	return $image;
}

/** Converte artes em PNG/TIFF com fundo branco conectado transparente. */
function pw_personalizados_transform_order_art() {
	pw_personalizados_ajax_guard();
	try {
		pw_personalizados_transform_order_art_run();
	} catch ( Throwable $error ) {
		// Rede de segurança final: sem isto, um erro fatal aqui (memória,
		// Imagick, etc.) derrubava a página inteira em vez de responder em
		// JSON — o navegador então mostrava "Unexpected token '<'... is not
		// valid JSON" sem nenhuma pista do que realmente aconteceu.
		if ( function_exists( 'pw_printway_log' ) ) {
			pw_printway_log( 'pedidos', 'error', 'Conversão de arte: erro inesperado — ' . get_class( $error ) . ': ' . $error->getMessage() . ' em ' . $error->getFile() . ':' . $error->getLine() );
		}
		wp_send_json_error( array(
			'message' => 'Erro inesperado ao converter a arte: ' . $error->getMessage() . ' (' . get_class( $error ) . ', linha ' . $error->getLine() . ' de ' . basename( $error->getFile() ) . ').',
		), 500 );
	}
}
add_action( 'wp_ajax_pw_personalizados_transform_order_art', 'pw_personalizados_transform_order_art' );

function pw_personalizados_transform_order_art_run() {
	$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
	$operation = isset( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : '';
	$code = isset( $_POST['code'] ) ? strtoupper( preg_replace( '/[^A-Za-z0-9-]/', '', sanitize_text_field( wp_unslash( $_POST['code'] ) ) ) ) : 'ARTE';
	$source = $attachment_id ? get_attached_file( $attachment_id ) : '';
	$mime = $attachment_id ? get_post_mime_type( $attachment_id ) : '';
	if ( ! $source || ! file_exists( $source ) ) {
		wp_send_json_error( array( 'message' => 'O arquivo original (anexo ' . $attachment_id . ') não foi localizado na biblioteca de mídia.' ), 404 );
	}
	if ( ! class_exists( 'Imagick' ) ) {
		wp_send_json_error( array( 'message' => 'O servidor precisa ter o módulo Imagick habilitado para processar PDF e transparência.' ), 501 );
	}
	if ( in_array( $operation, array( 'pdf_to_png', 'pdf_to_png_raw', 'pdf_to_tiff' ), true ) && 'application/pdf' !== $mime ) {
		wp_send_json_error( array( 'message' => 'A conversão para PNG ou TIFF está disponível somente para arquivos PDF (este é ' . $mime . ').' ), 400 );
	}
	if ( 'remove_background' === $operation && 'image/png' !== $mime ) {
		wp_send_json_error( array( 'message' => 'Converta o PDF para PNG antes de remover o fundo.' ), 400 );
	}
	if ( ! in_array( $operation, array( 'pdf_to_png', 'pdf_to_png_raw', 'pdf_to_tiff', 'remove_background' ), true ) ) {
		wp_send_json_error( array( 'message' => 'Operação de imagem inválida: "' . $operation . '".' ), 400 );
	}
	if ( function_exists( 'pw_personalizados_raise_memory_for_large_render' ) ) {
		pw_personalizados_raise_memory_for_large_render();
	} elseif ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'image' );
	}
	$uploads = wp_upload_dir();
	if ( ! empty( $uploads['error'] ) ) {
		wp_send_json_error( array( 'message' => $uploads['error'] ), 500 );
	}
	if ( empty( $uploads['path'] ) ) {
		wp_send_json_error( array( 'message' => 'A pasta de uploads do WordPress não retornou um caminho válido.' ), 500 );
	}
	$is_tiff = 'pdf_to_tiff' === $operation;
	$source_geometry = in_array( $operation, array( 'pdf_to_png', 'pdf_to_png_raw', 'pdf_to_tiff' ), true ) ? pw_personalizados_pdf_geometry( $source ) : array();
	$suffix = in_array( $operation, array( 'pdf_to_png', 'pdf_to_png_raw' ), true ) ? ( 'pdf_to_png_raw' === $operation ? '-png-source' : '-png' ) : ( $is_tiff ? '-tiff' : '-transparente' );
	$extension = $is_tiff ? '.tiff' : '.png';
	$mime_type = $is_tiff ? 'image/tiff' : 'image/png';
	$filename = wp_unique_filename( $uploads['path'], sanitize_file_name( $code . $suffix . $extension ) );
	$destination = trailingslashit( $uploads['path'] ) . $filename;

	// Mesmo ajuste do "Montar Tiff": largura de DTF UV costuma ser fixa mas
	// o comprimento é livre, então uma arte comprida a 900 DPI pode passar
	// de 1 bilhão de pixels e estourar a memória. Reduz o DPI só quando
	// necessário pra caber com segurança.
	$max_dpi_param = isset( $_POST['max_dpi'] ) ? min( 900, max( 72, (int) $_POST['max_dpi'] ) ) : 900;
	$render_dpi = $max_dpi_param;
	if ( in_array( $operation, array( 'pdf_to_png', 'pdf_to_png_raw', 'pdf_to_tiff' ), true ) ) {
		$geometry_width_mm = ! empty( $source_geometry['page_widths_mm'][0] ) ? (float) $source_geometry['page_widths_mm'][0] : (float) ( $source_geometry['width_mm'] ?? 0 );
		$geometry_height_mm = ! empty( $source_geometry['page_heights_mm'][0] ) ? (float) $source_geometry['page_heights_mm'][0] : (float) ( $source_geometry['height_mm'] ?? 0 );
		$render_dpi = pw_personalizados_adaptive_render_dpi( $geometry_width_mm, $geometry_height_mm, $max_dpi_param, min( 72, $max_dpi_param ) );
		if ( $render_dpi < $max_dpi_param && function_exists( 'pw_printway_log' ) ) {
			pw_printway_log( 'pedidos', 'info', 'Conversão de arte: DPI reduzido de ' . $max_dpi_param . ' para ' . $render_dpi . ' pra evitar estouro de memória.', array( 'anexo' => $attachment_id, 'largura_mm' => $geometry_width_mm, 'altura_mm' => $geometry_height_mm ) );
		}
	}

	try {
		$image = new Imagick();
		if ( in_array( $operation, array( 'pdf_to_png', 'pdf_to_png_raw' ), true ) ) {
			// PNG e a entrada da IA usam até 900 DPI (reduzido acima se a arte
			// for grande) para manter detalhes finos de textos e contornos.
			if ( 'pdf_to_png_raw' === $operation ) {
				// A etapa intermediária da IA precisa receber o branco original
				// para que o modelo consiga separar o primeiro plano corretamente.
				$image->setResolution( $render_dpi, $render_dpi );
				$image->readImage( $source . '[0]' );
				$image->setIteratorIndex( 0 );
				$image->setImageFormat( 'png' );
				$image->setImageBackgroundColor( new ImagickPixel( 'white' ) );
				$processed = $image->mergeImageLayers( Imagick::LAYERMETHOD_FLATTEN );
			} else {
				// Renderiza com fundo branco e remove o branco conectado às
				// bordas por floodfill — mais confiável que alpha nativo para
				// PDFs DTF UV cujo Imagick renderiza tudo transparente quando
				// setBackgroundColor('transparent') é usado.
				$native = $max_dpi_param < 900
					? false
					: pw_personalizados_render_pdf_native_alpha( $source, $render_dpi );
				if ( is_wp_error( $native ) ) {
					throw new Exception( $native->get_error_message() );
				}
				if ( false === $native ) {
					$image->setResolution( $render_dpi, $render_dpi );
					$image->readImage( $source . '[0]' );
					$image->setIteratorIndex( 0 );
					$processed = pw_personalizados_remove_connected_white_background( $image );
				} else {
					$processed = $native;
				}
			}
		} elseif ( $is_tiff ) {
			// Mesma lógica do PNG acima: preserva a transparência nativa do
			// PDF quando ela existe (evita perda de qualidade e as bordas
			// brancas falhadas do floodfill), com o floodfill como reserva.
			$native = pw_personalizados_render_pdf_native_alpha( $source, $render_dpi );
			if ( is_wp_error( $native ) ) {
				throw new Exception( $native->get_error_message() );
			}
			if ( false === $native ) {
				$image->setResolution( $render_dpi, $render_dpi );
				$image->readImage( $source . '[0]' );
				$image->setIteratorIndex( 0 );
				$processed = pw_personalizados_remove_connected_white_background( $image );
			} else {
				$processed = $native;
			}
			$processed->setImageFormat( 'tiff' );
		} else {
			$image->readImage( $source );
			$image->setIteratorIndex( 0 );
			$image->setImageFormat( 'png' );
			$image->setImageAlphaChannel( Imagick::ALPHACHANNEL_ACTIVATE );
			$image->borderImage( new ImagickPixel( 'white' ), 1, 1 );
			// Mesmo fuzz pequeno de pw_personalizados_remove_connected_white_background()
			// — sem ele sobra um halo fino de antialiasing branco na borda de cada figura.
			$image->floodFillPaintImage( new ImagickPixel( 'transparent' ), pw_personalizados_imagick_fuzz_from_255( 18 ), new ImagickPixel( 'white' ), 0, 0, false );
			$image->shaveImage( 1, 1 );
			$processed = $image;
		}
		// PNG e TIFF precisam registrar a densidade que corresponde à área física
		// do PDF; apenas definir a resolução antes da leitura não é suficiente,
		// pois o arquivo rasterizado pode perder essa informação ao ser gravado.
		if ( in_array( $operation, array( 'pdf_to_png', 'pdf_to_png_raw', 'pdf_to_tiff' ), true ) ) {
			$target_width_mm = ! empty( $source_geometry['page_widths_mm'][0] ) ? (float) $source_geometry['page_widths_mm'][0] : (float) ( $source_geometry['width_mm'] ?? 0 );
			$target_height_mm = ! empty( $source_geometry['page_heights_mm'][0] ) ? (float) $source_geometry['page_heights_mm'][0] : (float) ( $source_geometry['height_mm'] ?? 0 );
			$width_px = (int) $processed->getImageWidth();
			$height_px = (int) $processed->getImageHeight();
			$dpi_x = $target_width_mm > 0 && $width_px > 0 ? $width_px / ( $target_width_mm / 25.4 ) : $render_dpi;
			$dpi_y = $target_height_mm > 0 && $height_px > 0 ? $height_px / ( $target_height_mm / 25.4 ) : $render_dpi;
			if ( method_exists( $processed, 'setImageUnits' ) && defined( 'Imagick::RESOLUTION_PIXELSPERINCH' ) ) {
				$processed->setImageUnits( Imagick::RESOLUTION_PIXELSPERINCH );
			}
			if ( method_exists( $processed, 'setImageResolution' ) ) {
				$processed->setImageResolution( $dpi_x, $dpi_y );
			}
		}
		if ( 'remove_background' === $operation ) {
			$alpha = $processed->getImageChannelMean( Imagick::CHANNEL_ALPHA );
			if ( ! is_array( $alpha ) || ! isset( $alpha['mean'] ) || (float) $alpha['mean'] <= 0.5 ) {
				throw new Exception( 'A remoção deixaria a imagem vazia. O arquivo original foi preservado.' );
			}
		}
		if ( $is_tiff ) {
			// Depois do floodFillPaintImage, o Imagick às vezes reclassifica o
			// tipo interno da imagem (ex.: para tons de cinza/paleta, comum em
			// artes com poucas cores) — e o gravador de TIFF respeita esse
			// tipo, ignorando o canal alfa e saindo tudo preto mesmo com a
			// transparência correta na memória. Forçar TRUECOLORMATTE garante
			// que o TIFF sempre saia como RGBA de verdade.
			if ( defined( 'Imagick::IMGTYPE_TRUECOLORMATTE' ) ) {
				$processed->setImageType( Imagick::IMGTYPE_TRUECOLORMATTE );
			}
			if ( defined( 'Imagick::ALPHACHANNEL_ACTIVATE' ) ) {
				$processed->setImageAlphaChannel( Imagick::ALPHACHANNEL_ACTIVATE );
			}
			$processed->setImageCompression( Imagick::COMPRESSION_ZIP );
			$processed->setImageCompressionQuality( 100 );
			$processed->setOption( 'tiff:alpha', 'unassociated' );
		} else {
			$processed->setImageCompression( Imagick::COMPRESSION_ZIP );
			$processed->setImageCompressionQuality( 100 );
		}
		$processed->writeImage( $destination );
		if ( $processed !== $image ) { $processed->clear(); }
		$image->clear();
	} catch ( Throwable $error ) {
		wp_send_json_error( array(
			'message' => sprintf(
				'O servidor não conseguiu processar a arte: %s (arquivo: %s; classe do erro: %s; memória em uso: %s de %s)',
				$error->getMessage(),
				basename( $source ),
				get_class( $error ),
				size_format( memory_get_usage( true ) ),
				ini_get( 'memory_limit' )
			),
		), 500 );
	}
	if ( ! file_exists( $destination ) ) {
		wp_send_json_error( array( 'message' => 'O arquivo processado não foi criado.' ), 500 );
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$generated_id = wp_insert_attachment( array(
		'post_mime_type' => $mime_type,
		'post_title'     => $code . $suffix,
		'post_status'    => 'inherit',
	), $destination );
	if ( is_wp_error( $generated_id ) ) {
		wp_send_json_error( array( 'message' => $generated_id->get_error_message() ), 500 );
	}
	wp_update_attachment_metadata( $generated_id, wp_generate_attachment_metadata( $generated_id, $destination ) );
	wp_send_json_success( array(
		'id'   => (int) $generated_id,
		'url'  => wp_get_attachment_url( $generated_id ),
		'name' => basename( $destination ),
		'mime' => $mime_type,
	) );
}

/** Retorna a posição de uma situação no fluxo operacional do pedido. */
function pw_personalizados_order_status_rank( $status ) {
	$flow = array( 'Criação da arte', 'Arte aprovada', 'Em produção', 'Produzido', 'Aguardando entrega', 'Entregue' );
	$rank = array_search( (string) $status, $flow, true );
	return false === $rank ? -1 : (int) $rank;
}

/** Mesma noção de "marketplace" usada no frontend (orderOriginIsMarketplace)
 * — pedidos desses canais não bloqueiam por falta de arte em PDF, só
 * avisam (ver promptMarketplaceMissingArt() em pedidos.js). Sem essa
 * exceção aqui, o servidor rejeitava QUALQUER gravação da tabela de
 * pedidos inteira (não só a do pedido em questão) enquanto existisse um
 * pedido de marketplace sem arte parado numa situação avançada. */
function pw_personalizados_order_origin_is_marketplace( $order ) {
	$origin = '';
	if ( is_array( $order ) ) {
		$origin = isset( $order['origin'] ) ? (string) $order['origin'] : '';
		if ( ! $origin && isset( $order['client'] ) && is_array( $order['client'] ) ) {
			$origin = isset( $order['client']['origin'] ) ? (string) $order['client']['origin'] : '';
		}
	}
	return in_array( $origin, array( 'Mercado Livre', 'Shopee' ), true );
}

/** Confirma se todos os itens possuem um anexo identificado como PDF. */
function pw_personalizados_order_art_error( $order ) {
	if ( ! is_array( $order ) || pw_personalizados_order_origin_is_marketplace( $order ) || pw_personalizados_order_status_rank( isset( $order['status'] ) ? $order['status'] : '' ) < pw_personalizados_order_status_rank( 'Arte aprovada' ) ) {
		return '';
	}
	$items = isset( $order['items'] ) && is_array( $order['items'] ) ? $order['items'] : array();
	$missing = array();
	foreach ( $items as $index => $item ) {
		$art = is_array( $item ) && isset( $item['art'] ) && is_array( $item['art'] ) ? $item['art'] : array();
		$attachment_id = isset( $art['id'] ) ? absint( $art['id'] ) : 0;
		$mime = $attachment_id ? (string) get_post_mime_type( $attachment_id ) : ( isset( $art['mime'] ) ? (string) $art['mime'] : '' );
		$name = ( isset( $art['name'] ) ? (string) $art['name'] : '' ) . ' ' . ( isset( $art['url'] ) ? (string) $art['url'] : '' );
		$is_pdf = 'application/pdf' === strtolower( $mime ) || 1 === preg_match( '/\.pdf(?:$|\?)/i', $name );
		if ( ! $is_pdf ) {
			$missing[] = isset( $item['description'] ) && $item['description'] ? sanitize_text_field( $item['description'] ) : 'Produto ' . ( $index + 1 );
		}
	}
	if ( ! $items ) {
		return 'O pedido precisa ter ao menos um produto com arte em PDF.';
	}
	if ( $missing ) {
		return 'Anexe uma arte em PDF em cada produto antes de avançar a situação. Pendentes: ' . implode( '; ', array_slice( $missing, 0, 4 ) ) . ( count( $missing ) > 4 ? '; e outros.' : '.' );
	}
	return '';
}

/** Valida somente uma nova entrada ou um avanço de situação. */
function pw_personalizados_validate_order_art_transition( $order, $previous_order = null ) {
	$new_rank = pw_personalizados_order_status_rank( isset( $order['status'] ) ? $order['status'] : '' );
	$old_rank = is_array( $previous_order ) ? pw_personalizados_order_status_rank( isset( $previous_order['status'] ) ? $previous_order['status'] : '' ) : -1;
	if ( $new_rank < pw_personalizados_order_status_rank( 'Arte aprovada' ) || ( is_array( $previous_order ) && $new_rank <= $old_rank ) ) {
		return '';
	}
	return pw_personalizados_order_art_error( $order );
}

function pw_personalizados_save_storage() {
	global $wpdb;
	pw_personalizados_ajax_guard();

	$key = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';
	$is_migration = ! empty( $_POST['migration'] ) && current_user_can( 'manage_options' );

	if ( ! pw_personalizados_storage_key_allowed( $key ) ) {
		wp_send_json_error( array( 'message' => 'Tipo de registro não permitido.' ), 400 );
	}
	if ( in_array( $key, array( 'pw_personalizados_dtf_expense_categories', 'pw_personalizados_dtf_expense_units', 'pw_personalizados_dtf_expense_movements', 'pw_personalizados_expense_units_migrated', 'pw_personalizados_dtf_catalog', 'pw_personalizados_dtf_costs' ), true ) && ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Apenas administradores podem alterar os cadastros de despesas.' ), 403 );
	}

	$raw = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : '';
	if ( strlen( $raw ) > 8 * MB_IN_BYTES ) {
		wp_send_json_error( array( 'message' => 'O conjunto de dados excedeu o limite de segurança.' ), 413 );
	}
	$value = json_decode( $raw, true );
	if ( JSON_ERROR_NONE !== json_last_error() ) {
		wp_send_json_error( array( 'message' => 'Os dados recebidos são inválidos.' ), 400 );
	}

	pw_personalizados_install_tables();
	if ( 'pw_personalizados_orders' === $key && is_array( $value ) && ! $is_migration ) {
		$orders_table = pw_personalizados_table_map()['pw_personalizados_orders'];
		$previous_orders = array();
		$stored_rows = $wpdb->get_results( "SELECT object_id, payload FROM {$orders_table}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( (array) $stored_rows as $stored_row ) {
			$decoded = json_decode( isset( $stored_row['payload'] ) ? (string) $stored_row['payload'] : '', true );
			if ( is_array( $decoded ) ) {
				$previous_orders[ (string) $stored_row['object_id'] ] = $decoded;
			}
		}
		foreach ( $value as $order ) {
			if ( ! is_array( $order ) ) { continue; }
			$order_number = isset( $order['orderNumber'] ) ? (string) $order['orderNumber'] : '';
			$previous = isset( $previous_orders[ $order_number ] ) ? $previous_orders[ $order_number ] : null;
			$art_error = pw_personalizados_validate_order_art_transition( $order, $previous );
			if ( $art_error ) {
				wp_send_json_error( array( 'message' => ( $order_number ? $order_number . ': ' : '' ) . $art_error ), 422 );
			}
		}
	}
	$now = current_time( 'mysql', true );
	// PEDIDOS nunca mais passam pelo "apaga tudo e reinsere tudo" genérico
	// abaixo — só faz UPSERT (grava/atualiza cada pedido enviado, nunca
	// apaga nenhum que não veio no array). O comportamento antigo apagava a
	// tabela inteira e recriava a partir do array que ESSE navegador tinha
	// na memória — se outro computador tivesse acabado de criar um pedido
	// novo e o navegador atual ainda não tivesse sincronizado essa criação,
	// o próximo salvamento feito por ele (qualquer edição, de qualquer
	// outro pedido) apagava esse pedido novo pra sempre, sem aviso nenhum.
	// Foi exatamente isso que causou o pedido 00061 sumir só em um
	// computador: criado numa máquina, apagado pouco depois por outra
	// máquina salvando sua cópia desatualizada. Excluir um pedido de
	// verdade agora usa um endpoint próprio e específico
	// (pw_personalizados_delete_orders), nunca mais o array completo.
	if ( 'pw_personalizados_orders' === $key && is_array( $value ) ) {
		$orders_table = pw_personalizados_table_map()['pw_personalizados_orders'];
		$wpdb->query( 'START TRANSACTION' );
		foreach ( array_values( $value ) as $index => $record ) {
			if ( ! is_array( $record ) ) { continue; }
			$columns = pw_personalizados_record_columns( $record );
			$object_id = pw_personalizados_record_identity( $key, $record, $index );
			$payload = wp_json_encode( $record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			$result = $wpdb->query( $wpdb->prepare(
				"INSERT INTO {$orders_table} (object_id, code, name, status, event_date, total, payload, created_at, updated_at)
				 VALUES (%s, %s, %s, %s, %s, %f, %s, %s, %s)
				 ON DUPLICATE KEY UPDATE code = VALUES(code), name = VALUES(name), status = VALUES(status), event_date = VALUES(event_date), total = VALUES(total), payload = VALUES(payload), updated_at = VALUES(updated_at)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$object_id, $columns['code'], $columns['name'], $columns['status'], $columns['date'], $columns['total'], $payload, $now, $now
			) );
			if ( false === $result ) {
				$wpdb->query( 'ROLLBACK' );
				wp_send_json_error( array( 'message' => 'O banco recusou um dos pedidos.' ), 500 );
			}
		}
		$wpdb->query( 'COMMIT' );
		wp_send_json_success( array( 'saved' => true ) );
	} elseif ( pw_personalizados_is_collection_key( $key ) ) {
		if ( ! is_array( $value ) ) {
			wp_send_json_error( array( 'message' => 'A lista de registros recebida é inválida.' ), 400 );
		}
		$table = pw_personalizados_table_map()[ $key ];
		$wpdb->query( 'START TRANSACTION' );
		$deleted = $wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( false === $deleted ) {
			$wpdb->query( 'ROLLBACK' );
			wp_send_json_error( array( 'message' => 'Não foi possível preparar a tabela para gravação.' ), 500 );
		}
		foreach ( array_values( $value ) as $index => $record ) {
			if ( ! is_array( $record ) ) { continue; }
			$columns = pw_personalizados_record_columns( $record );
			$inserted = $wpdb->insert(
				$table,
				array(
					'object_id'  => pw_personalizados_record_identity( $key, $record, $index ),
					'code'       => $columns['code'],
					'name'       => $columns['name'],
					'status'     => $columns['status'],
					'event_date' => $columns['date'],
					'total'      => $columns['total'],
					'payload'    => wp_json_encode( $record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
					'created_at' => $now,
					'updated_at' => $now,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s' )
			);
			if ( false === $inserted ) {
				$wpdb->query( 'ROLLBACK' );
				wp_send_json_error( array( 'message' => 'O banco recusou um dos registros.' ), 500 );
			}
		}
		$wpdb->query( 'COMMIT' );
	} else {
		$table = pw_personalizados_meta_table();
		$result = $wpdb->replace(
			$table,
			array(
				'meta_key'   => $key,
				'meta_value' => wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'updated_at' => $now,
			),
			array( '%s', '%s', '%s' )
		);
		if ( false === $result ) {
			wp_send_json_error( array( 'message' => 'Não foi possível gravar a configuração no banco.' ), 500 );
		}
	}
	wp_send_json_success( array( 'saved' => true ) );
}
add_action( 'wp_ajax_pw_personalizados_save_storage', 'pw_personalizados_save_storage' );

/**
 * Exclui pedidos específicos (por número do pedido) — usado ao mover um
 * pedido pra Lixeira. Agora que pw_personalizados_save_storage só faz
 * UPSERT pra pedidos (nunca apaga nada a partir do array completo), a
 * exclusão de verdade precisa desse caminho próprio e direcionado, senão o
 * pedido nunca sairia do banco.
 */
function pw_personalizados_delete_orders() {
	global $wpdb;
	pw_personalizados_ajax_guard();
	$raw = isset( $_POST['order_numbers'] ) ? wp_unslash( $_POST['order_numbers'] ) : '';
	$order_numbers = json_decode( $raw, true );
	if ( ! is_array( $order_numbers ) || ! $order_numbers ) {
		wp_send_json_error( array( 'message' => 'Nenhum pedido informado para excluir.' ), 400 );
	}
	$order_numbers = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', array_map( 'strval', $order_numbers ) ) ) ) );
	if ( ! $order_numbers ) {
		wp_send_json_error( array( 'message' => 'Nenhum pedido válido informado para excluir.' ), 400 );
	}
	pw_personalizados_install_tables();
	$orders_table = pw_personalizados_table_map()['pw_personalizados_orders'];
	$placeholders = implode( ',', array_fill( 0, count( $order_numbers ), '%s' ) );
	$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$orders_table} WHERE object_id IN ({$placeholders})", $order_numbers ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( false === $deleted ) {
		wp_send_json_error( array( 'message' => 'Não foi possível excluir o(s) pedido(s) no banco.' ), 500 );
	}
	wp_send_json_success( array( 'deleted' => (int) $deleted ) );
}
add_action( 'wp_ajax_pw_personalizados_delete_orders', 'pw_personalizados_delete_orders' );

/**
 * Reserva atomicamente o próximo número de pedido da sequência global.
 * Retorna a string zero-padded com 5 dígitos (ex: "00042") ou WP_Error.
 * Usado pelo módulo DTF UV para garantir numeração unificada com os pedidos do sistema.
 */
function pw_personalizados_reserve_order_number() {
	global $wpdb;
	pw_personalizados_install_tables();
	$meta_table   = pw_personalizados_meta_table();
	$orders_table = pw_personalizados_table_map()['pw_personalizados_orders'];
	$sequence_key = 'pw_personalizados_order_sequence';
	$now          = current_time( 'mysql', true );

	$wpdb->query( $wpdb->prepare(
		"INSERT IGNORE INTO {$meta_table} (meta_key, meta_value, updated_at) VALUES (%s, %s, %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sequence_key, wp_json_encode( 1 ), $now
	) );

	$reserved = null;
	$wpdb->query( 'START TRANSACTION' );
	try {
		$stored_raw = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$meta_table} WHERE meta_key = %s FOR UPDATE", $sequence_key ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$stored     = json_decode( (string) $stored_raw, true );
		$next       = is_numeric( $stored ) ? max( 1, (int) $stored ) : 1;
		$existing   = $wpdb->get_col( "SELECT object_id FROM {$orders_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( (array) $existing as $object_id ) {
			if ( preg_match( '/^(?:PW-\d{8}-)?(\d{5})$/', (string) $object_id, $match ) ) {
				$next = max( $next, (int) $match[1] + 1 );
			}
		}
		if ( $next > 99999 ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'sequence_overflow', 'Sequência de pedidos atingiu o limite de 99.999.' );
		}
		$wpdb->replace( $meta_table, array(
			'meta_key'   => $sequence_key,
			'meta_value' => wp_json_encode( $next + 1 ),
			'updated_at' => $now,
		), array( '%s', '%s', '%s' ) );
		$wpdb->query( 'COMMIT' );
		$reserved = str_pad( (string) $next, 5, '0', STR_PAD_LEFT );
	} catch ( Throwable $error ) {
		$wpdb->query( 'ROLLBACK' );
		return new WP_Error( 'sequence_error', 'Não foi possível reservar o número do pedido.' );
	}
	return $reserved;
}

/**
 * Cadastra um novo pedido com numeração global protegida por transação.
 *
 * A listagem completa de pedidos continua disponível para os relatórios, mas
 * novos pedidos entram por esta operação atômica para que dois usuários não
 * recebam o mesmo sequencial nem sobrescrevam o cadastro um do outro.
 */
function pw_personalizados_create_order() {
	global $wpdb;
	pw_personalizados_ajax_guard();

	$raw = isset( $_POST['order'] ) ? wp_unslash( $_POST['order'] ) : '';
	if ( ! is_string( $raw ) || strlen( $raw ) > 2 * MB_IN_BYTES ) {
		wp_send_json_error( array( 'message' => 'Os dados do pedido excederam o limite de segurança.' ), 413 );
	}
	$order = json_decode( $raw, true );
	if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $order ) ) {
		wp_send_json_error( array( 'message' => 'Os dados do pedido são inválidos.' ), 400 );
	}
	$art_error = pw_personalizados_validate_order_art_transition( $order );
	if ( $art_error ) {
		wp_send_json_error( array( 'message' => $art_error ), 422 );
	}

	pw_personalizados_install_tables();
	$meta_table   = pw_personalizados_meta_table();
	$orders_table = pw_personalizados_table_map()['pw_personalizados_orders'];
	$sequence_key = 'pw_personalizados_order_sequence';
	$now          = current_time( 'mysql', true );

	// Garante que o registro exista antes do SELECT ... FOR UPDATE. Assim a
	// primeira gravação também adquire um lock real quando houver concorrência.
	$wpdb->query(
		$wpdb->prepare(
			"INSERT IGNORE INTO {$meta_table} (meta_key, meta_value, updated_at) VALUES (%s, %s, %s)",
			$sequence_key,
			wp_json_encode( 1 ),
			$now
		)
	);
	$wpdb->query( 'START TRANSACTION' );
	try {
		$stored_raw = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$meta_table} WHERE meta_key = %s FOR UPDATE", $sequence_key ) );
		$stored    = json_decode( (string) $stored_raw, true );
		$next      = is_numeric( $stored ) ? max( 1, (int) $stored ) : 1;
		$existing  = $wpdb->get_col( "SELECT object_id FROM {$orders_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( (array) $existing as $object_id ) {
			if ( preg_match( '/^(?:PW-\d{8}-)?(\d{5})$/', (string) $object_id, $match ) ) {
				$next = max( $next, (int) $match[1] + 1 );
			}
		}
		if ( $next > 99999 ) {
			$wpdb->query( 'ROLLBACK' );
			wp_send_json_error( array( 'message' => 'A sequência global de pedidos atingiu o limite de 99.999.' ), 409 );
		}

		$order['orderNumber'] = str_pad( (string) $next, 5, '0', STR_PAD_LEFT );
		$columns             = pw_personalizados_record_columns( $order );
		$inserted            = $wpdb->insert(
			$orders_table,
			array(
				'object_id'  => $order['orderNumber'],
				'code'       => $columns['code'],
				'name'       => $columns['name'],
				'status'     => $columns['status'],
				'event_date' => $columns['date'],
				'total'      => $columns['total'],
				'payload'    => wp_json_encode( $order, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s' )
		);
		if ( false === $inserted ) {
			$wpdb->query( 'ROLLBACK' );
			wp_send_json_error( array( 'message' => 'O banco recusou o novo pedido; tente novamente.' ), 409 );
		}
		$next_saved = $wpdb->replace(
			$meta_table,
			array(
				'meta_key'   => $sequence_key,
				'meta_value' => wp_json_encode( $next + 1 ),
				'updated_at' => $now,
			),
			array( '%s', '%s', '%s' )
		);
		if ( false === $next_saved ) {
			$wpdb->query( 'ROLLBACK' );
			wp_send_json_error( array( 'message' => 'Não foi possível atualizar a sequência do pedido.' ), 500 );
		}
		$wpdb->query( 'COMMIT' );
		wp_send_json_success( array( 'order' => $order, 'orderNumber' => $order['orderNumber'], 'sequence' => $next ) );
	} catch ( Throwable $error ) {
		$wpdb->query( 'ROLLBACK' );
		wp_send_json_error( array( 'message' => 'Não foi possível cadastrar o pedido com segurança.' ), 500 );
	}
}
add_action( 'wp_ajax_pw_personalizados_create_order', 'pw_personalizados_create_order' );

/** Entrega uma cópia atual do banco para o painel em modo ao vivo. */
function pw_personalizados_refresh_storage() {
	pw_personalizados_ajax_guard();
	wp_send_json_success( array( 'storage' => pw_personalizados_get_storage() ) );
}
add_action( 'wp_ajax_pw_personalizados_refresh_storage', 'pw_personalizados_refresh_storage' );

/* =========================================================
 * NOTIFICAÇÃO DE NOVO PEDIDO (ícone piscando, por usuário)
 * ========================================================= */

/**
 * "Retrato" leve de quantos pedidos existem agora — usado pra saber se
 * apareceu pedido novo desde a última vez que ESTE usuário viu. Por que só
 * COUNT(*) e não o maior row_id/created_at: pw_personalizados_save_storage()
 * apaga e reinsere a tabela inteira a cada salvamento (mesmo só editando um
 * pedido existente), então row_id/created_at mudam sempre e não servem pra
 * distinguir "editou" de "criou" — só a CONTAGEM de linhas é estável entre
 * edições e só sobe quando um pedido é de fato adicionado.
 */
function pw_personalizados_get_order_notification_snapshot() {
	global $wpdb;
	pw_personalizados_install_tables();
	$table = pw_personalizados_table_map()['pw_personalizados_orders'];
	$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return array( 'count' => $count );
}

/** Estado de notificação PARA UM usuário específico (ID do usuário do WordPress). */
function pw_personalizados_get_order_notification_status( $user_id ) {
	$snapshot = pw_personalizados_get_order_notification_snapshot();
	$acknowledged = (int) get_user_meta( $user_id, '_pw_order_notify_ack_count', true );
	$unseen = max( 0, $snapshot['count'] - $acknowledged );
	return array( 'hasNew' => $unseen > 0, 'count' => $snapshot['count'], 'unseenCount' => $unseen );
}

/** Confirma, só pra este usuário, que ele já viu o total atual de pedidos — o ícone para de piscar pra ele até um pedido novo ser criado. */
function pw_personalizados_mark_order_notifications_seen( $user_id ) {
	$snapshot = pw_personalizados_get_order_notification_snapshot();
	update_user_meta( $user_id, '_pw_order_notify_ack_count', $snapshot['count'] );
}

/** Monta o resumo de UM pedido (cliente, itens, total e a miniatura do
 * primeiro anexo, se houver) a partir do payload já decodificado. A arte
 * quase sempre é PDF, então a ordem de preferência pra miniatura é: (1) o
 * PNG já convertido (`art.convertedPng`, gerado ao montar TIFF/remover
 * fundo) quando existir — mais barato, já está pronto; (2) o próprio anexo,
 * se já for uma imagem; (3) gera uma miniatura rápida do PDF na hora
 * (`pw_personalizados_quick_pdf_thumbnail()`, a mesma usada no simulador
 * DTF UV) — só roda quando o usuário abre o aviso, então o custo do
 * render fica restrito a essa ação pontual, não a cada carregamento de
 * página. Sem nenhuma dessas três fontes, não mostra miniatura nenhuma
 * (nunca um botão "abrir anexo" no lugar). */
function pw_personalizados_build_order_summary( $order ) {
	$items = array();
	$thumbnail = ''; $thumbnail_mime = '';
	foreach ( (array) ( $order['items'] ?? array() ) as $item ) {
		if ( ! is_array( $item ) ) { continue; }
		$items[] = array(
			'description' => (string) ( $item['description'] ?? '' ),
			'quantity'    => (float) ( $item['quantity'] ?? 0 ),
		);
		if ( $thumbnail || ! is_array( $item['art'] ?? null ) ) { continue; }
		$converted = is_array( $item['art']['convertedPng'] ?? null ) ? $item['art']['convertedPng'] : null;
		if ( $converted && ! empty( $converted['url'] ) ) {
			$thumbnail = (string) $converted['url'];
			$thumbnail_mime = (string) ( $converted['mime'] ?? 'image/png' );
		} elseif ( ! empty( $item['art']['url'] ) && preg_match( '/^image\//i', (string) ( $item['art']['mime'] ?? '' ) ) ) {
			$thumbnail = (string) $item['art']['url'];
			$thumbnail_mime = (string) ( $item['art']['mime'] ?? '' );
		} elseif ( ! empty( $item['art']['id'] ) && function_exists( 'pw_personalizados_quick_pdf_thumbnail' ) ) {
			$attachment_id = (int) $item['art']['id'];
			$art_path = get_attached_file( $attachment_id );
			if ( $art_path && file_exists( $art_path ) && 'application/pdf' === get_post_mime_type( $attachment_id ) ) {
				$quick = pw_personalizados_quick_pdf_thumbnail( $art_path );
				if ( $quick ) {
					$thumbnail = 'data:image/png;base64,' . $quick;
					$thumbnail_mime = 'image/png';
				}
			}
		}
	}
	$client = is_array( $order['client'] ?? null ) ? $order['client'] : array();
	return array(
		'orderNumber'   => (string) ( $order['orderNumber'] ?? '' ),
		'client'        => (string) ( $client['name'] ?? '—' ),
		'createdDate'   => (string) ( $order['createdDate'] ?? '' ),
		'status'        => (string) ( $order['status'] ?? '' ),
		'total'         => is_numeric( $order['total'] ?? null ) ? (float) $order['total'] : null,
		'items'         => $items,
		'thumbnail'     => $thumbnail,
		'thumbnailMime' => $thumbnail_mime,
	);
}

/** Resumo dos pedidos NOVOS (ainda não vistos por este usuário) — pra
 * preencher o aviso de "pedido novo" com navegação entre eles. Como o
 * orderNumber é sequencial (ver pw_personalizados_get_order_notification_snapshot()
 * pra saber por que row_id/created_at não servem pra identificar "novo"), os
 * `$limit` pedidos com maior orderNumber são, por definição, os mais
 * recentes — e como esse limite já vem do delta contagem-atual menos
 * contagem-confirmada, eles são exatamente os ainda não vistos. Lê todos os
 * payloads pois não há coluna indexada pra "mais recente" nessa tabela — só
 * roda quando o usuário abre o aviso, então não é uma consulta frequente. */
function pw_personalizados_get_new_order_summaries( $limit ) {
	global $wpdb;
	$limit = max( 1, min( 50, (int) $limit ) );
	$table = pw_personalizados_table_map()['pw_personalizados_orders'];
	$rows = $wpdb->get_col( "SELECT payload FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$orders = array();
	foreach ( (array) $rows as $json ) {
		$order = json_decode( (string) $json, true );
		if ( ! is_array( $order ) ) { continue; }
		$order['_number'] = (int) preg_replace( '/\D/', '', (string) ( $order['orderNumber'] ?? '' ) );
		$orders[] = $order;
	}
	usort( $orders, function ( $a, $b ) { return $b['_number'] <=> $a['_number']; } );
	$orders = array_slice( $orders, 0, $limit );
	return array_map( 'pw_personalizados_build_order_summary', $orders );
}

function pw_personalizados_order_notification_status_ajax() {
	pw_personalizados_ajax_guard();
	wp_send_json_success( pw_personalizados_get_order_notification_status( get_current_user_id() ) );
}
add_action( 'wp_ajax_pw_personalizados_order_notification_status', 'pw_personalizados_order_notification_status_ajax' );

function pw_personalizados_mark_order_notifications_seen_ajax() {
	pw_personalizados_ajax_guard();
	pw_personalizados_mark_order_notifications_seen( get_current_user_id() );
	wp_send_json_success( array( 'marked' => true ) );
}
add_action( 'wp_ajax_pw_personalizados_mark_order_notifications_seen', 'pw_personalizados_mark_order_notifications_seen_ajax' );

function pw_personalizados_new_order_summaries_ajax() {
	pw_personalizados_ajax_guard();
	$status = pw_personalizados_get_order_notification_status( get_current_user_id() );
	$limit = max( 1, (int) $status['unseenCount'] );
	$summaries = pw_personalizados_get_new_order_summaries( $limit );
	if ( ! $summaries ) { wp_send_json_error( array( 'message' => 'Nenhum pedido encontrado.' ), 404 ); }
	wp_send_json_success( array( 'orders' => $summaries ) );
}
add_action( 'wp_ajax_pw_personalizados_new_order_summaries', 'pw_personalizados_new_order_summaries_ajax' );

/**
 * "Limpeza": manutenção real do banco de dados, não só um teste.
 * - Otimiza as tabelas do WordPress que este sistema mais usa (o app
 *   guarda os cadastros como JSON em wp_options/wp_postmeta, que ficam
 *   fragmentados depois de muitas atualizações).
 * - Remove transients expirados que o WordPress não limpou sozinho.
 * - Remove pastas temporárias órfãs do "Montar Tiff" (o mesmo que já
 *   roda sozinho a cada novo lote, aqui disponível sob demanda).
 * - Remove arquivos temporários de importação de backup mais antigos
 *   que 24h, se sobrou algum de uma importação interrompida.
 *
 * Função "pura" (sem depender de $_POST/nonce) pra poder ser chamada
 * tanto pelo botão manual quanto pelo agendamento automático semanal.
 */
function pw_personalizados_run_system_cleanup( $source = 'manual' ) {
	global $wpdb;
	$results = array();

	// Otimiza as tabelas principais (reduz fragmentação depois de muitos
	// updates/deletes — o ganho real depende do motor de tabela do MySQL,
	// por isso o resultado de cada uma é reportado em vez de assumido).
	$tables = array( $wpdb->options, $wpdb->postmeta, $wpdb->posts );
	foreach ( $tables as $table ) {
		$size_before = (int) $wpdb->get_var( $wpdb->prepare( "SELECT data_length + index_length FROM information_schema.TABLES WHERE table_schema = %s AND table_name = %s", DB_NAME, $table ) );
		$outcome = $wpdb->get_row( "OPTIMIZE TABLE `{$table}`", ARRAY_A );
		$size_after = (int) $wpdb->get_var( $wpdb->prepare( "SELECT data_length + index_length FROM information_schema.TABLES WHERE table_schema = %s AND table_name = %s", DB_NAME, $table ) );
		$freed = max( 0, $size_before - $size_after );
		$results[] = array(
			'label' => 'Tabela ' . $table . ' otimizada',
			'ok'    => true,
			'note'  => $freed > 0 ? ( size_format( $freed ) . ' liberados' ) : ( ( $outcome['Msg_text'] ?? '' ) ?: 'sem fragmentação a reduzir' ),
		);
	}

	// Transients expirados: o WordPress deveria limpar sozinho, mas em
	// muitos hostings o cron real não roda com frequência.
	$expired_count = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_%' AND option_value < UNIX_TIMESTAMP()"
	);
	if ( function_exists( 'delete_expired_transients' ) ) {
		delete_expired_transients( true );
	}
	$results[] = array( 'label' => 'Transients expirados removidos', 'ok' => true, 'note' => $expired_count . ' encontrado(s)' );

	// Pastas temporárias órfãs do "Montar Tiff" (mais de 2h, job abandonado).
	if ( function_exists( 'pw_personalizados_arts_tiff_gc' ) ) {
		$base = function_exists( 'pw_personalizados_arts_tiff_base_dir' ) ? pw_personalizados_arts_tiff_base_dir() : '';
		$before = $base && is_dir( $base ) ? count( glob( $base . '/*', GLOB_ONLYDIR ) ?: array() ) : 0;
		pw_personalizados_arts_tiff_gc();
		$after = $base && is_dir( $base ) ? count( glob( $base . '/*', GLOB_ONLYDIR ) ?: array() ) : 0;
		$results[] = array( 'label' => 'Pastas temporárias do "Montar Tiff" órfãs removidas', 'ok' => true, 'note' => max( 0, $before - $after ) . ' pasta(s)' );
	}

	// Sobras de importação de backup interrompida (mais de 24h).
	$backup_uploads = trailingslashit( wp_upload_dir()['basedir'] ) . 'pw-printway-backup-uploads';
	$stale_backup_files = 0;
	if ( is_dir( $backup_uploads ) ) {
		foreach ( glob( $backup_uploads . '/*' ) ?: array() as $path ) {
			if ( is_file( $path ) && filemtime( $path ) < time() - DAY_IN_SECONDS ) {
				@unlink( $path );
				$stale_backup_files++;
			}
		}
	}
	$results[] = array( 'label' => 'Arquivos temporários de backup (+24h) removidos', 'ok' => true, 'note' => $stale_backup_files . ' arquivo(s)' );

	update_option( 'pw_personalizados_last_cleanup', array(
		'time'         => time(),
		'source'       => in_array( $source, array( 'manual', 'automatic' ), true ) ? $source : 'manual',
		'results'      => $results,
		'expired_seen' => $expired_count,
	), false );

	if ( function_exists( 'pw_printway_log' ) ) {
		pw_printway_log( 'pedidos', 'info', 'Limpeza do sistema executada (' . ( 'automatic' === $source ? 'automática' : 'manual' ) . ').' );
	}

	return $results;
}

function pw_personalizados_system_cleanup() {
	pw_personalizados_ajax_guard();
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Somente administradores podem executar a limpeza do sistema.' ), 403 );
	}
	$results = pw_personalizados_run_system_cleanup( 'manual' );
	wp_send_json_success( array( 'results' => $results ) );
}
add_action( 'wp_ajax_pw_personalizados_system_cleanup', 'pw_personalizados_system_cleanup' );

/** Roda pelo agendamento automático do WordPress (wp-cron), sem interação do usuário. */
function pw_personalizados_scheduled_cleanup_run() {
	pw_personalizados_run_system_cleanup( 'automatic' );
}
add_action( 'pw_personalizados_cleanup_cron', 'pw_personalizados_scheduled_cleanup_run' );

/** Registra a periodicidade "semanal" pro wp-cron (o WordPress não vem com ela por padrão). */
add_filter( 'cron_schedules', function ( $schedules ) {
	if ( ! isset( $schedules['weekly'] ) ) {
		$schedules['weekly'] = array( 'interval' => 7 * DAY_IN_SECONDS, 'display' => 'Uma vez por semana' );
	}
	return $schedules;
} );

/** Garante que o agendamento semanal exista (chamado a cada carregamento do plugin). */
function pw_personalizados_ensure_cleanup_schedule() {
	if ( ! wp_next_scheduled( 'pw_personalizados_cleanup_cron' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'weekly', 'pw_personalizados_cleanup_cron' );
	}
}
add_action( 'init', 'pw_personalizados_ensure_cleanup_schedule' );

/** Devolve o status pra tela: última execução, se é recomendado rodar agora, e a próxima automática. */
function pw_personalizados_cleanup_status_fetch() {
	pw_personalizados_ajax_guard();
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Somente administradores podem ver o status da limpeza.' ), 403 );
	}
	$last = get_option( 'pw_personalizados_last_cleanup', null );
	global $wpdb;
	$expired_now = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_%' AND option_value < UNIX_TIMESTAMP()"
	);
	$orphan_dirs = 0;
	if ( function_exists( 'pw_personalizados_arts_tiff_base_dir' ) ) {
		$base = pw_personalizados_arts_tiff_base_dir();
		$orphan_dirs = $base && is_dir( $base ) ? count( glob( $base . '/*', GLOB_ONLYDIR ) ?: array() ) : 0;
	}
	$days_since_last = $last && ! empty( $last['time'] ) ? ( time() - (int) $last['time'] ) / DAY_IN_SECONDS : null;
	// Recomenda rodar de novo se nunca rodou, se já faz mais de 7 dias, ou
	// se já há um volume razoável de sobra acumulada detectável agora.
	$needed = ! $last || null === $days_since_last || $days_since_last > 7 || $expired_now > 50 || $orphan_dirs > 5;
	wp_send_json_success( array(
		'last'      => $last ?: null,
		'needed'    => $needed,
		'reason'    => ! $last ? 'nunca executada' : ( $days_since_last > 7 ? 'faz mais de 7 dias' : ( $expired_now > 50 ? $expired_now . ' transients expirados acumulados' : ( $orphan_dirs > 5 ? $orphan_dirs . ' pastas temporárias acumuladas' : 'em dia' ) ) ),
		'next_auto' => wp_next_scheduled( 'pw_personalizados_cleanup_cron' ),
	) );
}
add_action( 'wp_ajax_pw_personalizados_cleanup_status_fetch', 'pw_personalizados_cleanup_status_fetch' );

/* ===================== Correção automática de textos — agendamento ===================== */

/** Mesma lógica de correção de maiúsculas usada na tela (pedidos.js: suggestCaseFix), em PHP, para rodar sozinha via wp-cron. */
function pw_personalizados_case_correction_suggest( $value ) {
	$original = is_string( $value ) ? $value : (string) $value;
	$collapsed = trim( preg_replace( '/\s+/u', ' ', $original ) );
	if ( '' === $collapsed ) {
		return $collapsed;
	}
	$lower_connectors = array( 'de', 'da', 'do', 'das', 'dos', 'e' );
	$forced_words = array( 'rua' => 'Rua' );
	$designator_words = array( 'quadra', 'conjunto', 'lote', 'bloco', 'setor', 'modulo', 'apto', 'apartamento', 'sala', 'galpao', 'torre', 'ala', 'box', 'unidade' );
	$strip_accents = static function ( $text ) {
		$transliterated = remove_accents( $text );
		return strtolower( trim( $transliterated ) );
	};
	$words = explode( ' ', $collapsed );
	$result = array();
	foreach ( $words as $index => $word ) {
		$lower_word = mb_strtolower( $word, 'UTF-8' );
		$normalized_word = $strip_accents( $lower_word );
		if ( isset( $forced_words[ $normalized_word ] ) ) {
			$result[] = $forced_words[ $normalized_word ];
			continue;
		}
		$letters_only = preg_replace( '/[^A-Za-zÀ-ÖØ-öø-ÿ]/u', '', $word );
		$prev_word = $index > 0 ? $strip_accents( $words[ $index - 1 ] ) : '';
		if ( 1 === mb_strlen( $letters_only, 'UTF-8' ) && in_array( $prev_word, $designator_words, true ) ) {
			$result[] = mb_strtoupper( $word, 'UTF-8' );
			continue;
		}
		$is_connector = $index > 0 && in_array( $lower_word, $lower_connectors, true );
		$is_shouting = mb_strlen( $letters_only, 'UTF-8' ) > 2 && $word === mb_strtoupper( $word, 'UTF-8' ) && $word !== mb_strtolower( $word, 'UTF-8' );
		if ( ! $is_connector && ! $is_shouting ) {
			$result[] = $word;
			continue;
		}
		if ( $is_connector ) {
			$result[] = $lower_word;
			continue;
		}
		$parts = explode( '-', $lower_word );
		$titled = array_map( static function ( $part ) {
			return '' === $part ? $part : mb_strtoupper( mb_substr( $part, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $part, 1, null, 'UTF-8' );
		}, $parts );
		$result[] = implode( '-', $titled );
	}
	return implode( ' ', $result );
}

/** Lê/grava um valor dentro de um array associativo usando um caminho com pontos (ex.: "address.street"). */
function pw_personalizados_get_by_path( $array, $path ) {
	$keys = explode( '.', $path );
	$cursor = $array;
	foreach ( $keys as $key ) {
		if ( ! is_array( $cursor ) || ! array_key_exists( $key, $cursor ) ) {
			return null;
		}
		$cursor = $cursor[ $key ];
	}
	return $cursor;
}

function pw_personalizados_set_by_path( &$array, $path, $value ) {
	$keys = explode( '.', $path );
	$cursor = &$array;
	$last = array_pop( $keys );
	foreach ( $keys as $key ) {
		if ( ! isset( $cursor[ $key ] ) || ! is_array( $cursor[ $key ] ) ) {
			$cursor[ $key ] = array();
		}
		$cursor = &$cursor[ $key ];
	}
	$cursor[ $last ] = $value;
}

/** As mesmas fontes/campos varridos pela tela (pedidos.js: caseCorrectionSources), mas apontando pra tabela do banco. */
function pw_personalizados_case_correction_sources() {
	return array(
		'pw_personalizados_clients'   => array( 'name', 'address.street', 'address.neighborhood', 'address.city' ),
		'pw_personalizados_suppliers' => array( 'name', 'contact' ),
		'pw_personalizados_categories' => array( 'name' ),
		'pw_personalizados_units'      => array( 'name' ),
		'pw_personalizados_payment_methods' => array( 'name' ),
		'pw_personalizados_products'   => array( 'description' ),
		'pw_personalizados_orders'     => array( 'client.name', 'client.address.street', 'client.address.neighborhood', 'client.address.city' ),
	);
}

/** Varre e corrige sozinho (sem seleção manual) — usado pelo agendamento automático e pelo botão "Rodar agora". */
function pw_personalizados_case_correction_run_auto( $source = 'automatic' ) {
	global $wpdb;
	pw_personalizados_install_tables();
	$table_map = pw_personalizados_table_map();
	$fixed_fields = 0;
	$fixed_records = 0;
	foreach ( pw_personalizados_case_correction_sources() as $key => $paths ) {
		if ( ! isset( $table_map[ $key ] ) ) {
			continue;
		}
		$table = $table_map[ $key ];
		$rows = $wpdb->get_results( "SELECT row_id, payload FROM {$table}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( (array) $rows as $row ) {
			$record = json_decode( $row['payload'], true );
			if ( ! is_array( $record ) ) {
				continue;
			}
			$changed = false;
			foreach ( $paths as $path ) {
				$original = pw_personalizados_get_by_path( $record, $path );
				if ( ! is_string( $original ) || '' === trim( $original ) ) {
					continue;
				}
				$suggestion = pw_personalizados_case_correction_suggest( $original );
				if ( $suggestion !== $original ) {
					pw_personalizados_set_by_path( $record, $path, $suggestion );
					$changed = true;
					$fixed_fields++;
				}
			}
			if ( $changed ) {
				$fixed_records++;
				$columns = pw_personalizados_record_columns( $record );
				$payload = wp_json_encode( $record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
				$wpdb->update(
					$table,
					array( 'name' => $columns['name'], 'payload' => $payload, 'updated_at' => current_time( 'mysql', true ) ),
					array( 'row_id' => $row['row_id'] ),
					array( '%s', '%s', '%s' ),
					array( '%d' )
				);
			}
		}
	}
	update_option( 'pw_personalizados_case_correction_last_run', array(
		'time'    => time(),
		'source'  => in_array( $source, array( 'manual', 'automatic' ), true ) ? $source : 'manual',
		'fields'  => $fixed_fields,
		'records' => $fixed_records,
	), false );
	if ( function_exists( 'pw_printway_log' ) ) {
		pw_printway_log( 'pedidos', 'info', 'Correção automática de textos executada (' . ( 'automatic' === $source ? 'agendada' : 'manual' ) . ') — ' . $fixed_fields . ' campo(s) em ' . $fixed_records . ' registro(s).' );
	}
	return array( 'fields' => $fixed_fields, 'records' => $fixed_records );
}

function pw_personalizados_case_correction_default_schedule() {
	return array( 'enabled' => false, 'interval_value' => 30, 'interval_unit' => 'days' );
}

function pw_personalizados_case_correction_get_schedule() {
	$saved = get_option( 'pw_personalizados_case_correction_schedule', array() );
	$defaults = pw_personalizados_case_correction_default_schedule();
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return array(
		'enabled'        => ! empty( $saved['enabled'] ),
		'interval_value' => isset( $saved['interval_value'] ) ? max( 1, min( 365, (int) $saved['interval_value'] ) ) : $defaults['interval_value'],
		'interval_unit'  => isset( $saved['interval_unit'] ) && in_array( $saved['interval_unit'], array( 'days', 'weeks', 'months' ), true ) ? $saved['interval_unit'] : $defaults['interval_unit'],
	);
}

function pw_personalizados_case_correction_interval_seconds( $schedule ) {
	$unit_seconds = array( 'days' => DAY_IN_SECONDS, 'weeks' => WEEK_IN_SECONDS, 'months' => MONTH_IN_SECONDS );
	return max( 1, (int) $schedule['interval_value'] ) * $unit_seconds[ $schedule['interval_unit'] ];
}

/** Confere a cada hora (via wp-cron) se já passou do intervalo configurado; se sim, roda a correção sozinha. */
function pw_personalizados_case_correction_cron_tick() {
	$schedule = pw_personalizados_case_correction_get_schedule();
	if ( ! $schedule['enabled'] ) {
		return;
	}
	$last = get_option( 'pw_personalizados_case_correction_last_run', null );
	$interval = pw_personalizados_case_correction_interval_seconds( $schedule );
	if ( $last && ! empty( $last['time'] ) && ( time() - (int) $last['time'] ) < $interval ) {
		return;
	}
	pw_personalizados_case_correction_run_auto( 'automatic' );
}
add_action( 'pw_personalizados_case_correction_cron', 'pw_personalizados_case_correction_cron_tick' );

function pw_personalizados_case_correction_ensure_schedule() {
	if ( ! wp_next_scheduled( 'pw_personalizados_case_correction_cron' ) ) {
		wp_schedule_event( time() + 10 * MINUTE_IN_SECONDS, 'hourly', 'pw_personalizados_case_correction_cron' );
	}
}
add_action( 'init', 'pw_personalizados_case_correction_ensure_schedule' );

function pw_personalizados_case_correction_schedule_fetch() {
	pw_personalizados_ajax_guard();
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Somente administradores podem ver o agendamento.' ), 403 );
	}
	$schedule = pw_personalizados_case_correction_get_schedule();
	$last = get_option( 'pw_personalizados_case_correction_last_run', null );
	$next = null;
	if ( $schedule['enabled'] ) {
		$base = $last && ! empty( $last['time'] ) ? (int) $last['time'] : time();
		$next = $base + pw_personalizados_case_correction_interval_seconds( $schedule );
	}
	wp_send_json_success( array( 'schedule' => $schedule, 'last' => $last ?: null, 'next' => $next ) );
}
add_action( 'wp_ajax_pw_personalizados_case_correction_schedule_fetch', 'pw_personalizados_case_correction_schedule_fetch' );

function pw_personalizados_case_correction_schedule_save() {
	pw_personalizados_ajax_guard();
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Somente administradores podem alterar o agendamento.' ), 403 );
	}
	$unit = isset( $_POST['interval_unit'] ) ? sanitize_text_field( wp_unslash( $_POST['interval_unit'] ) ) : 'days';
	$schedule = array(
		'enabled'        => ! empty( $_POST['enabled'] ) && 'false' !== $_POST['enabled'],
		'interval_value' => isset( $_POST['interval_value'] ) ? max( 1, min( 365, (int) $_POST['interval_value'] ) ) : 30,
		'interval_unit'  => in_array( $unit, array( 'days', 'weeks', 'months' ), true ) ? $unit : 'days',
	);
	update_option( 'pw_personalizados_case_correction_schedule', $schedule, false );
	wp_send_json_success( array( 'schedule' => $schedule ) );
}
add_action( 'wp_ajax_pw_personalizados_case_correction_schedule_save', 'pw_personalizados_case_correction_schedule_save' );

function pw_personalizados_case_correction_run_now() {
	pw_personalizados_ajax_guard();
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Somente administradores podem executar a correção.' ), 403 );
	}
	$result = pw_personalizados_case_correction_run_auto( 'manual' );
	wp_send_json_success( $result );
}
add_action( 'wp_ajax_pw_personalizados_case_correction_run_now', 'pw_personalizados_case_correction_run_now' );

/* ================= fim da correção automática de textos — agendamento ================= */

function pw_personalizados_current_version() {
	pw_personalizados_ajax_guard();
	$ready = pw_personalizados_release_ready();
	wp_send_json_success(
		array(
			'version' => $ready ? PW_PERSONALIZADOS_VERSION : '',
			'ready'   => $ready,
		)
	);
}
add_action( 'wp_ajax_pw_personalizados_current_version', 'pw_personalizados_current_version' );

/**
 * Aba "Erros" em Configurações: lê o mesmo log central usado na tela
 * PrintWay → Logs do wp-admin (printway-logger.php), sem duplicar
 * armazenamento — um único registro pro sistema inteiro.
 */
function pw_personalizados_errors_tab_fetch() {
	pw_personalizados_ajax_guard();
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Somente administradores podem ver o registro de erros.' ), 403 );
	}
	if ( ! function_exists( 'pw_printway_log_read' ) ) {
		wp_send_json_success( array( 'entries' => array() ) );
	}
	$level = isset( $_POST['level'] ) ? sanitize_key( wp_unslash( $_POST['level'] ) ) : '';
	$entries = pw_printway_log_read( 300, $level );
	wp_send_json_success( array( 'entries' => $entries ) );
}
add_action( 'wp_ajax_pw_personalizados_errors_tab_fetch', 'pw_personalizados_errors_tab_fetch' );

/** Limpa o registro de erros — tudo, ou só as entradas marcadas na aba. */
function pw_personalizados_errors_tab_clear() {
	pw_personalizados_ajax_guard();
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Somente administradores podem limpar o registro de erros.' ), 403 );
	}
	$scope = isset( $_POST['scope'] ) ? sanitize_key( wp_unslash( $_POST['scope'] ) ) : 'all';
	if ( 'selected' === $scope ) {
		$ids = isset( $_POST['ids'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['ids'] ) ) : array();
		$removed = function_exists( 'pw_printway_log_clear_ids' ) ? pw_printway_log_clear_ids( $ids ) : 0;
		wp_send_json_success( array( 'removed' => $removed ) );
	}
	if ( function_exists( 'pw_printway_log_clear_all' ) ) {
		pw_printway_log_clear_all();
	}
	wp_send_json_success();
}
add_action( 'wp_ajax_pw_personalizados_errors_tab_clear', 'pw_personalizados_errors_tab_clear' );

/**
 * Recebe os erros que o próprio app já mostra pro usuário (showMessage
 * tipo 'error', em qualquer tela) e também exceções JS não tratadas, e
 * grava no log central — assim a aba "Erros" cobre o sistema inteiro,
 * sem precisar instrumentar cada função uma por uma.
 */
function pw_personalizados_errors_tab_report() {
	pw_personalizados_ajax_guard();
	if ( ! function_exists( 'pw_printway_log' ) ) {
		wp_send_json_success();
	}
	$message = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';
	$context = isset( $_POST['context'] ) ? sanitize_text_field( wp_unslash( $_POST['context'] ) ) : '';
	if ( ! $message ) {
		wp_send_json_success();
	}
	pw_printway_log( 'pedidos-cliente', 'error', substr( $message, 0, 500 ), array( 'contexto' => substr( $context, 0, 200 ) ) );
	wp_send_json_success();
}
add_action( 'wp_ajax_pw_personalizados_errors_tab_report', 'pw_personalizados_errors_tab_report' );

/** Salva a preferência individual de atualização ao vivo do painel. */
function pw_personalizados_save_dashboard_live() {
	pw_personalizados_ajax_guard();
	$enabled = isset( $_POST['enabled'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['enabled'] ) );
	update_user_meta( get_current_user_id(), 'pw_personalizados_dashboard_live', $enabled ? '1' : '0' );
	wp_send_json_success( array( 'enabled' => $enabled ) );
}
add_action( 'wp_ajax_pw_personalizados_save_dashboard_live', 'pw_personalizados_save_dashboard_live' );

function pw_personalizados_admin_menu() {
	add_submenu_page(
		'pw-printway',
		'Sistema interno — Pedidos personalizados',
		'Sistema interno',
		'manage_options',
		'pw-personalizados',
		'pw_personalizados_admin_page'
	);
}
add_action( 'admin_menu', 'pw_personalizados_admin_menu', 40 );

function pw_personalizados_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$tab      = isset( $_GET['aba'] ) ? sanitize_key( wp_unslash( $_GET['aba'] ) ) : 'shortcode';
	$base_url = admin_url( 'admin.php?page=pw-personalizados' );

	echo '<div class="wrap"><h1>Sistema interno — Pedidos personalizados</h1>';
	echo '<nav class="nav-tab-wrapper" style="margin-bottom:20px">';
	echo '<a class="nav-tab' . ( 'logs' !== $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( $base_url ) . '">Shortcode</a>';
	echo '<a class="nav-tab' . ( 'logs' === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( add_query_arg( 'aba', 'logs', $base_url ) ) . '">Logs</a>';
	echo '</nav>';

	if ( 'logs' === $tab ) {
		pw_printway_render_logs_page( true );
	} else {
		pw_personalizados_render_shortcode_tab();
	}

	echo '</div>';
}

function pw_personalizados_render_shortcode_tab() {
	echo '<h2>Como colocar o sistema numa página</h2>';
	echo '<p>Cole esta linha no lugar onde o sistema de pedidos deve aparecer — no Elementor, num widget de <strong>Shortcode</strong>:</p>';
	echo '<p style="display:flex;align-items:center;gap:10px;"><code id="pw-personalizados-shortcode-text" style="font-size:16px;padding:10px 14px;background:#f0f0f1;border:1px solid #dcdcde;border-radius:4px;">[printway_pedidos_personalizados]</code><button type="button" class="button" id="pw-personalizados-copy-shortcode">Copiar</button></p>';
	echo '<p class="description">O formulário, os estilos e as funções são carregados pelo módulo <strong>pedidos</strong> somente na página em que esse shortcode estiver presente.</p>';
	echo '<script>document.addEventListener("DOMContentLoaded",function(){var copyBtn=document.getElementById("pw-personalizados-copy-shortcode");if(copyBtn){copyBtn.addEventListener("click",function(){var text=document.getElementById("pw-personalizados-shortcode-text").textContent;navigator.clipboard.writeText(text).then(function(){var original=copyBtn.textContent;copyBtn.textContent="Copiado!";setTimeout(function(){copyBtn.textContent=original;},1500);});});}});</script>';
}

function pw_personalizados_tokens_status() {
	pw_personalizados_ajax_guard();
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Somente administradores.' ), 403 );
	}

	$tokens = array();

	// Shopee
	if ( function_exists( 'pw_shopee_is_connected' ) ) {
		$shopee_settings = function_exists( 'pw_shopee_get_settings' ) ? pw_shopee_get_settings() : array();
		$connected       = pw_shopee_is_connected();
		$env             = function_exists( 'pw_shopee_environment' ) ? pw_shopee_environment() : 'sandbox';
		$tokens['shopee'] = array(
			'status' => $connected ? 'ok' : 'missing',
			'label'  => $connected ? 'Conectado (' . ( 'production' === $env ? 'produção' : 'sandbox' ) . ')' : 'Não conectado',
		);
	} else {
		$tokens['shopee'] = array( 'status' => 'missing', 'label' => 'Módulo não carregado' );
	}

	// Mercado Livre
	if ( function_exists( 'pw_ml_access_token_value' ) ) {
		$ml_token = pw_ml_access_token_value();
		$tokens['mercadolivre'] = array(
			'status' => ( $ml_token && strlen( $ml_token ) > 10 ) ? 'ok' : 'missing',
			'label'  => ( $ml_token && strlen( $ml_token ) > 10 ) ? 'Token ativo' : 'Não conectado',
		);
	} else {
		$ml_settings = get_option( 'pw_printway_ml_settings', array() );
		$has_ml      = is_array( $ml_settings ) && ! empty( $ml_settings['access_token'] );
		$tokens['mercadolivre'] = array( 'status' => $has_ml ? 'ok' : 'missing', 'label' => $has_ml ? 'Token ativo' : 'Não conectado' );
	}

	// Anthropic
	$anthropic_key = get_option( 'pw_ml_anthropic_api_key', '' );
	$tokens['anthropic'] = array(
		'status' => ( '' !== $anthropic_key ) ? 'ok' : 'missing',
		'label'  => ( '' !== $anthropic_key ) ? 'Chave configurada' : 'Sem chave',
	);

	// Melhor Envio
	$me_secret = get_option( 'pw_personalizados_melhorenvio_secret', false );
	$tokens['melhorenvio'] = array(
		'status' => $me_secret ? 'ok' : 'missing',
		'label'  => $me_secret ? 'Token configurado' : 'Sem token',
	);

	// Frenet
	$frenet_secret = get_option( 'pw_personalizados_frenet_secret', false );
	if ( ! $frenet_secret ) {
		$legacy_frenet = get_option( 'woocommerce_frenet_settings', array() );
		$frenet_secret = is_array( $legacy_frenet ) && ! empty( $legacy_frenet['token'] ) && 'no' !== ( $legacy_frenet['enabled'] ?? 'yes' );
	}
	$tokens['frenet'] = array(
		'status' => $frenet_secret ? 'ok' : 'missing',
		'label'  => $frenet_secret ? 'Token configurado' : 'Sem token',
	);

	// NF-e — verifica se há certificado salvo
	$nfe_cert = get_option( 'pw_printway_nfe_certificate', '' );
	if ( ! $nfe_cert ) {
		$nfe_settings = get_option( 'pw_printway_nfe_settings', array() );
		$nfe_cert = is_array( $nfe_settings ) && ( ! empty( $nfe_settings['certificate'] ) || ! empty( $nfe_settings['cert_path'] ) );
	}
	$tokens['nfe'] = array(
		'status' => $nfe_cert ? 'ok' : 'missing',
		'label'  => $nfe_cert ? 'Certificado configurado' : 'Sem certificado',
	);

	// Pix
	$pix_settings = get_option( 'pw_printway_pix_settings', array() );
	if ( ! is_array( $pix_settings ) ) $pix_settings = array();
	$has_pix = ! empty( $pix_settings['key'] ) || ! empty( $pix_settings['pix_key'] );
	$tokens['pix'] = array(
		'status' => $has_pix ? 'ok' : 'missing',
		'label'  => $has_pix ? 'Chave Pix configurada' : 'Sem chave Pix',
	);

	// Mercado Pago
	$mp_settings  = get_option( 'pw_printway_mp_settings', array() );
	$mp_token     = is_array( $mp_settings ) ? ( $mp_settings['access_token'] ?? '' ) : '';
	if ( $mp_token && strlen( $mp_token ) > 10 ) {
		$mp_resp = wp_remote_get( 'https://api.mercadopago.com/v1/payment_methods?marketplace=NONE', array(
			'timeout' => 8,
			'headers' => array( 'Authorization' => 'Bearer ' . $mp_token ),
		) );
		$mp_code = is_wp_error( $mp_resp ) ? 0 : wp_remote_retrieve_response_code( $mp_resp );
		if ( $mp_code === 200 ) {
			$tokens['mercadopago'] = array( 'status' => 'online', 'label' => 'Token válido — Pix ativo' );
		} else {
			$tokens['mercadopago'] = array( 'status' => 'error', 'label' => 'Token inválido (código ' . $mp_code . ')' );
		}
	} else {
		$tokens['mercadopago'] = array( 'status' => 'missing', 'label' => 'Sem token' );
	}

	// WhatsApp Business
	$wa_token    = get_option( 'pw_personalizados_wa_token', '' );
	$wa_phone_id = get_option( 'pw_personalizados_wa_phone_id', '' );
	$wa_ok       = ! empty( $wa_token ) && ! empty( $wa_phone_id );
	$tokens['whatsapp'] = array(
		'status' => $wa_ok ? 'ok' : 'missing',
		'label'  => $wa_ok ? 'Token configurado' : 'Sem token',
	);

	wp_send_json_success( array( 'tokens' => $tokens ) );
}
add_action( 'wp_ajax_pw_personalizados_tokens_status', 'pw_personalizados_tokens_status' );

/** Formata tempo restante/decorrido até/desde $expires_at em PT-BR. */
function pw_personalizados_expiry_label( $expires_at ) {
	$diff = (int) $expires_at - time();
	if ( $diff <= 0 ) {
		$ago = abs( $diff );
		if ( $ago < 3600 )     return 'Expirou há ' . round( $ago / 60 ) . ' min';
		if ( $ago < 86400 )    return 'Expirou há ' . round( $ago / 3600 ) . 'h';
		return 'Expirou há ' . round( $ago / 86400 ) . ' dias';
	}
	if ( $diff < 3600 )        return 'Expira em ' . round( $diff / 60 ) . ' min';
	if ( $diff < 86400 )       return 'Expira em ' . round( $diff / 3600 ) . 'h';
	if ( $diff < 86400 * 30 )  return 'Expira em ' . round( $diff / 86400 ) . ' dias';
	return 'Expira em ' . round( $diff / ( 86400 * 30 ) ) . ' meses';
}

/** Verifica conectividade real das integrações via chamada HTTP a cada API. */
function pw_personalizados_tokens_verify() {
	pw_personalizados_ajax_guard();
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Somente administradores.' ), 403 );
		return;
	}

	$results = array();
	$timeout = 8;

	// WhatsApp — GET /v19.0/{phone_id} com Bearer token
	$wa_token    = get_option( 'pw_personalizados_wa_token', '' );
	$wa_phone_id = get_option( 'pw_personalizados_wa_phone_id', '' );
	if ( ! $wa_token || ! $wa_phone_id ) {
		$results['whatsapp'] = array( 'status' => 'missing', 'label' => 'Sem token configurado' );
	} else {
		$r    = wp_remote_get( 'https://graph.facebook.com/v19.0/' . rawurlencode( $wa_phone_id ), array(
			'headers' => array( 'Authorization' => 'Bearer ' . $wa_token ),
			'timeout' => $timeout,
		) );
		$code = is_wp_error( $r ) ? 0 : wp_remote_retrieve_response_code( $r );
		if ( is_wp_error( $r ) ) {
			$results['whatsapp'] = array( 'status' => 'error', 'label' => '✗ Sem conexão com a Meta' );
		} elseif ( 200 === $code ) {
			$body = json_decode( wp_remote_retrieve_body( $r ), true );
			$num  = $body['display_phone_number'] ?? $body['verified_name'] ?? '';
			$results['whatsapp'] = array( 'status' => 'online', 'label' => '✓ Online' . ( $num ? ' — ' . $num : '' ) );
		} else {
			$body     = json_decode( wp_remote_retrieve_body( $r ), true );
			$err_code = $body['error']['code'] ?? 0;
			$err_msg  = $body['error']['message'] ?? '';
			// Tenta extrair data de expiração da mensagem da Meta ("Session has expired on Fri, 25-Sep-26 22:00:00 PDT")
			$months_pt = array( 'Jan'=>'Jan','Feb'=>'Fev','Mar'=>'Mar','Apr'=>'Abr','May'=>'Mai','Jun'=>'Jun',
			                    'Jul'=>'Jul','Aug'=>'Ago','Sep'=>'Set','Oct'=>'Out','Nov'=>'Nov','Dec'=>'Dez' );
			if ( preg_match( '/Session has expired on \w+, (\d{1,2})-(\w{3})-(\d{2}) (\d{2}:\d{2})/', $err_msg, $m ) ) {
				$mes = $months_pt[ $m[2] ] ?? $m[2];
				$label = 'Token expirado em ' . $m[1] . '/' . $mes . ' às ' . $m[4];
			} elseif ( 190 === $err_code || stripos( $err_msg, 'access token' ) !== false ) {
				$label = 'Token expirado — renove em developers.facebook.com';
			} elseif ( stripos( $err_msg, 'Invalid OAuth' ) !== false ) {
				$label = 'Token OAuth inválido';
			} else {
				$label = 'Erro de autenticação (HTTP ' . $code . ')';
			}
			$results['whatsapp'] = array( 'status' => 'error', 'label' => '✗ ' . $label );
		}
	}

	// Anthropic — GET /v1/models com x-api-key
	$anthropic_key = get_option( 'pw_ml_anthropic_api_key', '' );
	if ( ! $anthropic_key ) {
		$results['anthropic'] = array( 'status' => 'missing', 'label' => 'Sem API key' );
	} else {
		$r    = wp_remote_get( 'https://api.anthropic.com/v1/models', array(
			'headers' => array( 'x-api-key' => $anthropic_key, 'anthropic-version' => '2023-06-01' ),
			'timeout' => $timeout,
		) );
		$code = is_wp_error( $r ) ? 0 : wp_remote_retrieve_response_code( $r );
		if ( is_wp_error( $r ) ) {
			$results['anthropic'] = array( 'status' => 'error', 'label' => '✗ Sem conexão com Anthropic' );
		} elseif ( 200 === $code ) {
			$results['anthropic'] = array( 'status' => 'online', 'label' => '✓ API key válida' );
		} else {
			$results['anthropic'] = array( 'status' => 'error', 'label' => '✗ API key inválida ou expirada' );
		}
	}

	// Melhor Envio — GET /api/v2/me com Bearer token descriptografado
	$me_token = function_exists( 'pw_personalizados_melhorenvio_token_value' )
		? pw_personalizados_melhorenvio_token_value()
		: '';
	$me_ua = function_exists( 'pw_personalizados_melhorenvio_user_agent' )
		? pw_personalizados_melhorenvio_user_agent()
		: 'PrintWay/1.0';
	if ( ! $me_token ) {
		$me_configured = (bool) get_option( 'pw_personalizados_melhorenvio_secret', false );
		$results['melhorenvio'] = $me_configured
			? array( 'status' => 'error', 'label' => '✗ Não foi possível ler o token' )
			: array( 'status' => 'missing', 'label' => 'Sem token' );
	} else {
		$r    = wp_remote_get( 'https://melhorenvio.com.br/api/v2/me', array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $me_token,
				'Accept'        => 'application/json',
				'User-Agent'    => $me_ua,
			),
			'timeout' => $timeout,
		) );
		$code = is_wp_error( $r ) ? 0 : wp_remote_retrieve_response_code( $r );
		if ( is_wp_error( $r ) ) {
			$results['melhorenvio'] = array( 'status' => 'error', 'label' => '✗ Sem conexão' );
		} elseif ( 200 === $code ) {
			$body = json_decode( wp_remote_retrieve_body( $r ), true );
			$name = trim( ( $body['firstname'] ?? '' ) . ' ' . ( $body['lastname'] ?? '' ) ) ?: ( $body['email'] ?? 'Online' );
			$results['melhorenvio'] = array( 'status' => 'online', 'label' => '✓ Online — ' . $name );
		} else {
			$results['melhorenvio'] = array( 'status' => 'error', 'label' => '✗ Token inválido ou expirado' );
		}
	}

	// Frenet — verifica token via POST à API de cotação
	$frenet_token = get_option( 'pw_personalizados_frenet_secret', '' );
	if ( ! $frenet_token ) {
		$legacy_frenet = get_option( 'woocommerce_frenet_settings', array() );
		$frenet_token  = ( is_array( $legacy_frenet ) && ! empty( $legacy_frenet['token'] ) && 'no' !== ( $legacy_frenet['enabled'] ?? 'yes' ) )
			? $legacy_frenet['token'] : '';
	}
	if ( ! $frenet_token ) {
		$results['frenet'] = array( 'status' => 'missing', 'label' => 'Sem token' );
	} else {
		$fr = wp_remote_post( 'https://api.frenet.com.br/shipping/quote', array(
			'headers' => array( 'Content-Type' => 'application/json', 'TOKEN' => $frenet_token ),
			'body'    => wp_json_encode( array(
				'SellerCEP' => '01310100', 'RecipientCEP' => '04538133',
				'ShipmentInvoiceValue' => 100.0,
				'ShippingItemArray' => array( array( 'Height'=>5,'Length'=>15,'Width'=>15,'Weight'=>0.3,'Quantity'=>1 ) ),
			) ),
			'timeout' => $timeout,
		) );
		if ( is_wp_error( $fr ) ) {
			$results['frenet'] = array( 'status' => 'error', 'label' => '✗ Sem conexão com Frenet' );
		} else {
			$fr_code = wp_remote_retrieve_response_code( $fr );
			$fr_body = json_decode( wp_remote_retrieve_body( $fr ), true );
			if ( 200 === $fr_code && isset( $fr_body['ShippingResult'] ) ) {
				$results['frenet'] = array( 'status' => 'online', 'label' => '✓ Frenet conectado' );
			} elseif ( isset( $fr_body['Message'] ) && strlen( $fr_body['Message'] ) > 0 ) {
				$results['frenet'] = array( 'status' => 'error', 'label' => '✗ ' . $fr_body['Message'] );
			} elseif ( 200 === $fr_code ) {
				$results['frenet'] = array( 'status' => 'online', 'label' => '✓ Frenet conectado' );
			} else {
				$results['frenet'] = array( 'status' => 'error', 'label' => '✗ Erro Frenet (HTTP ' . $fr_code . ')' );
			}
		}
	}

	// Shopee — usa função do módulo se disponível
	if ( function_exists( 'pw_shopee_access_token_valid' ) ) {
		$sh_s    = get_option( 'pw_printway_shopee_settings', array() );
		if ( ! is_array( $sh_s ) ) $sh_s = array();
		$sh_exp  = isset( $sh_s['expires_at'] ) ? pw_personalizados_expiry_label( (int) $sh_s['expires_at'] ) : '';
		$sh_name = ! empty( $sh_s['shop_name'] ) ? ' — ' . $sh_s['shop_name'] : '';
		$results['shopee'] = pw_shopee_access_token_valid()
			? array( 'status' => 'online', 'label' => '✓ Conectado' . $sh_name . ( $sh_exp ? ' · ' . $sh_exp : '' ) )
			: array( 'status' => 'error',  'label' => '✗ Token expirado' . ( $sh_exp ? ' · ' . $sh_exp : '' ) );
	} else {
		$results['shopee'] = array( 'status' => 'missing', 'label' => 'Módulo não carregado' );
	}

	// Mercado Livre — OAuth, verifica via GET /users/me
	$ml_s = get_option( 'pw_printway_ml_settings', array() );
	if ( ! is_array( $ml_s ) ) $ml_s = array();
	$ml_token = function_exists( 'pw_ml_access_token_value' ) ? pw_ml_access_token_value() : ( $ml_s['access_token'] ?? '' );
	if ( ! $ml_token || strlen( $ml_token ) <= 10 ) {
		$results['mercadolivre'] = array( 'status' => 'missing', 'label' => 'Não conectado' );
	} else {
		$ml_exp  = isset( $ml_s['expires_at'] ) ? pw_personalizados_expiry_label( (int) $ml_s['expires_at'] ) : '';
		$ml_r    = wp_remote_get( 'https://api.mercadolibre.com/users/me', array(
			'headers' => array( 'Authorization' => 'Bearer ' . $ml_token ),
			'timeout' => $timeout,
		) );
		$ml_code = is_wp_error( $ml_r ) ? 0 : wp_remote_retrieve_response_code( $ml_r );
		if ( is_wp_error( $ml_r ) ) {
			$results['mercadolivre'] = array( 'status' => 'error', 'label' => '✗ Sem conexão com Mercado Livre' );
		} elseif ( 200 === $ml_code ) {
			$ml_body = json_decode( wp_remote_retrieve_body( $ml_r ), true );
			$ml_nick = ! empty( $ml_body['nickname'] ) ? ' — ' . $ml_body['nickname'] : ( ! empty( $ml_s['nickname'] ) ? ' — ' . $ml_s['nickname'] : '' );
			$results['mercadolivre'] = array( 'status' => 'online', 'label' => '✓ Online' . $ml_nick . ( $ml_exp ? ' · ' . $ml_exp : '' ) );
		} else {
			$results['mercadolivre'] = array( 'status' => 'error', 'label' => '✗ Token inválido ou expirado' . ( $ml_exp ? ' · ' . $ml_exp : '' ) );
		}
	}

	// Mercado Pago — verifica Access Token via GET /v1/payment_methods
	$mp_settings = get_option( 'pw_printway_mp_settings', array() );
	$mp_token    = is_array( $mp_settings ) ? ( $mp_settings['access_token'] ?? '' ) : '';
	if ( ! $mp_token || strlen( $mp_token ) <= 10 ) {
		$results['mercadopago'] = array( 'status' => 'missing', 'label' => 'Sem token configurado' );
	} else {
		$mp_r    = wp_remote_get( 'https://api.mercadopago.com/v1/payment_methods?marketplace=NONE', array(
			'headers' => array( 'Authorization' => 'Bearer ' . $mp_token ),
			'timeout' => $timeout,
		) );
		$mp_code = is_wp_error( $mp_r ) ? 0 : wp_remote_retrieve_response_code( $mp_r );
		if ( is_wp_error( $mp_r ) ) {
			$results['mercadopago'] = array( 'status' => 'error', 'label' => '✗ Sem conexão com Mercado Pago' );
		} elseif ( 200 === $mp_code ) {
			$results['mercadopago'] = array( 'status' => 'online', 'label' => '✓ Token válido — Pix ativo' );
		} else {
			$results['mercadopago'] = array( 'status' => 'error', 'label' => '✗ Token inválido (HTTP ' . $mp_code . ')' );
		}
	}

	// NF-e e Pix — verificação de configuração apenas
	$nfe_cert = get_option( 'pw_printway_nfe_certificate', '' );
	if ( ! $nfe_cert ) {
		$nfe_s    = get_option( 'pw_printway_nfe_settings', array() );
		$nfe_cert = is_array( $nfe_s ) && ( ! empty( $nfe_s['certificate'] ) || ! empty( $nfe_s['cert_path'] ) );
	}
	$results['nfe'] = $nfe_cert
		? array( 'status' => 'ok', 'label' => 'Certificado configurado' )
		: array( 'status' => 'missing', 'label' => 'Sem certificado' );

	$pix_s   = get_option( 'pw_printway_pix_settings', array() );
	$pix_key = trim( isset( $pix_s['key'] ) ? $pix_s['key'] : ( isset( $pix_s['pix_key'] ) ? $pix_s['pix_key'] : '' ) );
	if ( ! $pix_key ) {
		$results['pix'] = array( 'status' => 'missing', 'label' => 'Sem chave Pix' );
	} else {
		$digits = preg_replace( '/[^0-9]/', '', $pix_key );
		if ( preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $pix_key ) ) {
			$results['pix'] = array( 'status' => 'online', 'label' => '✓ Chave aleatória válida' );
		} elseif ( filter_var( $pix_key, FILTER_VALIDATE_EMAIL ) ) {
			$results['pix'] = array( 'status' => 'online', 'label' => '✓ Chave e-mail válida' );
		} elseif ( preg_match( '/^\+55\d{10,11}$/', $pix_key ) || ( strlen( $digits ) === 11 && $digits[2] === '9' ) ) {
			$results['pix'] = array( 'status' => 'online', 'label' => '✓ Chave celular válida' );
		} elseif ( strlen( $digits ) === 11 ) {
			$results['pix'] = array( 'status' => 'online', 'label' => '✓ Chave CPF válida' );
		} elseif ( strlen( $digits ) === 14 ) {
			$results['pix'] = array( 'status' => 'online', 'label' => '✓ Chave CNPJ válida' );
		} else {
			$results['pix'] = array( 'status' => 'error', 'label' => '✗ Formato de chave inválido' );
		}
	}

	wp_send_json_success( array( 'tokens' => $results ) );
}
add_action( 'wp_ajax_pw_personalizados_tokens_verify', 'pw_personalizados_tokens_verify' );

function pw_personalizados_token_config_get() {
	pw_personalizados_ajax_guard();
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Somente administradores.' ), 403 );
	}
	$key = sanitize_key( $_POST['token_key'] ?? '' );
	$pix = get_option( 'pw_printway_pix_settings', array() );
	if ( ! is_array( $pix ) ) $pix = array();
	$configs = array(
		'shopee' => array(
			'label' => 'Shopee',
			'type'  => 'info',
			'note'  => 'A conexão usa OAuth via Shopee Open Platform. Clique em "Painel" para configurar parceiro ID e chave, depois autorize o acesso pelo painel do plugin.',
			'link'  => admin_url( 'admin.php?page=pw-printway-shopee' ),
			'link_label' => 'Painel do plugin →',
			'fields' => array(),
		),
		'mercadolivre' => array(
			'label' => 'Mercado Livre',
			'type'  => 'info',
			'note'  => 'A conexão usa OAuth. Autorize o acesso pelo painel de configuração e clique em "Reconectar" se o token expirar.',
			'link'  => admin_url( 'admin.php?page=pw-printway-ml' ),
			'link_label' => 'Painel do plugin →',
			'fields' => array(),
		),
		'anthropic' => array(
			'label' => 'Anthropic (IA)',
			'type'  => 'form',
			'fields' => array(
				array( 'id' => 'api_key', 'label' => 'API Key', 'is_set' => '' !== get_option( 'pw_ml_anthropic_api_key', '' ) ),
			),
		),
		'melhorenvio' => array(
			'label' => 'Melhor Envio',
			'type'  => 'form',
			'fields' => array(
				array( 'id' => 'token', 'label' => 'Token de acesso', 'is_set' => (bool) get_option( 'pw_personalizados_melhorenvio_secret', false ) ),
			),
		),
		'frenet' => array(
			'label' => 'Frenet (frete)',
			'type'  => 'form',
			'fields' => array(
				array( 'id' => 'token', 'label' => 'Token de acesso', 'is_set' => (bool) get_option( 'pw_personalizados_frenet_secret', false ) ),
			),
		),
		'nfe' => array(
			'label' => 'NF-e (certificado)',
			'type'  => 'info',
			'note'  => 'O certificado digital A1 e a senha são carregados na página dedicada da NF-e. O sistema não armazena o arquivo — apenas a validade é verificada.',
			'link'  => '#view:nfe',
			'link_label' => 'Configurar NF-e →',
			'fields' => array(),
		),
		'pix' => array(
			'label' => 'Pix / Gateway',
			'type'  => 'form',
			'fields' => array(
				array( 'id' => 'pix_key', 'label' => 'Chave Pix', 'is_set' => ! empty( $pix['key'] ) || ! empty( $pix['pix_key'] ) ),
			),
		),
		'mercadopago' => array(
			'label' => 'Mercado Pago',
			'type'  => 'form',
			'note'  => 'Use o Access Token de Produção gerado no painel de credenciais do Mercado Pago. Quando configurado, a calculadora DTF UV gera QR Pix registrado com confirmação automática de pagamento.',
			'fields' => array(
				array( 'id' => 'access_token', 'label' => 'Access Token (Produção)', 'is_set' => ! empty( ( get_option( 'pw_printway_mp_settings', array() ) )['access_token'] ?? '' ) ),
			),
		),
		'whatsapp' => array(
			'label'  => 'WhatsApp Business (Meta)',
			'type'   => 'form',
			'fields' => array(
				array( 'id' => 'wa_token',    'label' => 'Token de acesso permanente', 'is_set' => ! empty( get_option( 'pw_personalizados_wa_token', '' ) ) ),
				array( 'id' => 'wa_phone_id', 'label' => 'ID do número de telefone',  'is_set' => ! empty( get_option( 'pw_personalizados_wa_phone_id', '' ) ) ),
			),
		),
	);
	if ( ! isset( $configs[ $key ] ) ) {
		wp_send_json_error( array( 'message' => 'Integração não reconhecida.' ) );
	}
	wp_send_json_success( $configs[ $key ] );
}
add_action( 'wp_ajax_pw_personalizados_token_config_get', 'pw_personalizados_token_config_get' );

function pw_personalizados_token_config_save() {
	pw_personalizados_ajax_guard();
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Somente administradores.' ), 403 );
	}
	$key    = sanitize_key( $_POST['token_key'] ?? '' );
	$values = $_POST['values'] ?? array();
	if ( ! is_array( $values ) ) $values = array();
	$values = array_map( 'sanitize_text_field', $values );
	switch ( $key ) {
		case 'anthropic':
			if ( ! empty( $values['api_key'] ) ) update_option( 'pw_ml_anthropic_api_key', $values['api_key'] );
			break;
		case 'melhorenvio':
			if ( ! empty( $values['token'] ) ) update_option( 'pw_personalizados_melhorenvio_secret', $values['token'] );
			break;
		case 'frenet':
			if ( ! empty( $values['token'] ) ) update_option( 'pw_personalizados_frenet_secret', $values['token'] );
			break;
		case 'pix':
			if ( ! empty( $values['pix_key'] ) ) {
				$s = get_option( 'pw_printway_pix_settings', array() );
				if ( ! is_array( $s ) ) $s = array();
				$s['key'] = $values['pix_key'];
				update_option( 'pw_printway_pix_settings', $s );
			}
			break;
		case 'mercadopago':
			if ( ! empty( $values['access_token'] ) ) {
				$s = get_option( 'pw_printway_mp_settings', array() );
				if ( ! is_array( $s ) ) $s = array();
				$s['access_token'] = $values['access_token'];
				update_option( 'pw_printway_mp_settings', $s );
			}
			break;
		case 'whatsapp':
			if ( ! empty( $values['wa_token'] ) ) update_option( 'pw_personalizados_wa_token', $values['wa_token'] );
			if ( ! empty( $values['wa_phone_id'] ) ) update_option( 'pw_personalizados_wa_phone_id', $values['wa_phone_id'] );
			break;
		default:
			wp_send_json_error( array( 'message' => 'Esta integração não suporta edição direta.' ) );
			return;
	}
	wp_send_json_success( array( 'message' => 'Configuração salva.' ) );
}
add_action( 'wp_ajax_pw_personalizados_token_config_save', 'pw_personalizados_token_config_save' );

function pw_personalizados_whatsapp_settings_get() {
	pw_personalizados_ajax_guard();
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Somente administradores.' ), 403 );
	}
	$settings = get_option( 'pw_personalizados_wa_settings', array() );
	if ( ! is_array( $settings ) ) $settings = array();
	wp_send_json_success( $settings );
}
add_action( 'wp_ajax_pw_personalizados_whatsapp_settings_get', 'pw_personalizados_whatsapp_settings_get' );

function pw_personalizados_wa_default_template( $status ) {
	$defaults = array(
		'Criação da arte'    => "✍️ *PrintWay* — Pedido em análise\n\nOlá, <nome_cliente>!\n\nRecebemos seu pedido *#<numero_pedido>* e já estamos criando sua arte! Em breve entraremos em contato.<link_contato>\n\n_Este é um número automático de notificações._",
		'Arte aprovada'      => "✅ *PrintWay* — Arte aprovada!\n\nOlá, <nome_cliente>!\n\nSua arte foi aprovada. O pedido *#<numero_pedido>* já foi encaminhado para produção! 🎨<link_contato>\n\n_Este é um número automático de notificações._",
		'Em produção'        => "🖨️ *PrintWay* — Em produção!\n\nOlá, <nome_cliente>!\n\nSeu pedido *#<numero_pedido>* está sendo produzido agora.<link_contato>\n\n_Este é um número automático de notificações._",
		'Produzido'          => "📦 *PrintWay* — Produção concluída!\n\nOlá, <nome_cliente>!\n\nSeu pedido *#<numero_pedido>* saiu da produção e está pronto! Em breve seguirá para entrega. 🚚<link_contato>\n\n_Este é um número automático de notificações._",
		'Aguardando entrega' => "🚚 *PrintWay* — Saiu para entrega!\n\nOlá, <nome_cliente>!\n\nSeu pedido *#<numero_pedido>* está a caminho! 📦 Aguarde em breve.<link_contato>\n\n_Este é um número automático de notificações._",
		'Entregue'           => "🎉 *PrintWay* — Pedido entregue!\n\nOlá, <nome_cliente>!\n\nSeu pedido *#<numero_pedido>* foi entregue com sucesso! Obrigado pela confiança. ⭐<link_contato>\n\n_Este é um número automático de notificações._",
	);
	return $defaults[ $status ] ?? "📦 *PrintWay* — Atualização do pedido\n\nOlá, <nome_cliente>!\n\nSeu pedido *#<numero_pedido>* avançou para a etapa:\n➡ *<situacao>*<link_contato>\n\n_Este é um número automático de notificações._";
}

function pw_personalizados_whatsapp_settings_save() {
	pw_personalizados_ajax_guard();
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Somente administradores.' ), 403 );
	}
	$official_number = sanitize_text_field( $_POST['official_number'] ?? '' );
	$raw_statuses    = $_POST['notify_statuses'] ?? array();
	if ( ! is_array( $raw_statuses ) ) $raw_statuses = array();
	$allowed_statuses = array( 'Criação da arte', 'Arte aprovada', 'Em produção', 'Produzido', 'Aguardando entrega', 'Entregue' );
	$notify_statuses  = array_values( array_intersect( array_map( 'sanitize_text_field', $raw_statuses ), $allowed_statuses ) );
	$raw_templates    = $_POST['message_templates'] ?? array();
	if ( ! is_array( $raw_templates ) ) $raw_templates = array();
	$allowed_tpl_keys = array( 'Criação da arte', 'Arte aprovada', 'Em produção', 'Produzido', 'Aguardando entrega', 'Entregue' );
	$message_templates = array();
	foreach ( $allowed_tpl_keys as $s ) {
		if ( isset( $raw_templates[ $s ] ) ) {
			$message_templates[ $s ] = sanitize_textarea_field( $raw_templates[ $s ] );
		}
	}
	update_option( 'pw_personalizados_wa_settings', array(
		'official_number'   => $official_number,
		'notify_statuses'   => $notify_statuses,
		'message_templates' => $message_templates,
	) );
	wp_send_json_success( array( 'message' => 'Configurações salvas.' ) );
}
add_action( 'wp_ajax_pw_personalizados_whatsapp_settings_save', 'pw_personalizados_whatsapp_settings_save' );

function pw_personalizados_whatsapp_send() {
	pw_personalizados_ajax_guard();
	$order_number = sanitize_text_field( $_POST['order_number'] ?? '' );
	$new_status   = sanitize_text_field( $_POST['new_status'] ?? '' );

	$wa_token    = get_option( 'pw_personalizados_wa_token', '' );
	$wa_phone_id = get_option( 'pw_personalizados_wa_phone_id', '' );
	if ( ! $wa_token || ! $wa_phone_id ) {
		wp_send_json_success( array( 'skipped' => true, 'reason' => 'token_missing' ) );
		return;
	}

	$settings        = get_option( 'pw_personalizados_wa_settings', array() );
	if ( ! is_array( $settings ) ) $settings = array();
	$notify_statuses = is_array( $settings['notify_statuses'] ?? null ) ? $settings['notify_statuses'] : array();
	if ( ! in_array( $new_status, $notify_statuses, true ) ) {
		wp_send_json_success( array( 'skipped' => true, 'reason' => 'status_not_configured' ) );
		return;
	}

	// Busca pedido e cliente no storage
	$orders  = get_option( 'pw_personalizados_orders', array() );
	$clients = get_option( 'pw_personalizados_clients', array() );
	if ( ! is_array( $orders ) ) $orders = array();
	if ( ! is_array( $clients ) ) $clients = array();

	$order = null;
	foreach ( $orders as $o ) {
		if ( is_array( $o ) && isset( $o['orderNumber'] ) && (string) $o['orderNumber'] === (string) $order_number ) {
			$order = $o;
			break;
		}
	}
	if ( ! $order ) {
		wp_send_json_success( array( 'skipped' => true, 'reason' => 'order_not_found' ) );
		return;
	}

	$phone       = $order['client']['phone'] ?? '';
	$client_name = $order['client']['name'] ?? 'cliente';
	$client_id   = $order['client']['id'] ?? '';
	if ( ! $phone ) {
		wp_send_json_success( array( 'skipped' => true, 'reason' => 'no_phone' ) );
		return;
	}

	// Verifica opt-out do cliente
	if ( $client_id ) {
		foreach ( $clients as $c ) {
			if ( is_array( $c ) && isset( $c['id'] ) && (string) $c['id'] === (string) $client_id ) {
				if ( isset( $c['whatsappNotify'] ) && false === $c['whatsappNotify'] ) {
					wp_send_json_success( array( 'skipped' => true, 'reason' => 'client_opted_out' ) );
					return;
				}
				break;
			}
		}
	}

	// Formata número: garante DDI 55
	$digits = preg_replace( '/\D/', '', $phone );
	if ( strlen( $digits ) === 11 || strlen( $digits ) === 10 ) $digits = '55' . $digits;

	// Labels de situação
	$status_labels = array(
		'Criação da arte'   => 'Criação da arte',
		'Arte aprovada'     => 'Arte aprovada',
		'Em produção'       => 'Em produção',
		'Produzido'         => 'Produção pronta',
		'Aguardando entrega'=> 'Aguardando entrega',
		'Entregue'          => 'Entregue',
	);
	$status_label = $status_labels[ $new_status ] ?? $new_status;

	// Link de contato oficial
	$official_raw  = $settings['official_number'] ?? '';
	$official_d    = preg_replace( '/\D/', '', $official_raw );
	if ( strlen( $official_d ) === 11 || strlen( $official_d ) === 10 ) $official_d = '55' . $official_d;
	$contact_line  = ( strlen( $official_d ) >= 12 )
		? "\n\n📞 Para falar conosco: https://wa.me/" . $official_d
		: '';

	// Substitui variáveis dinâmicas no template configurado (ou usa o padrão por situação)
	$items         = is_array( $order['items'] ?? null ) ? $order['items'] : array();
	$first_item    = ! empty( $items[0] ) ? $items[0] : array();
	$ts_delivery   = ! empty( $order['requestedDelivery'] ) ? strtotime( $order['requestedDelivery'] ) : 0;
	$delivery_date = $ts_delivery ? date( 'd/m/Y', $ts_delivery ) : (string) ( $order['requestedDelivery'] ?? '' );
	$ts_created    = ! empty( $order['createdDate'] ) ? strtotime( $order['createdDate'] ) : 0;
	$created_date  = $ts_created ? date( 'd/m/Y', $ts_created ) : (string) ( $order['createdDate'] ?? '' );
	$total_fmt     = 'R$ ' . number_format( floatval( $order['total'] ?? 0 ), 2, ',', '.' );
	$templates     = is_array( $settings['message_templates'] ?? null ) ? $settings['message_templates'] : array();
	$template      = ! empty( $templates[ $new_status ] ) ? $templates[ $new_status ] : pw_personalizados_wa_default_template( $new_status );
	$replacements  = array(
		'<nome_cliente>'     => $client_name,
		'<codigo_cliente>'   => (string) ( $order['client']['code'] ?? '' ),
		'<numero_pedido>'    => $order_number,
		'<situacao>'         => $status_label,
		'<produto>'          => (string) ( $first_item['description'] ?? '' ),
		'<quantidade>'       => isset( $first_item['quantity'] ) ? (string) intval( $first_item['quantity'] ) : '',
		'<valor_total>'      => $total_fmt,
		'<prazo_entrega>'    => $delivery_date,
		'<data_criacao>'     => $created_date,
		'<status_pagamento>' => (string) ( $order['paymentStatus'] ?? '' ),
		'<link_contato>'     => $contact_line,
	);
	$message = str_replace( array_keys( $replacements ), array_values( $replacements ), $template );

	$url      = 'https://graph.facebook.com/v19.0/' . $wa_phone_id . '/messages';
	$payload  = wp_json_encode( array(
		'messaging_product' => 'whatsapp',
		'recipient_type'    => 'individual',
		'to'                => $digits,
		'type'              => 'text',
		'text'              => array( 'body' => $message, 'preview_url' => false ),
	) );
	$response = wp_remote_post( $url, array(
		'headers' => array( 'Authorization' => 'Bearer ' . $wa_token, 'Content-Type' => 'application/json' ),
		'body'    => $payload,
		'timeout' => 15,
	) );

	if ( is_wp_error( $response ) ) {
		wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		return;
	}
	$code = wp_remote_retrieve_response_code( $response );
	if ( 200 !== $code && 201 !== $code ) {
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		wp_send_json_error( array( 'message' => $body['error']['message'] ?? 'Erro HTTP ' . $code ) );
		return;
	}
	wp_send_json_success( array( 'sent' => true ) );
}
add_action( 'wp_ajax_pw_personalizados_whatsapp_send', 'pw_personalizados_whatsapp_send' );

/**
 * Recebe um pedido criado pela calculadora DTF UV online e o registra
 * na tabela de pedidos do sistema. Disparado via do_action('pw_dtf_order_created').
 *
 * @param array $data Dados do pedido DTF UV.
 */
function pw_personalizados_import_dtf_order( $data ) {
	global $wpdb;

	if ( ! is_array( $data ) ) {
		return;
	}

	$order_num = isset( $data['reference'] ) ? (string) $data['reference'] : '';
	if ( '' === $order_num ) {
		return;
	}

	pw_personalizados_install_tables();

	$now_sql  = current_time( 'mysql', true );
	$today    = wp_date( 'Y-m-d' );
	$time_now = wp_date( 'H:i:s' );
	$now_iso  = gmdate( 'Y-m-d\TH:i:s\Z' );

	$wp_user_id = isset( $data['user_id'] ) ? (int) $data['user_id'] : 0;
	$wp_user    = $wp_user_id > 0 ? get_userdata( $wp_user_id ) : false;
	$client_name = isset( $data['name'] ) ? (string) $data['name'] : '';

	$creator = array(
		'id'      => $wp_user_id,
		'name'    => $wp_user ? (string) $wp_user->display_name : $client_name,
		'login'   => $wp_user ? (string) $wp_user->user_login : (string) ( isset( $data['email'] ) ? $data['email'] : '' ),
		'role'    => 'Cliente',
		'summary' => $wp_user ? (string) $wp_user->display_name : $client_name,
	);

	$amount          = isset( $data['amount'] ) ? (float) $data['amount'] : 0;
	$height          = isset( $data['height'] ) ? (float) $data['height'] : 0;
	$email           = isset( $data['email'] ) ? (string) $data['email'] : '';
	$whatsapp        = isset( $data['whatsapp'] ) ? (string) $data['whatsapp'] : '';
	$payment_method  = isset( $data['payment_method'] ) ? (string) $data['payment_method'] : '';
	$payment_label   = isset( $data['payment_label'] ) ? (string) $data['payment_label'] : '';
	$delivery_label  = isset( $data['delivery_label'] ) ? (string) $data['delivery_label'] : '';
	$instructions    = isset( $data['instructions'] ) ? (string) $data['instructions'] : '';
	$detail          = isset( $data['detail'] ) ? (string) $data['detail'] : '';
	$points_used     = isset( $data['points_used'] ) ? (int) $data['points_used'] : 0;
	$points_discount = isset( $data['points_discount'] ) ? (float) $data['points_discount'] : 0;
	$is_paid         = in_array( $payment_method, array( 'pix', 'points' ), true );

	$notes_parts = array_filter( array(
		$height > 0 ? 'Altura: ' . number_format( $height, 2, ',', '.' ) . ' cm' : '',
		$payment_label ? 'Pagamento: ' . $payment_label : '',
		$delivery_label ? 'Entrega: ' . $delivery_label : '',
		$points_used > 0 ? 'Pontos: ' . $points_used . ' (-R$ ' . number_format( $points_discount, 2, ',', '.' ) . ')' : '',
		$detail ?: '',
	) );
	$notes = implode( ' — ', $notes_parts );
	if ( $instructions ) {
		$notes .= ( $notes ? "\n" : '' ) . 'Observações: ' . $instructions;
	}

	$payments = array();
	if ( $is_paid && $amount > 0 ) {
		$payments[] = array(
			'method'       => $payment_label,
			'type'         => 'Total',
			'date'         => $today,
			'value'        => $amount,
			'note'         => 'Pago pelo cliente via calculadora DTF UV online',
			'registeredAt' => $now_iso,
		);
	}

	$pdf_att_id  = isset( $data['pdf_attachment_id'] ) ? (int) $data['pdf_attachment_id'] : 0;
	$pdf_att_url = isset( $data['pdf_attachment_url'] ) ? (string) $data['pdf_attachment_url'] : '';
	$art_entry   = $pdf_att_id ? array( 'id' => $pdf_att_id, 'url' => $pdf_att_url, 'mime' => 'application/pdf', 'code' => '' ) : null;

	$order = array(
		'orderNumber'            => $order_num,
		'name'                   => $client_name,
		'createdAt'              => $now_sql,
		'createdDate'            => $today,
		'orderTime'              => $time_now,
		'lastChange'             => $now_iso,
		'requestedDelivery'      => '',
		'actualDelivery'         => '',
		'status'                 => 'Criação da arte',
		'origin'                 => 'DTF UV Online',
		'marketplaceOrderNumber' => '',
		'registrationSource'     => 'client_dtf',
		'createdBy'              => $creator,
		'client'                 => array(
			'id'                     => '',
			'code'                   => '',
			'name'                   => $client_name,
			'phone'                  => $whatsapp,
			'email'                  => $email,
			'document'               => '',
			'address'                => array(),
			'origin'                 => 'Normal',
			'marketplaceOrderNumber' => '',
			'monthlyClosing'         => false,
			'closingDay'             => 0,
		),
		'items'                  => array(
			array_filter( array(
				'product'  => 'Impressão DTF UV',
				'quantity' => 1,
				'unit'     => 'un.',
				'price'    => $amount,
				'total'    => $amount,
				'notes'    => $detail ?: ( $height > 0 ? 'Altura: ' . number_format( $height, 2, ',', '.' ) . ' cm' : 'Pedido via calculadora online' ),
				'art'      => $art_entry,
			) ),
		),
		'personalizationNotes'   => $notes,
		'total'                  => $amount,
		'discount'               => 0,
		'surcharge'              => 0,
		'paid'                   => $is_paid ? $amount : 0,
		'paymentMethod'          => $payment_label,
		'paymentType'            => $is_paid ? 'Total' : '',
		'paymentDate'            => $is_paid ? $today : '',
		'paymentStatus'          => $is_paid ? 'Pago' : '',
		'payments'               => $payments,
		'timeline'               => array(),
	);

	$columns      = pw_personalizados_record_columns( $order );
	$payload      = wp_json_encode( $order, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	$orders_table = pw_personalizados_table_map()['pw_personalizados_orders'];

	$wpdb->query( $wpdb->prepare(
		"INSERT INTO {$orders_table} (object_id, code, name, status, event_date, total, payload, created_at, updated_at)
		 VALUES (%s, %s, %s, %s, %s, %f, %s, %s, %s)
		 ON DUPLICATE KEY UPDATE code = VALUES(code), name = VALUES(name), status = VALUES(status), event_date = VALUES(event_date), total = VALUES(total), payload = VALUES(payload), updated_at = VALUES(updated_at)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$order_num, $columns['code'], $columns['name'], $columns['status'], $columns['date'], $columns['total'], $payload, $now_sql, $now_sql
	) );
}
add_action( 'pw_dtf_order_created', 'pw_personalizados_import_dtf_order' );


