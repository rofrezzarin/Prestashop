<?php
/*
Módulo: PrintWay Mercado Livre
Description: Conexão OAuth com o aplicativo do Mercado Livre (Client ID/Client Secret,
             autorização e renovação automática de token) — base para futura vinculação
             dos pedidos recebidos pelo marketplace aos pedidos do Sistema interno.
Version: 1.7.1
Author: Rodrigo
Text Domain: printway-mercadolivre
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PW_ML_VERSION', '1.8.5' );

/* =========================================================
 * MENU
 * ========================================================= */

add_action( 'admin_menu', 'pw_ml_register_admin_menu' );
function pw_ml_register_admin_menu() {
	add_submenu_page(
		'pw-printway',
		'Mercado Livre',
		'Mercado Livre',
		'manage_options',
		'pw-printway-mercadolivre',
		'pw_ml_render_admin_page'
	);
}

function pw_ml_admin_url( $args = array() ) {
	$base = admin_url( 'admin.php?page=pw-printway-mercadolivre' );
	return $args ? add_query_arg( $args, $base ) : $base;
}

function pw_ml_redirect_uri() {
	return admin_url( 'admin-post.php?action=pw_printway_ml_oauth_callback' );
}

/* =========================================================
 * ARMAZENAMENTO (Client Secret / tokens sempre cifrados)
 * ========================================================= */

function pw_ml_get_settings() {
	$settings = get_option( 'pw_printway_ml_settings', array() );
	return is_array( $settings ) ? $settings : array();
}

function pw_ml_update_settings( $partial ) {
	$settings = array_merge( pw_ml_get_settings(), $partial );
	update_option( 'pw_printway_ml_settings', $settings, false );
	return $settings;
}

function pw_ml_seal( $value, $purpose ) {
	if ( ! function_exists( 'openssl_encrypt' ) || ! in_array( 'aes-256-gcm', openssl_get_cipher_methods(), true ) ) {
		return new WP_Error( 'ml_crypto', 'O servidor precisa habilitar OpenSSL com AES-256-GCM para guardar essa credencial com segurança.' );
	}
	try {
		$iv = random_bytes( 12 );
		$tag = '';
		$aad = 'pw-ml-' . $purpose . '-v1';
		$key = hash( 'sha256', wp_salt( 'auth' ) . '|' . $aad, true );
		$cipher = openssl_encrypt( (string) $value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $aad );
		if ( false === $cipher ) { throw new RuntimeException(); }
		return array( 'v' => 1, 'iv' => base64_encode( $iv ), 'tag' => base64_encode( $tag ), 'cipher' => base64_encode( $cipher ) );
	} catch ( Throwable $error ) {
		return new WP_Error( 'ml_crypto', 'Não foi possível proteger essa credencial. Nada foi alterado.' );
	}
}

function pw_ml_unseal( $saved, $purpose ) {
	if ( ! is_array( $saved ) || 1 !== ( $saved['v'] ?? null ) || ! function_exists( 'openssl_decrypt' ) ) { return ''; }
	$iv = base64_decode( $saved['iv'] ?? '', true );
	$tag = base64_decode( $saved['tag'] ?? '', true );
	$cipher = base64_decode( $saved['cipher'] ?? '', true );
	if ( ! is_string( $iv ) || 12 !== strlen( $iv ) || ! is_string( $tag ) || 16 !== strlen( $tag ) || ! is_string( $cipher ) ) { return ''; }
	$aad = 'pw-ml-' . $purpose . '-v1';
	$key = hash( 'sha256', wp_salt( 'auth' ) . '|' . $aad, true );
	$plain = openssl_decrypt( $cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $aad );
	return is_string( $plain ) ? $plain : '';
}

function pw_ml_sanitize_client_id( $value ) {
	return substr( preg_replace( '/[^0-9]/', '', (string) $value ), 0, 40 );
}

function pw_ml_client_id() {
	$settings = pw_ml_get_settings();
	return isset( $settings['client_id'] ) ? (string) $settings['client_id'] : '';
}

function pw_ml_client_secret_value() {
	$settings = pw_ml_get_settings();
	return pw_ml_unseal( $settings['client_secret'] ?? null, 'secret' );
}

function pw_ml_access_token_value() {
	$settings = pw_ml_get_settings();
	return pw_ml_unseal( $settings['access_token'] ?? null, 'access' );
}

function pw_ml_refresh_token_value() {
	$settings = pw_ml_get_settings();
	return pw_ml_unseal( $settings['refresh_token'] ?? null, 'refresh' );
}

function pw_ml_is_connected() {
	$settings = pw_ml_get_settings();
	return ! empty( $settings['access_token'] ) && ! empty( $settings['refresh_token'] );
}

/* =========================================================
 * CHAMADAS À API DO MERCADO LIVRE
 * ========================================================= */

/** Consulta /users/me com um Access Token já válido. */
function pw_ml_fetch_me( $access_token ) {
	$response = wp_remote_get( 'https://api.mercadolibre.com/users/me', array(
		'timeout' => 20,
		'headers' => array( 'Authorization' => 'Bearer ' . $access_token, 'Accept' => 'application/json' ),
	) );
	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'ml_network', 'Falha de conexão ao consultar a conta no Mercado Livre.' );
	}
	$status = (int) wp_remote_retrieve_response_code( $response );
	$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	if ( 200 !== $status || ! is_array( $data ) ) {
		return new WP_Error( 'ml_me_failed', 'O Mercado Livre não retornou os dados da conta (HTTP ' . $status . ').' );
	}
	return $data;
}

/** ID do "Agente de Mensageria" que passou a intermediar as mensagens pós-venda no site MLB (Brasil) desde 02/02/2026. */
define( 'PW_ML_MESSAGE_AGENT_ID_MLB', '3037675074' );

/**
 * Chamada genérica e autenticada à API do Mercado Livre — renova o Access
 * Token sozinho (via pw_ml_get_valid_access_token()) e loga qualquer falha
 * no log central do PrintWay, para qualquer relatório poder ser construído
 * em cima dela sem repetir a lógica de token/erro.
 */
function pw_ml_api_request( $method, $path, $args = array() ) {
	$token = pw_ml_get_valid_access_token();
	if ( is_wp_error( $token ) ) { return $token; }
	$request_args = array_merge( array(
		'method'  => $method,
		'timeout' => 25,
		'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json' ),
	), $args );
	if ( isset( $request_args['body'] ) && is_array( $request_args['body'] ) && 'GET' !== $method ) {
		$request_args['headers']['Content-Type'] = 'application/json';
		$request_args['body'] = wp_json_encode( $request_args['body'] );
	}
	$url = 0 === strpos( $path, 'http' ) ? $path : ( 'https://api.mercadolibre.com' . $path );
	$response = wp_remote_request( $url, $request_args );
	if ( is_wp_error( $response ) ) {
		pw_printway_log( 'mercadolivre', 'error', 'Falha de conexão ao chamar a API do Mercado Livre (' . $path . ').', array( 'path' => $path ) );
		return new WP_Error( 'ml_network', 'Falha de conexão com o Mercado Livre. Tente novamente.' );
	}
	$status = (int) wp_remote_retrieve_response_code( $response );
	$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	if ( $status < 200 || $status >= 300 ) {
		$message = is_array( $data ) && ! empty( $data['message'] ) ? $data['message'] : ( 'HTTP ' . $status );
		pw_printway_log( 'mercadolivre', 'error', 'O Mercado Livre recusou uma chamada da API (' . $path . '): ' . $message, array( 'path' => $path, 'http' => $status, 'message' => $message ) );
		return new WP_Error( 'ml_api_error', 'O Mercado Livre recusou a solicitação: ' . $message, array( 'http' => $status ) );
	}
	return is_array( $data ) ? $data : array();
}

/** Perguntas ainda sem resposta em qualquer anúncio do vendedor. */
function pw_ml_get_unanswered_questions( $limit = 50 ) {
	$settings = pw_ml_get_settings();
	$seller_id = (string) ( $settings['user_id'] ?? '' );
	if ( ! $seller_id ) { return new WP_Error( 'ml_not_connected', 'Conecte a conta do Mercado Livre antes de consultar perguntas.' ); }
	$data = pw_ml_api_request( 'GET', '/questions/search?seller_id=' . rawurlencode( $seller_id ) . '&status=UNANSWERED&api_version=4&limit=' . (int) $limit . '&sort_fields=date_created&sort_types=DESC' );
	if ( is_wp_error( $data ) ) { return $data; }
	$questions = is_array( $data['questions'] ?? null ) ? $data['questions'] : array();
	$item_ids = array_values( array_unique( array_filter( array_map( function ( $question ) { return (string) ( $question['item_id'] ?? '' ); }, $questions ) ) ) );
	$titles = $item_ids ? pw_ml_get_items_titles( $item_ids ) : array();
	$result = array();
	foreach ( $questions as $question ) {
		$item_id = (string) ( $question['item_id'] ?? '' );
		$result[] = array(
			'id'          => (string) ( $question['id'] ?? '' ),
			'text'        => (string) ( $question['text'] ?? '' ),
			'itemId'      => $item_id,
			'itemTitle'   => $titles[ $item_id ] ?? $item_id,
			'dateCreated' => (string) ( $question['date_created'] ?? '' ),
		);
	}
	return array( 'total' => (int) ( $data['total'] ?? count( $result ) ), 'questions' => $result );
}

/** Consulta em lote o título de vários anúncios (evita 1 chamada por pergunta). */
function pw_ml_get_items_titles( $item_ids ) {
	$item_ids = array_slice( array_values( array_filter( array_map( 'sanitize_text_field', (array) $item_ids ) ) ), 0, 20 );
	if ( ! $item_ids ) { return array(); }
	$data = pw_ml_api_request( 'GET', '/items?ids=' . rawurlencode( implode( ',', $item_ids ) ) . '&attributes=id,title' );
	if ( is_wp_error( $data ) || ! is_array( $data ) ) { return array(); }
	$titles = array();
	foreach ( $data as $entry ) {
		$body = is_array( $entry['body'] ?? null ) ? $entry['body'] : array();
		if ( ! empty( $body['id'] ) ) { $titles[ (string) $body['id'] ] = (string) ( $body['title'] ?? $body['id'] ); }
	}
	return $titles;
}

/** Responde a uma pergunta feita num anúncio (pré-venda). */
function pw_ml_answer_question( $question_id, $text ) {
	$question_id = preg_replace( '/[^0-9]/', '', (string) $question_id );
	$text = mb_substr( trim( (string) $text ), 0, 2000 );
	if ( ! $question_id || '' === $text ) { return new WP_Error( 'ml_input', 'Informe o texto da resposta.' ); }
	return pw_ml_api_request( 'POST', '/answers', array( 'body' => array( 'question_id' => (int) $question_id, 'text' => $text ) ) );
}

/** Packs/pedidos com mensagens pós-venda ainda não lidas pelo vendedor. */
function pw_ml_get_unread_message_packs() {
	$data = pw_ml_api_request( 'GET', '/messages/unread?role=seller&tag=post_sale' );
	if ( is_wp_error( $data ) ) { return $data; }
	$results = is_array( $data['results'] ?? null ) ? $data['results'] : array();
	$packs = array();
	foreach ( $results as $entry ) {
		$resource = (string) ( $entry['resource'] ?? '' );
		if ( ! preg_match( '#/packs/(\d+)/sellers/(\d+)#', $resource, $match ) ) { continue; }
		$packs[] = array( 'packId' => $match[1], 'count' => (int) ( $entry['count'] ?? 0 ) );
	}
	return $packs;
}

/** Conversa completa de um pack — por padrão marca as mensagens como lidas (o vendedor está abrindo pra ler). */
function pw_ml_get_pack_conversation( $pack_id, $mark_as_read = true ) {
	$settings = pw_ml_get_settings();
	$seller_id = (string) ( $settings['user_id'] ?? '' );
	$pack_id = preg_replace( '/[^0-9]/', '', (string) $pack_id );
	if ( ! $seller_id || ! $pack_id ) { return new WP_Error( 'ml_input', 'Conversa inválida.' ); }
	$path = '/messages/packs/' . $pack_id . '/sellers/' . $seller_id . '?tag=post_sale';
	if ( ! $mark_as_read ) { $path .= '&mark_as_read=false'; }
	$data = pw_ml_api_request( 'GET', $path );
	if ( is_wp_error( $data ) ) { return $data; }
	$messages = is_array( $data['messages'] ?? null ) ? $data['messages'] : array();
	$thread = array();
	foreach ( $messages as $message ) {
		$from_id = (string) ( is_array( $message['from'] ?? null ) ? ( $message['from']['user_id'] ?? '' ) : '' );
		$thread[] = array(
			'fromSeller' => $from_id === $seller_id,
			'text'       => is_array( $message['text'] ?? null ) ? (string) ( $message['text']['plain'] ?? '' ) : (string) ( $message['text'] ?? '' ),
			'date'       => is_array( $message['message_date'] ?? null ) ? (string) ( $message['message_date']['created'] ?? '' ) : (string) ( $message['date_created'] ?? '' ),
		);
	}
	usort( $thread, function ( $a, $b ) { return strcmp( (string) $a['date'], (string) $b['date'] ); } );
	return array( 'packId' => $pack_id, 'messages' => $thread, 'maxLength' => (int) ( $data['seller_max_message_length'] ?? 350 ) );
}

/** Responde numa conversa pós-venda (o comprador precisa ter iniciado a conversa antes). */
function pw_ml_send_pack_reply( $pack_id, $text ) {
	$settings = pw_ml_get_settings();
	$seller_id = (string) ( $settings['user_id'] ?? '' );
	$pack_id = preg_replace( '/[^0-9]/', '', (string) $pack_id );
	$text = mb_substr( trim( (string) $text ), 0, 350 );
	if ( ! $seller_id || ! $pack_id || '' === $text ) { return new WP_Error( 'ml_input', 'Informe o texto da mensagem.' ); }
	$path = '/messages/packs/' . $pack_id . '/sellers/' . $seller_id . '?tag=post_sale';
	$result = pw_ml_api_request( 'POST', $path, array( 'body' => array(
		'from' => array( 'user_id' => $seller_id ),
		'to'   => array( 'user_id' => PW_ML_MESSAGE_AGENT_ID_MLB ),
		'text' => $text,
	) ) );
	if ( ! is_wp_error( $result ) ) { pw_ml_record_replied_conversation( $pack_id, $text ); }
	return $result;
}

/** Guarda um histórico (mais recentes primeiro, até 200 registros) de mensagens pós-venda que ESTE sistema já respondeu. */
function pw_ml_record_replied_conversation( $pack_id, $text ) {
	$log = get_option( 'pw_ml_replied_messages_log', array() );
	if ( ! is_array( $log ) ) { $log = array(); }
	$user = wp_get_current_user();
	array_unshift( $log, array(
		'packId'     => (string) $pack_id,
		'repliedAt'  => time(),
		'repliedBy'  => $user && $user->exists() ? $user->display_name : '',
		'textPreview'=> mb_substr( (string) $text, 0, 140 ),
	) );
	update_option( 'pw_ml_replied_messages_log', array_slice( $log, 0, 200 ), false );
}

/** Histórico de mensagens pós-venda já respondidas por aqui. */
function pw_ml_get_replied_messages_log( $limit = 50 ) {
	$log = get_option( 'pw_ml_replied_messages_log', array() );
	if ( ! is_array( $log ) ) { $log = array(); }
	return array_slice( $log, 0, max( 1, (int) $limit ) );
}

/** Termômetro de reputação do vendedor (nível, Mercado Líder, reclamações, atraso, cancelamentos). */
function pw_ml_get_reputation_summary() {
	$settings = pw_ml_get_settings();
	$seller_id = (string) ( $settings['user_id'] ?? '' );
	if ( ! $seller_id ) { return new WP_Error( 'ml_not_connected', 'Conecte a conta do Mercado Livre antes de consultar a reputação.' ); }
	$data = pw_ml_api_request( 'GET', '/users/' . rawurlencode( $seller_id ) );
	if ( is_wp_error( $data ) ) { return $data; }
	$reputation = is_array( $data['seller_reputation'] ?? null ) ? $data['seller_reputation'] : array();
	$metrics = is_array( $reputation['metrics'] ?? null ) ? $reputation['metrics'] : array();
	$transactions = is_array( $reputation['transactions'] ?? null ) ? $reputation['transactions'] : array();
	$ratings = is_array( $transactions['ratings'] ?? null ) ? $transactions['ratings'] : array();
	$metric_value = function ( $key ) use ( $metrics ) {
		$metric = is_array( $metrics[ $key ] ?? null ) ? $metrics[ $key ] : array();
		return array( 'rate' => is_numeric( $metric['rate'] ?? null ) ? (float) $metric['rate'] : null, 'value' => (int) ( $metric['value'] ?? 0 ), 'period' => (string) ( $metric['period'] ?? '' ) );
	};
	return array(
		'nickname'         => (string) ( $data['nickname'] ?? '' ),
		'levelId'          => $reputation['level_id'] ?? null,
		'powerSellerStatus'=> $reputation['power_seller_status'] ?? null,
		'transactions'     => array(
			'completed' => (int) ( $transactions['completed'] ?? 0 ),
			'canceled'  => (int) ( $transactions['canceled'] ?? 0 ),
			'total'     => (int) ( $transactions['total'] ?? 0 ),
			'positive'  => is_numeric( $ratings['positive'] ?? null ) ? (float) $ratings['positive'] : null,
			'neutral'   => is_numeric( $ratings['neutral'] ?? null ) ? (float) $ratings['neutral'] : null,
			'negative'  => is_numeric( $ratings['negative'] ?? null ) ? (float) $ratings['negative'] : null,
		),
		'claims'           => $metric_value( 'claims' ),
		'cancellations'    => $metric_value( 'cancellations' ),
		'delayedHandling'  => $metric_value( 'delayed_handling_time' ),
	);
}

/** Traduz o status do pedido do Mercado Livre (vem em inglês da API). */
function pw_ml_order_status_label( $status ) {
	$map = array(
		'confirmed'          => 'Confirmado',
		'payment_required'   => 'Pagamento pendente',
		'payment_in_process' => 'Pagamento em processamento',
		'paid'               => 'Pago',
		'partially_paid'     => 'Pago parcialmente',
		'partially_refunded' => 'Reembolsado parcialmente',
		'pending_cancel'     => 'Cancelamento pendente',
		'cancelled'          => 'Cancelado',
		'invalid'            => 'Inválido',
	);
	$key = strtolower( trim( (string) $status ) );
	return $map[ $key ] ?? ( $status ? ucfirst( str_replace( '_', ' ', (string) $status ) ) : '—' );
}

/** Interpreta uma data "AAAA-MM-DD" vinda de um <input type="date">, ou null se inválida. */
function pw_ml_parse_custom_date( $value, $timezone, $time_suffix ) {
	$value = (string) $value;
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) { return null; }
	try {
		return new DateTime( $value . ' ' . $time_suffix, $timezone );
	} catch ( Throwable $error ) {
		return null;
	}
}

/**
 * Converte o período escolhido ("today"/"month"/"year"/"custom" ou uma
 * quantidade de dias) num intervalo from/to no fuso horário do site, já no
 * formato ISO 8601 com offset que a API do Mercado Livre espera.
 */
function pw_ml_financial_period_range( $period, $custom_from = '', $custom_to = '' ) {
	$timezone = wp_timezone();
	$now = new DateTime( 'now', $timezone );
	$to = clone $now;
	switch ( $period ) {
		case 'today':
			$from = new DateTime( 'today', $timezone );
			break;
		case 'month':
			$from = new DateTime( $now->format( 'Y-m-01 00:00:00' ), $timezone );
			break;
		case 'year':
			$from = new DateTime( $now->format( 'Y-01-01 00:00:00' ), $timezone );
			break;
		case 'custom':
			$parsed_from = pw_ml_parse_custom_date( $custom_from, $timezone, '00:00:00' );
			$parsed_to = pw_ml_parse_custom_date( $custom_to, $timezone, '23:59:59' );
			$from = $parsed_from ?: ( clone $now )->modify( '-30 days' );
			if ( $parsed_to ) { $to = $parsed_to; }
			break;
		default:
			$days = max( 1, min( 365, (int) $period ) );
			$from = ( clone $now )->modify( '-' . $days . ' days' );
			break;
	}
	return array( 'from' => $from->format( 'Y-m-d\TH:i:s.000P' ), 'to' => $to->format( 'Y-m-d\TH:i:s.000P' ) );
}

/** Legenda amigável do período escolhido, pros cartões do resumo financeiro. */
function pw_ml_financial_period_label( $period, $custom_from = '', $custom_to = '' ) {
	$map = array( 'today' => 'hoje', 'month' => 'este mês', 'year' => 'este ano' );
	if ( isset( $map[ $period ] ) ) { return $map[ $period ]; }
	if ( 'custom' === $period ) {
		$timezone = wp_timezone();
		$parsed_from = pw_ml_parse_custom_date( $custom_from, $timezone, '00:00:00' );
		$parsed_to = pw_ml_parse_custom_date( $custom_to, $timezone, '00:00:00' );
		if ( $parsed_from && $parsed_to ) { return 'de ' . $parsed_from->format( 'd/m/Y' ) . ' até ' . $parsed_to->format( 'd/m/Y' ); }
		return 'período personalizado';
	}
	$days = max( 1, min( 365, (int) $period ) );
	return 'nos últimos ' . $days . ' dias';
}

/**
 * Resumo financeiro estimado: pedidos pagos no período, faturamento bruto,
 * comissão total cobrada pelo Mercado Livre (payments[].marketplace_fee) e
 * valor líquido resultante. Não desconta frete/promoções — é uma estimativa.
 */
function pw_ml_get_financial_summary( $period, $custom_from = '', $custom_to = '' ) {
	$settings = pw_ml_get_settings();
	$seller_id = (string) ( $settings['user_id'] ?? '' );
	if ( ! $seller_id ) { return new WP_Error( 'ml_not_connected', 'Conecte a conta do Mercado Livre antes de consultar o financeiro.' ); }
	$range = pw_ml_financial_period_range( $period, $custom_from, $custom_to );
	$from = $range['from']; $to = $range['to'];
	$orders_count = 0; $gross = 0.0; $fee = 0.0; $shipping_total = 0.0; $offset = 0; $limit = 50; $pages = 0; $order_rows = array();
	do {
		$path = '/orders/search?seller=' . rawurlencode( $seller_id ) . '&order.status=paid'
			. '&order.date_closed.from=' . rawurlencode( $from ) . '&order.date_closed.to=' . rawurlencode( $to )
			. '&limit=' . $limit . '&offset=' . $offset;
		$data = pw_ml_api_request( 'GET', $path );
		if ( is_wp_error( $data ) ) { return $data; }
		$results = is_array( $data['results'] ?? null ) ? $data['results'] : array();
		foreach ( $results as $order ) {
			$orders_count++;
			$order_total = is_numeric( $order['paid_amount'] ?? null ) ? (float) $order['paid_amount'] : (float) ( $order['total_amount'] ?? 0 );
			$gross += $order_total;
			// Nem toda conta/categoria preenche payments[].marketplace_fee — quando vier
			// zerado, usa a soma de order_items[].sale_fee (preço por unidade) como
			// alternativa, que é onde essa mesma comissão normalmente aparece.
			$order_fee = 0.0;
			foreach ( (array) ( $order['payments'] ?? array() ) as $payment ) {
				if ( is_numeric( $payment['marketplace_fee'] ?? null ) ) { $order_fee += (float) $payment['marketplace_fee']; }
			}
			if ( $order_fee <= 0 ) {
				foreach ( (array) ( $order['order_items'] ?? array() ) as $item ) {
					if ( is_numeric( $item['sale_fee'] ?? null ) ) { $order_fee += (float) $item['sale_fee'] * (float) ( $item['quantity'] ?? 1 ); }
				}
			}
			$fee += $order_fee;
			// Custo de envio que fica descontado do vendedor. A fonte confiável é
			// o recurso dedicado /shipments/{id}/costs (ver pw_ml_get_shipment_costs)
			// — os campos order.shipping_cost/payments[].shipping_cost tentados
			// antes vinham zerados para esta conta e ficam só como último recurso
			// se a chamada de costs falhar (rede/erro pontual).
			$shipment_id_for_costs = (string) ( is_array( $order['shipping'] ?? null ) ? ( $order['shipping']['id'] ?? '' ) : '' );
			$order_shipping = 0.0;
			$costs_resolved = false;
			if ( $shipment_id_for_costs ) {
				$shipment_costs = pw_ml_get_shipment_costs( $shipment_id_for_costs, $seller_id );
				if ( ! is_wp_error( $shipment_costs ) ) {
					$order_shipping = (float) ( $shipment_costs['cost'] ?? 0.0 );
					$costs_resolved = true;
				}
			}
			if ( ! $costs_resolved ) {
				$order_shipping = is_numeric( $order['shipping_cost'] ?? null ) ? (float) $order['shipping_cost'] : 0.0;
				if ( $order_shipping <= 0 ) {
					foreach ( (array) ( $order['payments'] ?? array() ) as $payment ) {
						if ( is_numeric( $payment['shipping_cost'] ?? null ) ) { $order_shipping += (float) $payment['shipping_cost']; }
					}
				}
			}
			$shipping_total += $order_shipping;
			$item_labels = array();
			foreach ( (array) ( $order['order_items'] ?? array() ) as $item ) {
				$title = (string) ( is_array( $item['item'] ?? null ) ? ( $item['item']['title'] ?? '' ) : '' );
				if ( ! $title ) { continue; }
				$quantity = (int) ( $item['quantity'] ?? 1 );
				$item_labels[] = $title . ( $quantity > 1 ? ' ×' . $quantity : '' );
			}
			$buyer = is_array( $order['buyer'] ?? null ) ? $order['buyer'] : array();
			// Nome completo real do comprador (quando o Mercado Livre preenche
			// first_name/last_name) — mostrado abaixo do apelido; não é
			// garantido pela API, então cai pra vazio sem quebrar nada.
			$buyer_full_name = trim( (string) ( $buyer['first_name'] ?? '' ) . ' ' . (string) ( $buyer['last_name'] ?? '' ) );
			$pack_id = (string) ( ! empty( $order['pack_id'] ) ? $order['pack_id'] : ( $order['id'] ?? '' ) );
			$fiscal_document = pw_ml_get_fiscal_document_for_pack( $pack_id );
			$order_rows[] = array(
				'id'             => (string) ( $order['id'] ?? '' ),
				'packId'         => $pack_id,
				'shipmentId'     => $shipment_id_for_costs,
				'date'           => (string) ( $order['date_closed'] ?? $order['date_created'] ?? '' ),
				'buyer'          => (string) ( $buyer['nickname'] ?? ( ! empty( $buyer['id'] ) ? ( 'Comprador #' . $buyer['id'] ) : '—' ) ),
				'buyerFullName'  => $buyer_full_name,
				'items'          => $item_labels ? implode( ', ', $item_labels ) : '—',
				'total'          => round( $order_total, 2 ),
				'fee'            => round( $order_fee, 2 ),
				'shippingCost'   => round( $order_shipping, 2 ),
				'feeAndShipping' => round( $order_fee + $order_shipping, 2 ),
				'net'            => round( $order_total - $order_fee - $order_shipping, 2 ),
				'status'         => pw_ml_order_status_label( $order['status'] ?? '' ),
				'fiscalDocument' => $fiscal_document ? array( 'number' => $fiscal_document['number'], 'series' => $fiscal_document['series'], 'hasXml' => (bool) pw_ml_get_fiscal_document_xml_path( $pack_id ) ) : null,
				'labelPrinted'   => $shipment_id_for_costs ? pw_ml_get_label_print_info( $shipment_id_for_costs ) : null,
			);
		}
		$total = (int) ( is_array( $data['paging'] ?? null ) ? ( $data['paging']['total'] ?? 0 ) : 0 );
		$offset += $limit;
		$pages++;
	} while ( $offset < $total && $pages < 10 );
	usort( $order_rows, function ( $a, $b ) { return strcmp( (string) $b['date'], (string) $a['date'] ); } );
	// Total de pedidos pagos em TODO o histórico (sem filtro de data), só pra
	// contexto de "quantos existem no total" ao lado do total filtrado — uma
	// chamada leve (limit=1), lê só o "paging.total" da resposta.
	$all_time_total = $orders_count;
	$all_time_data = pw_ml_api_request( 'GET', '/orders/search?seller=' . rawurlencode( $seller_id ) . '&order.status=paid&limit=1' );
	if ( ! is_wp_error( $all_time_data ) && is_array( $all_time_data['paging'] ?? null ) ) {
		$all_time_total = (int) ( $all_time_data['paging']['total'] ?? $all_time_total );
	}
	return array(
		'period'             => (string) $period,
		'periodLabel'        => pw_ml_financial_period_label( $period, $custom_from, $custom_to ),
		'ordersCount'        => $orders_count,
		'allTimeOrdersCount' => $all_time_total,
		'gross'              => round( $gross, 2 ),
		'fee'                => round( $fee, 2 ),
		'shipping'           => round( $shipping_total, 2 ),
		'feeAndShipping'     => round( $fee + $shipping_total, 2 ),
		'net'                => round( $gross - $fee - $shipping_total, 2 ),
		'averageTicket'      => $orders_count > 0 ? round( $gross / $orders_count, 2 ) : 0,
		'orders'             => array_slice( $order_rows, 0, 100 ),
	);
}

/* =========================================================
 * NOTA FISCAL E ETIQUETA (por pedido)
 * ========================================================= */

/** Monta o corpo multipart/form-data manualmente (a API HTTP do WP não tem helper de upload de arquivo). */
function pw_ml_build_multipart_body( $field_name, $filename, $contents, $mime_type ) {
	$boundary = wp_generate_password( 24, false );
	$body  = "--{$boundary}\r\n";
	$body .= 'Content-Disposition: form-data; name="' . $field_name . '"; filename="' . $filename . "\"\r\n";
	$body .= "Content-Type: {$mime_type}\r\n\r\n";
	$body .= $contents . "\r\n";
	$body .= "--{$boundary}--\r\n";
	return array( 'body' => $body, 'boundary' => $boundary );
}

/**
 * Custo de frete REALMENTE descontado do vendedor para um envio. A fonte
 * confiável é o recurso dedicado /shipments/{id}/costs (campo
 * senders[].cost) — NÃO order.shipping_cost nem payments[].shipping_cost,
 * que na prática vêm zerados/ausentes para esta conta (mesmo tipo de
 * problema já visto e corrigido com marketplace_fee). Confirmado batendo
 * com o "Envios: -R$X" que a própria tela do Mercado Livre mostra ao
 * vendedor no pedido. Usa x-format-new:true (exigido por este recurso) e
 * não passa por pw_ml_api_request() pelo mesmo motivo de
 * pw_ml_get_shipment_details() (merge raso de headers perderia o
 * Authorization).
 */
function pw_ml_get_shipment_costs( $shipment_id, $seller_id = '' ) {
	$token = pw_ml_get_valid_access_token();
	if ( is_wp_error( $token ) ) { return $token; }
	$shipment_id = preg_replace( '/[^0-9]/', '', (string) $shipment_id );
	if ( ! $shipment_id ) { return new WP_Error( 'ml_input', 'Envio inválido.' ); }
	$response = wp_remote_get( 'https://api.mercadolibre.com/shipments/' . $shipment_id . '/costs', array(
		'timeout' => 20,
		'headers' => array( 'Authorization' => 'Bearer ' . $token, 'x-format-new' => 'true' ),
	) );
	if ( is_wp_error( $response ) ) { return new WP_Error( 'ml_network', 'Falha de conexão com o Mercado Livre.' ); }
	$status = (int) wp_remote_retrieve_response_code( $response );
	$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	if ( $status < 200 || $status >= 300 ) {
		$message = is_array( $data ) && ! empty( $data['message'] ) ? $data['message'] : ( 'HTTP ' . $status );
		return new WP_Error( 'ml_api_error', $message );
	}
	$senders = is_array( $data['senders'] ?? null ) ? $data['senders'] : array();
	$cost = 0.0;
	$matched = false;
	foreach ( $senders as $sender ) {
		if ( $seller_id && (string) ( $sender['user_id'] ?? '' ) === (string) $seller_id ) {
			$cost += is_numeric( $sender['cost'] ?? null ) ? (float) $sender['cost'] : 0.0;
			$matched = true;
		}
	}
	// Praticamente todo pedido tem um único vendedor no shipment — se por
	// algum motivo o user_id não bateu (ex.: campo vindo em formato
	// diferente), ainda assim usa o único sender existente em vez de
	// devolver zero.
	if ( ! $matched && 1 === count( $senders ) && is_numeric( $senders[0]['cost'] ?? null ) ) {
		$cost = (float) $senders[0]['cost'];
	}
	return array( 'cost' => $cost );
}

/**
 * Detalhes do envio (status/substatus/logistic_type) — usa o cabeçalho
 * x-format-new:true porque o substatus "invoice_pending" (que indica se essa
 * logística exige IMPORTAR a nota pelo envio, em vez de só anexar ao
 * pacote) só vem nesse formato de resposta. Não usa pw_ml_api_request()
 * porque essa função faz merge raso de headers (perderia o Authorization).
 */
function pw_ml_get_shipment_details( $shipment_id ) {
	$token = pw_ml_get_valid_access_token();
	if ( is_wp_error( $token ) ) { return $token; }
	$shipment_id = preg_replace( '/[^0-9]/', '', (string) $shipment_id );
	if ( ! $shipment_id ) { return new WP_Error( 'ml_input', 'Envio inválido.' ); }
	$response = wp_remote_get( 'https://api.mercadolibre.com/shipments/' . $shipment_id, array(
		'timeout' => 20,
		'headers' => array( 'Authorization' => 'Bearer ' . $token, 'x-format-new' => 'true' ),
	) );
	if ( is_wp_error( $response ) ) { return new WP_Error( 'ml_network', 'Falha de conexão com o Mercado Livre.' ); }
	$status = (int) wp_remote_retrieve_response_code( $response );
	$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	if ( $status < 200 || $status >= 300 ) {
		$message = is_array( $data ) && ! empty( $data['message'] ) ? $data['message'] : ( 'HTTP ' . $status );
		return new WP_Error( 'ml_api_error', $message );
	}
	return array(
		'status'       => (string) ( $data['status'] ?? '' ),
		'substatus'    => (string) ( $data['substatus'] ?? '' ),
		// No formato novo (x-format-new: true, obrigatório desde 12/10/2025) o
		// tipo de logística vem ANINHADO em logistic.type, não mais como
		// logistic_type solto na raiz (esse era o formato antigo) — confirmado
		// batendo com o JSON de exemplo oficial da documentação de Shipments.
		// Sem esse fallback pro formato novo, logisticType sempre voltava vazio
		// e a nota fiscal de envios drop_off/cross_docking/xd_drop_off nunca
		// era roteada pro fluxo correto (invoice_data).
		'logisticType' => (string) ( $data['logistic']['type'] ?? $data['logistic_type'] ?? '' ),
	);
}

/**
 * Envia o XML pelo fluxo alternativo exigido por envios com logística
 * drop_off/xd_drop_off/cross_docking/xd_same_day (substatus
 * "invoice_pending") — diferente de anexar ao pacote, IMPORTAR a nota pelo
 * envio de fato destrava a etiqueta (substatus muda pra "ready_to_print").
 * Corpo é o XML cru (Content-Type: application/xml), não multipart. Só
 * aceita NFe modelo 55 já autorizada em produção pela SEFAZ (não aceita
 * homologação) e, segundo a documentação do Mercado Livre, depois que a
 * etiqueta é impressa a nota não pode mais ser trocada — é um envio único.
 */
/**
 * Traduz pro português as mensagens de erro cruas que a API do Mercado Livre
 * devolve ao importar a nota fiscal de um envio (documentação oficial
 * "Importar Nota Fiscal" — tabela "Referências de código de erro"), pra não
 * mostrar texto em inglês pro usuário. Recebe o array decodificado da
 * resposta (com 'error' e/ou 'message') e devolve sempre uma string em
 * português — usa a mensagem original (com prefixo) como último recurso pra
 * qualquer erro ainda não mapeado aqui.
 */
function pw_ml_translate_invoice_error_message( $data, $raw_message ) {
	$code = is_array( $data ) ? (string) ( $data['error'] ?? '' ) : '';
	$map = array(
		'shipment_invoice_already_saved'                      => 'Já existe uma nota fiscal salva para este envio no Mercado Livre.',
		'duplicated_fiscal_key'                                => 'Já existe uma nota fiscal salva com essa chave fiscal em outro envio.',
		'invalid_nfe_cstat'                                    => 'A nota fiscal não está autorizada pela Sefaz (status diferente de "autorizada").',
		'wrong_invoice_date'                                   => 'A data da nota fiscal precisa ser posterior à data da venda.',
		'wrong_sender_zipcode'                                 => 'O CEP do vendedor na nota fiscal não corresponde ao CEP cadastrado na venda.',
		'wrong_receiver_zipcode'                                => 'O CEP do comprador na nota fiscal não corresponde ao CEP da venda.',
		'wrong_receiver_cnpj'                                  => 'O CNPJ do comprador na nota fiscal não corresponde ao da venda.',
		'wrong_receiver_cpf'                                   => 'O CPF do comprador na nota fiscal não corresponde ao da venda.',
		'wrong_receiver_state_tax'                              => 'A Inscrição Estadual do comprador na nota fiscal não corresponde à da venda.',
		'invalid_user'                                         => 'Você não tem permissão para importar a nota fiscal deste envio.',
		'seller_not_allowed_to_import_nfe'                     => 'Sua conta não tem permissão para importar notas fiscais pela API — use o emissor de notas fiscais do Mercado Livre.',
		'shipment_invoice_should_contain_company_state_tax_id' => 'Falta informar a Inscrição Estadual na nota fiscal.',
		'invalid_state_tax_id'                                 => 'A Inscrição Estadual informada na nota fiscal é inválida.',
		'invalid_operation_for_site_id'                        => 'Essa operação só é permitida para o Brasil (MLB).',
		'error_parse_invoice_data'                             => 'Não foi possível interpretar os dados da nota fiscal enviada — verifique o arquivo XML.',
		'invalid_parameter'                                    => 'O arquivo enviado contém um campo inválido.',
		'invalid_caller_id'                                    => 'Identificação inválida na requisição ao Mercado Livre.',
		'sender_ie_not_found'                                  => 'Seu CNPJ não está cadastrado na Sefaz como contribuinte — revise CNPJ, Inscrição Estadual e estado no seu cadastro do Mercado Livre.',
		'invalid_sender_ie_for_state'                          => 'Sua Inscrição Estadual é inválida para o estado cadastrado — revise seu cadastro no Mercado Livre.',
		'invalid_sender_ie'                                    => 'A Inscrição Estadual da nota fiscal é diferente da cadastrada no Mercado Livre.',
		'invalid_sender_cnpj'                                  => 'O CNPJ da nota fiscal é diferente do cadastrado no Mercado Livre.',
		'different_state_nfe_shipment_origin'                  => 'O estado (UF) da nota fiscal é diferente do estado de origem do envio.',
		'nfe_order_value_divergence'                           => 'O valor da nota fiscal diverge do valor total dos itens do pedido.',
	);
	if ( $code && isset( $map[ $code ] ) ) { return $map[ $code ]; }
	// Mensagens sem um "error" code conhecido — casadas pelo próprio texto cru.
	if ( preg_match( '/status is wrong/i', $raw_message ) ) {
		return 'Este envio não está mais aguardando nota fiscal (o status já avançou) — não é possível importar a nota fiscal por aqui para este pedido. Se ele ainda precisar da nota, resolva diretamente no painel do Mercado Livre.';
	}
	if ( preg_match( '/policy returned UNAUTHORIZED/i', $raw_message ) ) {
		return 'Falta uma permissão funcional habilitada no aplicativo do Mercado Livre (ou o token precisa ser reconectado após habilitá-la).';
	}
	// Mensagens do fluxo antigo (/packs/.../fiscal_documents), mantidas aqui
	// como segurança — na teoria não deveriam mais aparecer depois do fix do
	// roteamento por tipo de logística, mas se aparecerem o texto já sai em
	// português em vez de inglês cru.
	if ( preg_match( '/must use the biller of MercadoLibre/i', $raw_message ) ) {
		return 'Este tipo de envio exige o fluxo de importação de nota fiscal (não o de anexar) — se este erro aparecer, avise o suporte, pois o roteamento deveria ter evitado isso.';
	}
	if ( preg_match( '/must use the NF-e reporting flow/i', $raw_message ) ) {
		return 'Sua conta usa o emissor de notas fiscais do próprio Mercado Livre (faturador) — a nota precisa ser emitida por lá, não é possível anexar um XML próprio.';
	}
	if ( preg_match( '/Input XML is not valid/i', $raw_message ) ) {
		return 'O arquivo XML enviado não é uma nota fiscal válida.';
	}
	if ( preg_match( '/File cannot be empty/i', $raw_message ) ) {
		return 'O arquivo enviado está vazio.';
	}
	if ( preg_match( '/not authorized/i', $raw_message ) ) {
		return 'Você não tem permissão para realizar esta operação.';
	}
	return $raw_message;
}

function pw_ml_import_shipment_invoice_data( $shipment_id, $xml_content ) {
	$token = pw_ml_get_valid_access_token();
	if ( is_wp_error( $token ) ) { return $token; }
	$shipment_id = preg_replace( '/[^0-9]/', '', (string) $shipment_id );
	if ( ! $shipment_id ) { return new WP_Error( 'ml_input', 'Envio inválido.' ); }
	if ( '' === trim( (string) $xml_content ) ) { return new WP_Error( 'ml_input', 'Selecione o arquivo XML da nota fiscal.' ); }
	if ( strlen( $xml_content ) > 1048576 ) { return new WP_Error( 'ml_input', 'O arquivo XML deve ter no máximo 1 MB.' ); }
	$response = wp_remote_post( 'https://api.mercadolibre.com/shipments/' . $shipment_id . '/invoice_data/?siteId=MLB', array(
		'timeout' => 30,
		'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/xml' ),
		'body'    => $xml_content,
	) );
	if ( is_wp_error( $response ) ) {
		pw_printway_log( 'mercadolivre', 'error', 'Falha de conexão ao importar a nota fiscal do envio ' . $shipment_id . ' ao Mercado Livre.', array( 'shipment_id' => $shipment_id ) );
		return new WP_Error( 'ml_network', 'Falha de conexão com o Mercado Livre.' );
	}
	$status = (int) wp_remote_retrieve_response_code( $response );
	$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	if ( $status < 200 || $status >= 300 ) {
		$raw_message = is_array( $data ) && ! empty( $data['message'] ) ? $data['message'] : ( 'HTTP ' . $status );
		$message = pw_ml_translate_invoice_error_message( $data, $raw_message );
		pw_printway_log( 'mercadolivre', 'error', 'O Mercado Livre recusou a importação da nota fiscal do envio ' . $shipment_id . ': ' . $message, array( 'shipment_id' => $shipment_id, 'http' => $status, 'message' => $raw_message ) );
		// Código de erro próprio pra esse caso específico ("status is wrong" —
		// o envio já avançou de etapa, não está mais esperando nota) porque
		// quem chama (pw_ml_attach_fiscal_document) precisa distinguir esse
		// caso dos demais pra tratar como "já resolvido fora do sistema" em
		// vez de um erro de verdade.
		$error_code = preg_match( '/status is wrong/i', $raw_message ) ? 'ml_shipment_status_wrong' : 'ml_api_error';
		return new WP_Error( $error_code, 'O Mercado Livre recusou a importação da nota fiscal do envio ' . $shipment_id . ': ' . $message );
	}
	return is_array( $data ) ? $data : array();
}

/**
 * Anexa o XML de uma nota fiscal JÁ AUTORIZADA (com protocolo da SEFAZ) a um
 * pedido do Mercado Livre — o Mercado Livre gera o DANFE em PDF a partir
 * dele. O Mercado Livre não emite a nota, só recebe a já emitida.
 *
 * Decide sozinho qual dos DOIS fluxos da API usar, verificando o envio
 * ANTES de tentar (em vez de só reagir ao erro "Access denied, you must use
 * the biller of MercadoLibre"): a maioria dos envios usa "anexar ao pacote"
 * (POST /packs/{pack_id}/fiscal_documents, multipart) — só disponibiliza o
 * documento ao comprador, não libera etiqueta. Envios com logística
 * drop_off/xd_drop_off/cross_docking/xd_same_day (substatus
 * "invoice_pending") EXIGEM o fluxo de "importar pelo envio" (POST
 * /shipments/{shipment_id}/invoice_data, XML cru) — que É o que libera a
 * etiqueta pra esses casos. Sem shipment_id (ou se a consulta ao envio
 * falhar), cai no fluxo padrão como antes.
 */
function pw_ml_attach_fiscal_document( $pack_id, $file_content, $filename, $shipment_id = '' ) {
	$pack_id = preg_replace( '/[^0-9]/', '', (string) $pack_id );
	if ( ! $pack_id && ! $shipment_id ) { return new WP_Error( 'ml_input', 'Pedido inválido.' ); }
	if ( '' === trim( (string) $file_content ) ) { return new WP_Error( 'ml_input', 'Selecione o arquivo XML da nota fiscal.' ); }
	if ( strlen( $file_content ) > 1048576 ) { return new WP_Error( 'ml_input', 'O arquivo XML deve ter no máximo 1 MB.' ); }

	if ( $shipment_id ) {
		$details = pw_ml_get_shipment_details( $shipment_id );
		// A condição correta pra decidir qual fluxo usar é o TIPO DE LOGÍSTICA do
		// envio, não o substatus no momento do clique (a versão anterior checava
		// substatus === 'invoice_pending', o que falhava sempre que o envio já
		// tinha avançado de substatus por qualquer motivo, mesmo sendo de um tipo
		// de logística restrito). Confirmado na documentação oficial do Mercado
		// Livre ("Anexar Nota Fiscal" — Restrições por país e tipo logístico):
		// no Brasil, o endpoint /packs/{id}/fiscal_documents SEMPRE recusa
		// (\"Access denied, you must use the biller of MercadoLibre\") pra esses
		// tipos de logística, então pra eles tem que usar SEMPRE o fluxo
		// alternativo de importação (/shipments/{id}/invoice_data).
		$restricted_logistics = array( 'fulfillment', 'cross_docking', 'xd_drop_off', 'drop_off' );
		$logistic_type = ! is_wp_error( $details ) ? (string) ( $details['logisticType'] ?? '' ) : '';
		// Log de diagnóstico temporário: registra sempre (sucesso ou falha) o que
		// foi lido do envio antes de decidir o fluxo, pra conseguirmos ver no
		// Log central EXATAMENTE o que o Mercado Livre respondeu se esse envio
		// específico continuar caindo no fluxo errado — sem isso, qualquer nova
		// investigação seria só mais um chute.
		pw_printway_log(
			'mercadolivre',
			'info',
			'Diagnóstico envio de nota fiscal — envio ' . $shipment_id . ': ' . ( is_wp_error( $details )
				? ( 'falha ao consultar detalhes do envio (' . $details->get_error_message() . ')' )
				: ( 'status=' . ( $details['status'] ?? '' ) . ', substatus=' . ( $details['substatus'] ?? '' ) . ', logisticType=' . ( $logistic_type ?: '(vazio)' ) . ', restrito=' . ( in_array( $logistic_type, $restricted_logistics, true ) ? 'sim' : 'não' ) )
			),
			array( 'shipment_id' => $shipment_id, 'pack_id' => $pack_id )
		);
		if ( in_array( $logistic_type, $restricted_logistics, true ) ) {
			$result = pw_ml_import_shipment_invoice_data( $shipment_id, $file_content );
			$record_id = $pack_id ? $pack_id : $shipment_id;
			if ( is_wp_error( $result ) ) {
				// "status is wrong" quer dizer que esse envio já avançou de etapa
				// (etiqueta/NF já resolvidos fora do sistema, ex.: direto no painel
				// do Mercado Livre) — em vez de mostrar erro, trata como "já
				// enviado": lê os dados reais da própria NFe que o usuário tentou
				// subir agora e grava normalmente, guardando também o XML.
				if ( 'ml_shipment_status_wrong' === $result->get_error_code() ) {
					$nfe = pw_ml_extract_nfe_number_from_xml( $file_content );
					pw_ml_save_fiscal_document_xml( $record_id, $file_content );
					pw_ml_record_fiscal_document_sent( $record_id, $nfe['number'], $nfe['series'] );
					return array( 'alreadyAdvanced' => true, 'number' => $nfe['number'], 'series' => $nfe['series'] );
				}
				return $result;
			}
			$nfe = pw_ml_extract_nfe_number_from_xml( $file_content );
			pw_ml_save_fiscal_document_xml( $record_id, $file_content );
			pw_ml_record_fiscal_document_sent( $record_id, $nfe['number'], $nfe['series'] );
			return $result;
		}
	} else {
		pw_printway_log( 'mercadolivre', 'info', 'Diagnóstico envio de nota fiscal — pacote ' . $pack_id . ': nenhum shipment_id foi recebido do frontend, indo direto pro fluxo antigo (/packs/.../fiscal_documents).', array( 'pack_id' => $pack_id ) );
	}

	$token = pw_ml_get_valid_access_token();
	if ( is_wp_error( $token ) ) { return $token; }
	if ( ! $pack_id ) { return new WP_Error( 'ml_input', 'Pedido inválido.' ); }
	$multipart = pw_ml_build_multipart_body( 'fiscal_document', $filename ? sanitize_file_name( $filename ) : 'nota-fiscal.xml', $file_content, 'application/xml' );
	$response = wp_remote_post( 'https://api.mercadolibre.com/packs/' . $pack_id . '/fiscal_documents', array(
		'timeout' => 30,
		'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'multipart/form-data; boundary=' . $multipart['boundary'] ),
		'body'    => $multipart['body'],
	) );
	if ( is_wp_error( $response ) ) {
		pw_printway_log( 'mercadolivre', 'error', 'Falha de conexão ao enviar a nota fiscal do pacote ' . $pack_id . ' ao Mercado Livre.', array( 'pack_id' => $pack_id ) );
		return new WP_Error( 'ml_network', 'Falha de conexão com o Mercado Livre.' );
	}
	$status = (int) wp_remote_retrieve_response_code( $response );
	$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	if ( $status < 200 || $status >= 300 ) {
		$raw_message = is_array( $data ) && ! empty( $data['message'] ) ? $data['message'] : ( 'HTTP ' . $status );
		$message = pw_ml_translate_invoice_error_message( $data, $raw_message );
		pw_printway_log( 'mercadolivre', 'error', 'O Mercado Livre recusou o envio da nota fiscal do pacote ' . $pack_id . ': ' . $message, array( 'pack_id' => $pack_id, 'http' => $status, 'message' => $raw_message ) );
		return new WP_Error( 'ml_api_error', 'O Mercado Livre recusou o envio da nota fiscal do pacote ' . $pack_id . ': ' . $message );
	}
	if ( is_array( $data ) ) {
		$nfe = pw_ml_extract_nfe_number_from_xml( $file_content );
		pw_ml_save_fiscal_document_xml( $pack_id, $file_content );
		pw_ml_record_fiscal_document_sent( $pack_id, $nfe['number'], $nfe['series'] );
	}
	return is_array( $data ) ? $data : array( 'id' => '' );
}

/** Pasta (fora da área pública de mídia) onde os XMLs de NFe enviados ficam
 * guardados, associados ao pack_id — permite oferecer "baixar XML" depois e,
 * pro fluxo de "já enviado manualmente" (ver mercadolivre-report.php), guardar
 * o arquivo mesmo quando o Mercado Livre já não aceita mais o envio. */
function pw_ml_fiscal_xml_dir() {
	$uploads = wp_upload_dir();
	return trailingslashit( (string) ( $uploads['basedir'] ?? '' ) ) . 'pw-ml-nf-xml';
}

function pw_ml_save_fiscal_document_xml( $pack_id, $xml_content ) {
	$pack_id = preg_replace( '/[^0-9]/', '', (string) $pack_id );
	if ( ! $pack_id || '' === trim( (string) $xml_content ) ) { return false; }
	$dir = pw_ml_fiscal_xml_dir();
	if ( ! wp_mkdir_p( $dir ) ) { return false; }
	return false !== file_put_contents( trailingslashit( $dir ) . $pack_id . '.xml', $xml_content );
}

/** Devolve o caminho local do XML salvo pra um pack, ou '' se nunca foi guardado. */
function pw_ml_get_fiscal_document_xml_path( $pack_id ) {
	$pack_id = preg_replace( '/[^0-9]/', '', (string) $pack_id );
	if ( ! $pack_id ) { return ''; }
	$path = trailingslashit( pw_ml_fiscal_xml_dir() ) . $pack_id . '.xml';
	return is_readable( $path ) ? $path : '';
}

/** Extrai número e série da NFe direto do XML enviado — é isso, e não o ID
 * interno do fiscal_document do Mercado Livre, que faz sentido mostrar pro
 * usuário como "o número da nota". Um XML fora do padrão apenas devolve
 * campos vazios, nunca falha o envio em si (já foi aceito pelo Mercado Livre
 * nesse ponto). */
function pw_ml_extract_nfe_number_from_xml( $xml_content ) {
	$number = ''; $series = '';
	libxml_use_internal_errors( true );
	$xml = simplexml_load_string( (string) $xml_content );
	libxml_use_internal_errors( false );
	if ( $xml ) {
		$xml->registerXPathNamespace( 'nfe', 'http://www.portalfiscal.inf.br/nfe' );
		$ide = $xml->xpath( '//nfe:ide' );
		if ( empty( $ide ) ) { $ide = $xml->xpath( '//ide' ); }
		if ( ! empty( $ide ) ) {
			$number = (string) ( $ide[0]->nNF ?? '' );
			$series = (string) ( $ide[0]->serie ?? '' );
		}
	}
	return array( 'number' => $number, 'series' => $series );
}

/**
 * Histórico local de quais pacotes do Mercado Livre já tiveram XML de nota
 * fiscal enviado por este sistema — não existe endpoint na API do Mercado
 * Livre pra "listar notas já enviadas", então isso é a única fonte da
 * verdade pra saber (mesmo depois de recarregar a página) quais pedidos já
 * têm nota. Mesmo padrão/limite de pw_ml_replied_messages_log().
 */
function pw_ml_record_fiscal_document_sent( $pack_id, $number, $series ) {
	$log = get_option( 'pw_ml_fiscal_documents_log', array() );
	if ( ! is_array( $log ) ) { $log = array(); }
	$log = array_values( array_filter( $log, function ( $entry ) use ( $pack_id ) { return ( $entry['packId'] ?? '' ) !== (string) $pack_id; } ) );
	array_unshift( $log, array(
		'packId' => (string) $pack_id,
		'number' => (string) $number,
		'series' => (string) $series,
		'sentAt' => current_time( 'mysql' ),
		'sentBy' => function_exists( 'wp_get_current_user' ) ? wp_get_current_user()->display_name : '',
	) );
	update_option( 'pw_ml_fiscal_documents_log', array_slice( $log, 0, 500 ), false );
}

/** Apaga o registro local de nota fiscal enviada pra um pack — usado quando
 * a nota enviada estava errada e o usuário precisa tentar de novo. Só mexe
 * no registro LOCAL (o que faz o botão "Enviar XML" reaparecer); nunca
 * cancela nem desfaz nada do lado do Mercado Livre. */
function pw_ml_clear_fiscal_document_sent( $pack_id ) {
	$log = get_option( 'pw_ml_fiscal_documents_log', array() );
	if ( ! is_array( $log ) ) { return; }
	$log = array_values( array_filter( $log, function ( $entry ) use ( $pack_id ) { return ( $entry['packId'] ?? '' ) !== (string) $pack_id; } ) );
	update_option( 'pw_ml_fiscal_documents_log', $log, false );
}

/** Devolve o registro local de nota fiscal enviada pra um pack, ou null se nunca foi enviada por aqui. */
function pw_ml_get_fiscal_document_for_pack( $pack_id ) {
	$log = get_option( 'pw_ml_fiscal_documents_log', array() );
	if ( ! is_array( $log ) ) { return null; }
	foreach ( $log as $entry ) {
		if ( ( $entry['packId'] ?? '' ) === (string) $pack_id ) { return $entry; }
	}
	return null;
}

/**
 * Histórico local de quando a etiqueta de cada envio foi impressa (aberta)
 * pela primeira vez — quem e quando. Grava só na PRIMEIRA vez; aberturas
 * seguintes do mesmo shipment_id viram "Reimprimir" no frontend sem
 * sobrescrever esse registro original. Mesmo padrão de
 * pw_ml_record_fiscal_document_sent().
 */
function pw_ml_record_label_printed_if_new( $shipment_id ) {
	$shipment_id = preg_replace( '/[^0-9]/', '', (string) $shipment_id );
	if ( ! $shipment_id ) { return null; }
	$existing = pw_ml_get_label_print_info( $shipment_id );
	if ( $existing ) { return $existing; }
	$log = get_option( 'pw_ml_label_prints_log', array() );
	if ( ! is_array( $log ) ) { $log = array(); }
	$entry = array(
		'shipmentId' => $shipment_id,
		'printedAt'  => current_time( 'mysql' ),
		'printedBy'  => function_exists( 'wp_get_current_user' ) ? wp_get_current_user()->display_name : '',
	);
	array_unshift( $log, $entry );
	update_option( 'pw_ml_label_prints_log', array_slice( $log, 0, 1000 ), false );
	return $entry;
}

/** Devolve o registro de primeira impressão da etiqueta pra um envio, ou null se nunca foi impressa por aqui. */
function pw_ml_get_label_print_info( $shipment_id ) {
	$shipment_id = preg_replace( '/[^0-9]/', '', (string) $shipment_id );
	if ( ! $shipment_id ) { return null; }
	$log = get_option( 'pw_ml_label_prints_log', array() );
	if ( ! is_array( $log ) ) { return null; }
	foreach ( $log as $entry ) {
		if ( ( $entry['shipmentId'] ?? '' ) === $shipment_id ) { return $entry; }
	}
	return null;
}

/** Status do envio — usado pra saber se a etiqueta já foi liberada (ready_to_ship em diante). */
function pw_ml_get_shipment_status( $shipment_id ) {
	$shipment_id = preg_replace( '/[^0-9]/', '', (string) $shipment_id );
	if ( ! $shipment_id ) { return new WP_Error( 'ml_input', 'Envio inválido.' ); }
	$data = pw_ml_api_request( 'GET', '/shipments/' . $shipment_id );
	if ( is_wp_error( $data ) ) { return $data; }
	$status = (string) ( $data['status'] ?? '' );
	$ready_statuses = array( 'ready_to_ship', 'shipped', 'delivered' );
	return array( 'status' => $status, 'ready' => in_array( $status, $ready_statuses, true ) );
}

/** Busca os bytes crus do PDF da etiqueta já liberada — quem chama decide como servir ao navegador. */
function pw_ml_fetch_shipment_label_pdf( $shipment_id ) {
	$token = pw_ml_get_valid_access_token();
	if ( is_wp_error( $token ) ) { return $token; }
	$shipment_id = preg_replace( '/[^0-9]/', '', (string) $shipment_id );
	if ( ! $shipment_id ) { return new WP_Error( 'ml_input', 'Envio inválido.' ); }
	$response = wp_remote_get( 'https://api.mercadolibre.com/shipment_labels?shipment_ids=' . $shipment_id . '&response_type=pdf', array(
		'timeout' => 30,
		'headers' => array( 'Authorization' => 'Bearer ' . $token ),
	) );
	if ( is_wp_error( $response ) ) {
		pw_printway_log( 'mercadolivre', 'error', 'Falha de conexão ao buscar a etiqueta do envio ' . $shipment_id . ' no Mercado Livre.', array( 'shipment_id' => $shipment_id ) );
		return new WP_Error( 'ml_network', 'Falha de conexão com o Mercado Livre.' );
	}
	$status = (int) wp_remote_retrieve_response_code( $response );
	$body = wp_remote_retrieve_body( $response );
	if ( $status < 200 || $status >= 300 ) {
		$data = json_decode( (string) $body, true );
		$message = is_array( $data ) && ! empty( $data['message'] ) ? $data['message'] : ( 'HTTP ' . $status );
		pw_printway_log( 'mercadolivre', 'error', 'O Mercado Livre recusou o pedido de etiqueta do envio ' . $shipment_id . ': ' . $message, array( 'shipment_id' => $shipment_id, 'http' => $status, 'message' => $message ) );
		return new WP_Error( 'ml_api_error', 'O Mercado Livre ainda não liberou a etiqueta do envio ' . $shipment_id . ': ' . $message );
	}
	return $body;
}

/* =========================================================
 * NOTIFICAÇÃO NO MENU (ícone piscando pra administradores)
 * ========================================================= */

/** Só os IDs das perguntas pendentes (mais leve que pw_ml_get_unanswered_questions — sem buscar título dos anúncios). */
function pw_ml_get_unanswered_question_ids( $limit = 50 ) {
	$settings = pw_ml_get_settings();
	$seller_id = (string) ( $settings['user_id'] ?? '' );
	if ( ! $seller_id ) { return new WP_Error( 'ml_not_connected', 'Conecte a conta do Mercado Livre.' ); }
	$data = pw_ml_api_request( 'GET', '/questions/search?seller_id=' . rawurlencode( $seller_id ) . '&status=UNANSWERED&api_version=4&limit=' . (int) $limit );
	if ( is_wp_error( $data ) ) { return $data; }
	$questions = is_array( $data['questions'] ?? null ) ? $data['questions'] : array();
	$ids = array_values( array_filter( array_map( function ( $question ) { return (string) ( $question['id'] ?? '' ); }, $questions ) ) );
	return array( 'ids' => $ids, 'total' => (int) ( $data['total'] ?? count( $ids ) ) );
}

/** ID do pedido mais recente (qualquer status) dentro de uma janela curta — usado como "sentinela" de pedido novo, junto com a contagem de pedidos nessa mesma janela (pro resumo da notificação). */
function pw_ml_get_recent_order_signature() {
	$settings = pw_ml_get_settings();
	$seller_id = (string) ( $settings['user_id'] ?? '' );
	if ( ! $seller_id ) { return array( 'id' => '', 'count' => 0 ); }
	$from = gmdate( 'Y-m-d\T00:00:00.000\-00:00', strtotime( '-2 days' ) );
	$data = pw_ml_api_request( 'GET', '/orders/search?seller=' . rawurlencode( $seller_id ) . '&order.date_created.from=' . rawurlencode( $from ) . '&limit=50' );
	if ( is_wp_error( $data ) ) { return array( 'id' => '', 'count' => 0 ); }
	$results = is_array( $data['results'] ?? null ) ? $data['results'] : array();
	$latest_id = ''; $latest_time = 0;
	foreach ( $results as $order ) {
		$time = strtotime( (string) ( $order['date_created'] ?? '' ) );
		if ( $time && $time > $latest_time ) { $latest_time = $time; $latest_id = (string) ( $order['id'] ?? '' ); }
	}
	return array( 'id' => $latest_id, 'count' => count( $results ) );
}

/**
 * "Retrato" atual de tudo que poderia acender a notificação: um hash que só
 * muda quando surge pergunta/mensagem/pedido novo — comparar esse hash com o
 * que cada administrador já confirmou ter visto é o que permite o ícone
 * piscar de forma individual por administrador (vê-lo dispensa só pra quem
 * clicou, continua piscando pros demais até eles também confirmarem).
 */
function pw_ml_get_notification_snapshot() {
	if ( ! pw_ml_is_connected() ) { return new WP_Error( 'ml_not_connected', 'Conta do Mercado Livre não conectada.' ); }
	$questions = pw_ml_get_unanswered_question_ids();
	if ( is_wp_error( $questions ) ) { return $questions; }
	$packs = pw_ml_get_unread_message_packs();
	if ( is_wp_error( $packs ) ) { return $packs; }
	$order_signature = pw_ml_get_recent_order_signature();
	$pack_signature = array_map( function ( $pack ) { return $pack['packId'] . ':' . $pack['count']; }, $packs );
	$has_anything = ! empty( $questions['ids'] ) || ! empty( $packs ) || '' !== $order_signature['id'];
	$signature = md5( wp_json_encode( array(
		'q' => implode( ',', $questions['ids'] ),
		'm' => implode( ',', $pack_signature ),
		'o' => $order_signature['id'],
	) ) );
	return array(
		'signature'           => $signature,
		'hasAnything'         => $has_anything,
		'questionsCount'      => (int) $questions['total'],
		'unreadMessagesCount' => array_sum( array_column( $packs, 'count' ) ),
		'newOrdersCount'      => (int) $order_signature['count'],
	);
}

/** Estado de notificação PARA UM administrador específico ($user_id = ID do usuário do WordPress, não do Mercado Livre). */
function pw_ml_get_notification_status( $user_id ) {
	$snapshot = pw_ml_get_notification_snapshot();
	if ( is_wp_error( $snapshot ) ) { return $snapshot; }
	$acknowledged = (string) get_user_meta( $user_id, '_pw_ml_notify_ack_signature', true );
	$snapshot['hasNew'] = $snapshot['hasAnything'] && ( $snapshot['signature'] !== $acknowledged );
	if ( $snapshot['hasNew'] ) {
		// Mostra apenas os pedidos novos desde o último aviso visto, não o total dos 2 dias.
		$ack_count = (int) get_user_meta( $user_id, '_pw_ml_notify_ack_order_count', true );
		$snapshot['newOrdersCount'] = max( 0, $snapshot['newOrdersCount'] - $ack_count );
	}
	unset( $snapshot['hasAnything'] );
	return $snapshot;
}

/** Confirma, só para este administrador, que ele viu o estado atual — o ícone para de piscar pra ele até o retrato mudar de novo. */
function pw_ml_mark_notifications_seen( $user_id ) {
	$snapshot = pw_ml_get_notification_snapshot();
	if ( is_wp_error( $snapshot ) ) { return $snapshot; }
	update_user_meta( $user_id, '_pw_ml_notify_ack_signature', $snapshot['signature'] );
	update_user_meta( $user_id, '_pw_ml_notify_ack_order_count', (int) $snapshot['newOrdersCount'] );
	return true;
}

/** Guarda o par de tokens vindo de uma troca de código ou de uma renovação, e atualiza o apelido exibido. */
function pw_ml_store_tokens( $data ) {
	$access_raw = (string) ( $data['access_token'] ?? '' );
	$refresh_raw = (string) ( $data['refresh_token'] ?? '' );
	if ( ! $access_raw || ! $refresh_raw ) { return false; }
	$expires_in = isset( $data['expires_in'] ) ? (int) $data['expires_in'] : 21600;
	$sealed_access = pw_ml_seal( $access_raw, 'access' );
	$sealed_refresh = pw_ml_seal( $refresh_raw, 'refresh' );
	if ( is_wp_error( $sealed_access ) || is_wp_error( $sealed_refresh ) ) {
		pw_printway_log( 'mercadolivre', 'error', 'Falha ao proteger os tokens do Mercado Livre ao salvar.' );
		return false;
	}
	$nickname = '';
	$user_id = sanitize_text_field( (string) ( $data['user_id'] ?? '' ) );
	$who = pw_ml_fetch_me( $access_raw );
	if ( ! is_wp_error( $who ) ) {
		$nickname = sanitize_text_field( (string) ( $who['nickname'] ?? '' ) );
		if ( ! $user_id && ! empty( $who['id'] ) ) { $user_id = sanitize_text_field( (string) $who['id'] ); }
	}
	pw_ml_update_settings( array(
		'access_token'  => $sealed_access,
		'refresh_token' => $sealed_refresh,
		'expires_at'    => time() + $expires_in,
		'user_id'       => $user_id,
		'nickname'      => $nickname,
		'connected_at'  => time(),
	) );
	return true;
}

/**
 * Devolve um Access Token pronto para uso, renovando sozinho via Refresh Token
 * quando estiver perto de expirar. É isso que qualquer integração futura
 * (vincular pedidos, consultar anúncios, etc.) deve chamar — nunca ler o
 * Access Token cru direto das configurações.
 */
function pw_ml_get_valid_access_token() {
	$settings = pw_ml_get_settings();
	$access_token = pw_ml_unseal( $settings['access_token'] ?? null, 'access' );
	$refresh_token = pw_ml_unseal( $settings['refresh_token'] ?? null, 'refresh' );
	$expires_at = (int) ( $settings['expires_at'] ?? 0 );
	if ( ! $access_token || ! $refresh_token ) {
		return new WP_Error( 'ml_not_connected', 'Conecte a conta do Mercado Livre em PrintWay → Mercado Livre.' );
	}
	if ( $expires_at > ( time() + 120 ) ) {
		return $access_token;
	}
	$client_id = pw_ml_client_id();
	$client_secret = pw_ml_client_secret_value();
	if ( ! $client_id || ! $client_secret ) {
		return new WP_Error( 'ml_missing_credentials', 'Client ID/Client Secret do Mercado Livre não configurados.' );
	}
	$response = wp_remote_post( 'https://api.mercadolibre.com/oauth/token', array(
		'timeout' => 20,
		'headers' => array( 'Accept' => 'application/json' ),
		'body'    => array(
			'grant_type'    => 'refresh_token',
			'client_id'     => $client_id,
			'client_secret' => $client_secret,
			'refresh_token' => $refresh_token,
		),
	) );
	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'ml_network', 'Não foi possível renovar o token do Mercado Livre (falha de conexão).' );
	}
	$status = (int) wp_remote_retrieve_response_code( $response );
	$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	if ( 200 !== $status || empty( $data['access_token'] ) ) {
		$message = is_array( $data ) && ! empty( $data['message'] ) ? $data['message'] : ( 'HTTP ' . $status );
		pw_printway_log( 'mercadolivre', 'error', 'Falha ao renovar o token do Mercado Livre.', array( 'http' => $status, 'message' => $message ) );
		return new WP_Error( 'ml_refresh_failed', 'O Mercado Livre recusou a renovação do token. Reconecte a conta em PrintWay → Mercado Livre.' );
	}
	pw_ml_store_tokens( $data );
	return $data['access_token'];
}

/* =========================================================
 * FLUXO OAuth (admin-post)
 * ========================================================= */

add_action( 'admin_post_pw_printway_ml_save_credentials', 'pw_ml_handle_save_credentials' );
function pw_ml_handle_save_credentials() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Sem permissão.' ); }
	check_admin_referer( 'pw_ml_save_credentials' );
	$client_id = pw_ml_sanitize_client_id( $_POST['pw_ml_client_id'] ?? '' );
	$client_secret_raw = isset( $_POST['pw_ml_client_secret'] ) ? trim( (string) wp_unslash( $_POST['pw_ml_client_secret'] ) ) : '';
	unset( $_POST['pw_ml_client_secret'], $_REQUEST['pw_ml_client_secret'] );
	$partial = array( 'client_id' => $client_id );
	if ( '' !== $client_secret_raw ) {
		$sealed = pw_ml_seal( $client_secret_raw, 'secret' );
		if ( is_wp_error( $sealed ) ) {
			wp_safe_redirect( pw_ml_admin_url( array( 'aba' => 'credenciais', 'pw_ml_notice' => 'crypto_error' ) ) );
			exit;
		}
		$partial['client_secret'] = $sealed;
	}
	pw_ml_update_settings( $partial );
	wp_safe_redirect( pw_ml_admin_url( array( 'aba' => 'credenciais', 'pw_ml_notice' => 'saved' ) ) );
	exit;
}

add_action( 'admin_post_pw_printway_ml_oauth_start', 'pw_ml_handle_oauth_start' );
/** Gera o code_verifier do PKCE (RFC 7636): string aleatória em base64url, sem padding. */
function pw_ml_generate_code_verifier() {
	return rtrim( strtr( base64_encode( random_bytes( 64 ) ), '+/', '-_' ), '=' );
}

/** code_challenge = SHA-256 do code_verifier, também em base64url sem padding (method S256). */
function pw_ml_code_challenge_from_verifier( $verifier ) {
	return rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
}

function pw_ml_handle_oauth_start() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Sem permissão.' ); }
	check_admin_referer( 'pw_ml_oauth_start' );
	$client_id = pw_ml_client_id();
	if ( ! $client_id ) {
		wp_safe_redirect( pw_ml_admin_url( array( 'aba' => 'credenciais', 'pw_ml_notice' => 'missing_credentials' ) ) );
		exit;
	}
	$state = wp_generate_password( 32, false );
	$code_verifier = pw_ml_generate_code_verifier();
	set_transient( 'pw_ml_oauth_state_' . get_current_user_id(), array( 'state' => $state, 'verifier' => $code_verifier ), 600 );
	$url = add_query_arg(
		array(
			'response_type'         => 'code',
			'client_id'             => $client_id,
			'redirect_uri'          => pw_ml_redirect_uri(),
			'state'                 => $state,
			'code_challenge'        => pw_ml_code_challenge_from_verifier( $code_verifier ),
			'code_challenge_method' => 'S256',
		),
		'https://auth.mercadolivre.com.br/authorization'
	);
	wp_redirect( esc_url_raw( $url ) );
	exit;
}

add_action( 'admin_post_pw_printway_ml_oauth_callback', 'pw_ml_handle_oauth_callback' );
function pw_ml_handle_oauth_callback() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Sem permissão.' ); }
	$state_key = 'pw_ml_oauth_state_' . get_current_user_id();
	$stored = get_transient( $state_key );
	delete_transient( $state_key );
	$stored_state = is_array( $stored ) ? ( $stored['state'] ?? '' ) : $stored; // "$stored" era só a string state antes do PKCE (compat).
	$code_verifier = is_array( $stored ) ? ( $stored['verifier'] ?? '' ) : '';

	if ( ! empty( $_GET['error'] ) ) {
		pw_printway_log( 'mercadolivre', 'warning', 'Autorização do Mercado Livre cancelada ou recusada.', array( 'error' => sanitize_text_field( wp_unslash( $_GET['error'] ) ) ) );
		wp_safe_redirect( pw_ml_admin_url( array( 'aba' => 'credenciais', 'pw_ml_notice' => 'denied' ) ) );
		exit;
	}

	$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
	$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
	if ( ! $code || ! $state || ! $stored_state || ! hash_equals( (string) $stored_state, $state ) ) {
		wp_safe_redirect( pw_ml_admin_url( array( 'aba' => 'credenciais', 'pw_ml_notice' => 'state_mismatch' ) ) );
		exit;
	}

	$client_id = pw_ml_client_id();
	$client_secret = pw_ml_client_secret_value();
	if ( ! $client_id || ! $client_secret ) {
		wp_safe_redirect( pw_ml_admin_url( array( 'aba' => 'credenciais', 'pw_ml_notice' => 'missing_credentials' ) ) );
		exit;
	}

	$token_body = array(
		'grant_type'    => 'authorization_code',
		'client_id'     => $client_id,
		'client_secret' => $client_secret,
		'code'          => $code,
		'redirect_uri'  => pw_ml_redirect_uri(),
	);
	if ( $code_verifier ) { $token_body['code_verifier'] = $code_verifier; }

	$response = wp_remote_post( 'https://api.mercadolibre.com/oauth/token', array(
		'timeout' => 25,
		'headers' => array( 'Accept' => 'application/json' ),
		'body'    => $token_body,
	) );
	if ( is_wp_error( $response ) ) {
		pw_printway_log( 'mercadolivre', 'error', 'Falha de rede ao trocar o código de autorização do Mercado Livre.' );
		wp_safe_redirect( pw_ml_admin_url( array( 'aba' => 'credenciais', 'pw_ml_notice' => 'network_error' ) ) );
		exit;
	}
	$status = (int) wp_remote_retrieve_response_code( $response );
	$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	if ( 200 !== $status || empty( $data['access_token'] ) || empty( $data['refresh_token'] ) ) {
		$message = is_array( $data ) && ! empty( $data['message'] ) ? $data['message'] : ( 'HTTP ' . $status );
		pw_printway_log( 'mercadolivre', 'error', 'O Mercado Livre recusou a troca do código de autorização.', array( 'http' => $status, 'message' => $message ) );
		wp_safe_redirect( pw_ml_admin_url( array( 'aba' => 'credenciais', 'pw_ml_notice' => 'exchange_failed' ) ) );
		exit;
	}

	pw_ml_store_tokens( $data );
	wp_safe_redirect( pw_ml_admin_url( array( 'aba' => 'credenciais', 'pw_ml_notice' => 'connected' ) ) );
	exit;
}

add_action( 'admin_post_pw_printway_ml_check', 'pw_ml_handle_check_connection' );
function pw_ml_handle_check_connection() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Sem permissão.' ); }
	check_admin_referer( 'pw_ml_check' );
	$token = pw_ml_get_valid_access_token();
	if ( is_wp_error( $token ) ) {
		wp_safe_redirect( pw_ml_admin_url( array( 'aba' => 'credenciais', 'pw_ml_notice' => 'check_failed' ) ) );
		exit;
	}
	$who = pw_ml_fetch_me( $token );
	if ( is_wp_error( $who ) ) {
		wp_safe_redirect( pw_ml_admin_url( array( 'aba' => 'credenciais', 'pw_ml_notice' => 'check_failed' ) ) );
		exit;
	}
	pw_ml_update_settings( array(
		'nickname' => sanitize_text_field( (string) ( $who['nickname'] ?? '' ) ),
		'user_id'  => sanitize_text_field( (string) ( $who['id'] ?? '' ) ),
	) );
	wp_safe_redirect( pw_ml_admin_url( array( 'aba' => 'credenciais', 'pw_ml_notice' => 'check_ok' ) ) );
	exit;
}

add_action( 'admin_post_pw_printway_ml_disconnect', 'pw_ml_handle_disconnect' );
function pw_ml_handle_disconnect() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Sem permissão.' ); }
	check_admin_referer( 'pw_ml_disconnect' );
	pw_ml_update_settings( array(
		'access_token'  => null,
		'refresh_token' => null,
		'expires_at'    => 0,
		'user_id'       => '',
		'nickname'      => '',
		'connected_at'  => 0,
	) );
	wp_safe_redirect( pw_ml_admin_url( array( 'aba' => 'credenciais', 'pw_ml_notice' => 'disconnected' ) ) );
	exit;
}

/* =========================================================
 * TELA DE ADMIN
 * ========================================================= */

function pw_ml_render_admin_notice() {
	if ( empty( $_GET['pw_ml_notice'] ) ) { return; }
	$notice = sanitize_key( wp_unslash( $_GET['pw_ml_notice'] ) );
	$map = array(
		'saved'               => array( 'success', 'Credenciais salvas.' ),
		'connected'           => array( 'success', 'Conta do Mercado Livre conectada com sucesso.' ),
		'disconnected'        => array( 'success', 'Conexão com o Mercado Livre removida.' ),
		'check_ok'            => array( 'success', 'Conexão verificada: o token está válido.' ),
		'denied'              => array( 'error', 'A autorização foi cancelada ou recusada no Mercado Livre.' ),
		'state_mismatch'      => array( 'error', 'Não foi possível confirmar a autorização. Tente conectar novamente.' ),
		'missing_credentials' => array( 'error', 'Salve o Client ID e o Client Secret antes de conectar.' ),
		'exchange_failed'     => array( 'error', 'O Mercado Livre recusou a autorização. Confira o Client ID/Secret e a URI de redirect configurada no aplicativo.' ),
		'network_error'       => array( 'error', 'Falha de conexão ao falar com o Mercado Livre. Tente novamente.' ),
		'check_failed'        => array( 'error', 'Não foi possível confirmar a conexão. Talvez seja necessário reconectar.' ),
		'crypto_error'        => array( 'error', 'O servidor não conseguiu proteger a credencial (verifique o suporte a OpenSSL/AES-256-GCM).' ),
	);
	if ( ! isset( $map[ $notice ] ) ) { return; }
	list( $type, $message ) = $map[ $notice ];
	echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
}

function pw_ml_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	$tab = isset( $_GET['aba'] ) ? sanitize_key( wp_unslash( $_GET['aba'] ) ) : 'credenciais';
	$base_url = pw_ml_admin_url();

	echo '<div class="wrap"><h1>PrintWay - Mercado Livre</h1>';
	pw_ml_render_admin_notice();
	echo '<nav class="nav-tab-wrapper" style="margin-bottom:20px">';
	echo '<a class="nav-tab' . ( 'pedidos' !== $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( $base_url ) . '">Credenciais</a>';
	echo '<a class="nav-tab' . ( 'pedidos' === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( add_query_arg( 'aba', 'pedidos', $base_url ) ) . '">Pedidos</a>';
	echo '</nav>';

	if ( 'pedidos' === $tab ) {
		pw_ml_render_orders_tab();
	} else {
		pw_ml_render_credentials_tab();
	}

	echo '</div>';
}

function pw_ml_render_orders_tab() {
	echo '<h2>Vincular pedidos</h2>';
	echo '<p>Em breve: depois de conectar a conta na aba <strong>Credenciais</strong>, esta aba vai permitir vincular os pedidos recebidos pelo Mercado Livre aos pedidos do Sistema interno.</p>';
}

function pw_ml_render_credentials_tab() {
	$settings = pw_ml_get_settings();
	$client_id = pw_ml_client_id();
	$has_secret = ! empty( $settings['client_secret'] );
	$connected = pw_ml_is_connected();
	$redirect_uri = pw_ml_redirect_uri();

	echo '<h2>Conexão com o Mercado Livre</h2>';
	if ( $connected ) {
		$expires_at = (int) ( $settings['expires_at'] ?? 0 );
		$nickname = (string) ( $settings['nickname'] ?? '' );
		$user_id = (string) ( $settings['user_id'] ?? '' );
		echo '<div class="notice notice-success inline" style="padding:12px;">';
		echo '<p><strong>✅ Conectado' . ( $nickname ? ' como ' . esc_html( $nickname ) : '' ) . '</strong>' . ( $user_id ? ' (ID ' . esc_html( $user_id ) . ')' : '' ) . '</p>';
		echo '<p>Token de acesso válido até ' . esc_html( wp_date( 'd/m/Y H:i', $expires_at, wp_timezone() ) ) . ' — renovado automaticamente quando o sistema precisar dele.</p>';
		echo '</div>';
		echo '<p style="display:flex;gap:10px;flex-wrap:wrap;">';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline;">';
		echo '<input type="hidden" name="action" value="pw_printway_ml_check" />';
		wp_nonce_field( 'pw_ml_check' );
		echo '<button type="submit" class="button">Verificar conexão agora</button></form>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline;" onsubmit="return confirm(\'Desconectar a conta do Mercado Livre?\');">';
		echo '<input type="hidden" name="action" value="pw_printway_ml_disconnect" />';
		wp_nonce_field( 'pw_ml_disconnect' );
		echo '<button type="submit" class="button button-secondary">Desconectar</button></form>';
		echo '</p>';
	} else {
		echo '<div class="notice notice-warning inline" style="padding:12px;"><p>🔌 Ainda não conectado a nenhuma conta do Mercado Livre.</p></div>';
	}

	echo '<h3>1. Credenciais do aplicativo</h3>';
	echo '<p>Copie do painel de desenvolvedores do Mercado Livre (<a href="https://developers.mercadolivre.com.br/devcenter" target="_blank" rel="noopener noreferrer">developers.mercadolivre.com.br/devcenter</a> → seu aplicativo):</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="pw_printway_ml_save_credentials" />';
	wp_nonce_field( 'pw_ml_save_credentials' );
	echo '<table class="form-table"><tbody>';
	echo '<tr><th><label for="pw-ml-client-id">Client ID (App ID)</label></th><td><input type="text" id="pw-ml-client-id" name="pw_ml_client_id" class="regular-text" value="' . esc_attr( $client_id ) . '" autocomplete="off" inputmode="numeric"></td></tr>';
	echo '<tr><th><label for="pw-ml-client-secret">Client Secret</label></th><td><input type="password" id="pw-ml-client-secret" name="pw_ml_client_secret" class="regular-text" autocomplete="new-password" placeholder="' . ( $has_secret ? 'Já salvo — deixe em branco para manter' : 'Cole o Client Secret' ) . '">';
	echo '<p class="description">' . ( $has_secret ? 'Já existe um Client Secret salvo. Preencha apenas para substituí-lo.' : 'Obrigatório para conectar.' ) . '</p></td></tr>';
	echo '</tbody></table>';
	echo '<button type="submit" class="button button-primary">Salvar credenciais</button>';
	echo '</form>';

	echo '<h3>2. URI de redirect</h3>';
	echo '<p>Configure exatamente esta URL como "URI de redirect" no seu aplicativo, em <a href="https://developers.mercadolivre.com.br/devcenter" target="_blank" rel="noopener noreferrer">developers.mercadolivre.com.br/devcenter</a> → seu aplicativo → Editar aplicativo:</p>';
	echo '<p style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;"><code id="pw-ml-redirect-uri" style="font-size:14px;padding:10px 14px;background:#f0f0f1;border:1px solid #dcdcde;border-radius:4px;word-break:break-all;">' . esc_html( $redirect_uri ) . '</code><button type="button" class="button" id="pw-ml-copy-redirect">Copiar</button></p>';

	echo '<h3>3. Conectar a conta</h3>';
	if ( $client_id && ( $has_secret || $connected ) ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="pw_printway_ml_oauth_start" />';
		wp_nonce_field( 'pw_ml_oauth_start' );
		echo '<button type="submit" class="button button-primary">' . ( $connected ? 'Reconectar' : 'Conectar com o Mercado Livre' ) . '</button>';
		echo '</form>';
		echo '<p class="description">Você será redirecionado ao Mercado Livre para autorizar o acesso e voltará automaticamente para esta página.</p>';
	} else {
		echo '<p class="description">Salve o Client ID e o Client Secret acima antes de conectar.</p>';
	}

	echo '<script>document.addEventListener("DOMContentLoaded",function(){var btn=document.getElementById("pw-ml-copy-redirect");if(btn){btn.addEventListener("click",function(){var text=document.getElementById("pw-ml-redirect-uri").textContent;navigator.clipboard.writeText(text).then(function(){var original=btn.textContent;btn.textContent="Copiado!";setTimeout(function(){btn.textContent=original;},1500);});});}});</script>';
}
