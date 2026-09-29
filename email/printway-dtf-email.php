<?php
/**
 * Módulo: PrintWay DTF UV - Envio de pedidos
 * Description: Recebe os pedidos da calculadora DTF UV e envia os dados e anexos pelo wp_mail().
 * Version: 2.4.21
 * Author: PrintWay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PW_DTF_AJAX_ACTION = 'printway_dtf_send_order';
const PW_DTF_VALIDATE_PAY_LATER_ACTION = 'printway_dtf_validate_pay_later';
const PW_DTF_PREPARE_PAYMENT_ACTION = 'printway_dtf_prepare_payment';
const PW_DTF_SAVE_CALCULATION_ACTION = 'printway_dtf_save_calculation';
const PW_DTF_CURRENT_USER_ACTION = 'printway_dtf_get_current_user_profile';
const PW_DTF_NONCE_ACTION = 'printway_dtf_send_order';
const PW_DTF_MAX_FILE_SIZE = 10485760;
const PW_DTF_PRINT_WIDTH_CM = 28;

/** Oculta a barra de administração do WordPress para usuários que não são administradores nem colaboradores. */
add_filter( 'show_admin_bar', function ( $show ) {
	if ( ! is_user_logged_in() ) {
		return false;
	}
	$user  = wp_get_current_user();
	$roles = array_map( 'sanitize_key', (array) $user->roles );
	$allowed = array( 'administrator', 'colaborador', 'collaborator', 'contributor' );
	return (bool) array_intersect( $allowed, $roles );
} );

function pw_dtf_disable_calculator_cache() {
	if ( is_admin() || ! is_page( 'calcular_dtf_uv' ) ) {
		return;
	}

	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}

	do_action( 'litespeed_control_set_nocache' );
	nocache_headers();
}
add_action( 'template_redirect', 'pw_dtf_disable_calculator_cache', 0 );

/** Configurações do programa Points and Rewards aplicadas aos pedidos DTF UV. */
function pw_dtf_default_points_settings() {
	return array(
		'enabled'                => false,
		'earning_reais'          => 1,
		'earning_points'         => 1,
		'redemption_reais'       => 1,
		'redemption_points'      => 1,
		'max_redemption_percent' => 100,
		'expiration_days'      => 0,
	);
}

add_action( 'init', 'pw_dtf_schedule_points_expiry' );
add_action( 'pw_dtf_expire_points', 'pw_dtf_expire_due_points' );
add_action( 'woocommerce_account_points_endpoint', 'pw_dtf_render_points_expiration_notice', 5 );

function pw_dtf_schedule_points_expiry() {
	if ( ! wp_next_scheduled( 'pw_dtf_expire_points' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'pw_dtf_expire_points' );
	}
}

function pw_dtf_get_points_settings() {
	$saved = get_option( 'pw_dtf_points_settings', array() );
	$saved = is_array( $saved ) ? $saved : array();

	$settings = wp_parse_args( $saved, pw_dtf_default_points_settings() );
	/* Compatibilidade com a regra única usada nas versões anteriores. */
	$legacy_reais = isset( $settings['equivalence_reais'] ) ? $settings['equivalence_reais'] : 1;
	$legacy_points = isset( $settings['equivalence_points'] ) ? $settings['equivalence_points'] : 1;
	$settings['earning_reais'] = max( 0.01, (float) ( $settings['earning_reais'] ?? $legacy_reais ) );
	$settings['earning_points'] = max( 0.01, (float) ( $settings['earning_points'] ?? $legacy_points ) );
	$settings['redemption_reais'] = max( 0.01, (float) ( $settings['redemption_reais'] ?? $legacy_reais ) );
	$settings['redemption_points'] = max( 0.01, (float) ( $settings['redemption_points'] ?? $legacy_points ) );
	/* Campos derivados, preservados para a calculadora e validação do pedido. */
	$settings['earn_points_per_real'] = $settings['earning_points'] / $settings['earning_reais'];
	$settings['points_per_real'] = $settings['redemption_points'] / $settings['redemption_reais'];

	return $settings;
}

function pw_dtf_points_is_enabled() {
	$settings = pw_dtf_get_points_settings();
	return ! empty( $settings['enabled'] );
}

function pw_dtf_get_recipient_email() {
	$email = sanitize_email( get_option( 'pw_dtf_recipient_email', '' ) );
	return is_email( $email ) ? $email : get_option( 'admin_email' );
}

function pw_dtf_get_email_settings() {
	return wp_parse_args( get_option( 'pw_dtf_email_settings', array() ), array( 'notify_status_changes' => true ) );
}

function pw_dtf_email_logo_url() {
	$custom_logo_id = (int) get_theme_mod( 'custom_logo' );
	$logo = $custom_logo_id ? wp_get_attachment_image_url( $custom_logo_id, 'full' ) : '';
	return $logo ? $logo : get_site_icon_url( 128 );
}

function pw_dtf_customer_orders_url() {
	$base = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'orders' ) : home_url( '/minha-conta/orders/' );
	return add_query_arg( 'tipo', 'dtf-uv', $base );
}

function pw_dtf_get_company_contact_details() {
	$woocommerce_address = array_filter( array(
		get_option( 'woocommerce_store_address', '' ),
		get_option( 'woocommerce_store_address_2', '' ),
		trim( get_option( 'woocommerce_store_city', '' ) . ( get_option( 'woocommerce_store_state', '' ) ? ' - ' . get_option( 'woocommerce_store_state', '' ) : '' ) . ( get_option( 'woocommerce_store_postcode', '' ) ? ' - ' . get_option( 'woocommerce_store_postcode', '' ) : '' ) ),
	) );
	$defaults = array(
		'address' => implode( ', ', $woocommerce_address ),
		'whatsapp' => get_option( 'woocommerce_store_phone', '' ),
		'phone' => get_option( 'woocommerce_store_phone', '' ),
		'site' => home_url(),
		'instagram' => '',
	);
	$saved = get_option( 'pw_dtf_contact_settings', array() );
	$saved = is_array( $saved ) ? $saved : array();
	$contact = wp_parse_args( $saved, $defaults );
	$phone = $contact['whatsapp'] ? $contact['whatsapp'] : $contact['phone'];
	$digits = preg_replace( '/\D+/', '', $phone );
	if ( $digits && strlen( $digits ) <= 11 ) { $digits = '55' . $digits; }

	return array(
		'name'     => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		'email'    => get_option( 'woocommerce_email_from_address', get_option( 'admin_email' ) ),
		'phone'    => $contact['phone'],
		'whatsapp_label' => $contact['whatsapp'],
		'whatsapp' => $digits ? 'https://wa.me/' . $digits : '',
		'address'  => $contact['address'],
		'site'     => $contact['site'],
		'instagram'=> $contact['instagram'],
	);
}

function pw_dtf_render_email_html( $title, $intro, $rows ) {
	$logo = pw_dtf_email_logo_url();
	$contact = pw_dtf_get_company_contact_details();
	$site = $contact['name'];
	$html = '<!doctype html><html><body style="margin:0;background:#f4f6f8;font-family:Arial,sans-serif;color:#253044"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td style="padding:28px 12px"><table role="presentation" width="640" cellpadding="0" cellspacing="0" style="max-width:640px;margin:auto;background:#fff;border-radius:12px;overflow:hidden">';
	$html .= '<tr><td style="background:#123f71;padding:24px;text-align:center">' . ( $logo ? '<img src="' . esc_url( $logo ) . '" alt="' . esc_attr( $site ) . '" style="max-height:70px;max-width:220px">' : '<strong style="font-size:24px;color:#fff">' . esc_html( $site ) . '</strong>' ) . '</td></tr>';
	$html .= '<tr><td style="padding:28px"><h1 style="margin:0 0 12px;font-size:23px;color:#123f71">' . esc_html( $title ) . '</h1><p style="margin:0 0 20px;line-height:1.5">' . esc_html( $intro ) . '</p><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:8px">';
	foreach ( $rows as $label => $value ) {
		$display_value = esc_html( $value );
		if ( 'Pedido' === $label && '' !== $value ) {
			$display_value = '<a href="' . esc_url( pw_dtf_customer_orders_url() ) . '" style="color:#123f71;font-weight:700;text-decoration:none">' . esc_html( $value ) . '</a>';
		}
		$html .= '<tr><td style="padding:10px 12px;border-bottom:1px solid #edf1f5;color:#667085;width:42%">' . esc_html( $label ) . '</td><td style="padding:10px 12px;border-bottom:1px solid #edf1f5;font-weight:600">' . $display_value . '</td></tr>';
	}
	$footer = array_filter( array( $contact['address'] ) );
	$html .= '</table><div style="margin:22px 0 0;padding:16px;background:#f7f9fc;border-radius:8px;color:#667085;font-size:13px;line-height:1.6"><strong style="color:#344054">' . esc_html( $site ) . '</strong><br>' . esc_html( implode( ' | ', $footer ) );
	if ( $contact['phone'] ) {
		$html .= '<br>Telefone: ' . esc_html( $contact['phone'] );
	}
	if ( $contact['whatsapp_label'] ) {
		$html .= '<br>WhatsApp: <a href="' . esc_url( $contact['whatsapp'] ) . '" style="color:#123f71;font-weight:700;text-decoration:none">' . esc_html( $contact['whatsapp_label'] ) . '</a>';
	}
	if ( $contact['site'] ) {
		$html .= '<br>Site: <a href="' . esc_url( $contact['site'] ) . '" style="color:#123f71;text-decoration:none">' . esc_html( $contact['site'] ) . '</a>';
	}
	if ( $contact['instagram'] ) {
		$instagram_url = preg_match( '#^https?://#i', $contact['instagram'] ) ? $contact['instagram'] : 'https://instagram.com/' . ltrim( $contact['instagram'], '@/' );
		$html .= '<br>Instagram: <a href="' . esc_url( $instagram_url ) . '" style="color:#123f71;text-decoration:none">' . esc_html( $contact['instagram'] ) . '</a>';
	}
	$html .= '</div><p style="margin:14px 0 0;color:#98a2b3;font-size:12px">Mensagem automática de ' . esc_html( $site ) . '.</p></td></tr></table></td></tr></table></body></html>';
	return $html;
}

function pw_dtf_log_email( $recipient, $subject, $type, $sent, $order_id = 0 ) {
	$post_id = wp_insert_post( array(
		'post_type' => 'pw_dtf_email_log',
		'post_status' => 'publish',
		'post_title' => sanitize_text_field( $subject ),
	), true );
	if ( is_wp_error( $post_id ) ) { return; }
	update_post_meta( $post_id, '_pw_dtf_email_recipient', sanitize_email( $recipient ) );
	update_post_meta( $post_id, '_pw_dtf_email_type', sanitize_text_field( $type ) );
	update_post_meta( $post_id, '_pw_dtf_email_sent', $sent ? 'yes' : 'no' );
	update_post_meta( $post_id, '_pw_dtf_email_delivery_status', $sent ? 'accepted_by_wordpress' : 'failed_before_acceptance' );
	update_post_meta( $post_id, '_pw_dtf_email_delivery_note', $sent ? 'O WordPress aceitou o envio. A entrega final depende do servidor SMTP e do destinatário.' : 'O WordPress não aceitou o envio.' );
	update_post_meta( $post_id, '_pw_dtf_email_order_id', absint( $order_id ) );
}

function pw_dtf_send_status_email( $order_id, $status ) {
	$settings = pw_dtf_get_email_settings();
	$email = sanitize_email( get_post_meta( $order_id, '_pw_dtf_email', true ) );
	$reference = get_post_meta( $order_id, '_pw_dtf_reference', true );
	if ( empty( $settings['notify_status_changes'] ) ) {
		pw_dtf_log_email( $email, 'Atualização do pedido ' . $reference, 'Aviso de status desativado', false, $order_id );
		return;
	}
	if ( ! is_email( $email ) ) {
		pw_dtf_log_email( $email, 'Atualização do pedido ' . $reference, 'E-mail do cliente inválido', false, $order_id );
		return;
	}
	$rows = array(
		'Pedido' => $reference,
		'Status atual' => $status,
		'Valor' => wp_strip_all_tags( wc_price( (float) get_post_meta( $order_id, '_pw_dtf_amount', true ) ) ),
		'Pagamento' => get_post_meta( $order_id, '_pw_dtf_payment_label', true ),
		'Data do pedido' => pw_dtf_format_order_date( get_post( $order_id ) ),
	);
	$subject = sprintf( '[%s] Atualização do pedido %s', get_bloginfo( 'name' ), $reference );
	$sent = wp_mail( $email, $subject, pw_dtf_render_email_html( 'Atualização do seu pedido DTF UV', 'O status do seu pedido foi atualizado.', $rows ), array( 'Content-Type: text/html; charset=UTF-8' ) );
	pw_dtf_log_email( $email, $subject, 'Atualização de status: ' . $status, $sent, $order_id );
}

function pw_dtf_get_user_points_balance( $user_id ) {
	return max( 0, (int) get_user_meta( absint( $user_id ), 'wps_wpr_points', true ) );
}

/**
 * Atualiza o saldo que o Points and Rewards for WooCommerce utiliza e mantém
 * um registro identificado como DTF UV para auditoria do administrador.
 */
function pw_dtf_add_points_to_user( $user_id, $points, $reference ) {
	$user_id = absint( $user_id );
	$points  = (int) $points;
	if ( $user_id <= 0 || $points <= 0 ) {
		return 0;
	}

	$current = pw_dtf_get_user_points_balance( $user_id );
	update_user_meta( $user_id, 'wps_wpr_points', $current + $points );

	$details = get_user_meta( $user_id, 'points_details', true );
	$details = is_array( $details ) ? $details : array();
	if ( ! isset( $details['printway_dtf_uv_points'] ) || ! is_array( $details['printway_dtf_uv_points'] ) ) {
		$details['printway_dtf_uv_points'] = array();
	}
	if ( ! isset( $details['points_on_order'] ) || ! is_array( $details['points_on_order'] ) ) {
		$details['points_on_order'] = array();
	}
	$settings = pw_dtf_get_points_settings();
	$expires_at = (int) $settings['expiration_days'] > 0
		? wp_date( 'Y-m-d H:i:s', time() + ( (int) $settings['expiration_days'] * DAY_IN_SECONDS ), wp_timezone() )
		: '';
	$details['printway_dtf_uv_points'][] = array(
		'printway_dtf_uv_points' => $points,
		'date'                    => current_time( 'mysql' ),
		'reference'               => sanitize_text_field( $reference ),
		'expires_at'              => $expires_at,
		'expired'                 => false,
	);
	// Registro compatível com a tabela nativa de histórico de pedidos.
	$details['points_on_order'][] = array(
		'points_on_order' => $points,
		'date'            => current_time( 'mysql' ),
		'reference'       => sanitize_text_field( $reference ),
		'source'          => 'printway_dtf_uv',
	);
	update_user_meta( $user_id, 'points_details', $details );

	return $points;
}

function pw_dtf_deduct_points_from_user( $user_id, $points, $reference ) {
	$user_id = absint( $user_id );
	$points = max( 0, (int) $points );
	$current = pw_dtf_get_user_points_balance( $user_id );
	if ( $user_id <= 0 || $points <= 0 || $points > $current ) {
		return false;
	}
	update_user_meta( $user_id, 'wps_wpr_points', $current - $points );
	$details = get_user_meta( $user_id, 'points_details', true );
	$details = is_array( $details ) ? $details : array();
	$details['printway_dtf_uv_redemption'][] = array( 'printway_dtf_uv_redemption' => -$points, 'date' => current_time( 'mysql' ), 'reference' => sanitize_text_field( $reference ) );
	update_user_meta( $user_id, 'points_details', $details );
	return true;
}

/** Restaura pontos se o pedido não puder ser registrado ou enviado. */
function pw_dtf_restore_points_to_user( $user_id, $points, $reference ) {
	$user_id = absint( $user_id );
	$points  = max( 0, (int) $points );
	if ( $user_id <= 0 || $points <= 0 ) {
		return false;
	}

	update_user_meta( $user_id, 'wps_wpr_points', pw_dtf_get_user_points_balance( $user_id ) + $points );
	$details = get_user_meta( $user_id, 'points_details', true );
	$details = is_array( $details ) ? $details : array();
	if ( ! isset( $details['printway_dtf_uv_refund'] ) || ! is_array( $details['printway_dtf_uv_refund'] ) ) {
		$details['printway_dtf_uv_refund'] = array();
	}
	$details['printway_dtf_uv_refund'][] = array(
		'printway_dtf_uv_refund' => $points,
		'date'                    => current_time( 'mysql' ),
		'reference'               => sanitize_text_field( $reference ),
	);
	update_user_meta( $user_id, 'points_details', $details );

	return true;
}

/** Expira somente créditos que este módulo DTF UV concedeu. */
function pw_dtf_expire_due_points() {
	$users = get_users( array( 'fields' => 'ID', 'meta_key' => 'points_details', 'number' => -1 ) );
	$now = current_time( 'timestamp' );
	foreach ( $users as $user_id ) {
		$details = get_user_meta( $user_id, 'points_details', true );
		if ( empty( $details['printway_dtf_uv_points'] ) || ! is_array( $details['printway_dtf_uv_points'] ) ) {
			continue;
		}
		$changed = false;
		$deduct = 0;
		foreach ( $details['printway_dtf_uv_points'] as &$entry ) {
			$expires = isset( $entry['expires_at'] ) ? strtotime( $entry['expires_at'] ) : false;
			if ( empty( $entry['expired'] ) && $expires && $expires <= $now ) {
				$deduct += max( 0, (int) $entry['printway_dtf_uv_points'] );
				$entry['expired'] = true;
				$changed = true;
			}
		}
		unset( $entry );
		if ( ! $changed ) {
			continue;
		}
		$current = pw_dtf_get_user_points_balance( $user_id );
		update_user_meta( $user_id, 'wps_wpr_points', max( 0, $current - $deduct ) );
		update_user_meta( $user_id, 'points_details', $details );
	}
}

function pw_dtf_render_points_expiration_notice() {
	if ( ! is_user_logged_in() ) {
		return;
	}
	pw_dtf_expire_due_points();
	$details = get_user_meta( get_current_user_id(), 'points_details', true );
	$entries = isset( $details['printway_dtf_uv_points'] ) && is_array( $details['printway_dtf_uv_points'] ) ? $details['printway_dtf_uv_points'] : array();
	$valid = array_filter( $entries, function( $entry ) { return empty( $entry['expired'] ) && ! empty( $entry['expires_at'] ); } );
	if ( empty( $valid ) ) {
		return;
	}
	usort( $valid, function( $a, $b ) { return strcmp( $a['expires_at'], $b['expires_at'] ); } );
	$next = reset( $valid );
	echo '<div class="woocommerce-info">Seus pontos de DTF UV têm validade. O próximo vencimento é em <strong>' . esc_html( wp_date( 'd/m/Y', strtotime( $next['expires_at'] ), wp_timezone() ) ) . '</strong>.</div>';
}

function pw_dtf_award_points_for_confirmed_order( $order_id ) {
	if ( ! pw_dtf_points_is_enabled() || get_post_meta( $order_id, '_pw_dtf_points_awarded', true ) ) {
		return;
	}

	$user_id = (int) get_post_field( 'post_author', $order_id );
	$amount  = (float) get_post_meta( $order_id, '_pw_dtf_amount', true );
	$settings = pw_dtf_get_points_settings();
	$points = (int) floor( $amount * (float) $settings['earn_points_per_real'] );
	$awarded = pw_dtf_add_points_to_user( $user_id, $points, get_post_meta( $order_id, '_pw_dtf_reference', true ) );

	if ( $awarded > 0 ) {
		update_post_meta( $order_id, '_pw_dtf_points_awarded', $awarded );
	}
}

add_action( 'init', 'pw_dtf_register_order_storage' );
add_action( 'admin_menu', 'pw_dtf_register_admin_menu' );
add_filter( 'woocommerce_account_menu_items', 'pw_dtf_remove_legacy_account_menu', 999 );
add_action( 'woocommerce_account_orders_endpoint', 'pw_dtf_render_orders_tabs', 1 );
add_action( 'template_redirect', 'pw_dtf_default_orders_to_dtf_uv' );

function pw_dtf_default_orders_to_dtf_uv() {
	if ( ! is_user_logged_in() ) {
		return;
	}
	if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'orders' ) ) {
		return;
	}
	if ( isset( $_GET['tipo'] ) ) {
		return;
	}
	wp_safe_redirect( add_query_arg( 'tipo', 'dtf-uv', wc_get_account_endpoint_url( 'orders' ) ) );
	exit;
}

function pw_dtf_remove_legacy_account_menu( $items ) {
	unset( $items['pedidos-dtf-uv'] );

	return $items;
}

function pw_dtf_register_order_storage() {
	register_post_type(
		'pw_dtf_order',
		array(
			'labels' => array(
				'name'          => 'Pedidos DTF UV',
				'singular_name' => 'Pedido DTF UV',
			),
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'exclude_from_search' => true,
			'supports'            => array( 'title', 'author' ),
			'menu_icon'           => 'dashicons-media-spreadsheet',
		)
	);
	register_post_type(
		'pw_dtf_email_log',
		array(
			'labels' => array( 'name' => 'E-mails DTF UV', 'singular_name' => 'E-mail DTF UV' ),
			'public' => false, 'show_ui' => false, 'show_in_menu' => false,
			'supports' => array( 'title' ),
		)
	);
	register_post_type(
		'pw_dtf_calculation',
		array(
			'labels' => array( 'name' => 'Cálculos DTF UV', 'singular_name' => 'Cálculo DTF UV' ),
			'public' => false, 'show_ui' => false, 'show_in_menu' => false,
			'supports' => array( 'title', 'author' ),
		)
	);
}

function pw_dtf_render_orders_tabs() {
	$type     = isset( $_GET['tipo'] ) ? sanitize_key( wp_unslash( $_GET['tipo'] ) ) : 'site';
	$base_url = wc_get_account_endpoint_url( 'orders' );
	$site_url = remove_query_arg( 'tipo', $base_url );
	$dtf_url  = add_query_arg( 'tipo', 'dtf-uv', $base_url );

	echo '<nav class="pw-dtf-order-tabs" aria-label="Tipos de pedidos" style="display:flex;gap:10px;flex-wrap:wrap;margin:0 0 22px">';
	echo '<a class="woocommerce-button button' . ( 'dtf-uv' === $type ? ' alt' : '' ) . '" href="' . esc_url( $dtf_url ) . '"' . ( 'dtf-uv' === $type ? ' aria-current="page"' : '' ) . '>Pedidos de DTF UV</a>';
	echo '<a class="woocommerce-button button' . ( 'site' === $type ? ' alt' : '' ) . '" href="' . esc_url( $site_url ) . '"' . ( 'site' === $type ? ' aria-current="page"' : '' ) . '>Pedidos do site</a>';
	echo '</nav>';

	if ( 'dtf-uv' === $type ) {
		remove_action( 'woocommerce_account_orders_endpoint', 'woocommerce_account_orders', 10 );
		pw_dtf_render_account_orders();
	}
}

function pw_dtf_render_account_orders() {
	if ( ! is_user_logged_in() ) {
		echo '<p>Faça login para consultar seus pedidos.</p>';
		return;
	}

	$orders = get_posts(
		array(
			'post_type'      => 'pw_dtf_order',
			'post_status'    => 'publish',
			'author'         => get_current_user_id(),
			'posts_per_page' => 50,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);
	echo '<h2>Pedidos de DTF UV</h2>';

	if ( empty( $orders ) ) {
		echo '<p>Nenhum pedido de DTF UV foi enviado por esta conta.</p>';
		return;
	}

	$hide_completed = '1' === get_user_meta( get_current_user_id(), '_pw_dtf_hide_completed_orders', true );
	echo '<p><label style="display:inline-flex;align-items:center;gap:7px;cursor:pointer"><input id="pw-dtf-hide-completed" type="checkbox"' . checked( $hide_completed, true, false ) . '> Ocultar concluídos</label></p>';
	$ajax_url = admin_url( 'admin-ajax.php' );
	$nonce    = wp_create_nonce( PW_DTF_NONCE_ACTION );

	echo '<style>
		.pw-dtf-account-completed{opacity:.58;background:#f6f8fa}
		.pw-dtf-account-completed td{color:#667085}
		.pw-dtf-pay-badge{display:inline-block;padding:2px 8px;border-radius:12px;font-size:12px;font-weight:600}
		.pw-dtf-pay-badge.paid{background:#dcfce7;color:#166534}
		.pw-dtf-pay-badge.pending{background:#fef9c3;color:#854d0e}
		.pw-dtf-pay-badge.waiting{background:#fee2e2;color:#991b1b}
		.pw-dtf-qr-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;background:#2563eb;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600;margin-top:4px}
		.pw-dtf-qr-btn:hover{background:#1d4ed8}
		#pw-dtf-qr-modal{display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.6);align-items:center;justify-content:center}
		#pw-dtf-qr-modal.active{display:flex}
		#pw-dtf-qr-modal-inner{background:#fff;border-radius:14px;padding:28px 24px;max-width:380px;width:92%;text-align:center;position:relative}
		#pw-dtf-qr-modal-close{position:absolute;top:10px;right:14px;font-size:22px;cursor:pointer;background:none;border:none;color:#555}
		#pw-dtf-qr-modal img{width:220px;height:220px;margin:10px auto}
		#pw-dtf-qr-copy{display:inline-flex;align-items:center;gap:5px;padding:8px 14px;background:#f0f9ff;border:1px solid #7dd3fc;border-radius:7px;cursor:pointer;font-size:12px;font-family:monospace;word-break:break-all;max-width:100%;margin:6px 0}
		#pw-dtf-qr-status{margin-top:10px;font-size:13px;color:#555}
	</style>';

	echo '<div style="overflow-x:auto">';
	echo '<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table pw-dtf-sortable-table">';
	echo '<thead><tr>';
	echo '<th>Pedido</th><th>Data</th><th>Valor</th><th>Forma de Pagamento</th><th>Status Pagamento</th><th>Entrega</th><th>Situação</th><th>Ação</th>';
	echo '</tr></thead><tbody>';

	$payment_status_labels = array(
		'paid'       => array( 'label' => 'Pago', 'class' => 'paid' ),
		'pending_mp' => array( 'label' => 'Aguardando Pix', 'class' => 'pending' ),
		'aguardando' => array( 'label' => 'Aguardando', 'class' => 'waiting' ),
		''           => array( 'label' => '—', 'class' => '' ),
	);

	foreach ( $orders as $order ) {
		$reference      = get_post_meta( $order->ID, '_pw_dtf_reference', true );
		$amount         = (float) get_post_meta( $order->ID, '_pw_dtf_amount', true );
		$payment        = get_post_meta( $order->ID, '_pw_dtf_payment_label', true );
		$delivery       = get_post_meta( $order->ID, '_pw_dtf_delivery_label', true );
		$status         = get_post_meta( $order->ID, '_pw_dtf_status', true );
		$pay_status     = (string) get_post_meta( $order->ID, '_pw_dtf_payment_status', true );
		$pay_info       = isset( $payment_status_labels[ $pay_status ] ) ? $payment_status_labels[ $pay_status ] : $payment_status_labels[''];
		$can_pay        = in_array( $pay_status, array( 'pending_mp', 'aguardando', '' ), true ) && $amount > 0;

		echo '<tr class="' . ( 'Concluído' === $status ? 'pw-dtf-account-completed' : '' ) . '">';
		echo '<td data-title="Pedido">' . esc_html( $reference ) . '</td>';
		echo '<td data-title="Data">' . esc_html( pw_dtf_format_order_date( $order ) ) . '</td>';
		echo '<td data-title="Valor">' . wp_kses_post( wc_price( $amount ) ) . '</td>';
		echo '<td data-title="Forma de Pagamento">' . esc_html( $payment ) . '</td>';
		echo '<td data-title="Status Pagamento"><span class="pw-dtf-pay-badge ' . esc_attr( $pay_info['class'] ) . '">' . esc_html( $pay_info['label'] ) . '</span></td>';
		echo '<td data-title="Entrega">' . esc_html( $delivery ? $delivery : 'Não informada' ) . '</td>';
		echo '<td data-title="Situação">' . esc_html( $status ? $status : 'Enviado para análise' ) . '</td>';
		echo '<td data-title="Ação">';
		if ( $can_pay ) {
			echo '<button class="pw-dtf-qr-btn" data-order-id="' . esc_attr( $order->ID ) . '" data-amount="' . esc_attr( $amount ) . '" onclick="pwDtfOpenQr(this)">&#128247; Gerar QR Code para pagamento</button>';
		} else {
			echo '—';
		}
		echo '</td>';
		echo '</tr>';
	}

	echo '</tbody></table></div>';

	echo '<div id="pw-dtf-qr-modal"><div id="pw-dtf-qr-modal-inner">';
	echo '<button id="pw-dtf-qr-modal-close" onclick="pwDtfCloseQr()" aria-label="Fechar">&times;</button>';
	echo '<h3 style="margin:0 0 4px">Pagamento via Pix</h3>';
	echo '<p style="margin:0 0 10px;color:#555;font-size:13px">Escaneie o QR Code ou copie o código Pix</p>';
	echo '<img id="pw-dtf-qr-img" src="" alt="QR Code Pix" />';
	echo '<div id="pw-dtf-qr-copy" onclick="pwDtfCopyPix(this)" title="Clique para copiar"><span id="pw-dtf-qr-text"></span></div>';
	echo '<div id="pw-dtf-qr-status">Aguardando pagamento...</div>';
	echo '</div></div>';

	$js = 'var _pwDtfQrOrderId=0,_pwDtfQrPayId=0,_pwDtfQrPollTimer=null,_pwDtfQrSeq=0;
function pwDtfOpenQr(btn){
  var orderId=btn.dataset.orderId;
  _pwDtfQrOrderId=orderId;_pwDtfQrSeq++;
  var seq=_pwDtfQrSeq;
  var modal=document.getElementById("pw-dtf-qr-modal");
  var img=document.getElementById("pw-dtf-qr-img");
  var txt=document.getElementById("pw-dtf-qr-text");
  var st=document.getElementById("pw-dtf-qr-status");
  img.src="";txt.textContent="";st.textContent="Gerando QR Code...";
  modal.classList.add("active");
  var form=new URLSearchParams();
  form.append("action","pw_dtf_create_pix_for_order");
  form.append("nonce",' . wp_json_encode( $nonce ) . ');
  form.append("order_id",orderId);
  fetch(' . wp_json_encode( $ajax_url ) . ',{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},body:form.toString()})
  .then(function(r){return r.json();})
  .then(function(json){
    if(seq!==_pwDtfQrSeq){return;}
    if(!json.success){st.textContent=(json.data&&json.data.message)||"Erro ao gerar QR Code.";return;}
    _pwDtfQrPayId=json.data.payment_id;
    if(json.data.qr_base64){img.src="data:image/png;base64,"+json.data.qr_base64;}
    txt.textContent=json.data.qr_code||"";
    st.textContent="Aguardando confirmação do pagamento...";
    if(_pwDtfQrPollTimer){clearInterval(_pwDtfQrPollTimer);}
    _pwDtfQrPollTimer=setInterval(function(){pwDtfPollQr(seq,_pwDtfQrPayId,orderId);},4000);
  })
  .catch(function(){if(seq===_pwDtfQrSeq){st.textContent="Erro de conexão. Tente novamente.";}});
}
function pwDtfPollQr(seq,payId,orderId){
  if(seq!==_pwDtfQrSeq){clearInterval(_pwDtfQrPollTimer);return;}
  var form=new URLSearchParams();
  form.append("action","pw_dtf_mp_check_pix");
  form.append("nonce",' . wp_json_encode( $nonce ) . ');
  form.append("payment_id",payId);
  fetch(' . wp_json_encode( $ajax_url ) . ',{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},body:form.toString()})
  .then(function(r){return r.json();})
  .then(function(json){
    if(seq!==_pwDtfQrSeq){return;}
    if(json.success&&json.data&&json.data.status==="approved"){
      clearInterval(_pwDtfQrPollTimer);
      var st=document.getElementById("pw-dtf-qr-status");
      st.style.color="#166534";st.textContent="✓ Pagamento confirmado! Seu pedido foi atualizado.";
      pwDtfRegisterPay(orderId,payId,seq);
    }
  }).catch(function(){});
}
function pwDtfRegisterPay(orderId,payId,seq){
  var form=new URLSearchParams();
  form.append("action","pw_dtf_register_mp_payment");
  form.append("nonce",' . wp_json_encode( $nonce ) . ');
  form.append("order_id",orderId);
  form.append("mp_payment_id",payId);
  fetch(' . wp_json_encode( $ajax_url ) . ',{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},body:form.toString()}).catch(function(){});
  setTimeout(function(){location.reload();},3000);
}
function pwDtfCloseQr(){
  _pwDtfQrSeq++;
  if(_pwDtfQrPollTimer){clearInterval(_pwDtfQrPollTimer);_pwDtfQrPollTimer=null;}
  document.getElementById("pw-dtf-qr-modal").classList.remove("active");
}
function pwDtfCopyPix(el){
  var t=document.getElementById("pw-dtf-qr-text").textContent;
  if(!t){return;}
  navigator.clipboard&&navigator.clipboard.writeText(t).then(function(){el.style.background="#dcfce7";setTimeout(function(){el.style.background="";},1500);});
}';

	echo '<script>' . $js . '</script>';

	echo '<script>(function(){var toggle=document.getElementById("pw-dtf-hide-completed");if(!toggle){return;}function apply(){document.querySelectorAll(".pw-dtf-account-completed").forEach(function(row){row.style.display=toggle.checked?"none":"";});}toggle.addEventListener("change",function(){apply();var form=new URLSearchParams();form.append("action","printway_dtf_save_completed_visibility");form.append("nonce",window.printway_dtf_nonce||"");form.append("hide",toggle.checked?"1":"0");fetch((window.PW_SERVER_DATA&&window.PW_SERVER_DATA.ajax_url)||"/wp-admin/admin-ajax.php",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},body:form.toString()});});apply();})();</script>';
	pw_dtf_render_sortable_table_script();
}

/**
 * Formata a data do pedido usando o fuso horário configurado no WordPress.
 */
function pw_dtf_format_order_date( $order ) {
	$date = get_post_datetime( $order, 'date', 'edit' );

	return $date ? wp_date( 'd/m/Y H:i', $date->getTimestamp(), wp_timezone() ) : '';
}

/**
 * Torna clicáveis os títulos das tabelas de listagem do DTF UV.
 */
function pw_dtf_render_sortable_table_script() {
	echo '<style>.pw-dtf-sortable-table th[data-pw-sort],.pw-dtf-orders-table th[data-pw-sort],.pw-dtf-users-table th[data-pw-sort]{cursor:pointer;user-select:none}.pw-dtf-sortable-table th[data-pw-sort]::after,.pw-dtf-orders-table th[data-pw-sort]::after,.pw-dtf-users-table th[data-pw-sort]::after{content:" ↕";color:#777;font-size:12px}.pw-dtf-sortable-table th[data-pw-sort="asc"]::after,.pw-dtf-orders-table th[data-pw-sort="asc"]::after,.pw-dtf-users-table th[data-pw-sort="asc"]::after{content:" ↑"}.pw-dtf-sortable-table th[data-pw-sort="desc"]::after,.pw-dtf-orders-table th[data-pw-sort="desc"]::after,.pw-dtf-users-table th[data-pw-sort="desc"]::after{content:" ↓"}</style>';
	echo '<script>(function(){function value(cell){var field=cell.querySelector("select,input");if(field&&field.tagName==="SELECT"){return field.options[field.selectedIndex].textContent.trim();}if(field&&field.value){return field.value.trim();}return cell.textContent.trim();}function normalized(raw){var date=raw.match(/^(\\d{2})\\/(\\d{2})\\/(\\d{4})(?:\\s+(\\d{2}):(\\d{2}))?/);if(date){return "D"+date[3]+date[2]+date[1]+(date[4]||"00")+(date[5]||"00");}var number=raw.replace(/R\\$|cm|[^0-9,.-]/gi,"").replace(/\\./g,"").replace(",",".");return /^-?\\d+(?:\\.\\d+)?$/.test(number)?"N"+String(parseFloat(number)).padStart(20,"0"):"T"+raw.toLocaleLowerCase("pt-BR");}function setTable(table){if(table.dataset.pwSortReady){return;}table.dataset.pwSortReady="1";table.querySelectorAll("thead th").forEach(function(header,index){if(header.dataset.pwNoSort!==undefined){return;}header.dataset.pwSort="";header.tabIndex=0;header.setAttribute("role","button");header.setAttribute("title","Ordenar por esta coluna");function sort(){var direction=header.dataset.pwSort==="asc"?"desc":"asc",body=table.tBodies[0];if(!body){return;}table.querySelectorAll("thead th").forEach(function(item){if(item!==header){item.dataset.pwSort="";}});Array.from(body.rows).sort(function(left,right){var a=normalized(value(left.cells[index]||document.createElement("td"))),b=normalized(value(right.cells[index]||document.createElement("td")));return (a<b?-1:a>b?1:0)*(direction==="asc"?1:-1);}).forEach(function(row){body.appendChild(row);});header.dataset.pwSort=direction;}header.addEventListener("click",sort);header.addEventListener("keydown",function(event){if(event.key==="Enter"||event.key===" "){event.preventDefault();sort();}});});}function setup(){document.querySelectorAll("table.pw-dtf-sortable-table,table.pw-dtf-orders-table,table.pw-dtf-users-table").forEach(setTable);}if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",setup);}else{setup();}})();</script>';
}

function pw_dtf_register_admin_menu() {
	add_submenu_page(
		'pw-printway',
		'DTF UV',
		'DTF UV',
		'manage_options',
		'pw-printway-dtf-uv',
		'pw_dtf_render_admin_dtf_uv_page'
	);
}

function pw_dtf_render_admin_dtf_uv_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$tab      = isset( $_GET['aba'] ) ? sanitize_key( wp_unslash( $_GET['aba'] ) ) : 'pedidos';
	$base_url = admin_url( 'admin.php?page=pw-printway-dtf-uv' );

	echo '<div class="wrap"><h1>PrintWay - DTF UV</h1>';
	echo '<style>
        .pw-dtf-orders-wrap{max-width:100%;overflow-x:auto}
        .pw-dtf-orders-table{width:100%;min-width:1280px;table-layout:fixed}
        .pw-dtf-orders-table th,.pw-dtf-orders-table td{vertical-align:middle;overflow-wrap:anywhere}
        .pw-dtf-orders-table th:nth-child(1){width:3%}.pw-dtf-orders-table th:nth-child(2){width:13%}.pw-dtf-orders-table th:nth-child(3){width:12%}.pw-dtf-orders-table th:nth-child(4){width:10%}.pw-dtf-orders-table th:nth-child(5){width:12%}.pw-dtf-orders-table th:nth-child(6){width:12%}.pw-dtf-orders-table th:nth-child(7){width:8%}.pw-dtf-orders-table th:nth-child(8){width:11%}.pw-dtf-orders-table th:nth-child(9){width:19%}
        .pw-dtf-orders-table select{width:100%;max-width:100%;box-sizing:border-box}
        .pw-dtf-users-table{width:100%;min-width:1000px;table-layout:fixed}
        .pw-dtf-users-table th:nth-child(1){width:13%}.pw-dtf-users-table th:nth-child(2){width:6%}.pw-dtf-users-table th:nth-child(3){width:21%}.pw-dtf-users-table th:nth-child(4){width:17%}.pw-dtf-users-table th:nth-child(5){width:12%}.pw-dtf-users-table th:nth-child(6){width:18%}.pw-dtf-users-table th:nth-child(7){width:13%}
        .pw-dtf-users-table select,.pw-dtf-users-table input:not([type="checkbox"]){width:100%;max-width:100%;box-sizing:border-box}
        .pw-dtf-users-table th:nth-child(7),.pw-dtf-users-table td:nth-child(7){text-align:center}
        .pw-dtf-price-section{margin:22px 0;padding:16px;background:#fff;border:1px solid #ccd0d4;border-radius:4px}
        .pw-dtf-price-section h3{margin-top:0}.pw-dtf-price-table{width:100%;max-width:900px}
        .pw-dtf-price-table input{width:100%;box-sizing:border-box}.pw-dtf-price-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}
        .pw-dtf-points-settings{max-width:760px;padding:18px;background:#fff;border:1px solid #ccd0d4;border-radius:4px}
        .pw-dtf-points-settings .pw-dtf-setting-row{display:grid;grid-template-columns:minmax(260px,1fr) 300px;gap:16px;align-items:center;margin:16px 0}
        .pw-dtf-points-settings input[type="number"]{width:100%}.pw-dtf-points-settings .pw-dtf-equivalence input{width:96px!important;min-width:96px;flex:0 0 96px}.pw-dtf-points-help{color:#50575e;margin:4px 0 0}
        .pw-dtf-save-all .dashicons{vertical-align:text-bottom;margin-right:4px}
        .pw-dtf-order-summary{margin:0 0 14px;padding:10px 12px;background:#fff;border:1px solid #ccd0d4;border-radius:4px}
        .pw-dtf-filters{margin:0 0 16px}
        .pw-dtf-date-filter,.pw-dtf-status-filter{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
        .pw-dtf-date-filter{margin:0 0 10px;padding:10px 12px;background:#fff;border:1px solid #ccd0d4;border-radius:4px}
        .pw-dtf-date-filter label,.pw-dtf-status-filter label{white-space:nowrap}
        .pw-dtf-date-filter input[type="date"]{max-width:150px}
        .pw-dtf-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:0 0 14px}
        .pw-dtf-print-header,.pw-dtf-print-status{display:none}
        @media print{
            @page{size:A4 landscape;margin:12mm}
            #wpadminbar,#adminmenumain,#wpfooter,.update-nag,.notice,.nav-tab-wrapper,.pw-dtf-filters,.pw-dtf-actions{display:none!important}
            #wpcontent,#wpbody-content{margin:0!important;padding:0!important}
            .wrap{margin:0!important}.wrap>h1{display:none}
            .pw-dtf-print-header{display:block;border-bottom:2px solid #1d2327;margin:0 0 16px;padding:0 0 12px}
            .pw-dtf-print-logo{max-height:62px;max-width:230px;width:auto;height:auto;margin:0 0 8px}
            .pw-dtf-print-header h1{margin:0 0 6px;font-size:21px}.pw-dtf-print-header p{margin:3px 0;font-size:11px}
            .pw-dtf-order-summary{margin:0 0 12px;border-color:#999}
            .pw-dtf-orders-wrap{overflow:visible}.pw-dtf-orders-table{min-width:0!important;table-layout:auto;font-size:10px}
            .pw-dtf-orders-table th,.pw-dtf-orders-table td{padding:6px!important;overflow-wrap:anywhere}
            .pw-dtf-orders-table select{display:none!important}.pw-dtf-print-status{display:inline!important}
        }
    </style>';
	echo '<nav class="nav-tab-wrapper" style="margin-bottom:20px">';
	echo '<a class="nav-tab' . ( ! in_array( $tab, array( 'abandonados', 'usuarios', 'precos', 'pontos', 'email', 'emails', 'shortcode', 'config' ), true ) ? ' nav-tab-active' : '' ) . '" href="' . esc_url( $base_url ) . '">Pedidos</a>';
	echo '<a class="nav-tab' . ( 'abandonados' === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( add_query_arg( 'aba', 'abandonados', $base_url ) ) . '">Abandonados</a>';
	echo '<a class="nav-tab' . ( 'usuarios' === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( add_query_arg( 'aba', 'usuarios', $base_url ) ) . '">Usuários e Código liberação</a>';
	echo '<a class="nav-tab' . ( 'precos' === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( add_query_arg( 'aba', 'precos', $base_url ) ) . '">Tabela de preços</a>';
	echo '<a class="nav-tab' . ( 'pontos' === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( add_query_arg( 'aba', 'pontos', $base_url ) ) . '">Pontos DTF UV</a>';
	echo '<a class="nav-tab' . ( 'email' === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( add_query_arg( 'aba', 'email', $base_url ) ) . '">Contato</a>';
	echo '<a class="nav-tab' . ( 'emails' === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( add_query_arg( 'aba', 'emails', $base_url ) ) . '">E-mails</a>';
	echo '<a class="nav-tab' . ( 'shortcode' === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( add_query_arg( 'aba', 'shortcode', $base_url ) ) . '">Shortcode</a>';
	echo '<a class="nav-tab' . ( 'config' === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( add_query_arg( 'aba', 'config', $base_url ) ) . '" style="' . ( '1' === get_option( 'pw_dtf_maintenance_mode' ) ? 'font-weight:700;color:#b91c1c' : '' ) . '">⚙ Config' . ( '1' === get_option( 'pw_dtf_maintenance_mode' ) ? ' 🔧' : '' ) . '</a>';
	echo '</nav>';

	if ( 'abandonados' === $tab ) {
		pw_dtf_render_admin_abandoned_calculations_page( true );
	} elseif ( 'usuarios' === $tab ) {
		pw_dtf_render_admin_users_page( true );
	} elseif ( 'precos' === $tab ) {
		pw_dtf_render_admin_prices_page( true );
	} elseif ( 'pontos' === $tab ) {
		pw_dtf_render_admin_points_page( true );
	} elseif ( 'email' === $tab ) {
		pw_dtf_render_admin_email_page( true );
	} elseif ( 'emails' === $tab ) {
		pw_dtf_render_admin_email_log_page( true );
	} elseif ( 'shortcode' === $tab ) {
		pw_dtf_render_admin_shortcode_page( true );
	} elseif ( 'config' === $tab ) {
		pw_dtf_render_admin_config_page( true );
	} else {
		pw_dtf_render_admin_orders_page( true );
	}

	pw_dtf_render_sortable_table_script();
	echo '</div>';
}

add_action( 'admin_init', 'pw_dtf_process_admin_actions' );

function pw_dtf_process_admin_actions() {
	if ( ! current_user_can( 'manage_options' ) || empty( $_POST['pw_dtf_admin_action'] ) ) {
		return;
	}

	$action = sanitize_key( wp_unslash( $_POST['pw_dtf_admin_action'] ) );

	if ( 'delete_selected_orders' === $action ) {
		check_admin_referer( 'pw_dtf_update_all_order_statuses' );
		$order_ids = isset( $_POST['delete_orders'] ) && is_array( $_POST['delete_orders'] ) ? array_map( 'absint', wp_unslash( $_POST['delete_orders'] ) ) : array();
		$deleted = 0;
		foreach ( $order_ids as $order_id ) {
			if ( $order_id && 'pw_dtf_order' === get_post_type( $order_id ) ) {
				wp_delete_post( $order_id, true );
				$deleted++;
			}
		}
		if ( $deleted > 0 ) {
			pw_dtf_admin_redirect( 'pw-printway-dtf-uv', 'order_deleted' );
		}
		pw_dtf_admin_redirect( 'pw-printway-dtf-uv', 'no_orders_selected' );
	}

	if ( 'delete_selected_email_logs' === $action ) {
		check_admin_referer( 'pw_dtf_delete_email_logs' );
		$log_ids = isset( $_POST['email_logs'] ) && is_array( $_POST['email_logs'] ) ? array_map( 'absint', wp_unslash( $_POST['email_logs'] ) ) : array();
		$deleted = 0;
		foreach ( $log_ids as $log_id ) {
			if ( $log_id && 'pw_dtf_email_log' === get_post_type( $log_id ) ) { wp_delete_post( $log_id, true ); $deleted++; }
		}
		pw_dtf_admin_redirect( 'pw-printway-dtf-uv', $deleted ? 'email_logs_deleted' : 'no_email_logs_selected', 'emails' );
	}

	if ( 'delete_selected_calculations' === $action ) {
		check_admin_referer( 'pw_dtf_delete_calculations' );
		$calculation_ids = isset( $_POST['calculations'] ) && is_array( $_POST['calculations'] ) ? array_map( 'absint', wp_unslash( $_POST['calculations'] ) ) : array();
		$deleted = 0;
		foreach ( $calculation_ids as $calculation_id ) {
			if ( $calculation_id && 'pw_dtf_calculation' === get_post_type( $calculation_id ) ) { wp_delete_post( $calculation_id, true ); $deleted++; }
		}
		pw_dtf_admin_redirect( 'pw-printway-dtf-uv', $deleted ? 'calculations_deleted' : 'no_calculations_selected', 'abandonados' );
	}

	if ( 'update_all_order_statuses' === $action ) {
		check_admin_referer( 'pw_dtf_update_all_order_statuses' );
		$statuses = isset( $_POST['order_statuses'] ) && is_array( $_POST['order_statuses'] )
			? wp_unslash( $_POST['order_statuses'] )
			: array();

		foreach ( $statuses as $order_id => $status ) {
			$order_id = absint( $order_id );
			$status   = sanitize_text_field( $status );

			if ( 'pw_dtf_order' === get_post_type( $order_id ) && in_array( $status, pw_dtf_order_statuses(), true ) ) {
				$previous_status = get_post_meta( $order_id, '_pw_dtf_status', true );
				update_post_meta( $order_id, '_pw_dtf_status', $status );
				if ( $previous_status !== $status ) { pw_dtf_send_status_email( $order_id, $status ); }
				if ( in_array( $status, array( 'Pagamento confirmado', 'Concluído' ), true ) ) {
					pw_dtf_award_points_for_confirmed_order( $order_id );
				}
			}
		}

		pw_dtf_admin_redirect( 'pw-printway-dtf-uv', 'status_updated' );
	}

	if ( 'update_order_status' === $action ) {
		check_admin_referer( 'pw_dtf_update_order_status' );
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$status   = isset( $_POST['order_status'] ) ? sanitize_text_field( wp_unslash( $_POST['order_status'] ) ) : '';

		if ( 'pw_dtf_order' === get_post_type( $order_id ) && in_array( $status, pw_dtf_order_statuses(), true ) ) {
			$previous_status = get_post_meta( $order_id, '_pw_dtf_status', true );
			update_post_meta( $order_id, '_pw_dtf_status', $status );
			if ( $previous_status !== $status ) { pw_dtf_send_status_email( $order_id, $status ); }
			if ( in_array( $status, array( 'Pagamento confirmado', 'Concluído' ), true ) ) {
				pw_dtf_award_points_for_confirmed_order( $order_id );
			}
			pw_dtf_admin_redirect( 'pw-printway-dtf-uv', 'status_updated' );
		}

		pw_dtf_admin_redirect( 'pw-printway-dtf-uv', 'invalid_order' );
	}

	if ( 'update_all_user_access' === $action ) {
		check_admin_referer( 'pw_dtf_update_all_user_access' );
		$roles_post = isset( $_POST['user_roles'] ) && is_array( $_POST['user_roles'] )
			? wp_unslash( $_POST['user_roles'] )
			: array();
		$codes_post = isset( $_POST['unlock_codes'] ) && is_array( $_POST['unlock_codes'] )
			? wp_unslash( $_POST['unlock_codes'] )
			: array();
		$roles = wp_roles()->roles;

		foreach ( $roles_post as $user_id => $role ) {
			$user_id = absint( $user_id );
			$role    = sanitize_key( $role );
			$code    = isset( $codes_post[ $user_id ] ) ? sanitize_text_field( $codes_post[ $user_id ] ) : '';
			$user    = get_user_by( 'id', $user_id );

			if ( ! $user || ! isset( $roles[ $role ] ) ) {
				continue;
			}

			if ( ! pw_dtf_set_user_unlock_code( $user_id, $code ) ) {
				pw_dtf_admin_redirect( 'pw-printway-dtf-uv', 'xml_write_failed', 'usuarios' );
			}

			if ( $user_id !== get_current_user_id() || 'administrator' === $role || ! current_user_can( 'administrator' ) ) {
				$user->set_role( $role );
				pw_dtf_sync_wp_role_to_pedidos( $user, $role );
			}

			$bypass_post = isset( $_POST['maintenance_bypass'] ) && is_array( $_POST['maintenance_bypass'] )
				? $_POST['maintenance_bypass']
				: array();
			if ( ! empty( $bypass_post[ $user_id ] ) ) {
				update_user_meta( $user_id, '_pw_dtf_maintenance_bypass', '1' );
			} else {
				delete_user_meta( $user_id, '_pw_dtf_maintenance_bypass' );
			}
		}

		pw_dtf_admin_redirect( 'pw-printway-dtf-uv', 'user_updated', 'usuarios' );
	}

	if ( 'update_user_access' === $action ) {
		check_admin_referer( 'pw_dtf_update_user_access' );
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$code    = isset( $_POST['unlock_code'] ) ? sanitize_text_field( wp_unslash( $_POST['unlock_code'] ) ) : '';
		$role    = isset( $_POST['user_role'] ) ? sanitize_key( wp_unslash( $_POST['user_role'] ) ) : '';
		$user    = get_user_by( 'id', $user_id );
		$roles   = wp_roles()->roles;

		if ( ! $user || ! isset( $roles[ $role ] ) ) {
			pw_dtf_admin_redirect( 'pw-printway-dtf-uv', 'invalid_user', 'usuarios' );
		}

		if ( $user_id === get_current_user_id() && 'administrator' !== $role && current_user_can( 'administrator' ) ) {
			pw_dtf_admin_redirect( 'pw-printway-dtf-uv', 'own_role_protected', 'usuarios' );
		}

		if ( ! pw_dtf_set_user_unlock_code( $user_id, $code ) ) {
			pw_dtf_admin_redirect( 'pw-printway-dtf-uv', 'xml_write_failed', 'usuarios' );
		}

		$user->set_role( $role );
		pw_dtf_sync_wp_role_to_pedidos( $user, $role );
		pw_dtf_admin_redirect( 'pw-printway-dtf-uv', 'user_updated', 'usuarios' );
	}

	if ( 'update_price_table' === $action ) {
		check_admin_referer( 'pw_dtf_update_price_table' );
		$rows = pw_dtf_sanitize_price_table_rows( isset( $_POST['price_rows'] ) ? wp_unslash( $_POST['price_rows'] ) : array() );

		if ( is_wp_error( $rows ) ) {
			pw_dtf_admin_redirect( 'pw-printway-dtf-uv', 'price_invalid', 'precos' );
		}

		if ( ! pw_dtf_save_unified_price_table( $rows ) ) {
			pw_dtf_admin_redirect( 'pw-printway-dtf-uv', 'price_write_failed', 'precos' );
		}

		update_option( 'pw_dtf_rounding_settings', array(
			'round_cm'    => ! empty( $_POST['round_cm'] ),
			'round_price' => ! empty( $_POST['round_price'] ),
		), false );

		pw_dtf_admin_redirect( 'pw-printway-dtf-uv', 'price_updated', 'precos' );
	}

	if ( 'update_points_settings' === $action ) {
		check_admin_referer( 'pw_dtf_update_points_settings' );
		$settings = array(
			'enabled'                => ! empty( $_POST['points_enabled'] ),
			'earning_reais'          => max( 0.01, (float) str_replace( ',', '.', (string) wp_unslash( $_POST['earning_reais'] ?? 1 ) ) ),
			'earning_points'         => max( 0.01, (float) str_replace( ',', '.', (string) wp_unslash( $_POST['earning_points'] ?? 1 ) ) ),
			'redemption_reais'       => max( 0.01, (float) str_replace( ',', '.', (string) wp_unslash( $_POST['redemption_reais'] ?? 1 ) ) ),
			'redemption_points'      => max( 0.01, (float) str_replace( ',', '.', (string) wp_unslash( $_POST['redemption_points'] ?? 1 ) ) ),
			'max_redemption_percent' => min( 100, max( 0, (int) wp_unslash( $_POST['max_redemption_percent'] ?? 0 ) ) ),
			'expiration_days'        => max( 0, absint( $_POST['expiration_days'] ?? 0 ) ),
		);
		update_option( 'pw_dtf_points_settings', $settings, false );
		pw_dtf_admin_redirect( 'pw-printway-dtf-uv', 'points_updated', 'pontos' );
	}

	if ( 'update_recipient_email' === $action ) {
		check_admin_referer( 'pw_dtf_update_recipient_email' );
		$email = sanitize_email( wp_unslash( $_POST['recipient_email'] ?? '' ) );
		if ( '' !== $email && ! is_email( $email ) ) {
			pw_dtf_admin_redirect( 'pw-printway-dtf-uv', 'email_invalid', 'email' );
		}
		update_option( 'pw_dtf_recipient_email', $email, false );
		update_option( 'pw_dtf_email_settings', array( 'notify_status_changes' => ! empty( $_POST['notify_status_changes'] ) ), false );
		update_option( 'pw_dtf_contact_settings', array(
			'address' => sanitize_textarea_field( wp_unslash( $_POST['company_address'] ?? '' ) ),
			'whatsapp' => sanitize_text_field( wp_unslash( $_POST['company_whatsapp'] ?? '' ) ),
			'phone' => sanitize_text_field( wp_unslash( $_POST['company_phone'] ?? '' ) ),
			'site' => esc_url_raw( wp_unslash( $_POST['company_site'] ?? '' ) ),
			'instagram' => sanitize_text_field( wp_unslash( $_POST['company_instagram'] ?? '' ) ),
		), false );
		pw_dtf_admin_redirect( 'pw-printway-dtf-uv', 'email_updated', 'email' );
	}

	if ( 'save_maintenance_mode' === $action ) {
		check_admin_referer( 'pw_dtf_save_maintenance_mode' );
		$enabled   = ! empty( $_POST['maintenance_mode'] );
		$until_raw = isset( $_POST['maintenance_until'] ) ? sanitize_text_field( wp_unslash( $_POST['maintenance_until'] ) ) : '';

		$until = 0;
		if ( '' !== $until_raw ) {
			$tz = wp_timezone();
			$dt = DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i', $until_raw, $tz );
			if ( $dt ) {
				$until = $dt->getTimestamp();
			}
		}

		/* Se manutenção está ativa mas a previsão já passou, desativa automaticamente. */
		if ( $enabled && $until > 0 && time() >= $until ) {
			$enabled = false;
			$until   = 0;
		}

		update_option( 'pw_dtf_maintenance_mode', $enabled ? '1' : '0', false );
		update_option( 'pw_dtf_maintenance_until', $until, false );

		pw_dtf_admin_redirect( 'pw-printway-dtf-uv', $enabled ? 'maintenance_mode_on' : 'maintenance_mode_off', 'config' );
	}
}

function pw_dtf_admin_redirect( $page, $notice, $tab = '' ) {
	$args = array( 'page' => $page, 'pw_dtf_notice' => $notice );
	if ( '' !== $tab ) {
		$args['aba'] = $tab;
	}
	wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
	exit;
}

function pw_dtf_order_statuses() {
	return array(
		'Enviado para análise',
		'Aguardando pagamento',
		'Pagamento confirmado',
		'Em produção',
		'Pronto para retirada',
		'Saiu para entrega',
		'Concluído',
		'Cancelado',
	);
}

function pw_dtf_render_admin_notice() {
	if ( empty( $_GET['pw_dtf_notice'] ) ) {
		return;
	}

	$notice = sanitize_key( wp_unslash( $_GET['pw_dtf_notice'] ) );
	$messages = array(
		'status_updated'     => array( 'success', 'Status do pedido atualizado.' ),
		'order_deleted'      => array( 'success', 'Pedido excluído permanentemente.' ),
		'no_orders_selected' => array( 'error', 'Selecione ao menos um pedido para excluir.' ),
		'email_logs_deleted' => array( 'success', 'Registros de e-mail excluídos.' ),
		'calculations_deleted' => array( 'success', 'Registros abandonados excluídos.' ),
		'no_calculations_selected' => array( 'error', 'Selecione ao menos um cálculo para excluir.' ),
		'no_email_logs_selected' => array( 'error', 'Selecione ao menos um registro de e-mail para apagar.' ),
		'user_updated'       => array( 'success', 'Usuário, função e código atualizados.' ),
		'xml_write_failed'   => array( 'error', 'Não foi possível gravar o arquivo validacao_pagar_depois.xml. Verifique a permissão de escrita da pasta dtfuv.' ),
		'own_role_protected' => array( 'error', 'Por segurança, não é permitido remover sua própria função de administrador nesta tela.' ),
		'invalid_user'       => array( 'error', 'Usuário ou função inválidos.' ),
		'invalid_order'      => array( 'error', 'Pedido ou status inválido.' ),
		'price_updated'      => array( 'success', 'Tabela de preços atualizada.' ),
		'price_invalid'      => array( 'error', 'Informe pelo menos uma faixa válida para Cliente direto e Revenda.' ),
		'price_write_failed' => array( 'error', 'Não foi possível gravar o arquivo precos.xml. Verifique a permissão de escrita da pasta dtfuv.' ),
		'points_updated'     => array( 'success', 'Configurações de pontos DTF UV atualizadas.' ),
		'email_updated'           => array( 'success', 'E-mail de recebimento DTF UV atualizado.' ),
		'email_invalid'           => array( 'error', 'Informe um e-mail válido ou deixe o campo vazio para usar o e-mail padrão do WordPress.' ),
		'maintenance_mode_on'     => array( 'success', '🔧 Modo manutenção ATIVADO. A calculadora está bloqueada para clientes.' ),
		'maintenance_mode_off'    => array( 'success', '✅ Modo manutenção DESATIVADO. A calculadora está funcionando normalmente.' ),
	);

	if ( isset( $messages[ $notice ] ) ) {
		echo '<div class="notice notice-' . esc_attr( $messages[ $notice ][0] ) . ' is-dismissible"><p>' . esc_html( $messages[ $notice ][1] ) . '</p></div>';
	}
}

function pw_dtf_render_admin_config_page( $embedded = false ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$maintenance = '1' === get_option( 'pw_dtf_maintenance_mode' );
	$until       = (int) get_option( 'pw_dtf_maintenance_until', 0 );

	/* Auto-desativação server-side ao abrir a tela. */
	if ( $maintenance && $until > 0 && time() >= $until ) {
		$maintenance = false;
		update_option( 'pw_dtf_maintenance_mode', '0', false );
		update_option( 'pw_dtf_maintenance_until', 0, false );
		$until = 0;
	}

	/* Valor para o campo datetime-local (timezone do WordPress). */
	$until_input = $until > 0 ? wp_date( 'Y-m-d\TH:i', $until ) : '';

	/* Tempo restante em texto (para exibição estática na tela admin). */
	$remaining_text = '';
	if ( $maintenance && $until > 0 ) {
		$diff = $until - time();
		if ( $diff > 0 ) {
			$d = floor( $diff / 86400 );
			$h = floor( ( $diff % 86400 ) / 3600 );
			$m = floor( ( $diff % 3600 ) / 60 );
			$parts = array();
			if ( $d > 0 ) { $parts[] = $d . ( 1 === $d ? ' dia' : ' dias' ); }
			if ( $h > 0 ) { $parts[] = $h . ( 1 === $h ? ' hora' : ' horas' ); }
			if ( $m > 0 || $h > 0 || $d > 0 ) { $parts[] = $m . ( 1 === $m ? ' minuto' : ' minutos' ); }
			$remaining_text = implode( ', ', $parts );
		}
	}

	$card_border = $maintenance ? '2px solid #b91c1c' : '1px solid #ccd0d4';
	$card_bg     = $maintenance ? '#fff5f5' : '#fff';

	echo '<div style="max-width:700px">';
	echo '<h2 style="margin:0 0 6px">Configurações da Calculadora DTF UV</h2>';
	echo '<p style="color:#50575e;margin:0 0 24px">Controle o acesso à página <code>/calcular_dtf_uv/</code>.</p>';

	/* ── Cartão principal ── */
	echo '<div style="background:' . esc_attr( $card_bg ) . ';border:' . esc_attr( $card_border ) . ';border-radius:8px;padding:24px 28px;margin:0 0 20px">';

	echo '<div style="display:flex;align-items:center;gap:12px;margin:0 0 16px">';
	echo '<span style="font-size:30px" role="img" aria-label="manutenção">🔧</span>';
	echo '<div>';
	echo '<strong style="font-size:15px;display:block;color:' . ( $maintenance ? '#b91c1c' : '#1d2327' ) . '">Modo manutenção</strong>';
	echo '<span style="font-size:13px;color:#50575e">Quando ativado, somente <b>Administradores</b> conseguem acessar a calculadora. Clientes veem tela de manutenção.</span>';
	echo '</div></div>';

	/* Status atual */
	if ( $maintenance ) {
		echo '<div style="display:inline-flex;align-items:center;gap:6px;background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5;border-radius:6px;padding:6px 14px;font-size:13px;font-weight:600;margin:0 0 20px">● ATIVADO — calculadora bloqueada para clientes</div>';
		if ( $remaining_text ) {
			echo '<div style="display:flex;align-items:center;gap:8px;background:#fff7ed;border:1px solid #fed7aa;border-radius:6px;padding:8px 14px;font-size:13px;color:#9a3412;margin:-12px 0 20px">';
			echo '<span style="font-size:16px">⏱</span><span>Tempo restante: <strong>' . esc_html( $remaining_text ) . '</strong></span>';
			echo '</div>';
		}
	} else {
		echo '<div style="display:inline-flex;align-items:center;gap:6px;background:#dcfce7;color:#166534;border:1px solid #86efac;border-radius:6px;padding:6px 14px;font-size:13px;font-weight:600;margin:0 0 20px">● DESATIVADO — calculadora funcionando normalmente</div>';
	}

	/* ── Formulário único ── */
	echo '<form method="post">';
	wp_nonce_field( 'pw_dtf_save_maintenance_mode' );
	echo '<input type="hidden" name="pw_dtf_admin_action" value="save_maintenance_mode">';

	/* Checkbox ativar/desativar */
	echo '<table class="form-table" style="margin:0">';
	echo '<tr>';
	echo '<th scope="row" style="width:200px;padding:8px 10px 8px 0;vertical-align:top"><label for="pw-maint-toggle" style="font-weight:600">Manutenção ativa</label></th>';
	echo '<td style="padding:4px 0">';
	echo '<label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer">';
	echo '<input type="checkbox" id="pw-maint-toggle" name="maintenance_mode" value="1"' . ( $maintenance ? ' checked' : '' ) . ' style="width:18px;height:18px">';
	echo '<span style="font-size:13px">Ativar bloqueio para clientes e visitantes</span>';
	echo '</label>';
	echo '</td></tr>';

	/* Campo de previsão de retorno */
	echo '<tr>';
	echo '<th scope="row" style="padding:12px 10px 8px 0;vertical-align:top"><label for="pw-maint-until" style="font-weight:600">Previsão de retorno</label></th>';
	echo '<td style="padding:8px 0">';
	echo '<input type="datetime-local" id="pw-maint-until" name="maintenance_until" value="' . esc_attr( $until_input ) . '" style="font-size:14px;padding:5px 8px">';
	echo '<p class="description" style="margin:6px 0 0;font-size:12px;color:#50575e">Opcional. Se definida, a manutenção será encerrada automaticamente nessa data e hora (fuso horário do site). ';
	echo 'Enquanto o cliente estiver na tela de manutenção, um contador regressivo será exibido e a calculadora abrirá sozinha ao chegar no horário.</p>';
	if ( $maintenance && $until > 0 ) {
		echo '<p style="margin:8px 0 0;font-size:12px;color:#9a3412"><strong>Programado para:</strong> ' . esc_html( wp_date( 'd/m/Y \à\s H:i', $until ) ) . '</p>';
	}
	echo '</td></tr>';
	echo '</table>';

	echo '<div style="margin:18px 0 0;display:flex;align-items:center;gap:14px;flex-wrap:wrap">';
	echo '<button type="submit" class="button button-primary" style="font-size:14px;height:36px;padding:0 22px">💾 Salvar configurações</button>';
	echo '<a href="' . esc_url( site_url( '/calcular_dtf_uv/' ) ) . '" target="_blank" style="font-size:13px;color:#2271b1">↗ Ver página da calculadora</a>';
	echo '</div>';
	echo '</form>';

	echo '</div>'; /* /card */

	/* ── Dica ── */
	echo '<div style="background:#f0f6fc;border-left:4px solid #2271b1;padding:12px 16px;border-radius:0 6px 6px 0;font-size:13px;color:#1d2327">';
	echo '<strong>Como funciona:</strong> ao ativar, o shortcode <code>[printway_dtf_uv]</code> exibe a tela de manutenção para clientes. ';
	echo 'Administradores sempre veem a calculadora normalmente (para testar). ';
	echo 'Com a <em>previsão de retorno</em> definida, o cliente vê um contador regressivo que se atualiza a cada segundo, ';
	echo 'e quando o horário chega a calculadora abre automaticamente para ele, mesmo que você esqueça de desativar.';
	echo '</div>';

	echo '</div>'; /* /max-width */
}

function pw_dtf_render_admin_orders_page( $embedded = false ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$orders = get_posts(
		array(
			'post_type'      => 'pw_dtf_order',
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);
	$date_mode = isset( $_GET['date_mode'] ) && 'period' === sanitize_key( wp_unslash( $_GET['date_mode'] ) ) ? 'period' : 'all';
	$date_start = isset( $_GET['date_start'] ) ? sanitize_text_field( wp_unslash( $_GET['date_start'] ) ) : '';
	$date_end = isset( $_GET['date_end'] ) ? sanitize_text_field( wp_unslash( $_GET['date_end'] ) ) : '';
	$date_start = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_start ) ? $date_start : '';
	$date_end = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_end ) ? $date_end : '';

	if ( 'period' === $date_mode ) {
		$orders = array_values(
			array_filter(
				$orders,
				function ( $order ) use ( $date_start, $date_end ) {
					$order_date = substr( $order->post_date, 0, 10 );

					if ( $date_start && $order_date < $date_start ) {
						return false;
					}

					if ( $date_end && $order_date > $date_end ) {
						return false;
					}

					return true;
				}
			)
		);
	}

	$status_options = pw_dtf_order_statuses();
	$available_statuses = array();

	foreach ( $orders as $order ) {
		$status = get_post_meta( $order->ID, '_pw_dtf_status', true );
		$status = $status ? $status : 'Enviado para análise';
		$available_statuses[ $status ] = true;
	}

	$status_options = array_values(
		array_filter(
			$status_options,
			function ( $status ) use ( $available_statuses ) {
				return isset( $available_statuses[ $status ] );
			}
		)
	);
	$selected_statuses = array();

	if ( isset( $_GET['pw_dtf_filters'] ) ) {
		$requested_statuses = isset( $_GET['status_filters'] ) && is_array( $_GET['status_filters'] )
			? wp_unslash( $_GET['status_filters'] )
			: array();

		foreach ( $requested_statuses as $requested_status ) {
			$requested_status = sanitize_text_field( $requested_status );
			if ( in_array( $requested_status, $status_options, true ) ) {
				$selected_statuses[] = $requested_status;
			}
		}
	} else {
		$selected_statuses = array_values(
			array_filter(
				$status_options,
				function ( $status ) {
					return 'Concluído' !== $status;
				}
			)
		);
	}

	$orders = array_values(
		array_filter(
			$orders,
			function ( $order ) use ( $selected_statuses ) {
				$status = get_post_meta( $order->ID, '_pw_dtf_status', true );
				$status = $status ? $status : 'Enviado para análise';

				return in_array( $status, $selected_statuses, true );
			}
		)
	);

	$total_amount = 0.0;
	$total_linear_meters = 0.0;

	foreach ( $orders as $order ) {
		$total_amount += (float) get_post_meta( $order->ID, '_pw_dtf_amount', true );
		$total_linear_meters += (float) get_post_meta( $order->ID, '_pw_dtf_height', true ) / 100;
	}

	$logo_url = '';
	$custom_logo_id = (int) get_theme_mod( 'custom_logo' );
	if ( $custom_logo_id ) {
		$logo_url = wp_get_attachment_image_url( $custom_logo_id, 'full' );
	}
	$period_label = 'all' === $date_mode
		? 'Todas as datas'
		: ( $date_start ? wp_date( 'd/m/Y', strtotime( $date_start ) ) : 'Início não informado' ) . ' até ' . ( $date_end ? wp_date( 'd/m/Y', strtotime( $date_end ) ) : 'Hoje' );
	$status_label = ! empty( $selected_statuses ) ? implode( ', ', $selected_statuses ) : 'Nenhum status selecionado';
	$print_requested = isset( $_GET['pw_dtf_generate_pdf'] );

	if ( ! $embedded ) {
		echo '<div class="wrap"><h1>PrintWay - DTF UV</h1>';
	}
	pw_dtf_render_admin_notice();
	echo '<h2>Pedidos de DTF UV</h2>';
	echo '<div class="pw-dtf-print-header">';
	if ( $logo_url ) {
		echo '<img class="pw-dtf-print-logo" src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '">';
	} else {
		echo '<strong>' . esc_html( get_bloginfo( 'name' ) ) . '</strong>';
	}
	echo '<h1>Relatório de pedidos DTF UV</h1>';
	echo '<p><strong>Período:</strong> ' . esc_html( $period_label ) . '</p>';
	echo '<p><strong>Status incluídos:</strong> ' . esc_html( $status_label ) . '</p>';
	echo '<p>Gerado em ' . esc_html( wp_date( 'd/m/Y H:i' ) ) . '</p>';
	echo '</div>';
	echo '<form id="pw-dtf-filter-form" method="get" class="pw-dtf-filters">';
	echo '<input type="hidden" name="page" value="pw-printway-dtf-uv">';
	echo '<input type="hidden" name="aba" value="pedidos">';
	echo '<input type="hidden" name="pw_dtf_filters" value="1">';
	echo '<div class="pw-dtf-date-filter">';
	echo '<strong>Datas:</strong>';
	echo '<label><input type="radio" name="date_mode" value="all"' . checked( 'all', $date_mode, false ) . '> Todas as datas</label>';
	echo '<label><input type="radio" name="date_mode" value="period"' . checked( 'period', $date_mode, false ) . '> Por período</label>';
	echo '<label>Data inicial <input id="pw-dtf-date-start" type="date" name="date_start" value="' . esc_attr( $date_start ) . '"' . disabled( 'period' !== $date_mode, true, false ) . '></label>';
	echo '<label>Data final <input id="pw-dtf-date-end" type="date" name="date_end" value="' . esc_attr( $date_end ) . '"' . disabled( 'period' !== $date_mode, true, false ) . '></label>';
	echo '<button type="submit" class="button">Aplicar filtros</button>';
	echo '</div>';
	echo '<div class="pw-dtf-status-filter"><strong>Mostrar:</strong>';
	foreach ( $status_options as $status_option ) {
		$filter_id = 'pw-dtf-status-' . sanitize_title( $status_option );
		echo '<label for="' . esc_attr( $filter_id ) . '"><input id="' . esc_attr( $filter_id ) . '" type="checkbox" name="status_filters[]" value="' . esc_attr( $status_option ) . '"' . checked( in_array( $status_option, $selected_statuses, true ), true, false ) . ' onchange="this.form.submit()"> ' . esc_html( $status_option ) . '</label>';
	}
	echo '</div></form>';
	echo '<script>document.addEventListener("DOMContentLoaded",function(){var radios=document.querySelectorAll("input[name=\\"date_mode\\"]"),start=document.getElementById("pw-dtf-date-start"),end=document.getElementById("pw-dtf-date-end");function pad(value){return String(value).padStart(2,"0");}function fillCurrentMonth(){var now=new Date(),today=now.getFullYear()+"-"+pad(now.getMonth()+1)+"-"+pad(now.getDate()),first=now.getFullYear()+"-"+pad(now.getMonth()+1)+"-01";if(start&&!start.value){start.value=first;}if(end&&!end.value){end.value=today;}}function updateDates(){var period=document.querySelector("input[name=\\"date_mode\\"]:checked"),enabled=period&&period.value==="period";if(enabled){fillCurrentMonth();}if(start){start.disabled=!enabled;}if(end){end.disabled=!enabled;}}radios.forEach(function(radio){radio.addEventListener("change",updateDates);});updateDates();});</script>';
	echo '<div class="pw-dtf-order-summary"><strong>Pedidos listados:</strong> ' . esc_html( count( $orders ) ) . ' &nbsp;|&nbsp; <strong>Total:</strong> ' . esc_html( wp_strip_all_tags( wc_price( $total_amount ) ) ) . ' &nbsp;|&nbsp; <strong>Impressão:</strong> ' . esc_html( number_format_i18n( $total_linear_meters, 2 ) ) . ' m lineares (largura de ' . PW_DTF_PRINT_WIDTH_CM . ' cm)</div>';
	echo '<div class="pw-dtf-actions">';
	echo '<button type="submit" form="pw-dtf-filter-form" name="pw_dtf_generate_pdf" value="1" class="button button-secondary"><span class="dashicons dashicons-media-document"></span> Gerar PDF</button>';
	if ( ! empty( $orders ) ) {
		echo '<button type="submit" form="pw-dtf-status-form" class="button button-primary pw-dtf-save-all"><span class="dashicons dashicons-saved"></span>Salvar todos os status</button>';
		echo '<button type="submit" form="pw-dtf-status-form" class="button button-link-delete" onclick="document.getElementById(\'pw-dtf-admin-action\').value=\'delete_selected_orders\';return window.confirm(\'Excluir permanentemente todos os pedidos selecionados? Esta ação não poderá ser desfeita.\');"><span class="dashicons dashicons-trash"></span> Excluir selecionados</button>';
	}
	echo '</div>';
	if ( $print_requested ) {
		echo '<script>window.addEventListener("load",function(){window.print();});</script>';
	}

	if ( empty( $orders ) ) {
		echo '<p>Nenhum pedido de DTF UV foi registrado.</p>';
		if ( ! $embedded ) {
			echo '</div>';
		}
		return;
	}

	echo '<form id="pw-dtf-status-form" method="post">';
	wp_nonce_field( 'pw_dtf_update_all_order_statuses' );
	echo '<input id="pw-dtf-admin-action" type="hidden" name="pw_dtf_admin_action" value="update_all_order_statuses">';
	echo '<div class="pw-dtf-orders-wrap"><table class="widefat fixed striped pw-dtf-orders-table"><thead><tr><th data-pw-no-sort><input type="checkbox" id="pw-dtf-select-all" aria-label="Selecionar todos os pedidos"></th><th>Pedido</th><th>Usuário</th><th>Cliente</th><th>Data</th><th>Medida</th><th>Valor</th><th>Pagamento</th><th>Status</th></tr></thead><tbody>';

	foreach ( $orders as $order ) {
		$user      = get_user_by( 'id', (int) $order->post_author );
		$reference = get_post_meta( $order->ID, '_pw_dtf_reference', true );
		$amount    = (float) get_post_meta( $order->ID, '_pw_dtf_amount', true );
		$height    = (float) get_post_meta( $order->ID, '_pw_dtf_height', true );
		$width     = (float) get_post_meta( $order->ID, '_pw_dtf_width', true );
		$width     = $width > 0 ? $width : PW_DTF_PRINT_WIDTH_CM;
		$payment   = get_post_meta( $order->ID, '_pw_dtf_payment_label', true );
		$status    = get_post_meta( $order->ID, '_pw_dtf_status', true );

		echo '<tr><td><input type="checkbox" class="pw-dtf-order-select" name="delete_orders[]" value="' . esc_attr( $order->ID ) . '" aria-label="Selecionar pedido ' . esc_attr( $reference ) . '"></td><td>' . esc_html( $reference ) . '</td>';
		echo '<td>' . esc_html( $user ? $user->user_login . ' (#' . $user->ID . ')' : ( (int) $order->post_author > 0 ? 'Usuário removido' : 'Visitante' ) ) . '</td>';
		echo '<td>' . esc_html( get_post_meta( $order->ID, '_pw_dtf_name', true ) ) . '</td>';
		echo '<td>' . esc_html( pw_dtf_format_order_date( $order ) ) . '</td>';
		echo '<td>' . esc_html( number_format_i18n( $width, 2 ) . ' cm × ' . number_format_i18n( $height, 2 ) . ' cm' ) . '</td>';
		echo '<td>' . esc_html( wp_strip_all_tags( wc_price( $amount ) ) ) . '</td>';
		echo '<td>' . esc_html( $payment ) . '</td><td><span class="pw-dtf-print-status">' . esc_html( $status ? $status : 'Enviado para análise' ) . '</span>';
		echo '<select name="order_statuses[' . esc_attr( $order->ID ) . ']" title="Alterar status do pedido" aria-label="Alterar status do pedido">';
		foreach ( pw_dtf_order_statuses() as $option ) {
			echo '<option value="' . esc_attr( $option ) . '"' . selected( $status, $option, false ) . '>' . esc_html( $option ) . '</option>';
		}
		echo '</select></td></tr>';
	}

	echo '</tbody></table></div></form><script>document.addEventListener("DOMContentLoaded",function(){var all=document.getElementById("pw-dtf-select-all");if(!all){return;}all.addEventListener("change",function(){document.querySelectorAll(".pw-dtf-order-select").forEach(function(box){box.checked=all.checked;});});});</script>';
	if ( ! $embedded ) {
		echo '</div>';
	}
}

function pw_dtf_render_admin_abandoned_calculations_page( $embedded = false ) {
	$calculations = get_posts( array( 'post_type' => 'pw_dtf_calculation', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'DESC' ) );
	$calculations = array_filter( $calculations, function( $calculation ) { return 'yes' !== get_post_meta( $calculation->ID, '_pw_dtf_calculation_completed', true ); } );
	if ( ! $embedded ) { echo '<div class="wrap"><h1>Cálculos abandonados</h1>'; }
	pw_dtf_render_admin_notice();
	echo '<h2>Cálculos abandonados</h2><p>Esta listagem mostra somente quem enviou um PDF ou usou a calculadora de medidas, mas não concluiu o pedido até o envio final. Pedidos concluídos não aparecem aqui.</p>';
	if ( empty( $calculations ) ) { echo '<p>Nenhum cálculo abandonado encontrado.</p>'; if ( ! $embedded ) { echo '</div>'; } return; }
	echo '<form method="post"><input type="hidden" name="pw_dtf_admin_action" value="delete_selected_calculations">'; wp_nonce_field( 'pw_dtf_delete_calculations' );
	echo '<p><button type="button" class="button" onclick="document.querySelectorAll(\'.pw-dtf-calculation-check\').forEach(function(c){c.checked=true;})">Selecionar todos</button> <button class="button button-secondary" type="submit" onclick="return confirm(\'Apagar os cálculos selecionados?\')">Apagar selecionados</button></p>';
	echo '<div class="pw-dtf-orders-wrap"><table class="widefat striped pw-dtf-sortable-table"><thead><tr><th data-pw-no-sort><input type="checkbox" onchange="document.querySelectorAll(\'.pw-dtf-calculation-check\').forEach(function(c){c.checked=event.target.checked;})"></th><th>Data</th><th>Origem</th><th>Usuário</th><th>Cliente</th><th>WhatsApp</th><th>E-mail</th><th>Medida</th><th>Valor</th><th>Etapa alcançada</th><th>QR Pix</th><th>Comprovante</th></tr></thead><tbody>';
	foreach ( $calculations as $calculation ) {
		$id = $calculation->ID; $user = get_userdata( (int) $calculation->post_author );
		$source = 'pdf' === get_post_meta( $id, '_pw_dtf_calc_source', true ) ? 'PDF enviado' : 'Calculadora de medidas';
		$height = (float) get_post_meta( $id, '_pw_dtf_calc_height', true ); $amount = (float) get_post_meta( $id, '_pw_dtf_calc_amount', true );
		$step = max( 1, min( 4, (int) get_post_meta( $id, '_pw_dtf_calc_last_step', true ) ) );
		$qr = 'yes' === get_post_meta( $id, '_pw_dtf_calc_pix_qr_generated', true ) ? 'Gerado' : 'Não gerado';
		$proof = 'yes' === get_post_meta( $id, '_pw_dtf_calc_proof_uploaded', true ) ? 'Enviado' : 'Não enviado';
		echo '<tr><td><input class="pw-dtf-calculation-check" type="checkbox" name="calculations[]" value="' . esc_attr( $id ) . '"></td><td>' . esc_html( pw_dtf_format_order_date( $calculation ) ) . '</td><td>' . esc_html( $source ) . '</td><td>' . esc_html( $user ? $user->user_login . ' (#' . $user->ID . ')' : 'Visitante' ) . '</td><td>' . esc_html( get_post_meta( $id, '_pw_dtf_calc_name', true ) ?: 'Não informado' ) . '</td><td>' . esc_html( get_post_meta( $id, '_pw_dtf_calc_whatsapp', true ) ?: 'Não informado' ) . '</td><td>' . esc_html( get_post_meta( $id, '_pw_dtf_calc_email', true ) ?: 'Não informado' ) . '</td><td>28,00 cm × ' . esc_html( number_format_i18n( $height, 2 ) ) . ' cm</td><td>' . wp_kses_post( wc_price( $amount ) ) . '</td><td>Etapa ' . esc_html( $step ) . ' de 4</td><td>' . esc_html( $qr ) . '</td><td>' . esc_html( $proof ) . '</td></tr>';
	}
	echo '</tbody></table></div></form>';
	if ( ! $embedded ) { echo '</div>'; }
}

function pw_dtf_render_admin_email_log_page( $embedded = false ) {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$logs = get_posts( array( 'post_type' => 'pw_dtf_email_log', 'post_status' => 'publish', 'posts_per_page' => 200, 'orderby' => 'date', 'order' => 'DESC' ) );
	if ( ! $embedded ) { echo '<div class="wrap"><h1>PrintWay - DTF UV</h1>'; }
	echo '<h2>E-mails enviados pelo DTF UV</h2><p>“Aceito pelo WordPress” significa que o WordPress encaminhou o e-mail ao serviço configurado. A confirmação de entrega depende de um serviço SMTP com rastreamento.</p>';
	if ( empty( $logs ) ) { echo '<p>Nenhum e-mail foi registrado ainda.</p>'; }
	else {
		echo '<form id="pw-dtf-email-log-form" method="post">';
		wp_nonce_field( 'pw_dtf_delete_email_logs' );
		echo '<input type="hidden" name="pw_dtf_admin_action" value="delete_selected_email_logs">';
		echo '<p><button type="submit" class="button button-link-delete" onclick="return window.confirm(\'Apagar permanentemente os registros selecionados?\');"><span class="dashicons dashicons-trash"></span> Apagar selecionados</button></p>';
		echo '<div class="pw-dtf-orders-wrap"><table class="widefat striped pw-dtf-sortable-table"><thead><tr><th data-pw-no-sort><input type="checkbox" id="pw-dtf-email-select-all" aria-label="Selecionar todos os e-mails"></th><th>Data</th><th>Tipo</th><th>Destinatário</th><th>Assunto</th><th>Pedido</th><th>Resultado</th></tr></thead><tbody>';
		foreach ( $logs as $log ) {
			$order_id = (int) get_post_meta( $log->ID, '_pw_dtf_email_order_id', true );
			$reference = $order_id ? get_post_meta( $order_id, '_pw_dtf_reference', true ) : '';
			$sent = 'yes' === get_post_meta( $log->ID, '_pw_dtf_email_sent', true );
			$note = get_post_meta( $log->ID, '_pw_dtf_email_delivery_note', true );
			$result = $sent
				? '<span style="color:#008a20;font-weight:700" title="' . esc_attr( $note ? $note : 'A entrega final depende do servidor SMTP.' ) . '">Aceito pelo WordPress</span><br><small>Entrega não confirmada</small>'
				: '<span style="color:#b32d2e;font-weight:700">Falhou antes do envio</span>';
			echo '<tr><td><input type="checkbox" class="pw-dtf-email-select" name="email_logs[]" value="' . esc_attr( $log->ID ) . '" aria-label="Selecionar registro de e-mail"></td><td>' . esc_html( pw_dtf_format_order_date( $log ) ) . '</td><td>' . esc_html( get_post_meta( $log->ID, '_pw_dtf_email_type', true ) ) . '</td><td>' . esc_html( get_post_meta( $log->ID, '_pw_dtf_email_recipient', true ) ) . '</td><td>' . esc_html( $log->post_title ) . '</td><td>' . esc_html( $reference ? $reference : '—' ) . '</td><td>' . $result . '</td></tr>';
		}
		echo '</tbody></table></div></form><script>document.addEventListener("DOMContentLoaded",function(){var all=document.getElementById("pw-dtf-email-select-all");if(!all){return;}all.addEventListener("change",function(){document.querySelectorAll(".pw-dtf-email-select").forEach(function(box){box.checked=all.checked;});});});</script>';
	}
	if ( ! $embedded ) { echo '</div>'; }
}

function pw_dtf_render_admin_email_page( $embedded = false ) {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$custom = get_option( 'pw_dtf_recipient_email', '' );
	$settings = pw_dtf_get_email_settings();
	$contact = pw_dtf_get_company_contact_details();
	if ( ! $embedded ) { echo '<div class="wrap"><h1>PrintWay - DTF UV</h1>'; }
	pw_dtf_render_admin_notice();
	echo '<h2>Contato e e-mails</h2><p>Estas informações aparecem somente nos e-mails do DTF UV. Os dados iniciais são trazidos do WooCommerce quando disponíveis.</p>';
	echo '<form method="post" class="pw-dtf-points-settings">';
	wp_nonce_field( 'pw_dtf_update_recipient_email' );
	echo '<input type="hidden" name="pw_dtf_admin_action" value="update_recipient_email">';
	echo '<p><label for="pw-dtf-recipient-email"><strong>E-mail para receber pedidos DTF UV</strong></label><br><input id="pw-dtf-recipient-email" type="email" class="regular-text" name="recipient_email" value="' . esc_attr( $custom ) . '" placeholder="' . esc_attr( get_option( 'admin_email' ) ) . '"></p>';
	echo '<p><label><strong>Endereço da empresa</strong><br><textarea class="large-text" rows="2" name="company_address">' . esc_textarea( $contact['address'] ) . '</textarea></label></p>';
	echo '<p><label><strong>WhatsApp</strong><br><input type="text" class="regular-text" name="company_whatsapp" value="' . esc_attr( $contact['whatsapp_label'] ) . '"></label></p>';
	echo '<p><label><strong>Telefone</strong><br><input type="text" class="regular-text" name="company_phone" value="' . esc_attr( $contact['phone'] ) . '"></label></p>';
	echo '<p><label><strong>Site</strong><br><input type="url" class="regular-text" name="company_site" value="' . esc_attr( $contact['site'] ) . '"></label></p>';
	echo '<p><label><strong>Instagram</strong><br><input type="text" class="regular-text" name="company_instagram" value="' . esc_attr( $contact['instagram'] ) . '" placeholder="@seuperfil ou URL"></label></p>';
	echo '<p><label><input type="checkbox" name="notify_status_changes" value="1"' . checked( ! empty( $settings['notify_status_changes'] ), true, false ) . '> <strong>Enviar e-mail para o cliente a cada alteração de status</strong></label><br><span class="description">O cliente receberá uma atualização profissional com o status e os dados do pedido.</span></p>';
	echo '<p><button type="submit" class="button button-primary">Salvar</button></p></form>';
	if ( ! $embedded ) { echo '</div>'; }
}

function pw_dtf_render_admin_points_page( $embedded = false ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings = pw_dtf_get_points_settings();
	if ( ! $embedded ) {
		echo '<div class="wrap"><h1>PrintWay - DTF UV</h1>';
	}
	pw_dtf_render_admin_notice();
	echo '<h2>Pontos DTF UV</h2>';
	echo '<p>Estas regras são aplicadas somente aos pedidos DTF UV. O saldo dos clientes é integrado ao plugin <strong>Points and Rewards for WooCommerce</strong>.</p>';
	echo '<form method="post" class="pw-dtf-points-settings">';
	wp_nonce_field( 'pw_dtf_update_points_settings' );
	echo '<input type="hidden" name="pw_dtf_admin_action" value="update_points_settings">';
	echo '<label><input type="checkbox" name="points_enabled" value="1"' . checked( ! empty( $settings['enabled'] ), true, false ) . '> <strong>Ativar programa de pontos para compras DTF UV</strong></label>';
	echo '<p class="pw-dtf-points-help">Desmarque esta opção para não exibir pontos nem permitir ganho ou troca de pontos na calculadora DTF UV.</p>';
	echo '<div class="pw-dtf-setting-row"><div><strong>Pontos gerados para o cliente</strong><p class="pw-dtf-points-help">Defina quantos pontos o cliente ganhará a cada valor pago no pedido.</p></div><div class="pw-dtf-equivalence" style="display:flex;gap:8px;align-items:center"><span>R$</span><input type="number" name="earning_reais" min="0.01" step="0.01" value="' . esc_attr( $settings['earning_reais'] ) . '"><span>=</span><input type="number" name="earning_points" min="0.01" step="0.01" value="' . esc_attr( $settings['earning_points'] ) . '"><span>pontos</span></div></div>';
	echo '<div class="pw-dtf-setting-row"><div><strong>Uso de pontos como desconto</strong><p class="pw-dtf-points-help">Defina quantos pontos são necessários para descontar um valor em reais do pedido.</p></div><div class="pw-dtf-equivalence" style="display:flex;gap:8px;align-items:center"><input type="number" name="redemption_points" min="0.01" step="0.01" value="' . esc_attr( $settings['redemption_points'] ) . '"><span>pontos = R$</span><input type="number" name="redemption_reais" min="0.01" step="0.01" value="' . esc_attr( $settings['redemption_reais'] ) . '"></div></div>';
	echo '<div class="pw-dtf-setting-row"><div><strong>Percentual máximo do pedido que pode ser pago com pontos</strong><p class="pw-dtf-points-help">0% bloqueia a troca. 50% permite trocar pontos por até metade do valor. 100% permite zerar o pedido com pontos.</p></div><div><input type="number" name="max_redemption_percent" min="0" max="100" step="1" value="' . esc_attr( $settings['max_redemption_percent'] ) . '"> %</div></div>';
	echo '<div class="pw-dtf-setting-row"><div><strong>Validade dos pontos DTF UV (dias)</strong><p class="pw-dtf-points-help">Deixe 0 para os pontos não expirarem. Informe, por exemplo, 365 para validade de um ano. O cliente verá o próximo vencimento no painel de pontos.</p></div><div><input type="number" name="expiration_days" min="0" step="1" value="' . esc_attr( $settings['expiration_days'] ) . '"> dias</div></div>';
	echo '<p><button type="submit" class="button button-primary"><span class="dashicons dashicons-saved"></span> Salvar configurações de pontos</button></p>';
	echo '</form>';

	if ( ! $embedded ) {
		echo '</div>';
	}
}

function pw_dtf_render_admin_users_page( $embedded = false ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$users = get_users( array( 'orderby' => 'login', 'order' => 'ASC', 'number' => 200 ) );
	$roles = wp_roles()->roles;

	if ( ! $embedded ) {
		echo '<div class="wrap"><h1>PrintWay - DTF UV</h1>';
	}
	pw_dtf_render_admin_notice();
	echo '<h2>Usuários e Código liberação (Pagar depois)</h2><p>Deixe o código vazio e salve para remover a autorização de Pagar depois daquele usuário.</p>';
	echo '<form method="post">';
	wp_nonce_field( 'pw_dtf_update_all_user_access' );
	echo '<input type="hidden" name="pw_dtf_admin_action" value="update_all_user_access">';
	echo '<p><button type="submit" class="button button-primary pw-dtf-save-all"><span class="dashicons dashicons-saved"></span>Salvar todos os usuários</button></p>';
	echo '<div class="pw-dtf-orders-wrap"><table class="widefat fixed striped pw-dtf-users-table"><thead><tr><th>Usuário</th><th>ID</th><th>E-mail</th><th>Função</th><th>Pontos atuais</th><th>Código liberação (Pagar depois)</th><th>Permitir em Manutenção</th></tr></thead><tbody>';

	foreach ( $users as $user ) {
		$current_role = ! empty( $user->roles ) ? reset( $user->roles ) : '';
		$code = pw_dtf_get_user_unlock_code( (int) $user->ID );
		$points = pw_dtf_get_user_points_balance( (int) $user->ID );
		$bypass = '1' === get_user_meta( (int) $user->ID, '_pw_dtf_maintenance_bypass', true );
		echo '<tr><td>' . esc_html( $user->user_login ) . '</td><td>' . esc_html( $user->ID ) . '</td><td>' . esc_html( $user->user_email ) . '</td><td>';
		echo '<select name="user_roles[' . esc_attr( $user->ID ) . ']">';
		foreach ( $roles as $slug => $role_data ) {
			echo '<option value="' . esc_attr( $slug ) . '"' . selected( $current_role, $slug, false ) . '>' . esc_html( translate_user_role( $role_data['name'] ) ) . '</option>';
		}
		echo '</select></td><td>' . esc_html( number_format_i18n( $points, 0 ) ) . '</td><td><input type="text" class="regular-text" name="unlock_codes[' . esc_attr( $user->ID ) . ']" value="' . esc_attr( $code ) . '" autocomplete="off"></td>';
		echo '<td><input type="checkbox" name="maintenance_bypass[' . esc_attr( $user->ID ) . ']" value="1"' . checked( $bypass, true, false ) . '></td></tr>';
	}

	echo '</tbody></table></div></form>';
	if ( ! $embedded ) {
		echo '</div>';
	}
}

function pw_dtf_price_xml_path() {
	return trailingslashit( ABSPATH ) . 'dtfuv/precos.xml';
}

function pw_dtf_default_price_table() {
	$bands = array(
		'direto' => array(
			array( 'medida' => 'A4', 'altura' => 21, 'valor' => 35.00 ),
			array( 'medida' => 'A3', 'altura' => 42, 'valor' => 55.00 ),
			array( 'medida' => '0.5m', 'altura' => 50, 'valor' => 75.00 ),
			array( 'medida' => '1-2m', 'altura' => 100, 'valor' => 100.00 ),
			array( 'medida' => '2-5m', 'altura' => 200, 'valor' => 190.00 ),
			array( 'medida' => '5-10m', 'altura' => 500, 'valor' => 420.00 ),
			array( 'medida' => '>10m', 'altura' => 10000, 'valor' => 7000.00 ),
		),
		'revenda' => array(
			array( 'medida' => 'A4', 'altura' => 21, 'valor' => 30.10 ),
			array( 'medida' => 'A3', 'altura' => 42, 'valor' => 47.30 ),
			array( 'medida' => '0.5m', 'altura' => 50, 'valor' => 64.50 ),
			array( 'medida' => '1-2m', 'altura' => 100, 'valor' => 86.00 ),
			array( 'medida' => '2-5m', 'altura' => 200, 'valor' => 163.40 ),
			array( 'medida' => '5-10m', 'altura' => 500, 'valor' => 361.20 ),
			array( 'medida' => '>10m', 'altura' => 10000, 'valor' => 6020.00 ),
		),
	);
	$excesses = array();
	foreach ( $bands as $type => $type_bands ) {
		$values = array( 0.95, 2.50, 0.50, 0.90, 0.77, 0.69, 0.70 );
		foreach ( $type_bands as $index => $band ) {
			$excesses[ $type ][] = array( 'medida' => $band['medida'], 'valor' => $values[ $index ] );
		}
	}

	return array( 'bands' => $bands, 'excesses' => $excesses );
}

function pw_dtf_get_price_table() {
	$table = pw_dtf_default_price_table();
	$path = pw_dtf_price_xml_path();

	if ( ! file_exists( $path ) || ! function_exists( 'simplexml_load_file' ) ) {
		return $table;
	}

	libxml_use_internal_errors( true );
	$xml = simplexml_load_file( $path, 'SimpleXMLElement', LIBXML_NONET );
	libxml_clear_errors();
	if ( false === $xml ) {
		return $table;
	}

	$bands = array( 'direto' => array(), 'revenda' => array() );
	$excesses = array( 'direto' => array(), 'revenda' => array() );
	foreach ( $xml->faixa as $node ) {
		$type = sanitize_key( (string) $node['tipo'] );
		$measure = sanitize_text_field( (string) $node['medida'] );
		$height = (float) $node['altura'];
		$value = (float) $node['valor'];
		if ( isset( $bands[ $type ] ) && '' !== $measure && $height > 0 && $value >= 0 ) {
			$bands[ $type ][] = array( 'medida' => $measure, 'altura' => $height, 'valor' => $value );
		}
	}
	foreach ( $xml->excedente as $node ) {
		$type = sanitize_key( (string) $node['tipo'] );
		$measure = sanitize_text_field( (string) $node['range'] );
		$value = (float) $node['valor'];
		if ( isset( $excesses[ $type ] ) && '' !== $measure && $value >= 0 ) {
			$excesses[ $type ][] = array( 'medida' => $measure, 'valor' => $value );
		}
	}

	foreach ( array( 'direto', 'revenda' ) as $type ) {
		if ( empty( $bands[ $type ] ) ) {
			return $table;
		}
		usort( $bands[ $type ], function ( $left, $right ) { return $left['altura'] <=> $right['altura']; } );
	}

	return array( 'bands' => $bands, 'excesses' => $excesses );
}

function pw_dtf_sanitize_price_rows( $posted, $has_height ) {
	$result = array( 'direto' => array(), 'revenda' => array() );
	if ( ! is_array( $posted ) ) {
		return $result;
	}
	foreach ( array( 'direto', 'revenda' ) as $type ) {
		$rows = isset( $posted[ $type ] ) && is_array( $posted[ $type ] ) ? $posted[ $type ] : array();
		$measures = isset( $rows['medida'] ) && is_array( $rows['medida'] ) ? $rows['medida'] : array();
		$heights = $has_height && isset( $rows['altura'] ) && is_array( $rows['altura'] ) ? $rows['altura'] : array();
		$values = isset( $rows['valor'] ) && is_array( $rows['valor'] ) ? $rows['valor'] : array();
		foreach ( $measures as $index => $measure ) {
			$measure = sanitize_text_field( $measure );
			$raw_value = isset( $values[ $index ] ) ? str_replace( ',', '.', $values[ $index ] ) : '';
			$value = is_numeric( $raw_value ) ? (float) $raw_value : -1;
			if ( '' === $measure || $value < 0 ) {
				continue;
			}
			$row = array( 'medida' => $measure, 'valor' => $value );
			if ( $has_height ) {
				$raw_height = isset( $heights[ $index ] ) ? str_replace( ',', '.', $heights[ $index ] ) : '';
				$height = is_numeric( $raw_height ) ? (float) $raw_height : 0;
				if ( $height <= 0 ) {
					continue;
				}
				$row['altura'] = $height;
			}
			$result[ $type ][] = $row;
		}
		if ( $has_height ) {
			usort( $result[ $type ], function ( $left, $right ) { return $left['altura'] <=> $right['altura']; } );
		}
	}

	return $result;
}

function pw_dtf_save_price_table( $bands, $excesses ) {
	$path = pw_dtf_price_xml_path();
	if ( ! is_dir( dirname( $path ) ) || ( file_exists( $path ) && ! is_writable( $path ) ) || ! is_writable( dirname( $path ) ) ) {
		return false;
	}
	$document = new DOMDocument( '1.0', 'UTF-8' );
	$document->formatOutput = true;
	$root = $document->appendChild( $document->createElement( 'precos' ) );
	foreach ( array( 'direto', 'revenda' ) as $type ) {
		foreach ( $bands[ $type ] as $band ) {
			$node = $document->createElement( 'faixa' );
			$node->setAttribute( 'tipo', $type );
			$node->setAttribute( 'medida', $band['medida'] );
			$node->setAttribute( 'altura', rtrim( rtrim( number_format( $band['altura'], 4, '.', '' ), '0' ), '.' ) );
			$node->setAttribute( 'valor', number_format( $band['valor'], 2, '.', '' ) );
			$root->appendChild( $node );
		}
	}
	foreach ( array( 'direto', 'revenda' ) as $type ) {
		foreach ( $excesses[ $type ] as $excess ) {
			$node = $document->createElement( 'excedente' );
			$node->setAttribute( 'tipo', $type );
			$node->setAttribute( 'range', $excess['medida'] );
			$node->setAttribute( 'valor', number_format( $excess['valor'], 2, '.', '' ) );
			$root->appendChild( $node );
		}
	}

	return false !== file_put_contents( $path, $document->saveXML(), LOCK_EX );
}

function pw_dtf_default_unified_price_table() {
	return array(
		array( 'medida' => 'A4', 'min' => 1, 'max' => 21, 'direto' => 1.66, 'revenda' => 1.43, 'excedente' => 0 ),
		array( 'medida' => 'A3', 'min' => 22, 'max' => 42, 'direto' => 1.42, 'revenda' => 1.22, 'excedente' => 0 ),
		array( 'medida' => '1/2m', 'min' => 50, 'max' => 99, 'direto' => 1.50, 'revenda' => 1.29, 'excedente' => 0 ),
		array( 'medida' => '1-2m', 'min' => 100, 'max' => 199, 'direto' => 0.90, 'revenda' => 0.77, 'excedente' => 0 ),
		array( 'medida' => '2-5m', 'min' => 200, 'max' => 499, 'direto' => 0.45, 'revenda' => 0.40, 'excedente' => 0 ),
		array( 'medida' => '5-10m', 'min' => 500, 'max' => 999, 'direto' => 0.42, 'revenda' => 0.38, 'excedente' => 0 ),
		array( 'medida' => '>10m', 'min' => 1000, 'max' => 5000, 'direto' => 0.40, 'revenda' => 0.37, 'excedente' => 0 ),
	);
}

function pw_dtf_get_unified_price_table() {
	$defaults = pw_dtf_default_unified_price_table();
	$saved = get_option( 'pw_dtf_unified_price_table', null );
	if ( is_array( $saved ) && ! empty( $saved ) ) {
		return $saved;
	}
	$path = pw_dtf_price_xml_path();
	if ( ! file_exists( $path ) || ! function_exists( 'simplexml_load_file' ) ) {
		return $defaults;
	}
	libxml_use_internal_errors( true );
	$xml = simplexml_load_file( $path, 'SimpleXMLElement', LIBXML_NONET );
	libxml_clear_errors();
	if ( false === $xml ) {
		return $defaults;
	}
	$direct = array();
	$revenda = array();
	$excesses = array();
	foreach ( $xml->faixa as $node ) {
		$type = sanitize_key( (string) $node['tipo'] );
		$measure = sanitize_text_field( (string) $node['medida'] );
		if ( '' === $measure || ! in_array( $type, array( 'direto', 'revenda' ), true ) ) {
			continue;
		}
		$min = isset( $node['min'] ) ? (float) $node['min'] : 0;
		$max = isset( $node['max'] ) && '' !== (string) $node['max'] ? (float) $node['max'] : (float) $node['altura'];
		$row = array( 'min' => $min, 'max' => $max, 'valor' => (float) $node['valor'] );
		if ( 'direto' === $type ) { $direct[ $measure ] = $row; } else { $revenda[ $measure ] = $row; }
	}
	foreach ( $xml->excedente as $node ) {
		$measure = sanitize_text_field( (string) $node['range'] );
		if ( '' !== $measure && ! isset( $excesses[ $measure ] ) ) { $excesses[ $measure ] = (float) $node['valor']; }
	}
	if ( empty( $direct ) || empty( $revenda ) ) {
		return $defaults;
	}
	$rows = array();
	foreach ( $direct as $measure => $row ) {
		if ( ! isset( $revenda[ $measure ] ) ) { continue; }
		$rows[] = array( 'medida' => $measure, 'min' => $row['min'], 'max' => $row['max'], 'direto' => $row['valor'], 'revenda' => $revenda[ $measure ]['valor'], 'excedente' => isset( $excesses[ $measure ] ) ? $excesses[ $measure ] : 0 );
	}
	usort( $rows, function ( $left, $right ) { return $left['max'] <=> $right['max']; } );
	$previous_max = 0;
	foreach ( $rows as &$row ) {
		if ( $row['min'] <= 0 ) {
			$row['min'] = $previous_max > 0 ? $previous_max + 1 : 1;
		}
		$previous_max = $row['max'];
	}
	unset( $row );
	usort( $rows, function ( $left, $right ) { return $left['min'] <=> $right['min']; } );
	if ( ! empty( $rows ) ) {
		update_option( 'pw_dtf_unified_price_table', $rows, false );
		return $rows;
	}
	return $defaults;
}

/**
 * Recalcula o valor no servidor usando a mesma regra exibida na calculadora.
 * Valores enviados pelo navegador nunca são usados como fonte de verdade.
 */
/**
 * Preço DTF UV por altura — mesma fórmula final usada no plugin de pedidos
 * e no JS desta página (confirmada em 2026-09-14): interpolação linear,
 * contínua em todas as transições entre faixas.
 *
 * A PRIMEIRA faixa (ex.: A4) é sempre um preço fixo — qualquer altura até
 * o teto dela mantém aquele valor.
 *
 * As DEMAIS faixas: o preço cadastrado é o valor exato NO TETO daquela
 * faixa. Dentro da faixa, o preço cresce em linha reta a partir de onde a
 * faixa anterior terminou (contínuo, sem saltos) até bater exatamente
 * nesse valor ao alcançar o teto.
 */
function pw_dtf_calculate_server_price( $height, $customer_type ) {
	$height = (float) $height;
	$customer_type = sanitize_key( (string) $customer_type );

	if ( $height <= 0 || ! in_array( $customer_type, array( 'direto', 'revenda' ), true ) ) {
		return new WP_Error( 'invalid_dtf_calculation', 'Os dados do cálculo são inválidos.' );
	}

	$bands = array();
	foreach ( pw_dtf_get_unified_price_table() as $row ) {
		$min = isset( $row['min'] ) ? (float) $row['min'] : 0;
		$has_max = isset( $row['max'] ) && '' !== (string) $row['max'];
		$max = $has_max ? (float) $row['max'] : null;
		$value = isset( $row[ $customer_type ] ) ? (float) $row[ $customer_type ] : -1;

		if ( $min > 0 && $value >= 0 ) {
			$bands[] = array( 'min' => $min, 'max' => $max, 'value' => $value );
		}
	}

	if ( empty( $bands ) ) {
		return new WP_Error( 'missing_dtf_prices', 'A tabela de preços DTF UV não está disponível.' );
	}

	usort(
		$bands,
		function ( $left, $right ) {
			return $left['min'] <=> $right['min'];
		}
	);

	// Localiza a faixa que cobre a altura. Quando a altura cai num vão
	// fracionário entre o teto de uma faixa e o piso da seguinte (ex.: uma
	// faixa termina em 49 cm e a próxima só começa em 50 cm — 49,5 cm não
	// bate em nenhuma das duas), cobra pela ÚLTIMA faixa já alcançada (a de
	// baixo), nunca por uma faixa maior/mais barata que a altura ainda nem
	// atingiu — antes o "else" caía direto na última faixa da tabela
	// (a de maior volume, com a tarifa mais barata), gerando um preço
	// muito abaixo do correto para qualquer altura dentro desses vãos.
	$active     = $bands[0];
	$active_idx = 0;
	foreach ( $bands as $idx => $band ) {
		if ( $height >= $band['min'] && ( null === $band['max'] || $height <= $band['max'] ) ) {
			$active     = $band;
			$active_idx = $idx;
			break;
		}
		if ( $height >= $band['min'] ) {
			$active     = $band;
			$active_idx = $idx;
		}
	}

	// Primeira faixa (A4): mínimo cobrado é o teto dela (ex.: 10 cm → cobra como 21 cm).
	$effective_height = ( 0 === $active_idx && null !== $active['max'] && $height < $active['max'] )
		? $active['max']
		: $height;

	return round( max( 0, $active['value'] * $effective_height ), 2 );
}

/**
 * Exercita todos os limites de faixa gravados. Cada valor configurado no fim
 * de uma faixa precisa retornar exatamente o mesmo preço no cálculo servidor.
 */
function pw_dtf_run_price_table_checks() {
	$checks = 0;
	$errors = array();

	foreach ( pw_dtf_get_unified_price_table() as $row ) {
		if ( ! isset( $row['max'] ) || '' === (string) $row['max'] ) {
			continue;
		}

		$height = (float) $row['max'];
		foreach ( array( 'direto', 'revenda' ) as $type ) {
			$checks++;
			$actual = pw_dtf_calculate_server_price( $height, $type );
			$expected = ( isset( $row[ $type ] ) && $row[ $type ] >= 0 ) ? round( (float) $row[ $type ] * $height, 2 ) : -1;
			if ( is_wp_error( $actual ) || $expected < 0 || abs( (float) $actual - $expected ) > 0.01 ) {
				$errors[] = sprintf( '%s (%s)', isset( $row['medida'] ) ? $row['medida'] : 'Faixa sem nome', $type );
			}
		}
	}

	return array(
		'checks' => $checks,
		'errors' => $errors,
	);
}

function pw_dtf_payment_session_transient_key( $session_id ) {
	return 'pw_dtf_pay_' . md5( (string) $session_id );
}

function pw_dtf_get_payment_session( $session_id ) {
	$session_id = sanitize_text_field( (string) $session_id );
	if ( '' === $session_id ) {
		return false;
	}
	$session = get_transient( pw_dtf_payment_session_transient_key( $session_id ) );
	if ( ! is_array( $session ) || empty( $session['id'] ) || ! hash_equals( (string) $session['id'], $session_id ) ) {
		return false;
	}
	if ( empty( $session['expires_at'] ) || (int) $session['expires_at'] < time() ) {
		delete_transient( pw_dtf_payment_session_transient_key( $session_id ) );
		return false;
	}
	return $session;
}

/**
 * Cria uma fotografia imutável do preço e dos pontos usados no QR.
 */
function pw_dtf_prepare_payment() {
	check_ajax_referer( PW_DTF_NONCE_ACTION, 'nonce' );

	$height = pw_dtf_post_decimal( 'height_cm' );
	$customer_type = pw_dtf_post_text( 'customer_type' );
	if ( ! is_user_logged_in() ) {
		$customer_type = 'direto';
	}
	$requested_points = absint( pw_dtf_post_raw( 'points_used' ) );
	$client_price = pw_dtf_post_decimal( 'client_price' );
	if ( $client_price > 0 ) {
		$original = round( $client_price, 2 );
	} else {
		$rounding = pw_dtf_get_rounding_settings();
		if ( ! empty( $rounding['round_cm'] ) ) {
			$height = ceil( $height );
		}
		$original = pw_dtf_calculate_server_price( $height, $customer_type );
		if ( ! is_wp_error( $original ) && ! empty( $rounding['round_price'] ) ) {
			$original = ceil( round( $original * 100 ) / 10 ) / 10;
		}
	}

	if ( is_wp_error( $original ) ) {
		wp_send_json_error( array( 'message' => $original->get_error_message() ), 400 );
	}

	$settings = pw_dtf_get_points_settings();
	$user_id = get_current_user_id();
	$points_used = 0;
	$points_discount = 0.0;

	if ( $requested_points > 0 ) {
		if ( ! $user_id || empty( $settings['enabled'] ) ) {
			wp_send_json_error( array( 'message' => 'A troca de pontos não está disponível.' ), 403 );
		}

		$balance = pw_dtf_get_user_points_balance( $user_id );
		$points_per_real = (float) $settings['points_per_real'];
		$maximum_discount = $original * ( max( 0, min( 100, (float) $settings['max_redemption_percent'] ) ) / 100 );
		$maximum_points = $points_per_real > 0
			? (int) floor( min( $balance, $maximum_discount * $points_per_real ) )
			: 0;

		if ( $requested_points > $maximum_points ) {
			wp_send_json_error( array( 'message' => 'O saldo ou o limite de pontos mudou. Atualize os pontos e tente novamente.' ), 409 );
		}

		$points_used = $requested_points;
		$points_discount = $points_per_real > 0
			? min( $maximum_discount, $points_used / $points_per_real )
			: 0;
	}

	$amount = round( max( 0, $original - $points_discount ), 2 );
	$session_id = wp_generate_uuid4();
	$expires_at = time() + ( 30 * MINUTE_IN_SECONDS );
	$session = array(
		'id'              => $session_id,
		'user_id'         => $user_id,
		'height'          => round( $height, 2 ),
		'customer_type'   => $customer_type,
		'original_amount' => round( $original, 2 ),
		'points_used'     => $points_used,
		'points_discount' => round( $points_discount, 2 ),
		'amount'          => $amount,
		'payment_method'  => $amount <= 0 ? 'points' : 'pix',
		'expires_at'      => $expires_at,
	);

	set_transient(
		pw_dtf_payment_session_transient_key( $session_id ),
		$session,
		30 * MINUTE_IN_SECONDS
	);

	wp_send_json_success(
		array(
			'payment_session' => $session_id,
			'original_amount' => number_format( $session['original_amount'], 2, '.', '' ),
			'points_used'     => $session['points_used'],
			'points_discount' => number_format( $session['points_discount'], 2, '.', '' ),
			'amount'          => number_format( $session['amount'], 2, '.', '' ),
			'payment_method'  => $session['payment_method'],
			'expires_at'      => $expires_at,
		)
	);
}

function pw_dtf_sanitize_price_table_rows( $posted ) {
	if ( ! is_array( $posted ) || empty( $posted['medida'] ) || ! is_array( $posted['medida'] ) ) {
		return new WP_Error( 'invalid_prices' );
	}
	$rows = array();
	foreach ( $posted['medida'] as $index => $measure ) {
		$measure = sanitize_text_field( $measure );
		$values = array();
		$raw_min = isset( $posted['min'][ $index ] ) ? str_replace( ',', '.', $posted['min'][ $index ] ) : '';
		$values['min'] = '' === trim( $raw_min ) && 0 === (int) $index ? 1 : ( is_numeric( $raw_min ) ? (float) $raw_min : -1 );
		foreach ( array( 'direto', 'revenda' ) as $field ) {
			$raw = isset( $posted[ $field ][ $index ] ) ? str_replace( ',', '.', $posted[ $field ][ $index ] ) : '';
			$values[ $field ] = is_numeric( $raw ) ? (float) $raw : -1;
		}
		// A coluna Excedente saiu do formulário — sem ela, a faixa fica travada
		// (preço fixo dentro do próprio intervalo). Se um valor ainda vier
		// (compatibilidade com uma tabela antiga), é respeitado normalmente.
		$raw_excedente = isset( $posted['excedente'][ $index ] ) ? str_replace( ',', '.', $posted['excedente'][ $index ] ) : '';
		$values['excedente'] = is_numeric( $raw_excedente ) ? (float) $raw_excedente : 0;
		$raw_discount = isset( $posted['desconto'][ $index ] ) ? str_replace( ',', '.', $posted['desconto'][ $index ] ) : '';
		$discount = is_numeric( $raw_discount ) ? (float) $raw_discount : -1;
		$source = isset( $posted['price_source'][ $index ] ) ? sanitize_key( $posted['price_source'][ $index ] ) : 'revenda';
		$max_raw = isset( $posted['max'][ $index ] ) ? str_replace( ',', '.', $posted['max'][ $index ] ) : '';
		$max = '' === trim( $max_raw ) ? '' : ( is_numeric( $max_raw ) ? (float) $max_raw : -1 );
		if ( '' === $measure || $values['min'] <= 0 || $values['direto'] <= 0 || $values['revenda'] < 0 || $values['excedente'] < 0 || -1 === $max ) {
			return new WP_Error( 'invalid_prices' );
		}
		if ( in_array( $source, array( 'direto', 'desconto' ), true ) ) {
			if ( $discount < 0 || $discount > 100 ) { return new WP_Error( 'invalid_prices' ); }
			$values['revenda'] = round( $values['direto'] * ( 1 - ( $discount / 100 ) ), 2 );
		} else {
			if ( $values['revenda'] > $values['direto'] ) { return new WP_Error( 'invalid_prices' ); }
			$discount = round( ( 1 - ( $values['revenda'] / $values['direto'] ) ) * 100, 2 );
		}
		$rows[] = array( 'medida' => $measure, 'min' => $values['min'], 'max' => $max, 'direto' => $values['direto'], 'revenda' => $values['revenda'], 'desconto' => $discount, 'excedente' => $values['excedente'] );
	}
	if ( empty( $rows ) ) { return new WP_Error( 'invalid_prices' ); }
	usort( $rows, function ( $left, $right ) { return $left['min'] <=> $right['min']; } );
	foreach ( $rows as $index => $row ) {
		$is_last = $index === count( $rows ) - 1;
		if ( '' === $row['max'] && ! $is_last ) { return new WP_Error( 'invalid_prices' ); }
		if ( '' !== $row['max'] && $row['max'] < $row['min'] ) { return new WP_Error( 'invalid_prices' ); }
		if ( $index > 0 ) {
			$previous = $rows[ $index - 1 ];
			if ( '' === $previous['max'] || $row['min'] <= $previous['max'] ) { return new WP_Error( 'invalid_prices' ); }
		}
	}
	return $rows;
}

function pw_dtf_save_unified_price_table( $rows ) {
	return false !== update_option( 'pw_dtf_unified_price_table', $rows, false );
}

function pw_dtf_get_rounding_settings() {
	$defaults = array( 'round_cm' => false, 'round_price' => false );
	$saved = get_option( 'pw_dtf_rounding_settings', $defaults );
	return is_array( $saved ) ? array_merge( $defaults, $saved ) : $defaults;
}

function pw_dtf_render_admin_shortcode_page( $embedded = false ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! $embedded ) { echo '<div class="wrap"><h1>PrintWay - DTF UV</h1>'; }
	echo '<h2>Como colocar a calculadora numa página</h2>';
	echo '<p>Cole esta linha no lugar onde a calculadora deve aparecer — no Elementor, num widget de <strong>Shortcode</strong> (não precisa mais colar o HTML/CSS/JS inteiro num widget de HTML, isso deixava o editor lento):</p>';
	// Texto fixo (não a constante do módulo dtfUV) de propósito: essa tela
	// não pode quebrar mesmo se aquele módulo não estiver instalado/ativo.
	echo '<p style="display:flex;align-items:center;gap:10px;"><code id="pw-dtf-shortcode-text" style="font-size:16px;padding:10px 14px;background:#f0f0f1;border:1px solid #dcdcde;border-radius:4px;">[printway_dtf_uv]</code><button type="button" class="button" id="pw-dtf-copy-shortcode">Copiar</button></p>';
	echo '<p class="description">O CSS e o JS da calculadora agora são arquivos próprios do plugin (módulo <code>dtfUV</code>) — carregados automaticamente só na página onde esse shortcode aparecer, sem pesar o restante do site nem o editor do Elementor.</p>';
	echo '<script>document.addEventListener("DOMContentLoaded",function(){var copyShortcodeBtn=document.getElementById("pw-dtf-copy-shortcode");if(copyShortcodeBtn){copyShortcodeBtn.addEventListener("click",function(){var text=document.getElementById("pw-dtf-shortcode-text").textContent;navigator.clipboard.writeText(text).then(function(){var original=copyShortcodeBtn.textContent;copyShortcodeBtn.textContent="Copiado!";setTimeout(function(){copyShortcodeBtn.textContent=original;},1500);});});}});</script>';
	if ( ! $embedded ) { echo '</div>'; }
}

function pw_dtf_render_admin_prices_page( $embedded = false ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$rows = pw_dtf_get_unified_price_table();
	$price_check = pw_dtf_run_price_table_checks();
	if ( ! $embedded ) { echo '<div class="wrap"><h1>PrintWay - DTF UV</h1>'; }
	pw_dtf_render_admin_notice();

	echo '<p>As faixas são verificadas ao salvar. A altura mínima de cada linha deve ser maior que a altura máxima da linha anterior. Na primeira faixa, a altura mínima pode ficar vazia e será gravada como 1 cm. Deixe a altura máxima vazia apenas na última faixa para atender qualquer valor acima dela.</p>';
	if ( empty( $price_check['errors'] ) ) {
		echo '<p class="notice notice-success inline"><strong>Verificação automática aprovada:</strong> ' . esc_html( $price_check['checks'] ) . ' limites de faixa testados no cálculo do servidor.</p>';
	} else {
		echo '<p class="notice notice-error inline"><strong>Verificação automática encontrou divergências:</strong> ' . esc_html( implode( ', ', $price_check['errors'] ) ) . '.</p>';
	}
	echo '<form method="post"><input type="hidden" name="pw_dtf_admin_action" value="update_price_table">';
	wp_nonce_field( 'pw_dtf_update_price_table' );
	echo '<section class="pw-dtf-price-section"><table class="widefat striped pw-dtf-price-table"><thead><tr><th>Medida</th><th>Altura mínima (cm)</th><th>Altura máxima (cm)</th><th>Preço Altura (cm)</th><th>% desconto revenda</th><th>Preço Revenda (cm)</th><th></th></tr></thead><tbody id="pw-dtf-price-rows">';
	foreach ( $rows as $row ) { pw_dtf_render_unified_price_row( $row ); }
	echo '</tbody></table><div class="pw-dtf-price-actions"><button type="button" class="button" id="pw-dtf-add-price-row">Adicionar faixa</button></div></section>';
	$rounding = pw_dtf_get_rounding_settings();
	echo '<section class="pw-dtf-price-section" style="margin-top:20px"><h3>Arredondamentos</h3>';
	echo '<p>Quando habilitado, a calculadora arredonda <strong>sempre para cima</strong> — medidas para o próximo cm inteiro e preços para a próxima dezena de centavos (ex.: R$&nbsp;1,41&nbsp;→&nbsp;R$&nbsp;1,50; 11,18&nbsp;cm&nbsp;→&nbsp;12&nbsp;cm).</p>';
	echo '<fieldset style="border:1px solid #dcdcde;padding:12px 16px;border-radius:4px;display:inline-flex;gap:20px;flex-wrap:wrap">';
	echo '<label style="display:flex;align-items:center;gap:6px;cursor:pointer"><input type="checkbox" name="round_cm" value="1"' . checked( ! empty( $rounding['round_cm'] ), true, false ) . '> <strong>Medidas (cm)</strong> — arredondar altura para o próximo cm inteiro</label>';
	echo '<label style="display:flex;align-items:center;gap:6px;cursor:pointer"><input type="checkbox" name="round_price" value="1"' . checked( ! empty( $rounding['round_price'] ), true, false ) . '> <strong>Preço R$</strong> — arredondar valor para a próxima dezena de centavos</label>';
	echo '</fieldset>';
	echo '<div class="pw-dtf-price-actions" style="margin-top:12px"><button type="submit" class="button button-primary pw-dtf-save-all"><span class="dashicons dashicons-saved"></span>Salvar tabela de preços</button></div>';
	echo '</section></form>';

	echo '<script>document.addEventListener("DOMContentLoaded",function(){var add=document.getElementById("pw-dtf-add-price-row"),body=document.getElementById("pw-dtf-price-rows");function number(input){return parseFloat(input.value)||0;}function sync(row,source){var direct=row.querySelector("[name=\\"price_rows[direto][]\\"]"),discount=row.querySelector("[name=\\"price_rows[desconto][]\\"]"),resale=row.querySelector("[name=\\"price_rows[revenda][]\\"]"),sourceField=row.querySelector("[name=\\"price_rows[price_source][]\\"]");if(!direct||!discount||!resale){return;}if(source==="revenda"){discount.value=number(direct)>0?Math.max(0,((1-number(resale)/number(direct))*100)).toFixed(2):"0.00";}else{resale.value=(number(direct)*(1-number(discount)/100)).toFixed(2);}if(sourceField){sourceField.value=source;}}function newRow(){return "<tr><td><input type=\\"text\\" name=\\"price_rows[medida][]\\" required></td><td><input type=\\"number\\" min=\\"0.01\\" step=\\"0.01\\" name=\\"price_rows[min][]\\"></td><td><input type=\\"number\\" min=\\"0.01\\" step=\\"0.01\\" name=\\"price_rows[max][]\\"></td><td><input type=\\"number\\" min=\\"0\\" step=\\"0.01\\" name=\\"price_rows[direto][]\\" required></td><td><input type=\\"number\\" min=\\"0\\" max=\\"100\\" step=\\"0.01\\" name=\\"price_rows[desconto][]\\" value=\\"0.00\\" required><input type=\\"hidden\\" name=\\"price_rows[price_source][]\\" value=\\"desconto\\"></td><td><input type=\\"number\\" min=\\"0\\" step=\\"0.01\\" name=\\"price_rows[revenda][]\\" required></td><td><button type=\\"button\\" class=\\"button-link-delete\\" data-pw-dtf-remove>Excluir</button></td></tr>";}if(add&&body){add.addEventListener("click",function(){body.insertAdjacentHTML("beforeend",newRow());});}document.addEventListener("input",function(event){var row=event.target.closest("#pw-dtf-price-rows tr");if(!row){return;}var name=event.target.name||"";if(name==="price_rows[revenda][]"){sync(row,"revenda");}else if(name==="price_rows[desconto][]"){sync(row,"desconto");}else if(name==="price_rows[direto][]"){sync(row,"direto");}});document.addEventListener("click",function(event){var remove=event.target.closest("[data-pw-dtf-remove]");if(remove){remove.closest("tr").remove();}});'
		. '});</script>';
	if ( ! $embedded ) { echo '</div>'; }
	return;

	$table = pw_dtf_get_price_table();
	$labels = array( 'direto' => 'Cliente direto (preço balcão)', 'revenda' => 'Revenda' );
	if ( ! $embedded ) {
		echo '<div class="wrap"><h1>PrintWay - DTF UV</h1>';
	}
	pw_dtf_render_admin_notice();
	echo '<h2>Tabela de preços DTF UV</h2><p>Altere os valores, inclua linhas quando necessário e clique em salvar. A calculadora passa a usar esta tabela imediatamente.</p>';
	echo '<form method="post">';
	wp_nonce_field( 'pw_dtf_update_price_table' );
	echo '<input type="hidden" name="pw_dtf_admin_action" value="update_price_table">';
	foreach ( $labels as $type => $label ) {
		echo '<section class="pw-dtf-price-section"><h3>Faixas — ' . esc_html( $label ) . '</h3><table class="widefat striped pw-dtf-price-table"><thead><tr><th>Medida</th><th>Altura até (cm)</th><th>Preço (R$)</th><th></th></tr></thead><tbody id="pw-dtf-bands-' . esc_attr( $type ) . '">';
		foreach ( $table['bands'][ $type ] as $row ) {
			pw_dtf_render_price_band_row( $type, $row );
		}
		echo '</tbody></table><p><button type="button" class="button" data-pw-dtf-add="band" data-type="' . esc_attr( $type ) . '">Adicionar faixa</button></p></section>';
	}
	foreach ( $labels as $type => $label ) {
		echo '<section class="pw-dtf-price-section"><h3>Excedentes por centímetro — ' . esc_html( $label ) . '</h3><table class="widefat striped pw-dtf-price-table"><thead><tr><th>Faixa / medida</th><th>Valor por cm (R$)</th><th></th></tr></thead><tbody id="pw-dtf-excesses-' . esc_attr( $type ) . '">';
		foreach ( $table['excesses'][ $type ] as $row ) {
			pw_dtf_render_price_excess_row( $type, $row );
		}
		echo '</tbody></table><p><button type="button" class="button" data-pw-dtf-add="excess" data-type="' . esc_attr( $type ) . '">Adicionar excedente</button></p></section>';
	}
	echo '<p><button type="submit" class="button button-primary pw-dtf-save-all"><span class="dashicons dashicons-saved"></span>Salvar tabela de preços</button></p></form>';
	echo '<script>document.addEventListener("click",function(event){var add=event.target.closest("[data-pw-dtf-add]"),remove=event.target.closest("[data-pw-dtf-remove]");if(remove){remove.closest("tr").remove();return;}if(!add){return;}var type=add.dataset.type,kind=add.dataset.pwDtfAdd,target=document.getElementById("pw-dtf-"+(kind==="band"?"bands":"excesses")+"-"+type);if(!target){return;}var fields=kind==="band"?"<td><input type=\"text\" name=\"price_bands["+type+"][medida][]\" required></td><td><input type=\"number\" min=\"0.01\" step=\"0.01\" name=\"price_bands["+type+"][altura][]\" required></td><td><input type=\"number\" min=\"0\" step=\"0.01\" name=\"price_bands["+type+"][valor][]\" required></td>":"<td><input type=\"text\" name=\"price_excesses["+type+"][medida][]\" required></td><td><input type=\"number\" min=\"0\" step=\"0.01\" name=\"price_excesses["+type+"][valor][]\" required></td>";target.insertAdjacentHTML("beforeend","<tr>"+fields+"<td><button type=\"button\" class=\"button-link-delete\" data-pw-dtf-remove>Excluir</button></td></tr>");});</script>';
	if ( ! $embedded ) {
		echo '</div>';
	}
}

function pw_dtf_render_price_band_row( $type, $row ) {
	echo '<tr><td><input type="text" name="price_bands[' . esc_attr( $type ) . '][medida][]" value="' . esc_attr( $row['medida'] ) . '" required></td><td><input type="number" min="0.01" step="0.01" name="price_bands[' . esc_attr( $type ) . '][altura][]" value="' . esc_attr( $row['altura'] ) . '" required></td><td><input type="number" min="0" step="0.01" name="price_bands[' . esc_attr( $type ) . '][valor][]" value="' . esc_attr( number_format( $row['valor'], 2, '.', '' ) ) . '" required></td><td><button type="button" class="button-link-delete" data-pw-dtf-remove>Excluir</button></td></tr>';
}

function pw_dtf_render_price_excess_row( $type, $row ) {
	echo '<tr><td><input type="text" name="price_excesses[' . esc_attr( $type ) . '][medida][]" value="' . esc_attr( $row['medida'] ) . '" required></td><td><input type="number" min="0" step="0.01" name="price_excesses[' . esc_attr( $type ) . '][valor][]" value="' . esc_attr( number_format( $row['valor'], 2, '.', '' ) ) . '" required></td><td><button type="button" class="button-link-delete" data-pw-dtf-remove>Excluir</button></td></tr>';
}

function pw_dtf_render_unified_price_row( $row ) {
	$discount = $row['direto'] > 0 ? max( 0, ( 1 - ( $row['revenda'] / $row['direto'] ) ) * 100 ) : 0;
	echo '<tr><td><input type="text" name="price_rows[medida][]" value="' . esc_attr( $row['medida'] ) . '" required></td><td><input type="number" min="0.01" step="0.01" name="price_rows[min][]" value="' . esc_attr( $row['min'] ) . '"></td><td><input type="number" min="0.01" step="0.01" name="price_rows[max][]" value="' . esc_attr( $row['max'] ) . '"></td><td><input type="number" min="0" step="0.01" name="price_rows[direto][]" value="' . esc_attr( number_format( $row['direto'], 2, '.', '' ) ) . '" required></td><td><input type="number" min="0" max="100" step="0.01" name="price_rows[desconto][]" value="' . esc_attr( number_format( $discount, 2, '.', '' ) ) . '" required><input type="hidden" name="price_rows[price_source][]" value="revenda"></td><td><input type="number" min="0" step="0.01" name="price_rows[revenda][]" value="' . esc_attr( number_format( $row['revenda'], 2, '.', '' ) ) . '" required></td><td><button type="button" class="button-link-delete" data-pw-dtf-remove>Excluir</button></td></tr>';
}

function pw_dtf_pay_later_xml_path() {
	return trailingslashit( ABSPATH ) . 'dtfuv/validacao_pagar_depois.xml';
}

function pw_dtf_get_user_unlock_code( $user_id ) {
	$stored = get_user_meta( absint( $user_id ), '_pw_dtf_pay_later_code', true );
	if ( '' !== $stored ) {
		return $stored;
	}
	$path = pw_dtf_pay_later_xml_path();

	if ( ! file_exists( $path ) || ! function_exists( 'simplexml_load_file' ) ) {
		return '';
	}

	libxml_use_internal_errors( true );
	$xml = simplexml_load_file( $path, 'SimpleXMLElement', LIBXML_NONET );
	libxml_clear_errors();

	if ( false === $xml ) {
		return '';
	}

	foreach ( $xml->codigo as $node ) {
		if ( (int) $node['usuario_id'] === (int) $user_id ) {
			$code = trim( (string) $node );
			if ( '' !== $code ) { update_user_meta( absint( $user_id ), '_pw_dtf_pay_later_code', $code ); }
			return $code;
		}
	}

	return '';
}

function pw_dtf_set_user_unlock_code( $user_id, $code ) {
	$user_id = absint( $user_id );
	$code = sanitize_text_field( $code );
	if ( '' === $code ) {
		return delete_user_meta( $user_id, '_pw_dtf_pay_later_code' ) || '' === get_user_meta( $user_id, '_pw_dtf_pay_later_code', true );
	}
	return false !== update_user_meta( $user_id, '_pw_dtf_pay_later_code', $code );
/*
	$existing = null;
	foreach ( $root->getElementsByTagName( 'codigo' ) as $node ) {
		if ( (int) $node->getAttribute( 'usuario_id' ) === (int) $user_id ) {
			$existing = $node;
			break;
		}
	}

	if ( '' === trim( $code ) ) {
		if ( $existing ) {
			$root->removeChild( $existing );
		}
	} else {
		if ( ! $existing ) {
			$existing = $document->createElement( 'codigo' );
			$root->appendChild( $existing );
		}
		$existing->setAttribute( 'usuario_id', (string) $user_id );
		$existing->setAttribute( 'ativo', 'sim' );
		$existing->nodeValue = sanitize_text_field( $code );
	}

	return false !== file_put_contents( $path, $document->saveXML(), LOCK_EX );
*/
}

add_action( 'wp_head', 'pw_dtf_expose_ajax_config', 1 );
add_action( 'wp_footer', 'pw_dtf_expose_ajax_config', 1 );
add_action( 'wp_footer', 'pw_dtf_enable_shared_excess_values', 999 );

function pw_dtf_expose_ajax_config() {
	static $printed = false;

	if ( $printed ) {
		return;
	}

	$printed = true;

	$_mp_s = get_option( 'pw_printway_mp_settings', array() );
	$_mp_token = is_array( $_mp_s ) ? ( $_mp_s['access_token'] ?? '' ) : '';

	$config = array(
		'ajax_url'          => admin_url( 'admin-ajax.php' ),
		'dtf_upload_url'    => admin_url( 'admin-ajax.php' ),
		'dtf_upload_action' => PW_DTF_AJAX_ACTION,
		'dtf_current_user_action' => PW_DTF_CURRENT_USER_ACTION,
		'dtf_nonce'         => wp_create_nonce( PW_DTF_NONCE_ACTION ),
		'loginUrl'          => wp_login_url(),
		'registerUrl'       => wp_registration_url(),
		'points'            => pw_dtf_get_points_settings(),
		'price_table'       => pw_dtf_get_unified_price_table(),
		'rounding'          => pw_dtf_get_rounding_settings(),
		'mp_pix_enabled'    => ( $_mp_token && strlen( $_mp_token ) > 10 ),
		'dtf_orders_url'    => function_exists( 'wc_get_account_endpoint_url' ) ? add_query_arg( 'tipo', 'dtf-uv', wc_get_account_endpoint_url( 'orders' ) ) : home_url( '/minha-conta/orders/?tipo=dtf-uv' ),
	);

	if ( is_user_logged_in() ) {
		$user       = wp_get_current_user();
		$role_names = wp_roles()->get_names();
		$role_slug  = ! empty( $user->roles ) ? reset( $user->roles ) : '';
		$role_label = $role_slug && isset( $role_names[ $role_slug ] )
			? translate_user_role( $role_names[ $role_slug ] )
			: ( current_user_can( 'manage_options' ) ? 'Administrador' : 'Usuário' );

		$first_name = (string) get_user_meta( $user->ID, 'first_name', true );
		$last_name  = (string) get_user_meta( $user->ID, 'last_name', true );
		if ( '' === $first_name ) {
			$first_name = (string) get_user_meta( $user->ID, 'billing_first_name', true );
		}
		if ( '' === $last_name ) {
			$last_name = (string) get_user_meta( $user->ID, 'billing_last_name', true );
		}

		$config['currentUser'] = array(
			'id'       => (int) $user->ID,
			'display'  => $user->display_name,
			'first_name' => $first_name,
			'last_name'  => $last_name,
			'full_name'  => trim( $first_name . ' ' . $last_name ),
			'login'    => $user->user_login,
			'email'    => $user->user_email,
			'whatsapp' => (string) get_user_meta( $user->ID, 'billing_phone', true ),
			'role'     => $role_label,
			'role_slug' => $role_slug,
			'is_admin' => current_user_can( 'manage_options' ),
			'can_pay_later' => '' !== pw_dtf_get_user_unlock_code( (int) $user->ID ),
			'pay_later_code' => pw_dtf_get_user_unlock_code( (int) $user->ID ),
			'points_balance' => pw_dtf_get_user_points_balance( (int) $user->ID ),
		);

		if ( current_user_can( 'manage_options' ) ) {
			$config['currentUser']['unlock_code'] = pw_dtf_get_user_unlock_code( (int) $user->ID );
		}
	}

	echo '<script>window.PW_SERVER_DATA=Object.assign({},window.PW_SERVER_DATA||{},' . wp_json_encode( $config ) . ');';
	echo 'window.PW_DTF_UPLOAD_URL=' . wp_json_encode( $config['dtf_upload_url'] ) . ';';
	echo 'window.PW_DTF_UPLOAD_ACTION=' . wp_json_encode( $config['dtf_upload_action'] ) . ';';
	echo 'window.printway_dtf_nonce=' . wp_json_encode( $config['dtf_nonce'] ) . ';';
	echo 'window.PW_DTF_MAIL_READY=true;';
	// Função central de cálculo DTF UV — fonte única da fórmula para todo JS do sistema.
	// Espelha pw_dtf_calculate_server_price() do PHP. Altere apenas aqui e no PHP.
	// pwDtfCalcPrice(height, priceTable, customerType) → {price, rate, band, effectiveHeight, detail} | null
	// Regra de mínimo: a primeira faixa (A4) sempre cobra pelo teto — ex.: 10 cm cobra como 21 cm.
	echo 'window.pwDtfCalcPrice=function(h,t,c){h=Number(h);if(!isFinite(h)||h<=0)return null;var tp=c||"direto",bs=(Array.isArray(t)?t:[]).map(function(r){return{label:String(r.medida||""),min:Number(r.min),max:(r.max===""||r.max==null)?null:Number(r.max),value:Number(r[tp])};}).filter(function(b){return isFinite(b.min)&&b.min>0&&isFinite(b.value)&&b.value>=0;}).sort(function(a,b){return a.min-b.min;});if(!bs.length)return null;var ai=0;for(var i=0;i<bs.length;i++){if(h>=bs[i].min&&(bs[i].max==null||h<=bs[i].max)){ai=i;break;}if(h>=bs[i].min){ai=i;}}var act=bs[ai],effH=(ai===0&&act.max!=null&&h<act.max)?act.max:h,p=Math.round(act.value*effH*100)/100,fmt=function(n){return Number(n).toLocaleString("pt-BR",{minimumFractionDigits:2,maximumFractionDigits:2});};return{price:p,rate:act.value,band:act.label,effectiveHeight:effH,detail:"Faixa: "+act.label+" — R$ "+fmt(act.value)+"/cm × "+fmt(effH)+" cm"+(effH!==h?" (mínimo "+fmt(effH)+" cm)":"")};};';
	echo '</script>';
}

function pw_dtf_enable_shared_excess_values() {
	if ( is_admin() ) {
		return;
	}

	echo '<script>if(typeof window.parseExcedentes==="function"){window.parseExcedentes=function(xml,tipo){var result={};Array.from(xml.getElementsByTagName("excedente")).filter(function(node){var nodeType=node.getAttribute("tipo");return !nodeType||nodeType===tipo;}).forEach(function(node){var range=node.getAttribute("range"),value=parseFloat(node.getAttribute("valor")||"0");if(!Number.isFinite(value)){return;}if(range){result[range]=value;}else{result.generic=value;}});return result;};}</script>';
}

/* ─────────────────────────────────────────────────────────
   MERCADO PAGO — PIX REGISTRADO (DTF UV)
   ───────────────────────────────────────────────────────── */

function pw_dtf_mp_get_token() {
	$s = get_option( 'pw_printway_mp_settings', array() );
	return is_array( $s ) ? ( $s['access_token'] ?? '' ) : '';
}

function pw_dtf_mp_create_pix_payment( $amount, $payer_name, $payer_email, $description = 'Pedido DTF UV', $idempotency_key = '' ) {
	$access_token = pw_dtf_mp_get_token();
	if ( ! $access_token ) {
		return new WP_Error( 'mp_not_configured', 'Mercado Pago não configurado.' );
	}
	if ( ! is_email( $payer_email ) ) {
		$payer_email = 'cliente@printway.com.br';
	}
	if ( ! $idempotency_key ) {
		$idempotency_key = 'pw-dtf-pix-' . wp_generate_uuid4();
	}
	$body = array(
		'transaction_amount' => round( (float) $amount, 2 ),
		'description'        => $description,
		'payment_method_id'  => 'pix',
		'payer'              => array( 'email' => $payer_email ),
	);
	$response = wp_remote_post( 'https://api.mercadopago.com/v1/payments', array(
		'headers' => array(
			'Authorization'     => 'Bearer ' . $access_token,
			'Content-Type'      => 'application/json',
			'X-Idempotency-Key' => $idempotency_key,
		),
		'body'    => wp_json_encode( $body ),
		'timeout' => 15,
	) );
	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'mp_connection', 'Erro de conexão com Mercado Pago: ' . $response->get_error_message() );
	}
	$code = wp_remote_retrieve_response_code( $response );
	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( 201 !== (int) $code || empty( $data['id'] ) ) {
		$msg = $data['message'] ?? ( $data['cause'][0]['description'] ?? 'Erro ao criar pagamento PIX.' );
		return new WP_Error( 'mp_api_error', $msg );
	}
	$pix = $data['point_of_interaction']['transaction_data'] ?? array();
	return array(
		'payment_id' => $data['id'],
		'qr_code'    => $pix['qr_code'] ?? '',
		'qr_base64'  => $pix['qr_code_base64'] ?? '',
		'expires_at' => $data['date_of_expiration'] ?? '',
	);
}

function pw_dtf_mp_create_pix() {
	$nonce = sanitize_text_field( $_POST['nonce'] ?? '' );
	if ( ! wp_verify_nonce( $nonce, PW_DTF_NONCE_ACTION ) ) {
		wp_send_json_error( array( 'message' => 'Sessão expirada. Recarregue a página.' ) );
	}
	$access_token = pw_dtf_mp_get_token();
	if ( ! $access_token ) {
		wp_send_json_error( array( 'message' => 'Mercado Pago não configurado.' ) );
	}
	$payment_session = sanitize_text_field( $_POST['payment_session'] ?? '' );
	if ( ! $payment_session ) {
		wp_send_json_error( array( 'message' => 'Sessão de pagamento inválida.' ) );
	}
	$session_data = pw_dtf_get_payment_session( $payment_session );
	if ( ! $session_data || ! is_array( $session_data ) ) {
		wp_send_json_error( array( 'message' => 'Sessão de pagamento expirada. Recalcule o pedido.' ) );
	}
	$amount = (float) ( $session_data['amount'] ?? 0 );
	if ( $amount <= 0 ) {
		wp_send_json_error( array( 'message' => 'Valor do pedido inválido.' ) );
	}
	$payer_email = sanitize_email( $_POST['payer_email'] ?? '' );
	if ( ! is_email( $payer_email ) ) {
		$payer_email = 'cliente@printway.com.br';
	}
	$idempotency_key = 'pw-dtf-pix-' . $payment_session;
	$body = array(
		'transaction_amount' => round( $amount, 2 ),
		'description'        => 'Pedido DTF UV',
		'payment_method_id'  => 'pix',
		'payer'              => array( 'email' => $payer_email ),
	);
	$response = wp_remote_post( 'https://api.mercadopago.com/v1/payments', array(
		'headers' => array(
			'Authorization'    => 'Bearer ' . $access_token,
			'Content-Type'     => 'application/json',
			'X-Idempotency-Key' => $idempotency_key,
		),
		'body'    => wp_json_encode( $body ),
		'timeout' => 15,
	) );
	if ( is_wp_error( $response ) ) {
		wp_send_json_error( array( 'message' => 'Erro de conexão com Mercado Pago: ' . $response->get_error_message() ) );
	}
	$code = wp_remote_retrieve_response_code( $response );
	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( 201 !== (int) $code || empty( $data['id'] ) ) {
		$msg = $data['message'] ?? ( $data['cause'][0]['description'] ?? 'Erro ao criar pagamento PIX.' );
		wp_send_json_error( array( 'message' => $msg ) );
	}
	$pix = $data['point_of_interaction']['transaction_data'] ?? array();
	update_user_meta( get_current_user_id(), '_pw_dtf_last_mp_payment', $data['id'] );
	wp_send_json_success( array(
		'payment_id'      => $data['id'],
		'qr_code'         => $pix['qr_code'] ?? '',
		'qr_code_base64'  => $pix['qr_code_base64'] ?? '',
		'ticket_url'      => $pix['ticket_url'] ?? '',
	) );
}
add_action( 'wp_ajax_pw_dtf_mp_create_pix',        'pw_dtf_mp_create_pix' );
add_action( 'wp_ajax_nopriv_pw_dtf_mp_create_pix', 'pw_dtf_mp_create_pix' );

function pw_dtf_mp_check_pix() {
	$nonce = sanitize_text_field( $_POST['nonce'] ?? '' );
	if ( ! wp_verify_nonce( $nonce, PW_DTF_NONCE_ACTION ) ) {
		wp_send_json_error( array( 'message' => 'Nonce inválido.' ) );
	}
	$access_token = pw_dtf_mp_get_token();
	if ( ! $access_token ) {
		wp_send_json_error( array( 'message' => 'Mercado Pago não configurado.' ) );
	}
	$payment_id = (int) ( $_POST['payment_id'] ?? 0 );
	if ( ! $payment_id ) {
		wp_send_json_error( array( 'message' => 'ID do pagamento inválido.' ) );
	}
	$response = wp_remote_get(
		'https://api.mercadopago.com/v1/payments/' . $payment_id,
		array(
			'headers' => array( 'Authorization' => 'Bearer ' . $access_token ),
			'timeout' => 10,
		)
	);
	if ( is_wp_error( $response ) ) {
		wp_send_json_error( array( 'message' => 'Erro de conexão.' ) );
	}
	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	wp_send_json_success( array(
		'status'        => $data['status'] ?? 'unknown',
		'status_detail' => $data['status_detail'] ?? '',
	) );
}
add_action( 'wp_ajax_pw_dtf_mp_check_pix',        'pw_dtf_mp_check_pix' );
add_action( 'wp_ajax_nopriv_pw_dtf_mp_check_pix', 'pw_dtf_mp_check_pix' );

add_action( 'wp_ajax_' . PW_DTF_AJAX_ACTION, 'pw_dtf_send_order' );
add_action( 'wp_ajax_nopriv_' . PW_DTF_AJAX_ACTION, 'pw_dtf_send_order' );
add_action( 'wp_ajax_' . PW_DTF_CURRENT_USER_ACTION, 'pw_dtf_get_current_user_profile' );
add_action( 'wp_ajax_' . PW_DTF_PREPARE_PAYMENT_ACTION, 'pw_dtf_prepare_payment' );
add_action( 'wp_ajax_nopriv_' . PW_DTF_PREPARE_PAYMENT_ACTION, 'pw_dtf_prepare_payment' );
add_action( 'wp_ajax_' . PW_DTF_SAVE_CALCULATION_ACTION, 'pw_dtf_save_calculation' );
add_action( 'wp_ajax_nopriv_' . PW_DTF_SAVE_CALCULATION_ACTION, 'pw_dtf_save_calculation' );

/**
 * Retorna os dados atuais do cadastro do usuário logado.
 * O nome completo é formado exclusivamente por Nome + Sobrenome e nunca pelo
 * campo Nome de exibição.
 */
/** Procura o cadastro do cliente no sistema de Pedidos por ID vinculado ou e-mail. */
function pw_dtf_sync_wp_role_to_pedidos( $user, $role ) {
	if ( ! $user ) return;
	$client_type = ( 'revendedor' === $role ) ? 'revenda' : 'direto';
	$found       = pw_dtf_find_pedidos_client( (int) $user->ID, $user->user_email );
	if ( ! $found ) return;
	global $wpdb;
	$table   = $wpdb->prefix . 'pw_personalizados_clients';
	$payload = $found['payload'];
	$payload['clientType'] = $client_type;
	$wpdb->update( $table, array( 'payload' => wp_json_encode( $payload ) ), array( 'object_id' => $found['id'] ), array( '%s' ), array( '%s' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

function pw_dtf_find_pedidos_client( $user_id, $email ) {
	global $wpdb;
	$table = $wpdb->prefix . 'pw_personalizados_clients';
	if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
		return null;
	}
	$linked_id = get_user_meta( $user_id, '_pw_dtf_client_id', true );
	if ( $linked_id ) {
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT object_id, payload FROM {$table} WHERE object_id = %s LIMIT 1", $linked_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $row ) {
			$p = json_decode( $row['payload'], true );
			if ( is_array( $p ) ) { return array( 'id' => $row['object_id'], 'payload' => $p ); }
		}
	}
	if ( $email ) {
		$like = '%' . $wpdb->esc_like( '"email":"' . $email ) . '%';
		$row  = $wpdb->get_row( $wpdb->prepare( "SELECT object_id, payload FROM {$table} WHERE payload LIKE %s LIMIT 1", $like ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $row ) {
			$p = json_decode( $row['payload'], true );
			if ( is_array( $p ) ) {
				update_user_meta( $user_id, '_pw_dtf_client_id', $row['object_id'] );
				return array( 'id' => $row['object_id'], 'payload' => $p );
			}
		}
	}
	return null;
}

function pw_dtf_get_current_user_profile() {
	check_ajax_referer( PW_DTF_NONCE_ACTION, 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => 'Usuário não está logado.' ), 401 );
	}

	$user = wp_get_current_user();
	$uid  = (int) $user->ID;

	$first_name = trim( (string) get_user_meta( $uid, 'first_name', true ) );
	$last_name  = trim( (string) get_user_meta( $uid, 'last_name', true ) );
	if ( '' === $first_name ) { $first_name = trim( (string) get_user_meta( $uid, 'billing_first_name', true ) ); }
	if ( '' === $last_name )  { $last_name  = trim( (string) get_user_meta( $uid, 'billing_last_name', true ) ); }

	$whatsapp     = (string) get_user_meta( $uid, 'billing_phone', true );
	$digits_only  = static function( $v ) { return preg_replace( '/\D/', '', (string) $v ); };
	$cpf          = $digits_only( get_user_meta( $uid, 'billing_cpf', true ) );
	$cnpj         = $digits_only( get_user_meta( $uid, 'billing_cnpj', true ) );
	$cpf_cnpj     = $cpf ?: $cnpj;
	$cep          = $digits_only( get_user_meta( $uid, 'billing_postcode', true ) );
	$street       = trim( (string) get_user_meta( $uid, 'billing_address_1', true ) );
	$number       = trim( (string) get_user_meta( $uid, 'billing_number', true ) );
	$complement   = trim( (string) get_user_meta( $uid, 'billing_address_2', true ) );
	$neighborhood = trim( (string) get_user_meta( $uid, 'billing_neighborhood', true ) );
	$city         = trim( (string) get_user_meta( $uid, 'billing_city', true ) );
	$state        = trim( (string) get_user_meta( $uid, 'billing_state', true ) );
	/* Tipo de cliente: papel WP como base, pedidos como fonte autoritativa. */
	$wp_role_slug = ! empty( $user->roles ) ? reset( $user->roles ) : '';
	$client_type  = ( 'revendedor' === $wp_role_slug ) ? 'revenda' : 'direto';

	$found = pw_dtf_find_pedidos_client( $uid, $user->user_email );
	if ( $found ) {
		$cp = $found['payload'];
		$ca = $cp['address'] ?? array();
		if ( ! $cpf_cnpj && ! empty( $cp['document'] ) ) { $cpf_cnpj     = $digits_only( $cp['document'] ); }
		if ( ! $cep          && ! empty( $ca['cep'] ) )          { $cep          = $digits_only( $ca['cep'] ); }
		if ( ! $street       && ! empty( $ca['street'] ) )       { $street       = (string) $ca['street']; }
		if ( ! $number       && ! empty( $ca['number'] ) )       { $number       = (string) $ca['number']; }
		if ( ! $complement   && ! empty( $ca['complement'] ) )   { $complement   = (string) $ca['complement']; }
		if ( ! $neighborhood && ! empty( $ca['neighborhood'] ) ) { $neighborhood = (string) $ca['neighborhood']; }
		if ( ! $city         && ! empty( $ca['city'] ) )         { $city         = (string) $ca['city']; }
		if ( ! $state        && ! empty( $ca['state'] ) )        { $state        = (string) $ca['state']; }
		if ( ! empty( $cp['clientType'] ) ) { $client_type = 'revenda' === $cp['clientType'] ? 'revenda' : 'direto'; }
	}

	$missing = array();
	if ( ! trim( $first_name . ' ' . $last_name ) ) { $missing[] = 'name'; }
	if ( ! $whatsapp )     { $missing[] = 'whatsapp'; }
	if ( ! $user->user_email ) { $missing[] = 'email'; }
	if ( ! $cpf_cnpj )    { $missing[] = 'cpf_cnpj'; }
	if ( ! $cep )         { $missing[] = 'cep'; }
	if ( ! $street )      { $missing[] = 'street'; }
	if ( ! $number )      { $missing[] = 'number'; }
	if ( ! $neighborhood ){ $missing[] = 'neighborhood'; }
	if ( ! $city )        { $missing[] = 'city'; }
	if ( ! $state )       { $missing[] = 'state'; }

	wp_send_json_success( array(
		'id'                   => $uid,
		'first_name'           => $first_name,
		'last_name'            => $last_name,
		'full_name'            => trim( $first_name . ' ' . $last_name ),
		'email'                => (string) $user->user_email,
		'whatsapp'             => $whatsapp,
		'cpf_cnpj'             => $cpf_cnpj,
		'cep'                  => $cep,
		'address_street'       => $street,
		'address_number'       => $number,
		'address_complement'   => $complement,
		'address_neighborhood' => $neighborhood,
		'address_city'         => $city,
		'address_state'        => $state,
		'client_type'          => $client_type,
		'missing_required'     => $missing,
	) );
}

function pw_dtf_save_user_profile() {
	check_ajax_referer( PW_DTF_NONCE_ACTION, 'nonce' );
	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => 'Não autenticado.' ), 401 );
	}
	$uid         = get_current_user_id();
	$full_name   = sanitize_text_field( wp_unslash( $_POST['name']         ?? '' ) );
	$whatsapp    = preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_POST['whatsapp']    ?? '' ) ) );
	$cpf_cnpj    = preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_POST['cpf_cnpj']   ?? '' ) ) );
	$cep         = preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_POST['cep']         ?? '' ) ) );
	$street      = sanitize_text_field( wp_unslash( $_POST['street']       ?? '' ) );
	$number      = sanitize_text_field( wp_unslash( $_POST['number']       ?? '' ) );
	$complement  = sanitize_text_field( wp_unslash( $_POST['complement']   ?? '' ) );
	$neighborhood = sanitize_text_field( wp_unslash( $_POST['neighborhood'] ?? '' ) );
	$city        = sanitize_text_field( wp_unslash( $_POST['city']         ?? '' ) );
	$state       = strtoupper( sanitize_text_field( wp_unslash( $_POST['state'] ?? '' ) ) );

	$name_parts  = explode( ' ', $full_name, 2 );
	$first_name  = trim( $name_parts[0] ?? '' );
	$last_name   = trim( $name_parts[1] ?? '' );

	if ( $first_name ) {
		update_user_meta( $uid, 'first_name', $first_name );
		update_user_meta( $uid, 'billing_first_name', $first_name );
	}
	if ( $last_name ) {
		update_user_meta( $uid, 'last_name', $last_name );
		update_user_meta( $uid, 'billing_last_name', $last_name );
	}
	if ( $whatsapp )      { update_user_meta( $uid, 'billing_phone', $whatsapp ); }
	if ( strlen( $cpf_cnpj ) === 11 ) { update_user_meta( $uid, 'billing_cpf', $cpf_cnpj ); }
	if ( strlen( $cpf_cnpj ) === 14 ) { update_user_meta( $uid, 'billing_cnpj', $cpf_cnpj ); }
	if ( $cep )           { update_user_meta( $uid, 'billing_postcode', $cep ); }
	if ( $street )        { update_user_meta( $uid, 'billing_address_1', $street ); }
	if ( $number )        { update_user_meta( $uid, 'billing_number', $number ); }
	update_user_meta( $uid, 'billing_address_2', $complement );
	if ( $neighborhood )  { update_user_meta( $uid, 'billing_neighborhood', $neighborhood ); }
	if ( $city )          { update_user_meta( $uid, 'billing_city', $city ); }
	if ( $state )         { update_user_meta( $uid, 'billing_state', $state ); }

	/* Sync para o cadastro de cliente no sistema de Pedidos. */
	$user  = get_userdata( $uid );
	$found = pw_dtf_find_pedidos_client( $uid, $user ? $user->user_email : '' );
	if ( $found ) {
		global $wpdb;
		$table   = $wpdb->prefix . 'pw_personalizados_clients';
		$payload = $found['payload'];
		if ( $full_name )   { $payload['name']     = $full_name; }
		if ( $whatsapp )    { $payload['phone']    = $whatsapp; }
		if ( $cpf_cnpj )    { $payload['document'] = $cpf_cnpj; }
		$addr = $payload['address'] ?? array();
		if ( $cep )         { $addr['cep']          = $cep; }
		if ( $street )      { $addr['street']       = $street; }
		if ( $number )      { $addr['number']       = $number; }
		$addr['complement'] = $complement;
		if ( $neighborhood ){ $addr['neighborhood'] = $neighborhood; }
		if ( $city )        { $addr['city']         = $city; }
		if ( $state )       { $addr['state']        = $state; }
		$payload['address'] = $addr;
		$wpdb->update(
			$table,
			array( 'name' => (string) ( $payload['name'] ?? '' ), 'payload' => wp_json_encode( $payload ), 'updated_at' => current_time( 'mysql', true ) ),
			array( 'object_id' => $found['id'] ),
			array( '%s', '%s', '%s' ),
			array( '%s' )
		);
	}

	wp_send_json_success( array( 'saved' => true ) );
}
add_action( 'wp_ajax_printway_dtf_save_user_profile', 'pw_dtf_save_user_profile' );
add_action( 'wp_ajax_pw_dtf_register_mp_payment',  'pw_dtf_register_mp_payment' );
add_action( 'wp_ajax_pw_dtf_create_pix_for_order', 'pw_dtf_create_pix_for_order' );
add_action( 'wp_ajax_' . PW_DTF_VALIDATE_PAY_LATER_ACTION, 'pw_dtf_validate_pay_later' );
add_action( 'wp_ajax_printway_dtf_get_pay_later_access', 'pw_dtf_get_pay_later_access' );
add_action( 'wp_ajax_printway_dtf_get_points_access', 'pw_dtf_get_points_access' );
add_action( 'wp_ajax_printway_dtf_get_price_table', 'pw_dtf_get_price_table_ajax' );
add_action( 'wp_ajax_nopriv_printway_dtf_get_price_table', 'pw_dtf_get_price_table_ajax' );
add_action( 'wp_ajax_printway_dtf_save_completed_visibility', 'pw_dtf_save_completed_visibility' );

/**
 * Consulta a autorização diretamente no servidor para evitar informações antigas
 * quando a página da calculadora estiver armazenada no cache.
 */
function pw_dtf_get_pay_later_access() {
	/* Endpoint devolve dado sensível por usuário (código de liberação); exige
	 * nonce para não poder ser lido via CSRF de outro site enquanto o usuário
	 * está autenticado. */
	check_ajax_referer( PW_DTF_NONCE_ACTION, 'nonce' );
	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => 'Faça login para consultar a liberação.' ), 403 );
	}

	$code = pw_dtf_get_user_unlock_code( get_current_user_id() );

	wp_send_json_success(
		array(
			'can_pay_later' => '' !== $code,
			'pay_later_code' => $code,
			'user_id'        => get_current_user_id(),
		)
	);
}

function pw_dtf_get_price_table_ajax() {
	wp_send_json_success( array( 'price_table' => pw_dtf_get_unified_price_table() ) );
}

function pw_dtf_save_calculation() {
	check_ajax_referer( PW_DTF_NONCE_ACTION, 'nonce' );
	$session = pw_dtf_post_text( 'session' );
	$source = pw_dtf_post_text( 'source' );
	$height = pw_dtf_post_decimal( 'height_cm' );
	$amount = pw_dtf_post_decimal( 'amount' );
	if ( strlen( $session ) < 12 || ! in_array( $source, array( 'pdf', 'manual' ), true ) || $height <= 0 || $amount < 0 ) {
		wp_send_json_error( array( 'message' => 'Dados de cálculo inválidos.' ), 400 );
	}
	$user_id = get_current_user_id();
	$query = array( 'post_type' => 'pw_dtf_calculation', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_pw_dtf_calc_session', 'meta_value' => $session );
	if ( $user_id ) { $query['author'] = $user_id; }
	$existing = get_posts( $query );
	$post_id = ! empty( $existing ) ? (int) $existing[0] : 0;
	if ( ! $post_id ) {
		$post_id = wp_insert_post( array( 'post_type' => 'pw_dtf_calculation', 'post_status' => 'publish', 'post_author' => $user_id, 'post_title' => 'Cálculo DTF UV - ' . wp_date( 'd/m/Y H:i' ) ), true );
		if ( is_wp_error( $post_id ) ) { wp_send_json_error( array( 'message' => 'Não foi possível registrar o cálculo.' ), 500 ); }
	}
	$previous_step = (int) get_post_meta( $post_id, '_pw_dtf_calc_last_step', true );
	$last_step = max( 1, min( 4, absint( pw_dtf_post_raw( 'last_step' ) ) ) );
	$last_step = max( $previous_step, $last_step );
	$previous_qr = 'yes' === get_post_meta( $post_id, '_pw_dtf_calc_pix_qr_generated', true );
	$previous_proof = 'yes' === get_post_meta( $post_id, '_pw_dtf_calc_proof_uploaded', true );
	$meta = array(
		'_pw_dtf_calc_session' => $session, '_pw_dtf_calc_source' => $source, '_pw_dtf_calc_height' => $height,
		'_pw_dtf_calc_amount' => $amount, '_pw_dtf_calc_customer_type' => pw_dtf_post_text( 'customer_type' ),
		'_pw_dtf_calc_name' => pw_dtf_post_text( 'name' ), '_pw_dtf_calc_whatsapp' => preg_replace( '/\D+/', '', pw_dtf_post_text( 'whatsapp' ) ),
		'_pw_dtf_calc_email' => sanitize_email( pw_dtf_post_raw( 'email' ) ), '_pw_dtf_calc_updated' => current_time( 'mysql' ), '_pw_dtf_calculation_completed' => 'no',
		'_pw_dtf_calc_last_step' => $last_step,
		'_pw_dtf_calc_pix_qr_generated' => ( $previous_qr || '1' === pw_dtf_post_text( 'pix_qr_generated' ) ) ? 'yes' : 'no',
		'_pw_dtf_calc_proof_uploaded' => ( $previous_proof || '1' === pw_dtf_post_text( 'proof_uploaded' ) ) ? 'yes' : 'no',
	);
	foreach ( $meta as $key => $value ) { update_post_meta( $post_id, $key, sanitize_text_field( (string) $value ) ); }
	wp_send_json_success( array( 'calculation_id' => (int) $post_id ) );
}

function pw_dtf_mark_calculation_completed( $calculation_id, $session, $order_reference ) {
	$calculation_id = absint( $calculation_id );
	if ( ! $calculation_id || 'pw_dtf_calculation' !== get_post_type( $calculation_id ) || ! $session || ! hash_equals( (string) get_post_meta( $calculation_id, '_pw_dtf_calc_session', true ), (string) $session ) ) { return; }
	$author = (int) get_post_field( 'post_author', $calculation_id );
	if ( $author && $author !== get_current_user_id() ) { return; }
	update_post_meta( $calculation_id, '_pw_dtf_calculation_completed', 'yes' );
	update_post_meta( $calculation_id, '_pw_dtf_calc_order_reference', sanitize_text_field( $order_reference ) );
}

function pw_dtf_save_completed_visibility() {
	check_ajax_referer( PW_DTF_NONCE_ACTION, 'nonce' );
	if ( ! is_user_logged_in() ) { wp_send_json_error( array( 'message' => 'Faça login.' ), 403 ); }
	update_user_meta( get_current_user_id(), '_pw_dtf_hide_completed_orders', '1' === pw_dtf_post_text( 'hide' ) ? '1' : '0' );
	wp_send_json_success();
}

/** Consulta saldo e regras atuais, sem depender do cache da página. */
function pw_dtf_get_points_access() {
	/* Mesmo raciocínio do endpoint de pay-later: devolve saldo de pontos do
	 * usuário, então precisa de nonce contra leitura via CSRF. */
	check_ajax_referer( PW_DTF_NONCE_ACTION, 'nonce' );
	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => 'Faça login para consultar os pontos.' ), 403 );
	}

	wp_send_json_success(
		array(
			'settings' => pw_dtf_get_points_settings(),
			'balance'  => pw_dtf_get_user_points_balance( get_current_user_id() ),
		)
	);
}

function pw_dtf_register_mp_payment() {
	check_ajax_referer( PW_DTF_NONCE_ACTION, 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => 'Login necessário.' ), 403 );
	}

	$order_id      = absint( pw_dtf_post_raw( 'order_id' ) );
	$mp_payment_id = sanitize_text_field( pw_dtf_post_raw( 'mp_payment_id' ) );

	if ( ! $order_id || ! $mp_payment_id ) {
		wp_send_json_error( array( 'message' => 'Parâmetros inválidos.' ), 400 );
	}

	$post = get_post( $order_id );
	if ( ! $post || 'pw_dtf_order' !== $post->post_type ) {
		wp_send_json_error( array( 'message' => 'Pedido não encontrado.' ), 404 );
	}

	$owner = (int) get_post_meta( $order_id, '_pw_dtf_user_id', true );
	if ( $owner && $owner !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Sem permissão.' ), 403 );
	}

	update_post_meta( $order_id, '_pw_dtf_mp_payment_id', $mp_payment_id );
	update_post_meta( $order_id, '_pw_dtf_payment_status', 'paid' );
	update_post_meta( $order_id, '_pw_dtf_payment_label', 'Pix MercadoPago — Pago' );

	$history   = get_post_meta( $order_id, '_pw_dtf_payment_history', true );
	$history   = is_array( $history ) ? $history : array();
	$history[] = array(
		'date'       => current_time( 'mysql' ),
		'method'     => 'Pix MercadoPago',
		'mp_id'      => $mp_payment_id,
		'status'     => 'Pago',
		'amount'     => (float) get_post_meta( $order_id, '_pw_dtf_amount', true ),
	);
	update_post_meta( $order_id, '_pw_dtf_payment_history', $history );

	wp_send_json_success( array( 'message' => 'Pagamento registrado.' ) );
}

function pw_dtf_create_pix_for_order() {
	check_ajax_referer( PW_DTF_NONCE_ACTION, 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => 'Login necessário.' ), 403 );
	}

	$order_id = absint( pw_dtf_post_raw( 'order_id' ) );
	if ( ! $order_id ) {
		wp_send_json_error( array( 'message' => 'Parâmetros inválidos.' ), 400 );
	}

	$post = get_post( $order_id );
	if ( ! $post || 'pw_dtf_order' !== $post->post_type ) {
		wp_send_json_error( array( 'message' => 'Pedido não encontrado.' ), 404 );
	}

	$owner = (int) get_post_meta( $order_id, '_pw_dtf_user_id', true );
	if ( $owner && $owner !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Sem permissão.' ), 403 );
	}

	$status = get_post_meta( $order_id, '_pw_dtf_payment_status', true );
	if ( 'paid' === $status ) {
		wp_send_json_error( array( 'message' => 'Este pedido já foi pago.' ), 409 );
	}

	$amount = (float) get_post_meta( $order_id, '_pw_dtf_amount', true );
	if ( $amount <= 0 ) {
		wp_send_json_error( array( 'message' => 'Valor inválido para geração do Pix.' ), 400 );
	}

	$name      = get_post_meta( $order_id, '_pw_dtf_name', true );
	$email_val = get_post_meta( $order_id, '_pw_dtf_email', true );
	$reference = $post->post_title;

	$result = pw_dtf_mp_create_pix_payment( $amount, $name, $email_val, $reference );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
	}

	update_post_meta( $order_id, '_pw_dtf_mp_payment_id', $result['payment_id'] );
	update_post_meta( $order_id, '_pw_dtf_payment_status', 'pending_mp' );

	wp_send_json_success( array(
		'payment_id'  => $result['payment_id'],
		'qr_code'     => $result['qr_code'],
		'qr_base64'   => $result['qr_base64'],
		'expires_at'  => $result['expires_at'],
	) );
}

function pw_dtf_validate_pay_later() {
	check_ajax_referer( PW_DTF_NONCE_ACTION, 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => 'Faça login para usar Pagar depois.' ), 403 );
	}

	$code = pw_dtf_post_text( 'code' );

	if ( ! pw_dtf_is_valid_pay_later_code( $code, wp_get_current_user() ) ) {
		wp_send_json_error( array( 'message' => 'Código de desbloqueio inválido.' ), 403 );
	}

	wp_send_json_success( array( 'message' => 'Código validado.' ) );
}

function pw_dtf_send_order() {
	check_ajax_referer( PW_DTF_NONCE_ACTION, 'nonce' );

	$name         = pw_dtf_post_text( 'name' );
	$whatsapp     = pw_dtf_post_text( 'whatsapp' );
	$email        = sanitize_email( pw_dtf_post_raw( 'email' ) );
	$instructions = sanitize_textarea_field( pw_dtf_post_raw( 'instructions' ) );
	$amount       = pw_dtf_post_decimal( 'amount' );
	$height       = pw_dtf_post_decimal( 'height_cm' );
	$customer     = pw_dtf_post_text( 'customer_type' );
	if ( ! is_user_logged_in() ) {
		$customer = 'direto';
	}
	$source       = pw_dtf_post_text( 'calculation_source' );
	$detail       = sanitize_textarea_field( pw_dtf_post_raw( 'calculation_detail' ) );
	$expression   = sanitize_textarea_field( pw_dtf_post_raw( 'calculation_expression' ) );
	$payment      = pw_dtf_post_text( 'payment_method' );
	$payment_type = pw_dtf_post_text( 'payment_option' );
	$pay_later_code = pw_dtf_post_text( 'pay_later_code' );
	$delivery     = pw_dtf_post_text( 'delivery_method' );
	$original     = pw_dtf_post_decimal( 'original_amount' );
	$points_used  = absint( pw_dtf_post_raw( 'points_used' ) );
	$points_discount = pw_dtf_post_decimal( 'points_discount' );
	$payment_session_id = pw_dtf_post_text( 'payment_session' );
	$calculation_id = absint( pw_dtf_post_raw( 'calculation_id' ) );
	$calculation_session = pw_dtf_post_text( 'calculation_session' );
	$send_customer_copy = '1' === pw_dtf_post_text( 'send_customer_copy' );

	$alternative_payments = array(
		'cartao_credito'        => 'Cartão de crédito',
		'cartao_debito'         => 'Cartão de débito',
		'dinheiro'              => 'Dinheiro',
		'pix'                   => 'Pix',
		'transferencia_bancaria' => 'Transferência bancária',
	);

	$delivery_options = array(
		'retirada_local' => 'Irei retirar no local',
		'entrega_taxa'   => 'Entregar mediante taxa de entrega que irei pagar',
	);

	$mp_payment_id = sanitize_text_field( $_POST['mp_payment_id'] ?? '' );

	if ( ! in_array( $payment, array( 'pix', 'points', 'alternative', 'mp_pix', 'finalizar_sem_pagar' ), true ) ) {
		wp_send_json_error( array( 'message' => 'A forma de pagamento informada é inválida.' ), 400 );
	}

	if ( 'alternative' === $payment ) {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				array( 'message' => 'Faça login para usar outra forma de pagamento.' ),
				403
			);
		}

		if ( ! pw_dtf_is_valid_pay_later_code( $pay_later_code, wp_get_current_user() ) ) {
			wp_send_json_error(
				array( 'message' => 'O código de desbloqueio para Pagar depois é inválido.' ),
				403
			);
		}

		if ( ! isset( $alternative_payments[ $payment_type ] ) ) {
			wp_send_json_error(
				array( 'message' => 'Selecione uma forma de pagamento válida.' ),
				400
			);
		}

	}

	if ( ! isset( $delivery_options[ $delivery ] ) ) {
		wp_send_json_error(
			array( 'message' => 'Selecione como deseja receber o pedido.' ),
			400
		);
	}

	$payment_session = false;

	if ( in_array( $payment, array( 'pix', 'points', 'mp_pix' ), true ) ) {
		$payment_session = pw_dtf_get_payment_session( $payment_session_id );

		if ( ! $payment_session ) {
			wp_send_json_error(
				array( 'message' => 'A sessão deste pagamento expirou ou é inválida. Gere o pagamento novamente.' ),
				409
			);
		}

		$expected_payment_method = ( 'mp_pix' === $payment ) ? 'pix' : $payment;
		if (
			(int) $payment_session['user_id'] !== get_current_user_id() ||
			abs( (float) $payment_session['height'] - $height ) > 0.01 ||
			(string) $payment_session['customer_type'] !== $customer ||
			(string) $payment_session['payment_method'] !== $expected_payment_method
		) {
			wp_send_json_error(
				array( 'message' => 'Os dados do pedido foram alterados depois da geração do pagamento. Gere o pagamento novamente.' ),
				409
			);
		}

		/*
		 * Usa somente os valores guardados pelo servidor quando o QR foi criado.
		 * Alterações feitas no navegador depois disso são ignoradas.
		 */
		$original = (float) $payment_session['original_amount'];
		$points_used = (int) $payment_session['points_used'];
		$points_discount = (float) $payment_session['points_discount'];
		$amount = (float) $payment_session['amount'];
	}

	if ( $points_used > 0 || $points_discount > 0 ) {
		$settings = pw_dtf_get_points_settings();
		$balance = is_user_logged_in() ? pw_dtf_get_user_points_balance( get_current_user_id() ) : 0;
		$max_discount = $original * ( max( 0, min( 100, (float) $settings['max_redemption_percent'] ) ) / 100 );
		$calculated_discount = (float) $settings['points_per_real'] > 0 ? $points_used / (float) $settings['points_per_real'] : 0;
		if ( ! in_array( $payment, array( 'pix', 'points' ), true ) || empty( $settings['enabled'] ) || ! is_user_logged_in() || $points_used > $balance || $points_discount > $max_discount + 0.01 || abs( $points_discount - $calculated_discount ) > 0.01 || abs( $amount - max( 0, $original - $points_discount ) ) > 0.01 ) {
			wp_send_json_error( array( 'message' => 'A troca de pontos informada não é válida.' ), 400 );
		}
	} elseif ( in_array( $payment, array( 'pix', 'mp_pix' ), true ) && $original > 0 && abs( $amount - $original ) > 0.01 ) {
		wp_send_json_error( array( 'message' => 'O valor do Pix não corresponde ao valor do pedido.' ), 400 );
	}

	$whatsapp_digits = preg_replace( '/\D+/', '', $whatsapp );
	if ( strlen( $name ) < 2 || ! is_email( $email ) || ! preg_match( '/^[1-9]{2}9\d{8}$/', $whatsapp_digits ) ) {
		wp_send_json_error(
			array( 'message' => 'Os dados de identificação estão incompletos ou inválidos.' ),
			400
		);
	}

	if ( ( $amount <= 0 && 'points' !== $payment ) || $height <= 0 ) {
		wp_send_json_error(
			array( 'message' => 'Os dados do cálculo estão inválidos.' ),
			400
		);
	}

	$pdf = pw_dtf_prepare_attachment(
		'pdf',
		array( 'pdf' => 'application/pdf' )
	);

	if ( is_wp_error( $pdf ) ) {
		wp_send_json_error( array( 'message' => $pdf->get_error_message() ), 400 );
	}

	$receipt = null;

	if ( 'pix' === $payment ) {
		$receipt = pw_dtf_prepare_attachment(
			'receipt',
			array(
				'pdf'      => 'application/pdf',
				'jpg|jpeg' => 'image/jpeg',
				'png'      => 'image/png',
				'webp'     => 'image/webp',
			)
		);

		if ( is_wp_error( $receipt ) ) {
			pw_dtf_delete_temp_file( $pdf );
			wp_send_json_error( array( 'message' => $receipt->get_error_message() ), 400 );
		}
	}

	$no_receipt_payment = in_array( $payment, array( 'mp_pix', 'finalizar_sem_pagar' ), true );

	$recipient = apply_filters( 'printway_dtf_recipient_email', pw_dtf_get_recipient_email() );

	if ( ! is_email( $recipient ) ) {
		pw_dtf_delete_temp_file( $pdf );
		if ( $receipt ) {
			pw_dtf_delete_temp_file( $receipt );
		}
		wp_send_json_error( array( 'message' => 'O e-mail de destino da gráfica não está configurado.' ), 500 );
	}

	$subject = sprintf(
		'[Pedido DTF UV] %s - R$ %s',
		$name,
		number_format_i18n( $amount, 2 )
	);

	$message_lines = array(
		'Novo pedido DTF UV',
		'',
		'Nome: ' . $name,
		'WhatsApp: ' . $whatsapp,
		'E-mail: ' . $email,
		'Valor: R$ ' . number_format_i18n( $amount, 2 ),
		$points_used > 0 ? 'Pontos utilizados: ' . $points_used . ' (desconto de R$ ' . number_format_i18n( $points_discount, 2 ) . ')' : 'Pontos utilizados: nenhum.',
		'Altura: ' . number_format_i18n( $height, 2 ) . ' cm',
		'Tipo de cliente: ' . $customer,
		'Fonte do cálculo: ' . $source,
		'Detalhe: ' . $detail,
		'Expressão: ' . $expression,
		'Forma de pagamento: ' . pw_dtf_payment_label( $payment, $payment_type, $alternative_payments ),
		'Opção de pagamento: ' . pw_dtf_payment_option_label( $payment, $payment_type, $alternative_payments ),
		'Entrega/retirada: ' . $delivery_options[ $delivery ],
		'',
		'Instruções complementares:',
		$instructions ? $instructions : 'Nenhuma.',
		'',
		'pix' === $payment
			? 'O comprovante foi analisado no navegador, mas o recebimento do Pix deve ser conferido antes da produção.'
			: 'O cliente escolheu pagar por outro meio. Confirme as condições e o pagamento antes da produção ou entrega.',
	);

	if ( is_user_logged_in() ) {
		$wp_user    = wp_get_current_user();
		$role_names = wp_roles()->get_names();
		$role_slug  = ! empty( $wp_user->roles ) ? reset( $wp_user->roles ) : '';
		$role_label = $role_slug && isset( $role_names[ $role_slug ] )
			? translate_user_role( $role_names[ $role_slug ] )
			: ( current_user_can( 'manage_options' ) ? 'Administrador' : 'Usuário' );

		array_splice(
			$message_lines,
			2,
			0,
			array(
				'Usuário WordPress: ' . $wp_user->display_name,
				'Login WordPress: ' . $wp_user->user_login,
				'ID WordPress: ' . (int) $wp_user->ID,
				'Função WordPress: ' . $role_label,
				'',
			)
		);
	}

	$message = pw_dtf_render_email_html(
		'Novo pedido DTF UV',
		'Um novo pedido foi enviado e aguarda conferência.',
		array(
			'Cliente' => $name,
			'WhatsApp' => $whatsapp,
			'E-mail' => $email,
			'Valor' => wp_strip_all_tags( wc_price( $amount ) ),
			'Altura' => number_format_i18n( $height, 2 ) . ' cm',
			'Tipo de cliente' => $customer,
			'Pagamento' => pw_dtf_payment_label( $payment, $payment_type, $alternative_payments ),
			'Entrega/retirada' => $delivery_options[ $delivery ],
			'Observações' => $instructions ? $instructions : 'Nenhuma.',
		)
	);

	$headers = array(
		'Content-Type: text/html; charset=UTF-8',
		'Reply-To: ' . $name . ' <' . $email . '>',
	);

	/*
	 * Primeiro reserva os pontos e registra o pedido. Assim, um e-mail aceito
	 * nunca fica sem pedido correspondente. Em qualquer falha posterior, os
	 * pontos e o registro são revertidos antes de responder ao cliente.
	 */
	$order_reference = pw_dtf_generate_order_reference( get_current_user_id() );
	$points_deducted = false;

	if ( $points_used > 0 ) {
		$points_deducted = pw_dtf_deduct_points_from_user( get_current_user_id(), $points_used, 'Desconto no pedido ' . $order_reference );
		if ( ! $points_deducted ) {
			pw_dtf_delete_temp_file( $pdf );
			if ( $receipt ) {
				pw_dtf_delete_temp_file( $receipt );
			}
			wp_send_json_error( array( 'message' => 'O saldo de pontos mudou. Atualize a página e tente novamente.' ), 409 );
		}
	}

	$order_reference = pw_dtf_store_order(
		array(
			'reference'      => $order_reference,
			'user_id'        => get_current_user_id(),
			'name'           => $name,
			'email'          => $email,
			'whatsapp'       => $whatsapp,
			'amount'         => $amount,
			'original'       => $original > 0 ? $original : $amount,
			'height'         => $height,
			'customer_type'  => $customer,
			'payment_label'  => pw_dtf_payment_label( $payment, $payment_type, $alternative_payments ),
			'delivery_label' => $delivery_options[ $delivery ],
			'points_used'    => $points_used,
			'points_discount'=> $points_discount,
			'payment_session'=> $payment_session_id,
		)
	);

	if ( '' === $order_reference ) {
		if ( $points_deducted ) {
			pw_dtf_restore_points_to_user( get_current_user_id(), $points_used, 'Estorno: pedido não registrado' );
		}
		pw_dtf_delete_temp_file( $pdf );
		if ( $receipt ) {
			pw_dtf_delete_temp_file( $receipt );
		}
		wp_send_json_error( array( 'message' => 'Não foi possível registrar o pedido. Nenhum ponto foi descontado.' ), 500 );
	}

	$order_post = get_page_by_title( $order_reference, OBJECT, 'pw_dtf_order' );
	$order_id   = $order_post ? (int) $order_post->ID : 0;

	if ( $order_id ) {
		if ( 'mp_pix' === $payment ) {
			update_post_meta( $order_id, '_pw_dtf_mp_payment_id', sanitize_text_field( $mp_payment_id ) );
			update_post_meta( $order_id, '_pw_dtf_payment_status', 'pending_mp' );
		} elseif ( 'finalizar_sem_pagar' === $payment ) {
			update_post_meta( $order_id, '_pw_dtf_payment_status', 'aguardando' );
		} elseif ( 'pix' === $payment ) {
			update_post_meta( $order_id, '_pw_dtf_payment_status', 'paid' );
		} elseif ( 'points' === $payment ) {
			update_post_meta( $order_id, '_pw_dtf_payment_status', 'paid' );
		}
	}

	$mail_error     = null;
	$error_listener = function ( $error ) use ( &$mail_error ) {
		$mail_error = $error;
	};

	add_action( 'wp_mail_failed', $error_listener );

	$attachments = array( $pdf['path'] );

	if ( $receipt ) {
		$attachments[] = $receipt['path'];
	}

	$sent = wp_mail(
		$recipient,
		$subject,
		$message,
		$headers,
		$attachments
	);
	pw_dtf_log_email( $recipient, $subject, 'Novo pedido (empresa)', $sent, $order_id );

	if ( $send_customer_copy && ( $sent || $no_receipt_payment ) ) {
		$customer_rows = array(
			'Cliente' => $name,
			'Valor' => wp_strip_all_tags( wc_price( $amount ) ),
			'Altura' => number_format_i18n( $height, 2 ) . ' cm',
			'Forma de pagamento' => pw_dtf_payment_label( $payment, $payment_type, $alternative_payments ),
			'Entrega/retirada' => $delivery_options[ $delivery ],
			'Status inicial' => 'Enviado para análise',
		);
		$customer_subject = sprintf( '[%s] Recebemos seu pedido DTF UV', get_bloginfo( 'name' ) );
		$customer_sent = wp_mail( $email, $customer_subject, pw_dtf_render_email_html( 'Pedido DTF UV recebido', 'Recebemos seu pedido e ele será conferido pela nossa equipe.', $customer_rows ), array( 'Content-Type: text/html; charset=UTF-8' ), array( $pdf['path'] ) );
		pw_dtf_log_email( $email, $customer_subject, 'Cópia do pedido (cliente)', $customer_sent, $order_id );
	}

	remove_action( 'wp_mail_failed', $error_listener );

	if ( ! $sent && ! $no_receipt_payment ) {
		if ( $points_deducted ) {
			pw_dtf_restore_points_to_user( get_current_user_id(), $points_used, 'Estorno: falha no envio do pedido ' . $order_reference );
		}
		if ( $order_id ) {
			wp_delete_post( $order_id, true );
		}
		pw_dtf_delete_temp_file( $pdf );
		if ( $receipt ) {
			pw_dtf_delete_temp_file( $receipt );
		}
		$data = array(
			'message' => 'O WordPress não conseguiu processar o envio do e-mail.',
		);

		if ( current_user_can( 'manage_options' ) && is_wp_error( $mail_error ) ) {
			$data['admin_debug'] = $mail_error->get_error_message();
		}

		wp_send_json_error( $data, 500 );
	}

	pw_dtf_delete_temp_file( $pdf );
	if ( $receipt ) {
		pw_dtf_delete_temp_file( $receipt );
	}

	if ( '' !== $order_reference && $payment_session ) {
		delete_transient( pw_dtf_payment_session_transient_key( $payment_session_id ) );
	}
	if ( '' !== $order_reference ) {
		pw_dtf_mark_calculation_completed( $calculation_id, $calculation_session, $order_reference );
	}

	if ( '' !== $order_reference ) {
		do_action( 'pw_dtf_order_created', array(
			'reference'       => $order_reference,
			'user_id'         => get_current_user_id(),
			'name'            => $name,
			'email'           => $email,
			'whatsapp'        => $whatsapp,
			'amount'          => $amount,
			'original_amount' => $original,
			'height'          => $height,
			'customer_type'   => $customer,
			'payment_method'  => $payment,
			'payment_label'   => pw_dtf_payment_option_label( $payment, $payment_type, $alternative_payments ),
			'delivery_label'  => isset( $delivery_options[ $delivery ] ) ? $delivery_options[ $delivery ] : $delivery,
			'points_used'     => $points_used,
			'points_discount' => $points_discount,
			'instructions'    => $instructions,
			'detail'          => $detail,
		) );
	}

	wp_send_json_success(
		array(
			'message'         => 'Pedido enviado com sucesso.',
			'order_reference' => $order_reference,
			'order_id'        => $order_id,
			'order_saved'     => '' !== $order_reference,
			'admin_debug'     => current_user_can( 'manage_options' )
				? 'O wp_mail() aceitou o envio para ' . $recipient . '.' . ( '' === $order_reference ? ' Atenção: o pedido não foi registrado na listagem.' : ' Pedido registrado como ' . $order_reference . '.' )
				: '',
		)
	);
}

function pw_dtf_payment_label( $payment, $payment_type = '', $alternative_payments = array() ) {
	switch ( $payment ) {
		case 'pix':    return 'Pagar agora por Pix';
		case 'points': return 'Pago integralmente com pontos';
		case 'mp_pix': return 'Pix MercadoPago — Aguardando pagamento';
		case 'finalizar_sem_pagar': return 'Aguardando pagamento';
		default:       return isset( $alternative_payments[ $payment_type ] ) ? $alternative_payments[ $payment_type ] : 'Pagar depois';
	}
}

function pw_dtf_payment_option_label( $payment, $payment_type = '', $alternative_payments = array() ) {
	switch ( $payment ) {
		case 'pix':    return 'Pix';
		case 'points': return 'Pontos';
		case 'mp_pix': return 'Pix (MercadoPago)';
		case 'finalizar_sem_pagar': return 'Sem pagamento inicial';
		default:       return isset( $alternative_payments[ $payment_type ] ) ? $alternative_payments[ $payment_type ] : 'Outro';
	}
}

function pw_dtf_generate_order_reference( $user_id ) {
	return sprintf(
		'DTF-%s-%d-%03d',
		wp_date( 'Ymd-His' ),
		absint( $user_id ),
		wp_rand( 0, 999 )
	);
}

function pw_dtf_store_order( $data ) {
	$user_id = isset( $data['user_id'] ) ? (int) $data['user_id'] : 0;

	$reference = ! empty( $data['reference'] )
		? sanitize_text_field( $data['reference'] )
		: pw_dtf_generate_order_reference( $user_id );

	$post_id = wp_insert_post(
		array(
			'post_type'   => 'pw_dtf_order',
			'post_status' => 'publish',
			'post_author' => $user_id,
			'post_title'  => $reference,
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		return '';
	}

	$meta = array(
		'_pw_dtf_reference'      => $reference,
		'_pw_dtf_name'           => $data['name'],
		'_pw_dtf_email'          => $data['email'],
		'_pw_dtf_whatsapp'       => $data['whatsapp'],
		'_pw_dtf_amount'         => $data['amount'],
		'_pw_dtf_original'       => $data['original'],
		'_pw_dtf_points_used'    => isset( $data['points_used'] ) ? $data['points_used'] : 0,
		'_pw_dtf_points_discount'=> isset( $data['points_discount'] ) ? $data['points_discount'] : 0,
		'_pw_dtf_payment_session'=> isset( $data['payment_session'] ) ? sanitize_text_field( $data['payment_session'] ) : '',
		'_pw_dtf_height'         => $data['height'],
		'_pw_dtf_width'          => PW_DTF_PRINT_WIDTH_CM,
		'_pw_dtf_customer_type'  => $data['customer_type'],
		'_pw_dtf_payment_label'  => $data['payment_label'],
		'_pw_dtf_delivery_label' => $data['delivery_label'],
		'_pw_dtf_status'         => 'Enviado para análise',
	);

	foreach ( $meta as $key => $value ) {
		update_post_meta( $post_id, $key, sanitize_text_field( (string) $value ) );
	}

	return $reference;
}

function pw_dtf_prepare_attachment( $field, $allowed_mimes ) {
	if ( empty( $_FILES[ $field ] ) || ! is_array( $_FILES[ $field ] ) ) {
		return new WP_Error( 'missing_file', 'Um dos arquivos obrigatórios não foi recebido.' );
	}

	$file = $_FILES[ $field ];

	if ( UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
		return new WP_Error( 'upload_error', 'Falha ao receber um dos arquivos enviados.' );
	}

	if ( (int) $file['size'] <= 0 || (int) $file['size'] > PW_DTF_MAX_FILE_SIZE ) {
		return new WP_Error( 'invalid_size', 'Cada arquivo deve ter no máximo 10 MB.' );
	}

	$filename = sanitize_file_name( wp_unslash( $file['name'] ) );
	$checked  = wp_check_filetype_and_ext( $file['tmp_name'], $filename, $allowed_mimes );

	if ( empty( $checked['ext'] ) || empty( $checked['type'] ) ) {
		return new WP_Error( 'invalid_type', 'O tipo de um dos arquivos enviados não é permitido.' );
	}

	$safety_check = pw_dtf_validate_attachment_content( $file['tmp_name'], $checked['type'] );
	if ( is_wp_error( $safety_check ) ) {
		return $safety_check;
	}

	$temp_base = wp_tempnam( $filename );

	if ( ! $temp_base ) {
		return new WP_Error( 'temp_error', 'Não foi possível preparar os anexos para envio.' );
	}

	$target = $temp_base . '.' . $checked['ext'];

	if ( ! copy( $file['tmp_name'], $target ) ) {
		@unlink( $temp_base );
		return new WP_Error( 'copy_error', 'Não foi possível preparar os anexos para envio.' );
	}

	@unlink( $temp_base );

	return array(
		'path' => $target,
		'name' => $filename,
	);
}

/** Verifica a assinatura real do arquivo antes de encaminhá-lo por e-mail. */
function pw_dtf_validate_attachment_content( $path, $mime ) {
	$mime = sanitize_mime_type( $mime );
	$size = (int) filesize( $path );

	if ( 'application/pdf' === $mime ) {
		$handle = @fopen( $path, 'rb' );
		$header = $handle ? (string) fread( $handle, 8 ) : '';
		if ( $handle ) {
			$sample = (string) fread( $handle, min( 1048576, max( 0, $size - 8 ) ) );
			fclose( $handle );
		} else {
			$sample = '';
		}

		if ( 0 !== strpos( $header, '%PDF-' ) ) {
			return new WP_Error( 'invalid_pdf_content', 'O arquivo enviado não possui uma assinatura PDF válida.' );
		}

		if ( preg_match( '#/(?:JavaScript|JS|Launch|RichMedia|EmbeddedFile)\b#i', $sample ) ) {
			return new WP_Error( 'unsafe_pdf_content', 'O PDF contém conteúdo ativo que não é aceito por segurança.' );
		}

		return true;
	}

	if ( 0 === strpos( $mime, 'image/' ) ) {
		$image = @getimagesize( $path );
		if ( ! is_array( $image ) || empty( $image['mime'] ) || $image['mime'] !== $mime ) {
			return new WP_Error( 'invalid_image_content', 'A imagem enviada não corresponde ao formato informado.' );
		}
		if ( empty( $image[0] ) || empty( $image[1] ) || ( (int) $image[0] * (int) $image[1] ) > 100000000 ) {
			return new WP_Error( 'unsafe_image_size', 'A imagem possui dimensões inválidas ou excessivas.' );
		}
	}

	return true;
}

function pw_dtf_delete_temp_file( $attachment ) {
	if ( is_array( $attachment ) && ! empty( $attachment['path'] ) && file_exists( $attachment['path'] ) ) {
		@unlink( $attachment['path'] );
	}
}

function pw_dtf_post_raw( $key ) {
	return isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
}

function pw_dtf_post_text( $key ) {
	return sanitize_text_field( pw_dtf_post_raw( $key ) );
}

function pw_dtf_post_decimal( $key ) {
	$value = str_replace( ',', '.', pw_dtf_post_raw( $key ) );

	return (float) $value;
}

function pw_dtf_is_valid_pay_later_code( $submitted_code, $user ) {
	$submitted_code = trim( (string) $submitted_code );

	if ( '' === $submitted_code || ! $user instanceof WP_User || ! $user->exists() ) {
		return false;
	}

	$stored_code = pw_dtf_get_user_unlock_code( (int) $user->ID );
	return '' !== $stored_code && hash_equals( $stored_code, $submitted_code );
}
