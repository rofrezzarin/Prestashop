<?php
/** Integração Melhor Envio: token pessoal (sem homologação), cotação e geração de etiqueta. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function pw_personalizados_melhorenvio_token( $value ) {
	if ( ! is_string( $value ) ) { return ''; }
	$token = trim( $value );
	if ( strlen( $token ) < 20 || strlen( $token ) > 4000 ) { return ''; }
	return $token;
}

function pw_personalizados_melhorenvio_seal( $token ) {
	if ( ! function_exists( 'openssl_encrypt' ) || ! in_array( 'aes-256-gcm', openssl_get_cipher_methods(), true ) ) {
		return new WP_Error( 'melhorenvio_crypto', 'O servidor precisa habilitar OpenSSL com AES-256-GCM para guardar o Token com segurança.' );
	}
	try {
		$iv = random_bytes( 12 );
		$tag = '';
		$key = hash( 'sha256', wp_salt( 'auth' ) . '|pw-melhorenvio-v1', true );
		$cipher = openssl_encrypt( $token, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, 'pw-melhorenvio-v1' );
		if ( false === $cipher ) { throw new RuntimeException(); }
		return array( 'v' => 1, 'iv' => base64_encode( $iv ), 'tag' => base64_encode( $tag ), 'cipher' => base64_encode( $cipher ) );
	} catch ( Throwable $error ) {
		return new WP_Error( 'melhorenvio_crypto', 'Não foi possível proteger o Token. Nenhuma credencial foi alterada.' );
	}
}

function pw_personalizados_melhorenvio_unseal( $saved ) {
	if ( ! is_array( $saved ) || 1 !== ( $saved['v'] ?? null ) || ! function_exists( 'openssl_decrypt' ) ) { return ''; }
	$iv = base64_decode( $saved['iv'] ?? '', true );
	$tag = base64_decode( $saved['tag'] ?? '', true );
	$cipher = base64_decode( $saved['cipher'] ?? '', true );
	if ( ! is_string( $iv ) || 12 !== strlen( $iv ) || ! is_string( $tag ) || 16 !== strlen( $tag ) || ! is_string( $cipher ) ) { return ''; }
	$key = hash( 'sha256', wp_salt( 'auth' ) . '|pw-melhorenvio-v1', true );
	return pw_personalizados_melhorenvio_token( openssl_decrypt( $cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, 'pw-melhorenvio-v1' ) );
}

function pw_personalizados_melhorenvio_token_value() {
	$saved = get_option( 'pw_personalizados_melhorenvio_secret', false );
	if ( false === $saved ) { return ''; }
	return pw_personalizados_melhorenvio_unseal( $saved );
}

function pw_personalizados_melhorenvio_user_agent() {
	$name = get_bloginfo( 'name' );
	$email = get_option( 'admin_email' );
	$app = trim( $name ) ? $name . ' - Printway Pedidos' : 'Printway Pedidos';
	return $app . ' (' . ( $email ? $email : 'contato@printway.com.br' ) . ')';
}

function pw_personalizados_melhorenvio_send_error( $error ) {
	$context = $error->get_error_data();
	wp_send_json_error( array( 'message' => $error->get_error_message(), 'code' => $error->get_error_code(), 'http' => is_array( $context ) ? ( $context['http'] ?? 0 ) : 0 ), 422 );
}

/** Chamada genérica autenticada à API do Melhor Envio (produção). */
function pw_personalizados_melhorenvio_call( $token, $method, $path, $body = null ) {
	$args = array(
		'method'  => $method,
		'timeout' => 25,
		'redirection' => 0,
		'sslverify' => true,
		'headers' => array(
			'Accept'        => 'application/json',
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $token,
			'User-Agent'    => pw_personalizados_melhorenvio_user_agent(),
		),
	);
	if ( null !== $body ) { $args['body'] = wp_json_encode( $body ); }
	$response = wp_remote_request( 'https://melhorenvio.com.br/api/v2/me' . $path, $args );
	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'melhorenvio_network', 'O servidor não conseguiu se conectar ao Melhor Envio. Tente novamente; se persistir, verifique a conexão HTTPS da hospedagem.' );
	}
	$status = (int) wp_remote_retrieve_response_code( $response );
	$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	$context = array( 'http' => $status );
	if ( in_array( $status, array( 401, 403 ), true ) ) {
		return new WP_Error( 'melhorenvio_auth_failed', 'O Melhor Envio recusou o Token. Abra "Conectar Melhor Envio" e confira se o Token ainda é válido (gere um novo, se precisar).', $context );
	}
	$message = '';
	if ( is_array( $data ) ) {
		if ( is_string( $data['message'] ?? null ) ) { $message = $data['message']; }
		elseif ( is_string( $data['error'] ?? null ) ) { $message = $data['error']; }
		elseif ( is_array( $data['errors'] ?? null ) ) {
			$parts = array();
			foreach ( $data['errors'] as $field => $errors ) {
				foreach ( (array) $errors as $item ) {
					if ( is_string( $item ) ) { $parts[] = $item; }
					elseif ( is_array( $item ) && is_string( $item['message'] ?? null ) ) { $parts[] = $item['message']; }
				}
			}
			$message = implode( ' ', array_filter( $parts ) );
		}
	}
	if ( ! $message ) {
		$raw_body = (string) wp_remote_retrieve_body( $response );
		if ( $raw_body ) { $message = mb_substr( wp_strip_all_tags( $raw_body ), 0, 300 ); }
	}
	if ( $status < 200 || $status >= 300 ) {
		return new WP_Error( 'melhorenvio_response', $message ? 'O Melhor Envio recusou a solicitação: ' . $message : 'O Melhor Envio retornou uma resposta inesperada (HTTP ' . $status . ').', $context );
	}
	return array( 'data' => $data, 'http' => $status );
}

function pw_personalizados_melhorenvio_status_label( $status ) {
	$map = array(
		'pending' => 'Pendente', 'released' => 'Liberado', 'generated' => 'Gerado', 'posted' => 'Postado',
		'delivered' => 'Entregue', 'canceled' => 'Cancelado', 'cancelled' => 'Cancelado', 'undelivered' => 'Não entregue',
		'lost' => 'Extraviado', 'returned' => 'Devolvido', 'chargeback' => 'Estornado', 'in_transit' => 'Em trânsito',
		'awaiting_pickup' => 'Aguardando coleta', 'ready_to_ship' => 'Pronto para envio',
	);
	$key = strtolower( trim( (string) $status ) );
	return $map[ $key ] ?? ( $status ? ucfirst( str_replace( '_', ' ', $status ) ) : '' );
}

function pw_personalizados_melhorenvio_track() {
	pw_personalizados_require_ajax_access();
	if ( ! check_ajax_referer( 'pw_personalizados_storage', 'nonce', false ) ) { wp_send_json_error( array( 'message' => 'Sua sessão expirou.', 'code' => 'session_expired' ), 403 ); }
	$token = pw_personalizados_melhorenvio_token_value();
	if ( ! $token ) { pw_personalizados_melhorenvio_send_error( new WP_Error( 'melhorenvio_not_configured', 'Salve o Token do Melhor Envio antes de rastrear.' ) ); }
	$raw_ids = $_POST['orders'] ?? array();
	$ids = array();
	if ( is_array( $raw_ids ) ) { foreach ( $raw_ids as $id ) { $clean = sanitize_text_field( (string) $id ); if ( $clean ) { $ids[] = $clean; } } }
	if ( ! $ids ) { pw_personalizados_melhorenvio_send_error( new WP_Error( 'melhorenvio_input', 'Nenhum envio para rastrear.' ) ); }
	$result = pw_personalizados_melhorenvio_call( $token, 'POST', '/shipment/tracking', array( 'orders' => $ids ) );
	if ( is_wp_error( $result ) ) { pw_personalizados_melhorenvio_send_error( $result ); }
	$raw = is_array( $result['data'] ) ? $result['data'] : array();
	$statuses = array();
	foreach ( $raw as $id => $info ) {
		if ( ! is_array( $info ) ) { continue; }
		$raw_status = (string) ( $info['status'] ?? '' );
		// O código de rastreio pode vir como string em 'tracking', como
		// objeto com chave 'code', ou no campo 'protocol' (Jadlog/outros).
		$tracking_raw = $info['tracking'] ?? '';
		if ( is_array( $tracking_raw ) ) {
			$tracking_raw = $tracking_raw['code'] ?? $tracking_raw['tracking'] ?? '';
		}
		if ( ! $tracking_raw && ! empty( $info['protocol'] ) ) {
			$tracking_raw = $info['protocol'];
		}
		// Fallback: busca o ID interno da etiqueta que o site do ME exibe como
		// código de rastreio enquanto a transportadora ainda não gerou o código
		// definitivo (cenário Jadlog em status 'released').
		if ( ! $tracking_raw && ! empty( $info['melhorenvio_shipping_id'] ) ) {
			$tracking_raw = $info['melhorenvio_shipping_id'];
		}
		$statuses[ sanitize_text_field( (string) $id ) ] = array(
			'status'     => pw_personalizados_melhorenvio_status_label( $raw_status ),
			'statusRaw'  => sanitize_text_field( $raw_status ),
			'tracking'   => sanitize_text_field( (string) $tracking_raw ),
		);
	}
	wp_send_json_success( array( 'statuses' => $statuses ) );
}
add_action( 'wp_ajax_pw_personalizados_melhorenvio_track', 'pw_personalizados_melhorenvio_track' );

function pw_personalizados_melhorenvio_cancel_label() {
	pw_personalizados_require_ajax_access();
	if ( ! check_ajax_referer( 'pw_personalizados_storage', 'nonce', false ) ) { wp_send_json_error( array( 'message' => 'Sua sessão expirou.', 'code' => 'session_expired' ), 403 ); }
	$token = pw_personalizados_melhorenvio_token_value();
	if ( ! $token ) { pw_personalizados_melhorenvio_send_error( new WP_Error( 'melhorenvio_not_configured', 'Token do Melhor Envio não configurado.' ) ); }
	$order_id = isset( $_POST['order_id'] ) ? sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) : '';
	if ( ! $order_id ) { pw_personalizados_melhorenvio_send_error( new WP_Error( 'melhorenvio_input', 'Etiqueta inválida para cancelamento.' ) ); }
	$reason = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : 'Cancelado pelo sistema';
	$body = array( 'order' => array( 'id' => $order_id, 'reason_id' => 2, 'description' => mb_substr( $reason, 0, 200 ) ) );
	$result = pw_personalizados_melhorenvio_call( $token, 'POST', '/shipment/cancel', $body );
	if ( is_wp_error( $result ) ) { pw_personalizados_melhorenvio_send_error( $result ); }
	wp_send_json_success( array( 'cancelled' => true ) );
}
add_action( 'wp_ajax_pw_personalizados_melhorenvio_cancel', 'pw_personalizados_melhorenvio_cancel_label' );

function pw_personalizados_melhorenvio_balance() {
	pw_personalizados_require_ajax_access();
	if ( ! check_ajax_referer( 'pw_personalizados_storage', 'nonce', false ) ) { wp_send_json_error( array( 'message' => 'Sua sessão expirou.', 'code' => 'session_expired' ), 403 ); }
	$token = pw_personalizados_melhorenvio_token_value();
	if ( ! $token ) { wp_send_json_success( array( 'configured' => false ) ); }
	$result = pw_personalizados_melhorenvio_call( $token, 'GET', '/balance' );
	if ( is_wp_error( $result ) ) { pw_personalizados_melhorenvio_send_error( $result ); }
	$data = is_array( $result['data'] ) ? $result['data'] : array();
	$balance = $data['balance'] ?? $data['amount'] ?? null;
	wp_send_json_success( array( 'configured' => true, 'balance' => is_numeric( $balance ) ? (float) $balance : null ) );
}
add_action( 'wp_ajax_pw_personalizados_melhorenvio_balance', 'pw_personalizados_melhorenvio_balance' );

function pw_personalizados_melhorenvio_check_token() {
	pw_personalizados_require_ajax_access();
	if ( ! check_ajax_referer( 'pw_personalizados_storage', 'nonce', false ) ) { wp_send_json_error( array( 'message' => 'Sua sessão expirou.', 'code' => 'session_expired' ), 403 ); }
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Somente administradores.', 'code' => 'forbidden' ), 403 ); }
	$token = pw_personalizados_melhorenvio_token_value();
	if ( ! $token ) { wp_send_json_success( array( 'configured' => false, 'valid' => false ) ); }
	$result = pw_personalizados_melhorenvio_call( $token, 'GET', '/shipment/companies' );
	if ( is_wp_error( $result ) ) {
		$invalid = 'melhorenvio_auth_failed' === $result->get_error_code();
		wp_send_json_success( array( 'configured' => true, 'valid' => false, 'message' => $invalid ? 'O Melhor Envio recusou esse Token.' : $result->get_error_message() ) );
	}
	wp_send_json_success( array( 'configured' => true, 'valid' => true ) );
}
add_action( 'wp_ajax_pw_personalizados_melhorenvio_check_token', 'pw_personalizados_melhorenvio_check_token' );

function pw_personalizados_melhorenvio_list_carriers() {
	pw_personalizados_require_ajax_access();
	if ( ! check_ajax_referer( 'pw_personalizados_storage', 'nonce', false ) ) { wp_send_json_error( array( 'message' => 'Sua sessão expirou.', 'code' => 'session_expired' ), 403 ); }
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Somente administradores.', 'code' => 'forbidden' ), 403 ); }
	$token = pw_personalizados_melhorenvio_token_value();
	if ( ! $token ) { pw_personalizados_melhorenvio_send_error( new WP_Error( 'melhorenvio_not_configured', 'Salve o Token do Melhor Envio antes de listar as transportadoras.' ) ); }
	$result = pw_personalizados_melhorenvio_call( $token, 'GET', '/shipment/companies' );
	if ( is_wp_error( $result ) ) { pw_personalizados_melhorenvio_send_error( $result ); }
	$raw = is_array( $result['data'] ) ? $result['data'] : array();
	$carriers = array();
	foreach ( $raw as $company ) {
		if ( ! is_array( $company ) || empty( $company['id'] ) ) { continue; }
		$carriers[] = array( 'id' => (string) $company['id'], 'name' => sanitize_text_field( (string) ( $company['name'] ?? 'Transportadora' ) ) );
	}
	$enabled = get_option( 'pw_personalizados_melhorenvio_carriers', array() );
	wp_send_json_success( array( 'carriers' => $carriers, 'enabled' => is_array( $enabled ) ? array_map( 'strval', $enabled ) : array() ) );
}
add_action( 'wp_ajax_pw_personalizados_melhorenvio_list_carriers', 'pw_personalizados_melhorenvio_list_carriers' );

function pw_personalizados_melhorenvio_save_carriers() {
	pw_personalizados_require_ajax_access();
	if ( ! check_ajax_referer( 'pw_personalizados_storage', 'nonce', false ) ) { wp_send_json_error( array( 'message' => 'Sua sessão expirou.', 'code' => 'session_expired' ), 403 ); }
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Somente administradores.', 'code' => 'forbidden' ), 403 ); }
	$raw = $_POST['carriers'] ?? array();
	$ids = array();
	if ( is_array( $raw ) ) { foreach ( $raw as $id ) { $ids[] = sanitize_text_field( (string) $id ); } }
	update_option( 'pw_personalizados_melhorenvio_carriers', array_values( array_unique( array_filter( $ids ) ) ), false );
	wp_send_json_success( array( 'saved' => true ) );
}
add_action( 'wp_ajax_pw_personalizados_melhorenvio_save_carriers', 'pw_personalizados_melhorenvio_save_carriers' );

function pw_personalizados_melhorenvio_save_token() {
	pw_personalizados_require_ajax_access();
	if ( ! check_ajax_referer( 'pw_personalizados_storage', 'nonce', false ) ) { wp_send_json_error( array( 'message' => 'Sua sessão expirou. Atualize o sistema e tente novamente.', 'code' => 'session_expired' ), 403 ); }
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Somente administradores podem configurar a conexão.', 'code' => 'forbidden' ), 403 ); }
	if ( ! is_ssl() ) { wp_send_json_error( array( 'message' => 'Abra o sistema por HTTPS para salvar a conexão.', 'code' => 'melhorenvio_https' ), 403 ); }
	$raw = $_POST['melhorenvio_token'] ?? '';
	$token = pw_personalizados_melhorenvio_token( is_string( $raw ) ? wp_unslash( $raw ) : '' );
	unset( $_POST['melhorenvio_token'], $_REQUEST['melhorenvio_token'] );
	if ( ! $token ) { pw_personalizados_melhorenvio_send_error( new WP_Error( 'melhorenvio_token_format', 'Cole o Token gerado em Gerenciar → Tokens no painel do Melhor Envio.' ) ); }
	$sealed = pw_personalizados_melhorenvio_seal( $token );
	if ( is_wp_error( $sealed ) ) { pw_personalizados_melhorenvio_send_error( $sealed ); }
	update_option( 'pw_personalizados_melhorenvio_secret', $sealed, false );
	if ( get_option( 'pw_personalizados_melhorenvio_secret' ) !== $sealed ) { pw_personalizados_melhorenvio_send_error( new WP_Error( 'melhorenvio_save_failed', 'Não foi possível salvar a conexão no servidor.' ) ); }
	wp_send_json_success( array( 'connected' => true ) );
}
add_action( 'wp_ajax_pw_personalizados_melhorenvio_connect', 'pw_personalizados_melhorenvio_save_token' );

function pw_personalizados_melhorenvio_package_input() {
	$values = array();
	foreach ( array( 'height' => 1000, 'width' => 1000, 'length' => 1000, 'weight' => 1000, 'value' => 10000000 ) as $field => $max ) {
		$raw = $_POST[ $field ] ?? '';
		$raw = is_string( $raw ) ? str_replace( ',', '.', wp_unslash( $raw ) ) : '';
		if ( ! is_numeric( $raw ) || (float) $raw <= 0 || (float) $raw > $max ) { return new WP_Error( 'melhorenvio_input', 'Informe medidas, peso e valor declarado maiores que zero.' ); }
		$values[ $field ] = (float) $raw;
	}
	return $values;
}

function pw_personalizados_melhorenvio_dimensions_input() {
	$values = array();
	foreach ( array( 'origin_cep', 'destination_cep' ) as $field ) {
		$raw = $_POST[ $field ] ?? '';
		$values[ $field ] = is_string( $raw ) ? preg_replace( '/\D+/', '', wp_unslash( $raw ) ) : '';
		if ( 8 !== strlen( $values[ $field ] ) ) { return new WP_Error( 'melhorenvio_input', 'Informe os CEPs de origem e destino com oito números.' ); }
	}
	$package = pw_personalizados_melhorenvio_package_input();
	if ( is_wp_error( $package ) ) { return $package; }
	return array_merge( $values, $package );
}

function pw_personalizados_melhorenvio_quote() {
	pw_personalizados_require_ajax_access();
	if ( ! check_ajax_referer( 'pw_personalizados_storage', 'nonce', false ) ) { wp_send_json_error( array( 'message' => 'Sua sessão expirou. Atualize o sistema e tente novamente.', 'code' => 'session_expired' ), 403 ); }
	$values = pw_personalizados_melhorenvio_dimensions_input();
	if ( is_wp_error( $values ) ) { pw_personalizados_melhorenvio_send_error( $values ); }
	$token = pw_personalizados_melhorenvio_token_value();
	if ( ! $token ) { pw_personalizados_melhorenvio_send_error( new WP_Error( 'melhorenvio_not_configured', 'Não há um Token do Melhor Envio salvo. Abra "Conectar Melhor Envio" para colar o seu.' ) ); }
	$body = array(
		'from' => array( 'postal_code' => $values['origin_cep'] ),
		'to'   => array( 'postal_code' => $values['destination_cep'] ),
		'volumes' => array( array(
			'height' => round( $values['height'], 2 ), 'width' => round( $values['width'], 2 ),
			'length' => round( $values['length'], 2 ), 'weight' => round( $values['weight'], 3 ),
			'insurance' => round( $values['value'], 2 ),
		) ),
		'options' => array( 'receipt' => false, 'own_hand' => false ),
	);
	$result = pw_personalizados_melhorenvio_call( $token, 'POST', '/shipment/calculate', $body );
	if ( is_wp_error( $result ) ) { pw_personalizados_melhorenvio_send_error( $result ); }
	$enabled_carriers = get_option( 'pw_personalizados_melhorenvio_carriers', array() );
	$enabled_carriers = is_array( $enabled_carriers ) ? array_map( 'strval', $enabled_carriers ) : array();
	$raw = is_array( $result['data'] ) ? $result['data'] : array();
	$services = array();
	foreach ( $raw as $service ) {
		if ( ! is_array( $service ) || ! empty( $service['error'] ) ) { continue; }
		$price = $service['custom_price'] ?? $service['price'] ?? null;
		if ( ! is_numeric( $price ) ) { continue; }
		$company = is_array( $service['company'] ?? null ) ? $service['company'] : array();
		$carrier_id = (string) ( $company['id'] ?? '' );
		if ( $enabled_carriers && $carrier_id && ! in_array( $carrier_id, $enabled_carriers, true ) ) { continue; }
		$services[] = array(
			'code' => (string) ( $service['id'] ?? '' ),
			'description' => (string) ( $service['name'] ?? 'Serviço de entrega' ),
			'carrier' => (string) ( $company['name'] ?? '' ),
			'price' => round( (float) $price, 2 ),
			'originalPrice' => is_numeric( $service['price'] ?? null ) ? round( (float) $service['price'], 2 ) : 0,
			'deliveryTime' => max( 0, (int) ( $service['custom_delivery_time'] ?? $service['delivery_time'] ?? 0 ) ),
		);
	}
	if ( ! $services ) { pw_personalizados_melhorenvio_send_error( new WP_Error( 'melhorenvio_no_services', 'O Melhor Envio não retornou serviços utilizáveis para esses dados. Confira os CEPs, medidas e peso informados.' ) ); }
	usort( $services, static function ( $a, $b ) { return $a['price'] <=> $b['price']; } );
	wp_send_json_success( array( 'services' => $services ) );
}
add_action( 'wp_ajax_pw_personalizados_melhorenvio_quote', 'pw_personalizados_melhorenvio_quote' );

function pw_personalizados_melhorenvio_party_input( $prefix ) {
	$fields = array(
		'name' => 200, 'document' => 20, 'phone' => 20, 'street' => 200, 'number' => 20,
		'complement' => 100, 'neighborhood' => 120, 'city' => 120, 'state' => 2,
	);
	$values = array();
	foreach ( $fields as $field => $max_length ) {
		$raw = $_POST[ $prefix . '_' . $field ] ?? '';
		$clean = is_string( $raw ) ? sanitize_text_field( wp_unslash( $raw ) ) : '';
		if ( 'complement' === $field ) { $values[ $field ] = mb_substr( $clean, 0, $max_length ); continue; }
		if ( '' === trim( $clean ) ) { return new WP_Error( 'melhorenvio_input', 'Preencha todos os dados obrigatórios de ' . ( 'sender' === $prefix ? 'remetente' : 'destinatário' ) . '.' ); }
		$values[ $field ] = mb_substr( trim( $clean ), 0, $max_length );
	}
	$cep_raw = $_POST[ $prefix . '_cep' ] ?? '';
	$values['cep'] = is_string( $cep_raw ) ? preg_replace( '/\D+/', '', wp_unslash( $cep_raw ) ) : '';
	if ( 8 !== strlen( $values['cep'] ) ) { return new WP_Error( 'melhorenvio_input', 'Informe o CEP de ' . ( 'sender' === $prefix ? 'remetente' : 'destinatário' ) . ' com oito números.' ); }
	if ( 2 !== strlen( $values['state'] ) ) { return new WP_Error( 'melhorenvio_input', 'Informe a UF com duas letras.' ); }
	return $values;
}

function pw_personalizados_melhorenvio_party_payload( $party ) {
	$digits = preg_replace( '/\D+/', '', $party['document'] );
	$payload = array(
		'name' => $party['name'], 'phone' => $digits ? preg_replace( '/\D+/', '', $party['phone'] ) : $party['phone'],
		'address' => $party['street'], 'complement' => $party['complement'], 'number' => $party['number'],
		'district' => $party['neighborhood'], 'city' => $party['city'], 'postal_code' => $party['cep'],
		'state_abbr' => strtoupper( $party['state'] ), 'country_id' => 'BR',
	);
	if ( 14 === strlen( $digits ) ) {
		$payload['company_document'] = $digits;
		$payload['state_register'] = 'ISENTO';
	} else {
		$payload['document'] = $digits;
	}
	return $payload;
}

function pw_personalizados_melhorenvio_generate_label() {
	pw_personalizados_require_ajax_access();
	if ( ! check_ajax_referer( 'pw_personalizados_storage', 'nonce', false ) ) { wp_send_json_error( array( 'message' => 'Sua sessão expirou. Atualize o sistema e tente novamente.', 'code' => 'session_expired' ), 403 ); }
	$service_id = isset( $_POST['service_id'] ) ? (int) $_POST['service_id'] : 0;
	if ( $service_id <= 0 ) { pw_personalizados_melhorenvio_send_error( new WP_Error( 'melhorenvio_input', 'Busque e selecione uma opção de frete antes de gerar a etiqueta.' ) ); }
	$dimensions = pw_personalizados_melhorenvio_package_input();
	if ( is_wp_error( $dimensions ) ) { pw_personalizados_melhorenvio_send_error( $dimensions ); }
	$sender = pw_personalizados_melhorenvio_party_input( 'sender' );
	if ( is_wp_error( $sender ) ) { pw_personalizados_melhorenvio_send_error( $sender ); }
	$recipient = pw_personalizados_melhorenvio_party_input( 'recipient' );
	if ( is_wp_error( $recipient ) ) { pw_personalizados_melhorenvio_send_error( $recipient ); }
	$token = pw_personalizados_melhorenvio_token_value();
	if ( ! $token ) { pw_personalizados_melhorenvio_send_error( new WP_Error( 'melhorenvio_not_configured', 'Não há um Token do Melhor Envio salvo. Abra "Conectar Melhor Envio" para colar o seu.' ) ); }

	$cart_body = array(
		'service' => $service_id,
		'from' => pw_personalizados_melhorenvio_party_payload( $sender ),
		'to' => pw_personalizados_melhorenvio_party_payload( $recipient ),
		'products' => array( array( 'name' => 'Produtos personalizados', 'quantity' => '1', 'unitary_value' => (string) round( $dimensions['value'], 2 ) ) ),
		'volumes' => array( array( 'height' => round( $dimensions['height'], 2 ), 'width' => round( $dimensions['width'], 2 ), 'length' => round( $dimensions['length'], 2 ), 'weight' => round( $dimensions['weight'], 3 ) ) ),
		'options' => array( 'platform' => get_bloginfo( 'name' ) ? get_bloginfo( 'name' ) : 'Printway', 'insurance_value' => round( $dimensions['value'], 2 ), 'receipt' => false, 'own_hand' => false, 'reverse' => false ),
	);
	$cart = pw_personalizados_melhorenvio_call( $token, 'POST', '/cart', $cart_body );
	if ( is_wp_error( $cart ) ) { pw_personalizados_melhorenvio_send_error( $cart ); }
	$order_id = is_array( $cart['data'] ) ? ( $cart['data']['id'] ?? '' ) : '';
	if ( ! $order_id ) { pw_personalizados_melhorenvio_send_error( new WP_Error( 'melhorenvio_response', 'O Melhor Envio não retornou o identificador da etiqueta ao inserir no carrinho.' ) ); }

	$checkout = pw_personalizados_melhorenvio_call( $token, 'POST', '/shipment/checkout', array( 'orders' => array( $order_id ) ) );
	if ( is_wp_error( $checkout ) ) {
		$message = $checkout->get_error_message();
		if ( false !== stripos( $message, 'saldo' ) || false !== stripos( $message, 'insufficient' ) ) {
			pw_personalizados_melhorenvio_send_error( new WP_Error( 'melhorenvio_balance', 'Saldo insuficiente na carteira do Melhor Envio para pagar esta etiqueta. Adicione saldo no painel do Melhor Envio e tente novamente.' ) );
		}
		pw_personalizados_melhorenvio_send_error( $checkout );
	}

	$generate = pw_personalizados_melhorenvio_call( $token, 'POST', '/shipment/generate', array( 'orders' => array( $order_id ) ) );
	if ( is_wp_error( $generate ) ) { pw_personalizados_melhorenvio_send_error( $generate ); }

	$print = pw_personalizados_melhorenvio_call( $token, 'POST', '/shipment/print', array( 'orders' => array( $order_id ), 'mode' => 'public' ) );
	if ( is_wp_error( $print ) ) { pw_personalizados_melhorenvio_send_error( $print ); }
	$print_data = is_array( $print['data'] ) ? $print['data'] : array();
	$label_url = $print_data['url'] ?? ( is_array( $print_data['urls'] ?? null ) ? ( $print_data['urls']['pdf'] ?? '' ) : '' );

	$tracking = '';
	$generate_data = is_array( $generate['data'] ) ? $generate['data'] : array();
	if ( isset( $generate_data[0]['tracking'] ) ) { $tracking = (string) $generate_data[0]['tracking']; }
	elseif ( isset( $generate_data['tracking'] ) ) { $tracking = (string) $generate_data['tracking']; }

	wp_send_json_success( array( 'tracking' => sanitize_text_field( $tracking ), 'labelUrl' => esc_url_raw( (string) $label_url ), 'orderId' => sanitize_text_field( (string) $order_id ) ) );
}
add_action( 'wp_ajax_pw_personalizados_melhorenvio_generate_label', 'pw_personalizados_melhorenvio_generate_label' );
