<?php
/** Cotação Frenet: credenciais privadas, validação real e respostas sem segredos. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function pw_personalizados_frenet_token( $value ) {
	if ( ! is_string( $value ) ) { return ''; }
	$token = trim( $value );
	// Consumer Key/Secret são credenciais do WooCommerce, não da cotação.
	if ( preg_match( '/^(ck_|cs_)/i', $token ) || ! preg_match( '/^[a-zA-Z0-9._~+\/=\-]{16,256}$/D', $token ) ) { return ''; }
	return $token;
}

function pw_personalizados_frenet_seal( $token ) {
	if ( ! function_exists( 'openssl_encrypt' ) || ! in_array( 'aes-256-gcm', openssl_get_cipher_methods(), true ) ) {
		return new WP_Error( 'frenet_crypto', 'O servidor precisa habilitar OpenSSL com AES-256-GCM para guardar o Token com segurança.' );
	}
	try {
		$iv = random_bytes( 12 );
		$tag = '';
		$key = hash( 'sha256', wp_salt( 'auth' ) . '|pw-frenet-v1', true );
		$cipher = openssl_encrypt( $token, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, 'pw-frenet-v1' );
		if ( false === $cipher ) { throw new RuntimeException(); }
		return array( 'v' => 1, 'iv' => base64_encode( $iv ), 'tag' => base64_encode( $tag ), 'cipher' => base64_encode( $cipher ) );
	} catch ( Throwable $error ) {
		return new WP_Error( 'frenet_crypto', 'Não foi possível proteger o Token. Nenhuma credencial foi alterada.' );
	}
}

function pw_personalizados_frenet_unseal( $saved ) {
	// Token salvo como string pura (via modal de tokens, sem criptografia AES)
	if ( is_string( $saved ) ) { return pw_personalizados_frenet_token( $saved ); }
	if ( ! is_array( $saved ) || 1 !== ( $saved['v'] ?? null ) || ! function_exists( 'openssl_decrypt' ) ) { return ''; }
	$iv = base64_decode( $saved['iv'] ?? '', true );
	$tag = base64_decode( $saved['tag'] ?? '', true );
	$cipher = base64_decode( $saved['cipher'] ?? '', true );
	if ( ! is_string( $iv ) || 12 !== strlen( $iv ) || ! is_string( $tag ) || 16 !== strlen( $tag ) || ! is_string( $cipher ) ) { return ''; }
	$key = hash( 'sha256', wp_salt( 'auth' ) . '|pw-frenet-v1', true );
	return pw_personalizados_frenet_token( openssl_decrypt( $cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, 'pw-frenet-v1' ) );
}

/** Usa instâncias existentes e habilitadas; nunca opções órfãs de zonas apagadas. */
function pw_personalizados_frenet_sources() {
	$saved = get_option( 'pw_personalizados_frenet_secret', false );
	if ( false !== $saved ) {
		$token = pw_personalizados_frenet_unseal( $saved );
		return $token ? array( array( 'token' => $token, 'source' => 'Conexão do sistema', 'id' => 'system' ) ) : new WP_Error( 'frenet_secret_unreadable', 'A conexão salva precisa ser refeita. Abra Conectar Frenet e valide o Token novamente.' );
	}
	$sources = array();
	$has_instances = false;
	if ( class_exists( 'WC_Shipping_Zones' ) && class_exists( 'WC_Shipping_Zone' ) ) {
		$zones = WC_Shipping_Zones::get_zones();
		$zone_ids = array( 0 );
		foreach ( $zones as $zone ) { $zone_ids[] = (int) $zone['zone_id']; }
		foreach ( array_unique( $zone_ids ) as $zone_id ) {
			$zone = new WC_Shipping_Zone( $zone_id );
			foreach ( $zone->get_shipping_methods( false ) as $method ) {
				if ( 'frenet' !== $method->id ) { continue; }
				$has_instances = true;
				if ( 'yes' !== $method->enabled ) { continue; }
				$token = pw_personalizados_frenet_token( $method->get_option( 'token', '' ) );
				if ( $token ) {
					$sources[] = array( 'token' => $token, 'source' => 'WooCommerce · zona ' . $zone_id . ' · método ' . (int) $method->instance_id, 'id' => 'wc-' . (int) $method->instance_id );
				}
			}
		}
	}
	if ( ! $has_instances ) {
		$legacy = get_option( 'woocommerce_frenet_settings', array() );
		$token = is_array( $legacy ) && 'no' !== ( $legacy['enabled'] ?? 'yes' ) ? pw_personalizados_frenet_token( $legacy['token'] ?? '' ) : '';
		if ( $token ) { $sources[] = array( 'token' => $token, 'source' => 'WooCommerce · configuração global', 'id' => 'wc-global' ); }
	}
	// Várias zonas da mesma conta não representam várias credenciais.
	$unique = array();
	foreach ( $sources as $source ) { $unique[ hash( 'sha256', $source['token'] ) ] = $source; }
	return array_values( $unique );
}

function pw_personalizados_frenet_credentials() {
	$sources = pw_personalizados_frenet_sources();
	return ! is_wp_error( $sources ) && 1 === count( $sources ) ? $sources[0] : array();
}

function pw_personalizados_frenet_select_source() {
	$sources = pw_personalizados_frenet_sources();
	if ( is_wp_error( $sources ) ) { return $sources; }
	if ( ! $sources ) { return new WP_Error( 'frenet_not_configured', 'Não há um Token válido em um método Frenet ativo. Abra Conectar Frenet para testar o Token da sua conta.' ); }
	if ( count( $sources ) > 1 ) { return new WP_Error( 'frenet_ambiguous', 'Existem Tokens diferentes nas zonas ativas. Abra Conectar Frenet e informe o Token da conta que este sistema deve usar.' ); }
	return $sources[0];
}

function pw_personalizados_frenet_input() {
	$values = array();
	foreach ( array( 'origin_cep', 'destination_cep' ) as $field ) {
		$raw = $_POST[ $field ] ?? '';
		$values[ $field ] = is_string( $raw ) ? preg_replace( '/\D+/', '', wp_unslash( $raw ) ) : '';
		if ( 8 !== strlen( $values[ $field ] ) ) { return new WP_Error( 'frenet_input', 'Informe os CEPs de origem e destino com oito números.' ); }
	}
	foreach ( array( 'height' => 1000, 'width' => 1000, 'length' => 1000, 'weight' => 1000, 'value' => 10000000 ) as $field => $max ) {
		$raw = $_POST[ $field ] ?? '';
		$raw = is_string( $raw ) ? str_replace( ',', '.', wp_unslash( $raw ) ) : '';
		if ( ! is_numeric( $raw ) || (float) $raw <= 0 || (float) $raw > $max ) { return new WP_Error( 'frenet_input', 'Informe medidas, peso e valor declarado maiores que zero.' ); }
		$values[ $field ] = (float) $raw;
	}
	return $values;
}

function pw_personalizados_frenet_request( $token, $values ) {
	$body = array(
		'SellerCEP' => $values['origin_cep'], 'RecipientCEP' => $values['destination_cep'],
		'RecipientCountry' => 'BR', 'ShipmentInvoiceValue' => round( $values['value'], 2 ),
		'ShippingItemArray' => array( array( 'Weight' => round( $values['weight'], 3 ), 'Length' => round( $values['length'], 2 ), 'Height' => round( $values['height'], 2 ), 'Width' => round( $values['width'], 2 ), 'Quantity' => 1 ) ),
	);
	$response = wp_remote_post( 'https://api.frenet.com.br/shipping/quote', array(
		'timeout' => 15, 'redirection' => 0, 'sslverify' => true,
		'headers' => array( 'Accept' => 'application/json', 'Content-Type' => 'application/json', 'token' => $token ),
		'body' => wp_json_encode( $body ),
	) );
	if ( is_wp_error( $response ) ) { return new WP_Error( 'frenet_network', 'O servidor não conseguiu se conectar à Frenet. Tente novamente; se persistir, verifique a conexão HTTPS da hospedagem.' ); }
	$status = (int) wp_remote_retrieve_response_code( $response );
	$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	$message = is_array( $data ) && is_string( $data['Message'] ?? null ) ? $data['Message'] : '';
	$context = array( 'http' => $status );
	if ( in_array( $status, array( 401, 403 ), true ) || preg_match( '/acesso negado|unauthorized|forbidden|invalid.{0,10}token|token.{0,10}inv[aá]lid/iu', $message ) ) {
		return new WP_Error( 'frenet_auth_failed', 'A cotação foi recusada pela Frenet. Use Conectar Frenet para testar o Token de Dados cadastrais → Chaves de acesso da sua conta.', $context );
	}
	if ( $status < 200 || $status >= 300 || ! is_array( $data ) ) { return new WP_Error( 'frenet_response', 'A Frenet retornou uma resposta inesperada. Tente novamente em instantes.', $context ); }
	$raw = $data['ShippingSevicesArray'] ?? $data['ShippingServicesArray'] ?? array();
	if ( ! is_array( $raw ) ) { $raw = array(); }
	if ( isset( $raw['ShippingPrice'] ) ) { $raw = array( $raw ); }
	$services = array();
	foreach ( $raw as $service ) {
		if ( ! is_array( $service ) || in_array( strtolower( (string) ( $service['Error'] ?? '' ) ), array( 'true', '1', 'yes' ), true ) ) { continue; }
		$price = str_replace( ',', '.', (string) ( $service['ShippingPrice'] ?? '' ) );
		if ( ! is_numeric( $price ) || (float) $price < 0 ) { continue; }
		$original = str_replace( ',', '.', (string) ( $service['OriginalShippingPrice'] ?? '' ) );
		// Nunca repassa resposta bruta/segredos da API ao navegador.
		$clean = static function ( $value ) use ( $token ) { return sanitize_text_field( str_replace( $token, '[protegido]', is_scalar( $value ) ? (string) $value : '' ) ); };
		$services[] = array( 'code' => $clean( $service['ServiceCode'] ?? '' ), 'description' => $clean( $service['ServiceDescription'] ?? 'Serviço de entrega' ), 'carrier' => $clean( $service['Carrier'] ?? '' ), 'price' => round( (float) $price, 2 ), 'originalPrice' => is_numeric( $original ) ? round( (float) $original, 2 ) : 0, 'deliveryTime' => max( 0, (int) ( $service['DeliveryTime'] ?? 0 ) ) );
	}
	if ( ! $services ) { return new WP_Error( 'frenet_no_services', 'A Frenet não retornou serviços utilizáveis. Confira CEP, medidas, peso e transportadoras habilitadas na conta.', $context ); }
	usort( $services, static function ( $a, $b ) { return $a['price'] <=> $b['price']; } );
	return array( 'services' => $services, 'http' => $status );
}

function pw_personalizados_frenet_send_error( $error, $source = '' ) {
	$context = $error->get_error_data();
	wp_send_json_error( array( 'message' => $error->get_error_message(), 'code' => $error->get_error_code(), 'http' => is_array( $context ) ? ( $context['http'] ?? 0 ) : 0, 'source' => $source ), 422 );
}

function pw_personalizados_frenet_quote() {
	pw_personalizados_require_ajax_access();
	if ( ! check_ajax_referer( 'pw_personalizados_storage', 'nonce', false ) ) { wp_send_json_error( array( 'message' => 'Sua sessão expirou. Atualize o sistema e tente novamente.', 'code' => 'session_expired' ), 403 ); }
	$values = pw_personalizados_frenet_input();
	if ( is_wp_error( $values ) ) { pw_personalizados_frenet_send_error( $values ); }
	$source = pw_personalizados_frenet_select_source();
	if ( is_wp_error( $source ) ) { pw_personalizados_frenet_send_error( $source ); }
	$result = pw_personalizados_frenet_request( $source['token'], $values );
	if ( is_wp_error( $result ) ) { pw_personalizados_frenet_send_error( $result, $source['source'] ); }
	wp_send_json_success( array( 'services' => $result['services'], 'http' => $result['http'], 'source' => $source['source'], 'connected' => false ) );
}
add_action( 'wp_ajax_pw_personalizados_frenet_quote', 'pw_personalizados_frenet_quote' );

/** Salva o Token Frenet direto (sem exigir uma cotação de teste). Somente administradores. Fica disponível para todos os usuários consultarem o CEP. */
function pw_personalizados_frenet_save_token() {
	pw_personalizados_require_ajax_access();
	if ( ! check_ajax_referer( 'pw_personalizados_storage', 'nonce', false ) ) { wp_send_json_error( array( 'message' => 'Sua sessão expirou. Atualize o sistema e tente novamente.', 'code' => 'session_expired' ), 403 ); }
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Somente administradores podem configurar a conexão.', 'code' => 'forbidden' ), 403 ); }
	if ( ! is_ssl() ) { wp_send_json_error( array( 'message' => 'Abra o sistema por HTTPS para salvar a conexão.', 'code' => 'frenet_https' ), 403 ); }
	$raw = $_POST['frenet_token'] ?? '';
	$token = pw_personalizados_frenet_token( is_string( $raw ) ? wp_unslash( $raw ) : '' );
	unset( $_POST['frenet_token'], $_REQUEST['frenet_token'] );
	if ( ! $token ) { pw_personalizados_frenet_send_error( new WP_Error( 'frenet_token_format', 'Cole o Token Frenet de Dados cadastrais → Chaves de acesso. Consumer Key e Consumer Secret não servem para cotar frete.' ) ); }
	$sealed = pw_personalizados_frenet_seal( $token );
	if ( is_wp_error( $sealed ) ) { pw_personalizados_frenet_send_error( $sealed ); }
	update_option( 'pw_personalizados_frenet_secret', $sealed, false );
	if ( get_option( 'pw_personalizados_frenet_secret' ) !== $sealed ) { pw_personalizados_frenet_send_error( new WP_Error( 'frenet_save_failed', 'Não foi possível salvar a conexão no servidor.' ) ); }
	wp_send_json_success( array( 'source' => 'Conexão do sistema', 'connected' => true ) );
}
add_action( 'wp_ajax_pw_personalizados_frenet_connect', 'pw_personalizados_frenet_save_token' );

/** Cotação unificada Melhor Envio + Frenet em uma única chamada AJAX. */
function pw_personalizados_shipping_quote_combined() {
	pw_personalizados_require_ajax_access();
	if ( ! check_ajax_referer( 'pw_personalizados_storage', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => 'Sessão expirada.', 'code' => 'session_expired' ), 403 );
	}
	$values = pw_personalizados_frenet_input();
	if ( is_wp_error( $values ) ) { wp_send_json_error( array( 'message' => $values->get_error_message() ), 422 ); }
	$out = array( 'services' => array(), 'me_status' => 'unconfigured', 'frenet_status' => 'unconfigured', 'me_balance' => null, 'me_error' => '', 'frenet_error' => '' );

	// --- Melhor Envio ---
	$me_token = function_exists( 'pw_personalizados_melhorenvio_token_value' ) ? pw_personalizados_melhorenvio_token_value() : '';
	if ( $me_token ) {
		$bal = pw_personalizados_melhorenvio_call( $me_token, 'GET', '/balance' );
		if ( ! is_wp_error( $bal ) ) {
			$bd = is_array( $bal['data'] ?? null ) ? $bal['data'] : array();
			$out['me_balance'] = is_numeric( $bd['balance'] ?? null ) ? round( (float) $bd['balance'], 2 ) : ( is_numeric( $bd['amount'] ?? null ) ? round( (float) $bd['amount'], 2 ) : null );
		}
		$me_body = array(
			'from'    => array( 'postal_code' => $values['origin_cep'] ),
			'to'      => array( 'postal_code' => $values['destination_cep'] ),
			'volumes' => array( array( 'height' => round( $values['height'], 2 ), 'width' => round( $values['width'], 2 ), 'length' => round( $values['length'], 2 ), 'weight' => round( $values['weight'], 3 ), 'insurance' => round( $values['value'], 2 ) ) ),
			'options' => array( 'receipt' => false, 'own_hand' => false ),
		);
		$me_r = pw_personalizados_melhorenvio_call( $me_token, 'POST', '/shipment/calculate', $me_body );
		if ( is_wp_error( $me_r ) ) {
			$out['me_status'] = 'error';
			$out['me_error']  = $me_r->get_error_message();
		} else {
			$out['me_status'] = 'ok';
			$enabled = get_option( 'pw_personalizados_melhorenvio_carriers', array() );
			$enabled = is_array( $enabled ) ? array_map( 'strval', $enabled ) : array();
			foreach ( (array) ( $me_r['data'] ?? array() ) as $svc ) {
				if ( ! is_array( $svc ) || ! empty( $svc['error'] ) ) { continue; }
				$price = $svc['custom_price'] ?? $svc['price'] ?? null;
				if ( ! is_numeric( $price ) ) { continue; }
				$co  = is_array( $svc['company'] ?? null ) ? $svc['company'] : array();
				$cid = (string) ( $co['id'] ?? '' );
				if ( $enabled && $cid && ! in_array( $cid, $enabled, true ) ) { continue; }
				$out['services'][] = array( 'platform' => 'me', 'code' => (string) ( $svc['id'] ?? '' ), 'description' => (string) ( $svc['name'] ?? 'Serviço de entrega' ), 'carrier' => (string) ( $co['name'] ?? '' ), 'logo' => (string) ( $co['picture'] ?? '' ), 'price' => round( (float) $price, 2 ), 'originalPrice' => is_numeric( $svc['price'] ?? null ) ? round( (float) $svc['price'], 2 ) : 0, 'deliveryTime' => max( 0, (int) ( $svc['custom_delivery_time'] ?? $svc['delivery_time'] ?? 0 ) ) );
			}
		}
	}

	// --- Frenet ---
	$fr_src = pw_personalizados_frenet_select_source();
	if ( ! is_wp_error( $fr_src ) && ! empty( $fr_src['token'] ) ) {
		$fr_r = pw_personalizados_frenet_request( $fr_src['token'], $values );
		if ( is_wp_error( $fr_r ) ) {
			$out['frenet_status'] = 'error';
			$out['frenet_error']  = $fr_r->get_error_message();
		} else {
			$out['frenet_status'] = 'ok';
			foreach ( $fr_r['services'] as $svc ) { $out['services'][] = array_merge( $svc, array( 'platform' => 'frenet' ) ); }
		}
	} elseif ( is_wp_error( $fr_src ) ) {
		$out['frenet_status'] = 'error';
		$out['frenet_error']  = $fr_src->get_error_message();
	}

	if ( ! $out['services'] ) {
		$errs = array();
		if ( $out['me_error'] )     { $errs[] = 'Melhor Envio: ' . $out['me_error']; }
		if ( $out['frenet_error'] ) { $errs[] = 'Frenet: ' . $out['frenet_error']; }
		if ( ! $errs )              { $errs[] = 'Configure Melhor Envio ou Frenet em Configurações → Tokens.'; }
		wp_send_json_error( array( 'message' => implode( ' · ', $errs ), 'me_status' => $out['me_status'], 'frenet_status' => $out['frenet_status'], 'me_error' => $out['me_error'], 'frenet_error' => $out['frenet_error'] ), 422 );
	}
	usort( $out['services'], static function( $a, $b ) { return $a['price'] <=> $b['price']; } );
	wp_send_json_success( $out );
}
add_action( 'wp_ajax_pw_personalizados_shipping_quote_combined', 'pw_personalizados_shipping_quote_combined' );
