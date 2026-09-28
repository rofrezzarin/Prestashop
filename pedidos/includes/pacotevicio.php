<?php
/**
 * Integração PacoteVício (via RapidAPI): rastreio DETALHADO (passo a passo)
 * dos Correios, usado só pra enriquecer o painel expandido de "Etiquetas
 * geradas" — Melhor Envio não fornece esse nível de detalhe, e o site
 * público Melhor Rastreio não pode ser embutido nem consultado pelo
 * servidor (bloqueia iframe e tem proteção anti-robô). PacoteVício é um
 * serviço de terceiros de verdade, acessado via gateway do RapidAPI
 * (host correios-rastreamento-de-encomendas.p.rapidapi.com), com plano
 * gratuito documentado (1.000 consultas/mês).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function pw_personalizados_pacotevicio_key_sanitize( $value ) {
	if ( ! is_string( $value ) ) { return ''; }
	$key = trim( $value );
	if ( strlen( $key ) < 10 || strlen( $key ) > 500 ) { return ''; }
	return $key;
}

function pw_personalizados_pacotevicio_seal( $key ) {
	if ( ! function_exists( 'openssl_encrypt' ) || ! in_array( 'aes-256-gcm', openssl_get_cipher_methods(), true ) ) {
		return new WP_Error( 'pacotevicio_crypto', 'O servidor precisa habilitar OpenSSL com AES-256-GCM para guardar a chave com segurança.' );
	}
	try {
		$iv = random_bytes( 12 );
		$tag = '';
		$enc_key = hash( 'sha256', wp_salt( 'auth' ) . '|pw-pacotevicio-v1', true );
		$cipher = openssl_encrypt( $key, 'aes-256-gcm', $enc_key, OPENSSL_RAW_DATA, $iv, $tag, 'pw-pacotevicio-v1' );
		if ( false === $cipher ) { throw new RuntimeException(); }
		return array( 'v' => 1, 'iv' => base64_encode( $iv ), 'tag' => base64_encode( $tag ), 'cipher' => base64_encode( $cipher ) );
	} catch ( Throwable $error ) {
		return new WP_Error( 'pacotevicio_crypto', 'Não foi possível proteger a chave. Nenhuma credencial foi alterada.' );
	}
}

function pw_personalizados_pacotevicio_unseal( $saved ) {
	if ( ! is_array( $saved ) || 1 !== ( $saved['v'] ?? null ) || ! function_exists( 'openssl_decrypt' ) ) { return ''; }
	$iv = base64_decode( $saved['iv'] ?? '', true );
	$tag = base64_decode( $saved['tag'] ?? '', true );
	$cipher = base64_decode( $saved['cipher'] ?? '', true );
	if ( ! is_string( $iv ) || 12 !== strlen( $iv ) || ! is_string( $tag ) || 16 !== strlen( $tag ) || ! is_string( $cipher ) ) { return ''; }
	$enc_key = hash( 'sha256', wp_salt( 'auth' ) . '|pw-pacotevicio-v1', true );
	return pw_personalizados_pacotevicio_key_sanitize( openssl_decrypt( $cipher, 'aes-256-gcm', $enc_key, OPENSSL_RAW_DATA, $iv, $tag, 'pw-pacotevicio-v1' ) );
}

function pw_personalizados_pacotevicio_key_value() {
	$saved = get_option( 'pw_personalizados_pacotevicio_key', false );
	if ( false === $saved ) { return ''; }
	return pw_personalizados_pacotevicio_unseal( $saved );
}

function pw_personalizados_pacotevicio_send_error( $error ) {
	wp_send_json_error( array( 'message' => $error->get_error_message(), 'code' => $error->get_error_code() ), 422 );
}

function pw_personalizados_pacotevicio_save_key() {
	pw_personalizados_require_ajax_access();
	if ( ! check_ajax_referer( 'pw_personalizados_storage', 'nonce', false ) ) { wp_send_json_error( array( 'message' => 'Sua sessão expirou. Atualize o sistema e tente novamente.', 'code' => 'session_expired' ), 403 ); }
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Somente administradores podem configurar a conexão.', 'code' => 'forbidden' ), 403 ); }
	if ( ! is_ssl() ) { wp_send_json_error( array( 'message' => 'Abra o sistema por HTTPS para salvar a conexão.', 'code' => 'pacotevicio_https' ), 403 ); }
	$raw = $_POST['pacotevicio_key'] ?? '';
	$key = pw_personalizados_pacotevicio_key_sanitize( is_string( $raw ) ? wp_unslash( $raw ) : '' );
	unset( $_POST['pacotevicio_key'], $_REQUEST['pacotevicio_key'] );
	if ( ! $key ) { pw_personalizados_pacotevicio_send_error( new WP_Error( 'pacotevicio_key_format', 'Cole a chave (X-RapidAPI-Key) gerada na área de testes do RapidAPI.' ) ); }
	$sealed = pw_personalizados_pacotevicio_seal( $key );
	if ( is_wp_error( $sealed ) ) { pw_personalizados_pacotevicio_send_error( $sealed ); }
	update_option( 'pw_personalizados_pacotevicio_key', $sealed, false );
	if ( get_option( 'pw_personalizados_pacotevicio_key' ) !== $sealed ) { pw_personalizados_pacotevicio_send_error( new WP_Error( 'pacotevicio_save_failed', 'Não foi possível salvar a chave no servidor.' ) ); }
	wp_send_json_success( array( 'connected' => true ) );
}
add_action( 'wp_ajax_pw_personalizados_pacotevicio_connect', 'pw_personalizados_pacotevicio_save_key' );

/**
 * Consulta o passo a passo detalhado de um código de rastreio dos Correios
 * via a API PacoteVício (RapidAPI, plano gratuito 1.000 consultas/mês).
 * Best-effort: qualquer falha (sem chave, rede, resposta inesperada) devolve
 * um WP_Error — quem chama deve continuar mostrando o resumo básico (que já
 * vem do próprio Melhor Envio) mesmo sem o passo a passo.
 */
function pw_personalizados_pacotevicio_track_correios( $tracking_code ) {
	$key = pw_personalizados_pacotevicio_key_value();
	if ( ! $key ) { return new WP_Error( 'pacotevicio_not_configured', 'Configure a chave da API PacoteVício em Configurações → Envio para ver o passo a passo detalhado.' ); }
	$tracking_code = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $tracking_code ) );
	if ( ! $tracking_code ) { return new WP_Error( 'pacotevicio_input', 'Código de rastreio inválido.' ); }
	$rapidapi_host = 'correios-rastreamento-de-encomendas.p.rapidapi.com';
	$url = 'https://' . $rapidapi_host . '/correios?' . http_build_query( array( 'tracking_code' => $tracking_code, 'confidence_level' => 'medium' ) );
	$response = wp_remote_get( $url, array(
		'timeout' => 25,
		'headers' => array(
			'X-RapidAPI-Key'  => $key,
			'X-RapidAPI-Host' => $rapidapi_host,
		),
	) );
	if ( is_wp_error( $response ) ) {
		pw_printway_log( 'melhorenvio', 'error', 'Falha de conexão ao consultar o rastreio detalhado (PacoteVício) do código ' . $tracking_code . '.' );
		return new WP_Error( 'pacotevicio_network', 'Falha de conexão ao consultar o rastreio detalhado.' );
	}
	$status = (int) wp_remote_retrieve_response_code( $response );
	$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	if ( $status < 200 || $status >= 300 ) {
		$message = is_array( $data ) && ! empty( $data['message'] ) ? $data['message'] : ( 'HTTP ' . $status );
		pw_printway_log( 'melhorenvio', 'error', 'A API PacoteVício recusou a consulta do código ' . $tracking_code . ': ' . $message );
		return new WP_Error( 'pacotevicio_api_error', 'Não foi possível obter o rastreio detalhado: ' . $message );
	}
	$events = array();
	foreach ( (array) ( $data['eventos'] ?? array() ) as $event ) {
		if ( ! is_array( $event ) ) { continue; }
		$address = is_array( $event['unidade']['endereco'] ?? null ) ? $event['unidade']['endereco'] : array();
		$city_state = array_filter( array( (string) ( $address['cidade'] ?? '' ), (string) ( $address['uf'] ?? '' ) ) );
		$events[] = array(
			'date'        => (string) ( $event['dtHrCriado']['date'] ?? '' ),
			'description' => (string) ( $event['descricao'] ?? ( $event['descricaoWeb'] ?? '' ) ),
			'place'       => implode( '/', $city_state ),
		);
	}
	return array(
		'events'    => $events,
		'delivered' => 'E' === (string) ( $data['situacao'] ?? '' ),
		'late'      => (bool) ( $data['atrasado'] ?? false ),
		'forecast'  => (string) ( $data['dtPrevista'] ?? '' ),
	);
}

function pw_personalizados_pacotevicio_track_ajax() {
	pw_personalizados_ajax_guard();
	$tracking_code = isset( $_POST['tracking_code'] ) ? sanitize_text_field( wp_unslash( $_POST['tracking_code'] ) ) : '';
	$result = pw_personalizados_pacotevicio_track_correios( $tracking_code );
	if ( is_wp_error( $result ) ) { wp_send_json_error( array( 'message' => $result->get_error_message(), 'code' => $result->get_error_code() ), 422 ); }
	wp_send_json_success( $result );
}
add_action( 'wp_ajax_pw_personalizados_pacotevicio_track', 'pw_personalizados_pacotevicio_track_ajax' );
