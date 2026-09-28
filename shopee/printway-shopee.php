<?php
/*
Módulo: PrintWay Shopee
Description: Conexão com a Shopee Open Platform (Partner ID/Partner Key,
             autorização da loja e renovação automática de token) — mesmo
             padrão do módulo Mercado Livre, base para futura vinculação dos
             pedidos recebidos pela Shopee aos pedidos do Sistema interno.
Version: 1.0.8
Author: Rodrigo
Text Domain: printway-shopee
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PW_SHOPEE_VERSION', '1.0.4' );

/* =========================================================
 * MENU
 * ========================================================= */

add_action( 'admin_init', function() {
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) return;
	$s = get_option( 'pw_printway_shopee_settings', array() );
	$needs_fix = ( isset( $s['partner_id'] ) && '1245881' === (string) $s['partner_id'] )
	          || ( isset( $s['environment'] ) && 'production' === $s['environment'] && empty( $s['shop_id'] ) );
	if ( $needs_fix ) {
		if ( isset( $s['partner_id'] ) && '1245881' === (string) $s['partner_id'] ) {
			$s['partner_id'] = '1245681';
		}
		if ( isset( $s['environment'] ) && 'production' === $s['environment'] && empty( $s['shop_id'] ) ) {
			$s['environment'] = 'sandbox';
		}
		update_option( 'pw_printway_shopee_settings', $s, false );
	}
} );

add_action( 'admin_menu', 'pw_shopee_register_admin_menu' );
function pw_shopee_register_admin_menu() {
	add_submenu_page(
		'pw-printway',
		'Shopee',
		'Shopee',
		'manage_options',
		'pw-printway-shopee',
		'pw_shopee_render_admin_page'
	);
}

function pw_shopee_admin_url( $args = array() ) {
	$base = admin_url( 'admin.php?page=pw-printway-shopee' );
	return $args ? add_query_arg( $args, $base ) : $base;
}

function pw_shopee_redirect_uri() {
	return admin_url( 'admin-post.php?action=pw_printway_shopee_oauth_callback' );
}

/* =========================================================
 * ARMAZENAMENTO (Partner Key / tokens sempre cifrados)
 * ========================================================= */

function pw_shopee_get_settings() {
	$settings = get_option( 'pw_printway_shopee_settings', array() );
	return is_array( $settings ) ? $settings : array();
}

function pw_shopee_update_settings( $partial ) {
	$settings = array_merge( pw_shopee_get_settings(), $partial );
	update_option( 'pw_printway_shopee_settings', $settings, false );
	return $settings;
}

/** Mesmo esquema de cifra do módulo Mercado Livre (AES-256-GCM, chave
 * derivada do salt do WordPress + um "purpose" próprio por campo) — só o AAD
 * muda ("pw-shopee-" em vez de "pw-ml-"), pra nunca misturar as duas. */
function pw_shopee_seal( $value, $purpose ) {
	if ( ! function_exists( 'openssl_encrypt' ) || ! in_array( 'aes-256-gcm', openssl_get_cipher_methods(), true ) ) {
		return new WP_Error( 'shopee_crypto', 'O servidor precisa habilitar OpenSSL com AES-256-GCM para guardar essa credencial com segurança.' );
	}
	try {
		$iv = random_bytes( 12 );
		$tag = '';
		$aad = 'pw-shopee-' . $purpose . '-v1';
		$key = hash( 'sha256', wp_salt( 'auth' ) . '|' . $aad, true );
		$cipher = openssl_encrypt( (string) $value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $aad );
		if ( false === $cipher ) { throw new RuntimeException(); }
		return array( 'v' => 1, 'iv' => base64_encode( $iv ), 'tag' => base64_encode( $tag ), 'cipher' => base64_encode( $cipher ) );
	} catch ( Throwable $error ) {
		return new WP_Error( 'shopee_crypto', 'Não foi possível proteger essa credencial. Nada foi alterado.' );
	}
}

function pw_shopee_unseal( $saved, $purpose ) {
	if ( ! is_array( $saved ) || 1 !== ( $saved['v'] ?? null ) || ! function_exists( 'openssl_decrypt' ) ) { return ''; }
	$iv = base64_decode( $saved['iv'] ?? '', true );
	$tag = base64_decode( $saved['tag'] ?? '', true );
	$cipher = base64_decode( $saved['cipher'] ?? '', true );
	if ( ! is_string( $iv ) || 12 !== strlen( $iv ) || ! is_string( $tag ) || 16 !== strlen( $tag ) || ! is_string( $cipher ) ) { return ''; }
	$aad = 'pw-shopee-' . $purpose . '-v1';
	$key = hash( 'sha256', wp_salt( 'auth' ) . '|' . $aad, true );
	$plain = openssl_decrypt( $cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $aad );
	return is_string( $plain ) ? $plain : '';
}

function pw_shopee_sanitize_partner_id( $value ) {
	return substr( preg_replace( '/[^0-9]/', '', (string) $value ), 0, 20 );
}

function pw_shopee_partner_id() {
	$settings = pw_shopee_get_settings();
	return isset( $settings['partner_id'] ) ? (string) $settings['partner_id'] : '';
}

function pw_shopee_partner_key_value() {
	$settings = pw_shopee_get_settings();
	return pw_shopee_unseal( $settings['partner_key'] ?? null, 'key' );
}

function pw_shopee_access_token_value() {
	$settings = pw_shopee_get_settings();
	return pw_shopee_unseal( $settings['access_token'] ?? null, 'access' );
}

function pw_shopee_refresh_token_value() {
	$settings = pw_shopee_get_settings();
	return pw_shopee_unseal( $settings['refresh_token'] ?? null, 'refresh' );
}

function pw_shopee_environment() {
	$settings = pw_shopee_get_settings();
	return 'production' === ( $settings['environment'] ?? '' ) ? 'production' : 'sandbox';
}

/** Domínio da API conforme o ambiente escolhido nas credenciais — a Shopee
 * usa domínios INTEIRAMENTE separados pra sandbox e produção; um Partner
 * ID/Key de sandbox nunca funciona no domínio de produção e vice-versa. */
function pw_shopee_api_base() {
	return 'production' === pw_shopee_environment()
		? 'https://partner.shopeemobile.com'
		: 'https://openplatform.sandbox.test-stable.shopee.sg';
}

// Domínio usado apenas para o redirect OAuth (tela de autorização do seller).
function pw_shopee_oauth_base() {
	return 'production' === pw_shopee_environment()
		? 'https://open.shopee.com'
		: 'https://open.sandbox.test-stable.shopee.com';
}

function pw_shopee_is_connected() {
	$settings = pw_shopee_get_settings();
	return ! empty( $settings['access_token'] ) && ! empty( $settings['refresh_token'] ) && ! empty( $settings['shop_id'] );
}

/* =========================================================
 * ASSINATURA HMAC-SHA256 (exigida em toda chamada da API v2)
 * ========================================================= */

/**
 * A Shopee (API v2) exige uma assinatura HMAC-SHA256 em TODA chamada — bem
 * diferente do Mercado Livre, que só usa um Bearer token simples. A string
 * base é sempre partner_id + path + timestamp, acrescida de access_token e
 * shop_id quando a chamada já é autenticada numa loja (endpoints públicos de
 * auth, como pegar/renovar o token, usam só partner_id+path+timestamp).
 */
function pw_shopee_sign( $path, $timestamp, $access_token = '', $shop_id = '' ) {
	$partner_id = pw_shopee_partner_id();
	$key        = pw_shopee_partner_key_value();
	$base       = $partner_id . $path . $timestamp . $access_token . $shop_id;
	// Chaves com prefixo 'shpk' (novo formato) são usadas como string literal no HMAC.
	return hash_hmac( 'sha256', $base, $key );
}

/* =========================================================
 * TOKEN DE ACESSO
 * ========================================================= */

/** Grava os tokens vindos de uma troca de código ou de uma renovação. */
function pw_shopee_store_tokens( $data, $shop_id = '' ) {
	$access_raw = (string) ( $data['access_token'] ?? '' );
	$refresh_raw = (string) ( $data['refresh_token'] ?? '' );
	if ( ! $access_raw || ! $refresh_raw ) { return false; }
	$expires_in = isset( $data['expire_in'] ) ? (int) $data['expire_in'] : 14400; // 4h é o padrão documentado.
	$sealed_access = pw_shopee_seal( $access_raw, 'access' );
	$sealed_refresh = pw_shopee_seal( $refresh_raw, 'refresh' );
	if ( is_wp_error( $sealed_access ) || is_wp_error( $sealed_refresh ) ) {
		pw_printway_log( 'shopee', 'error', 'Falha ao proteger os tokens da Shopee ao salvar.' );
		return false;
	}
	$partial = array(
		'access_token'  => $sealed_access,
		'refresh_token' => $sealed_refresh,
		'expires_at'    => time() + $expires_in,
		'connected_at'  => time(),
	);
	if ( $shop_id ) { $partial['shop_id'] = sanitize_text_field( (string) $shop_id ); }
	pw_shopee_update_settings( $partial );
	return true;
}

/**
 * Devolve um Access Token válido, renovando sozinho via refresh_token quando
 * estiver a menos de 2 minutos de expirar — mesmo padrão do Mercado Livre
 * (pw_ml_get_valid_access_token), qualquer chamada futura à API da Shopee
 * deve passar por aqui.
 */
function pw_shopee_get_valid_access_token() {
	$settings = pw_shopee_get_settings();
	$access_token = pw_shopee_unseal( $settings['access_token'] ?? null, 'access' );
	$refresh_token = pw_shopee_unseal( $settings['refresh_token'] ?? null, 'refresh' );
	$shop_id = (string) ( $settings['shop_id'] ?? '' );
	$expires_at = (int) ( $settings['expires_at'] ?? 0 );
	if ( ! $access_token || ! $refresh_token || ! $shop_id ) {
		return new WP_Error( 'shopee_not_connected', 'Conecte a loja da Shopee em PrintWay → Shopee.' );
	}
	if ( $expires_at > ( time() + 120 ) ) {
		return $access_token;
	}
	$partner_id = pw_shopee_partner_id();
	$partner_key = pw_shopee_partner_key_value();
	if ( ! $partner_id || ! $partner_key ) {
		return new WP_Error( 'shopee_missing_credentials', 'Partner ID/Partner Key da Shopee não configurados.' );
	}
	$path = '/api/v2/auth/access_token/get';
	$timestamp = time();
	$sign = pw_shopee_sign( $path, $timestamp );
	$url = pw_shopee_api_base() . $path . '?' . http_build_query( array(
		'partner_id' => $partner_id,
		'timestamp'  => $timestamp,
		'sign'       => $sign,
	) );
	$response = wp_remote_post( $url, array(
		'timeout' => 20,
		'headers' => array( 'Content-Type' => 'application/json' ),
		'body'    => wp_json_encode( array(
			'refresh_token' => $refresh_token,
			'shop_id'       => (int) $shop_id,
			'partner_id'    => (int) $partner_id,
		) ),
	) );
	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'shopee_network', 'Não foi possível renovar o token da Shopee (falha de conexão).' );
	}
	$status = (int) wp_remote_retrieve_response_code( $response );
	$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	if ( 200 !== $status || empty( $data['access_token'] ) ) {
		$message = is_array( $data ) && ! empty( $data['message'] ) ? $data['message'] : ( 'HTTP ' . $status );
		pw_printway_log( 'shopee', 'error', 'Falha ao renovar o token da Shopee.', array( 'http' => $status, 'message' => $message ) );
		return new WP_Error( 'shopee_refresh_failed', 'A Shopee recusou a renovação do token. Reconecte a loja em PrintWay → Shopee.' );
	}
	pw_shopee_store_tokens( $data, $shop_id );
	return $data['access_token'];
}

/**
 * Chamada genérica e autenticada à API da Shopee — assina a requisição,
 * renova o token sozinha e loga qualquer falha no log central do PrintWay.
 * $body vai como JSON no corpo (POST) ou é ignorado em GET.
 */
function pw_shopee_api_request( $method, $path, $body = null, $extra_query = array() ) {
	$token = pw_shopee_get_valid_access_token();
	if ( is_wp_error( $token ) ) { return $token; }
	$settings = pw_shopee_get_settings();
	$shop_id = (string) ( $settings['shop_id'] ?? '' );
	$partner_id = pw_shopee_partner_id();
	$timestamp = time();
	$sign = pw_shopee_sign( $path, $timestamp, $token, $shop_id );
	$query = array_merge( array(
		'partner_id'   => $partner_id,
		'timestamp'    => $timestamp,
		'access_token' => $token,
		'shop_id'      => $shop_id,
		'sign'         => $sign,
	), $extra_query );
	$url = pw_shopee_api_base() . $path . '?' . http_build_query( $query );
	$args = array( 'method' => $method, 'timeout' => 25, 'headers' => array( 'Content-Type' => 'application/json' ) );
	if ( null !== $body && 'GET' !== $method ) { $args['body'] = wp_json_encode( $body ); }
	$response = wp_remote_request( $url, $args );
	if ( is_wp_error( $response ) ) {
		pw_printway_log( 'shopee', 'error', 'Falha de conexão ao chamar a API da Shopee (' . $path . ').', array( 'path' => $path ) );
		return new WP_Error( 'shopee_network', 'Falha de conexão com a Shopee. Tente novamente.' );
	}
	$status = (int) wp_remote_retrieve_response_code( $response );
	$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	if ( $status < 200 || $status >= 300 || ( is_array( $data ) && ! empty( $data['error'] ) ) ) {
		$message = is_array( $data ) && ! empty( $data['message'] ) ? $data['message'] : ( is_array( $data ) && ! empty( $data['error'] ) ? $data['error'] : ( 'HTTP ' . $status ) );
		pw_printway_log( 'shopee', 'error', 'A Shopee recusou uma chamada da API (' . $path . '): ' . $message, array( 'path' => $path, 'http' => $status, 'message' => $message ) );
		return new WP_Error( 'shopee_api_error', 'A Shopee recusou a solicitação: ' . $message, array( 'http' => $status ) );
	}
	return is_array( $data ) ? $data : array();
}

/** Dados básicos da loja conectada (nome, região etc.) — usado pelo "Verificar conexão agora". */
function pw_shopee_fetch_shop_info() {
	return pw_shopee_api_request( 'GET', '/api/v2/shop/get_shop_info' );
}

/* =========================================================
 * SELLERCHAT — Mensagens
 * ========================================================= */

/** Lista conversas (padrão: todas, page_size até 25). */
function pw_shopee_get_conversations( $page_size = 25, $next_offset = 0 ) {
	$extra = array( 'page_size' => (int) $page_size, 'type' => 0 );
	if ( $next_offset ) { $extra['next_offset_timestamp'] = (int) $next_offset; }
	return pw_shopee_api_request( 'GET', '/api/v2/sellerchat/get_conversation_list', null, $extra );
}

/** Mensagens de uma conversa específica. */
function pw_shopee_get_messages_in_conversation( $conversation_id, $page_size = 25, $offset = '' ) {
	$extra = array( 'conversation_id' => (string) $conversation_id, 'page_size' => (int) $page_size );
	if ( '' !== $offset ) { $extra['offset'] = (string) $offset; }
	return pw_shopee_api_request( 'GET', '/api/v2/sellerchat/get_message', null, $extra );
}

/** Envia mensagem de texto em uma conversa. */
function pw_shopee_send_chat_message( $conversation_id, $to_id, $text ) {
	$body = array( 'to_id' => (int) $to_id, 'message_type' => 'text', 'content' => array( 'text' => (string) $text ) );
	return pw_shopee_api_request( 'POST', '/api/v2/sellerchat/send_message', $body, array( 'conversation_id' => (string) $conversation_id ) );
}

/* =========================================================
 * PEDIDOS
 * ========================================================= */

/**
 * Lista pedidos recentes. A API Shopee limita cada chamada a 15 dias;
 * para períodos maiores, faz múltiplas chamadas e combina os resultados.
 */
function pw_shopee_get_order_list( $days = 15 ) {
	$days      = max( 1, (int) $days );
	$all_orders = array();
	$time_end  = time();
	$remaining = $days;
	while ( $remaining > 0 ) {
		$chunk    = min( $remaining, 15 );
		$time_to  = $time_end;
		$time_from = $time_to - ( $chunk * 86400 );
		$result   = pw_shopee_api_request( 'GET', '/api/v2/order/get_order_list', null, array(
			'time_range_field'         => 'create_time',
			'time_from'                => $time_from,
			'time_to'                  => $time_to,
			'page_size'                => 50,
			'response_optional_fields' => 'buyer_username,order_status,total_amount,currency,create_time,update_time',
		) );
		if ( is_wp_error( $result ) ) { return $result; }
		$list = $result['response']['order_list'] ?? array();
		$all_orders = array_merge( $all_orders, $list );
		$remaining -= $chunk;
		$time_end  = $time_from;
		if ( empty( $list ) && $remaining > 0 ) { break; }
	}
	return array( 'response' => array( 'order_list' => $all_orders ) );
}

/* =========================================================
 * NOTIFICAÇÕES (ícone piscando na nav)
 * ========================================================= */

/** Hash compacto pra detectar se há novidade (conversas não lidas + pedido novo). */
function pw_shopee_get_notification_snapshot() {
	if ( ! pw_shopee_is_connected() ) { return ''; }
	$unread_ids = array();
	$convs = pw_shopee_get_conversations( 20 );
	if ( ! is_wp_error( $convs ) ) {
		foreach ( (array) ( $convs['response']['conversations'] ?? array() ) as $c ) {
			if ( ! empty( $c['unread_count'] ) ) { $unread_ids[] = (string) $c['conversation_id']; }
		}
	}
	$latest_sn = '';
	$recent = pw_shopee_api_request( 'GET', '/api/v2/order/get_order_list', null, array(
		'time_range_field' => 'create_time',
		'time_from'        => time() - 172800,
		'time_to'          => time(),
		'page_size'        => 1,
	) );
	if ( ! is_wp_error( $recent ) ) {
		$list = $recent['response']['order_list'] ?? array();
		if ( ! empty( $list[0]['order_sn'] ) ) { $latest_sn = $list[0]['order_sn']; }
	}
	return md5( implode( ',', $unread_ids ) . '|' . $latest_sn );
}

/** Compara snapshot atual com o que o usuário já viu. */
function pw_shopee_get_notification_status( $wp_user_id ) {
	$snapshot = pw_shopee_get_notification_snapshot();
	if ( '' === $snapshot ) { return array( 'hasNew' => false, 'hasAnything' => false ); }
	$ack = (string) get_user_meta( $wp_user_id, '_pw_shopee_notify_ack', true );
	$has_new = $ack !== $snapshot;
	$unread_count = 0;
	$convs = pw_shopee_get_conversations( 20 );
	if ( ! is_wp_error( $convs ) ) {
		foreach ( (array) ( $convs['response']['conversations'] ?? array() ) as $c ) {
			if ( ! empty( $c['unread_count'] ) ) { $unread_count += (int) $c['unread_count']; }
		}
	}
	return array( 'hasNew' => $has_new, 'hasAnything' => true, 'unreadCount' => $unread_count );
}

/** Grava o snapshot atual como "visto" para o usuário. */
function pw_shopee_mark_notifications_seen( $wp_user_id ) {
	$snapshot = pw_shopee_get_notification_snapshot();
	update_user_meta( $wp_user_id, '_pw_shopee_notify_ack', $snapshot );
}

/* =========================================================
 * FLUXO de autorização (admin-post)
 * ========================================================= */

add_action( 'admin_post_pw_printway_shopee_save_credentials', 'pw_shopee_handle_save_credentials' );
function pw_shopee_handle_save_credentials() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Sem permissão.' ); }
	check_admin_referer( 'pw_shopee_save_credentials' );
	$partner_id = pw_shopee_sanitize_partner_id( $_POST['pw_shopee_partner_id'] ?? '' );
	$partner_key_raw = isset( $_POST['pw_shopee_partner_key'] ) ? trim( (string) wp_unslash( $_POST['pw_shopee_partner_key'] ) ) : '';
	$environment = ( isset( $_POST['pw_shopee_environment'] ) && 'production' === $_POST['pw_shopee_environment'] ) ? 'production' : 'sandbox';
	unset( $_POST['pw_shopee_partner_key'], $_REQUEST['pw_shopee_partner_key'] );
	$partial = array( 'partner_id' => $partner_id, 'environment' => $environment );
	if ( '' !== $partner_key_raw ) {
		$sealed = pw_shopee_seal( $partner_key_raw, 'key' );
		if ( is_wp_error( $sealed ) ) {
			wp_safe_redirect( pw_shopee_admin_url( array( 'aba' => 'credenciais', 'pw_shopee_notice' => 'crypto_error' ) ) );
			exit;
		}
		$partial['partner_key'] = $sealed;
	}
	pw_shopee_update_settings( $partial );
	wp_safe_redirect( pw_shopee_admin_url( array( 'aba' => 'credenciais', 'pw_shopee_notice' => 'saved' ) ) );
	exit;
}

add_action( 'admin_post_pw_printway_shopee_oauth_start', 'pw_shopee_handle_oauth_start' );
function pw_shopee_handle_oauth_start() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Sem permissão.' ); }
	check_admin_referer( 'pw_shopee_oauth_start' );
	$partner_id = pw_shopee_partner_id();
	$partner_key = pw_shopee_partner_key_value();
	if ( ! $partner_id || ! $partner_key ) {
		wp_safe_redirect( pw_shopee_admin_url( array( 'aba' => 'credenciais', 'pw_shopee_notice' => 'missing_credentials' ) ) );
		exit;
	}
	$state = wp_generate_password( 32, false );
	set_transient( 'pw_shopee_oauth_state_' . get_current_user_id(), $state, 600 );
	$url = pw_shopee_oauth_base() . '/auth?' . http_build_query( array(
		'auth_type'     => 'seller',
		'partner_id'    => $partner_id,
		'redirect_uri'  => pw_shopee_redirect_uri(),
		'response_type' => 'code',
		'state'         => $state,
	) );
	wp_redirect( esc_url_raw( $url ) );
	exit;
}

add_action( 'admin_post_pw_printway_shopee_oauth_callback', 'pw_shopee_handle_oauth_callback' );
function pw_shopee_handle_oauth_callback() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Sem permissão.' ); }
	$state_key = 'pw_shopee_oauth_state_' . get_current_user_id();
	$stored_state = get_transient( $state_key );
	delete_transient( $state_key );

	$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
	if ( ! $stored_state || ! $state || ! hash_equals( (string) $stored_state, $state ) ) {
		wp_safe_redirect( pw_shopee_admin_url( array( 'aba' => 'credenciais', 'pw_shopee_notice' => 'state_mismatch' ) ) );
		exit;
	}

	$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
	$shop_id = isset( $_GET['shop_id'] ) ? sanitize_text_field( wp_unslash( $_GET['shop_id'] ) ) : '';
	if ( ! $code || ! $shop_id ) {
		wp_safe_redirect( pw_shopee_admin_url( array( 'aba' => 'credenciais', 'pw_shopee_notice' => 'denied' ) ) );
		exit;
	}

	$partner_id = pw_shopee_partner_id();
	$partner_key = pw_shopee_partner_key_value();
	if ( ! $partner_id || ! $partner_key ) {
		wp_safe_redirect( pw_shopee_admin_url( array( 'aba' => 'credenciais', 'pw_shopee_notice' => 'missing_credentials' ) ) );
		exit;
	}

	$path = '/api/v2/auth/token/get';
	$timestamp = time();
	$sign = pw_shopee_sign( $path, $timestamp );
	$url = pw_shopee_api_base() . $path . '?' . http_build_query( array(
		'partner_id' => $partner_id,
		'timestamp'  => $timestamp,
		'sign'       => $sign,
	) );
	$response = wp_remote_post( $url, array(
		'timeout' => 25,
		'headers' => array( 'Content-Type' => 'application/json' ),
		'body'    => wp_json_encode( array(
			'code'       => $code,
			'shop_id'    => (int) $shop_id,
			'partner_id' => (int) $partner_id,
		) ),
	) );
	if ( is_wp_error( $response ) ) {
		pw_printway_log( 'shopee', 'error', 'Falha de rede ao trocar o código de autorização da Shopee.' );
		wp_safe_redirect( pw_shopee_admin_url( array( 'aba' => 'credenciais', 'pw_shopee_notice' => 'network_error' ) ) );
		exit;
	}
	$status = (int) wp_remote_retrieve_response_code( $response );
	$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	if ( 200 !== $status || empty( $data['access_token'] ) || empty( $data['refresh_token'] ) ) {
		$message = is_array( $data ) && ! empty( $data['message'] ) ? $data['message'] : ( 'HTTP ' . $status );
		pw_printway_log( 'shopee', 'error', 'A Shopee recusou a troca do código de autorização.', array( 'http' => $status, 'message' => $message ) );
		update_option( 'pw_shopee_debug_exchange', array( 'http' => $status, 'body' => wp_remote_retrieve_body( $response ), 'url' => $url, 'ts' => time() ) );
		wp_safe_redirect( pw_shopee_admin_url( array( 'aba' => 'credenciais', 'pw_shopee_notice' => 'exchange_failed' ) ) );
		exit;
	}

	pw_shopee_store_tokens( $data, $shop_id );

	// Busca o nome da loja pra mostrar na tela — falha aqui não é crítica,
	// a conexão já está gravada e funcional mesmo sem o nome.
	$info = pw_shopee_fetch_shop_info();
	if ( ! is_wp_error( $info ) ) {
		pw_shopee_update_settings( array( 'shop_name' => sanitize_text_field( (string) ( $info['shop_name'] ?? '' ) ) ) );
	}

	wp_safe_redirect( pw_shopee_admin_url( array( 'aba' => 'credenciais', 'pw_shopee_notice' => 'connected' ) ) );
	exit;
}

add_action( 'admin_post_pw_printway_shopee_check', 'pw_shopee_handle_check_connection' );
function pw_shopee_handle_check_connection() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Sem permissão.' ); }
	check_admin_referer( 'pw_shopee_check' );
	$info = pw_shopee_fetch_shop_info();
	if ( is_wp_error( $info ) ) {
		wp_safe_redirect( pw_shopee_admin_url( array( 'aba' => 'credenciais', 'pw_shopee_notice' => 'check_failed' ) ) );
		exit;
	}
	pw_shopee_update_settings( array( 'shop_name' => sanitize_text_field( (string) ( $info['shop_name'] ?? '' ) ) ) );
	wp_safe_redirect( pw_shopee_admin_url( array( 'aba' => 'credenciais', 'pw_shopee_notice' => 'check_ok' ) ) );
	exit;
}

add_action( 'admin_post_pw_printway_shopee_disconnect', 'pw_shopee_handle_disconnect' );
function pw_shopee_handle_disconnect() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Sem permissão.' ); }
	check_admin_referer( 'pw_shopee_disconnect' );
	pw_shopee_update_settings( array(
		'access_token'  => null,
		'refresh_token' => null,
		'expires_at'    => 0,
		'shop_id'       => '',
		'shop_name'     => '',
		'connected_at'  => 0,
	) );
	wp_safe_redirect( pw_shopee_admin_url( array( 'aba' => 'credenciais', 'pw_shopee_notice' => 'disconnected' ) ) );
	exit;
}

/* =========================================================
 * DIAGNÓSTICO DE ASSINATURA (temporário)
 * ========================================================= */

add_action( 'admin_post_pw_shopee_sign_diag', 'pw_shopee_handle_sign_diag' );
function pw_shopee_handle_sign_diag() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Sem permissão.' ); }
	check_admin_referer( 'pw_shopee_sign_diag' );

	$partner_id = pw_shopee_partner_id();
	$key_raw    = pw_shopee_partner_key_value();
	$path       = '/api/v2/shop/auth_partner';
	$timestamp  = time();
	$base       = $partner_id . $path . $timestamp;

	$key1 = $key_raw;
	$key2 = str_starts_with( $key_raw, 'shpk' ) ? substr( $key_raw, 4 ) : $key_raw;
	$key3 = ( ctype_xdigit( $key2 ) && 0 === strlen( $key2 ) % 2 ) ? hex2bin( $key2 ) : $key2;

	$variants = array(
		'1 — raw completo (com shpk)'    => $key1,
		'2 — sem prefixo shpk (string)'  => $key2,
		'3 — sem shpk + hex2bin (bytes)' => $key3,
	);

	$fake_redirect = admin_url( 'admin.php?page=pw-printway-shopee&pw_diag_noop=1' );
	$api_base      = pw_shopee_api_base();

	$html = '<h1>Diagnóstico de Assinatura Shopee</h1>';
	$html .= '<p><strong>Partner ID:</strong> ' . esc_html( $partner_id ) . '</p>';
	$html .= '<p><strong>Chave armazenada (raw):</strong> <code>' . esc_html( $key_raw ) . '</code></p>';
	$html .= '<p><strong>Base string:</strong> <code>' . esc_html( $base ) . '</code></p>';
	$html .= '<p><strong>Timestamp:</strong> ' . esc_html( $timestamp ) . '</p>';
	$html .= '<hr>';

	foreach ( $variants as $label => $key ) {
		$sign = hash_hmac( 'sha256', $base, $key );
		$url  = $api_base . $path . '?' . http_build_query( array(
			'partner_id' => $partner_id,
			'redirect'   => $fake_redirect,
			'timestamp'  => $timestamp,
			'sign'       => $sign,
		) );
		$resp = wp_remote_get( $url, array( 'timeout' => 10, 'redirection' => 5 ) );
		if ( is_wp_error( $resp ) ) {
			$http_code = 'ERRO';
			$body_text = esc_html( $resp->get_error_message() );
		} else {
			$http_code = wp_remote_retrieve_response_code( $resp );
			$body      = wp_remote_retrieve_body( $resp );
			$body_text = esc_html( substr( $body, 0, 800 ) );
		}
		$has_error = ( str_contains( $body_text, 'error_sign' ) || str_contains( $body_text, 'Wrong sign' ) || str_contains( $body_text, 'wrong_sign' ) );
		$color     = $has_error ? '#c00' : '#00a32a';

		$html .= '<h3 style="color:' . ( $has_error ? '#c00' : '#006' ) . '">' . esc_html( $label ) . '</h3>';
		$html .= '<p><strong>Sign:</strong> <code>' . esc_html( $sign ) . '</code></p>';
		$html .= '<p><strong>URL chamada:</strong> <code style="word-break:break-all;font-size:11px;">' . esc_html( $url ) . '</code></p>';
		$html .= '<p><strong>HTTP:</strong> ' . esc_html( $http_code ) . ' &nbsp; <strong style="color:' . $color . '">' . ( $has_error ? '✗ error_sign detectado' : '✓ sem error_sign na resposta' ) . '</strong></p>';
		$html .= '<details><summary>Primeiros 800 bytes da resposta</summary><pre style="overflow:auto;max-height:200px;background:#f8f8f8;padding:8px;">' . $body_text . '</pre></details><hr>';
	}

	$html .= '<p><a href="' . esc_url( pw_shopee_admin_url() ) . '">&larr; Voltar</a></p>';
	wp_die( $html, 'Diagnóstico Shopee', array( 'response' => 200 ) );
}

/* =========================================================
 * TELA DE ADMIN
 * ========================================================= */

function pw_shopee_render_admin_notice() {
	if ( empty( $_GET['pw_shopee_notice'] ) ) { return; }
	$notice = sanitize_key( wp_unslash( $_GET['pw_shopee_notice'] ) );
	$map = array(
		'saved'               => array( 'success', 'Credenciais salvas.' ),
		'connected'           => array( 'success', 'Loja da Shopee conectada com sucesso.' ),
		'disconnected'        => array( 'success', 'Conexão com a Shopee removida.' ),
		'check_ok'            => array( 'success', 'Conexão verificada: o token está válido.' ),
		'denied'              => array( 'error', 'A autorização foi cancelada ou a Shopee não retornou o código/loja esperados.' ),
		'state_mismatch'      => array( 'error', 'Não foi possível confirmar a autorização. Tente conectar novamente.' ),
		'missing_credentials' => array( 'error', 'Salve o Partner ID e a Partner Key antes de conectar.' ),
		'exchange_failed'     => array( 'error', 'A Shopee recusou a autorização. Confira o Partner ID/Key, o ambiente (sandbox/produção) e a URL de redirecionamento configurada no app.' ),
		'network_error'       => array( 'error', 'Falha de conexão ao falar com a Shopee. Tente novamente.' ),
		'check_failed'        => array( 'error', 'Não foi possível confirmar a conexão. Talvez seja necessário reconectar.' ),
		'crypto_error'        => array( 'error', 'O servidor não conseguiu proteger a credencial (verifique o suporte a OpenSSL/AES-256-GCM).' ),
	);
	if ( ! isset( $map[ $notice ] ) ) { return; }
	list( $type, $message ) = $map[ $notice ];
	echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
}

function pw_shopee_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	$tab = isset( $_GET['aba'] ) ? sanitize_key( wp_unslash( $_GET['aba'] ) ) : 'credenciais';
	$base_url = pw_shopee_admin_url();

	echo '<div class="wrap"><h1>PrintWay - Shopee</h1>';
	pw_shopee_render_admin_notice();
	echo '<nav class="nav-tab-wrapper" style="margin-bottom:20px">';
	echo '<a class="nav-tab' . ( 'pedidos' !== $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( $base_url ) . '">Credenciais</a>';
	echo '<a class="nav-tab' . ( 'pedidos' === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( add_query_arg( 'aba', 'pedidos', $base_url ) ) . '">Pedidos</a>';
	echo '</nav>';

	if ( 'pedidos' === $tab ) {
		pw_shopee_render_orders_tab();
	} else {
		pw_shopee_render_credentials_tab();
	}

	echo '</div>';
}

function pw_shopee_render_orders_tab() {
	echo '<h2>Vincular pedidos</h2>';
	echo '<p>Em breve: depois de conectar a loja na aba <strong>Credenciais</strong>, esta aba vai permitir vincular os pedidos recebidos pela Shopee aos pedidos do Sistema interno — mesmo caminho já seguido com o Mercado Livre.</p>';
}

function pw_shopee_render_credentials_tab() {
	$settings = pw_shopee_get_settings();
	$partner_id = pw_shopee_partner_id();
	$has_key = ! empty( $settings['partner_key'] );
	$environment = pw_shopee_environment();
	$connected = pw_shopee_is_connected();
	$redirect_uri = pw_shopee_redirect_uri();

	echo '<h2>Conexão com a Shopee</h2>';
	if ( $connected ) {
		$expires_at = (int) ( $settings['expires_at'] ?? 0 );
		$shop_name = (string) ( $settings['shop_name'] ?? '' );
		$shop_id = (string) ( $settings['shop_id'] ?? '' );
		echo '<div class="notice notice-success inline" style="padding:12px;">';
		echo '<p><strong>✅ Conectado' . ( $shop_name ? ' como ' . esc_html( $shop_name ) : '' ) . '</strong>' . ( $shop_id ? ' (Shop ID ' . esc_html( $shop_id ) . ')' : '' ) . ' — ambiente <strong>' . ( 'production' === $environment ? 'Produção' : 'Sandbox (teste)' ) . '</strong></p>';
		echo '<p>Token de acesso válido até ' . esc_html( wp_date( 'd/m/Y H:i', $expires_at, wp_timezone() ) ) . ' — renovado automaticamente quando o sistema precisar dele.</p>';
		echo '</div>';
		echo '<p style="display:flex;gap:10px;flex-wrap:wrap;">';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline;">';
		echo '<input type="hidden" name="action" value="pw_printway_shopee_check" />';
		wp_nonce_field( 'pw_shopee_check' );
		echo '<button type="submit" class="button">Verificar conexão agora</button></form>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline;" onsubmit="return confirm(\'Desconectar a loja da Shopee?\');">';
		echo '<input type="hidden" name="action" value="pw_printway_shopee_disconnect" />';
		wp_nonce_field( 'pw_shopee_disconnect' );
		echo '<button type="submit" class="button button-secondary">Desconectar</button></form>';
		echo '</p>';
	} else {
		echo '<div class="notice notice-warning inline" style="padding:12px;"><p>🔌 Ainda não conectado a nenhuma loja da Shopee.</p></div>';
	}

	echo '<h3>1. Credenciais do aplicativo</h3>';
	echo '<p>Copie do <a href="https://open.shopee.com/console/app" target="_blank" rel="noopener noreferrer">Shopee Open Platform Console</a> → seu app → App List:</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="pw_printway_shopee_save_credentials" />';
	wp_nonce_field( 'pw_shopee_save_credentials' );
	echo '<table class="form-table"><tbody>';
	echo '<tr><th><label for="pw-shopee-partner-id">Partner ID</label></th><td><input type="text" id="pw-shopee-partner-id" name="pw_shopee_partner_id" class="regular-text" value="' . esc_attr( $partner_id ) . '" autocomplete="off" inputmode="numeric"></td></tr>';
	echo '<tr><th><label for="pw-shopee-partner-key">Partner Key</label></th><td><input type="password" id="pw-shopee-partner-key" name="pw_shopee_partner_key" class="regular-text" autocomplete="new-password" placeholder="' . ( $has_key ? 'Já salva — deixe em branco para manter' : 'Cole a Partner Key' ) . '">';
	echo '<p class="description">' . ( $has_key ? 'Já existe uma Partner Key salva. Preencha apenas para substituí-la.' : 'Obrigatória para conectar.' ) . '</p></td></tr>';
	echo '<tr><th><label for="pw-shopee-environment">Ambiente</label></th><td><select id="pw-shopee-environment" name="pw_shopee_environment"><option value="sandbox"' . selected( $environment, 'sandbox', false ) . '>Sandbox (teste)</option><option value="production"' . selected( $environment, 'production', false ) . '>Produção (loja real)</option></select>';
	echo '<p class="description">Comece em Sandbox com as credenciais de teste que a Shopee libera na hora. Depois que a Shopee liberar o app para produção, troque aqui e salve as credenciais de produção.</p></td></tr>';
	echo '</tbody></table>';
	echo '<button type="submit" class="button button-primary">Salvar credenciais</button>';
	echo '</form>';

	echo '<h3>2. URL de redirecionamento</h3>';
	echo '<p>Configure exatamente esta URL como "Redirect URL" no seu app, em <a href="https://open.shopee.com/console/app" target="_blank" rel="noopener noreferrer">open.shopee.com/console/app</a> → seu app → editar:</p>';
	echo '<p style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;"><code id="pw-shopee-redirect-uri" style="font-size:14px;padding:10px 14px;background:#f0f0f1;border:1px solid #dcdcde;border-radius:4px;word-break:break-all;">' . esc_html( $redirect_uri ) . '</code><button type="button" class="button" id="pw-shopee-copy-redirect">Copiar</button></p>';

	echo '<h3>3. Conectar a loja</h3>';
	if ( $partner_id && ( $has_key || $connected ) ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="pw_printway_shopee_oauth_start" />';
		wp_nonce_field( 'pw_shopee_oauth_start' );
		echo '<button type="submit" class="button button-primary">' . ( $connected ? 'Reconectar' : 'Conectar com a Shopee' ) . '</button>';
		echo '</form>';
		echo '<p class="description">Você será redirecionado à Shopee para autorizar o acesso à loja e voltará automaticamente para esta página.</p>';
	} else {
		echo '<p class="description">Salve o Partner ID e a Partner Key acima antes de conectar.</p>';
	}

	echo '<script>document.addEventListener("DOMContentLoaded",function(){var btn=document.getElementById("pw-shopee-copy-redirect");if(btn){btn.addEventListener("click",function(){var text=document.getElementById("pw-shopee-redirect-uri").textContent;navigator.clipboard.writeText(text).then(function(){var original=btn.textContent;btn.textContent="Copiado!";setTimeout(function(){btn.textContent=original;},1500);});});}});</script>';

	if ( $partner_id && $has_key ) {
		echo '<hr><details style="margin-top:20px"><summary style="cursor:pointer;color:#666;font-size:13px;">Ferramentas de diagnóstico</summary>';
		echo '<p style="margin-top:12px;"><strong>Testar assinatura:</strong> faz uma chamada real à Shopee com os 3 formatos possíveis de chave e mostra qual funciona.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline;">';
		echo '<input type="hidden" name="action" value="pw_shopee_sign_diag" />';
		wp_nonce_field( 'pw_shopee_sign_diag' );
		echo '<button type="submit" class="button">Testar assinatura agora</button>';
		echo '</form></details>';
	}
}
