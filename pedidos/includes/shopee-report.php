<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function pw_personalizados_shopee_module_ready() {
	return function_exists( 'pw_shopee_is_connected' ) && function_exists( 'pw_shopee_get_settings' );
}

/* ── Status ─────────────────────────────────────────────── */

function pw_personalizados_shopee_status() {
	pw_personalizados_ajax_guard();
	if ( ! pw_personalizados_shopee_module_ready() ) {
		wp_send_json_success( array( 'connected' => false, 'reason' => 'module_missing' ) );
	}
	$connected = pw_shopee_is_connected();
	$payload = array( 'connected' => $connected );
	if ( $connected ) {
		$settings    = pw_shopee_get_settings();
		$environment = function_exists( 'pw_shopee_environment' ) ? pw_shopee_environment() : '';
		$expires_at  = (int) ( $settings['expires_at'] ?? 0 );
		$payload['shopName']    = sanitize_text_field( (string) ( $settings['shop_name'] ?? '' ) );
		$payload['shopId']      = sanitize_text_field( (string) ( $settings['shop_id'] ?? '' ) );
		$payload['environment'] = $environment;
		$payload['expiresAt']   = $expires_at;
	}
	wp_send_json_success( $payload );
}
add_action( 'wp_ajax_pw_personalizados_shopee_status', 'pw_personalizados_shopee_status' );

/* ── Conversas ───────────────────────────────────────────── */

function pw_personalizados_shopee_conversations() {
	pw_personalizados_ajax_guard();
	if ( ! pw_personalizados_shopee_module_ready() || ! pw_shopee_is_connected() ) {
		wp_send_json_error( array( 'message' => 'Shopee não conectada.' ), 400 );
	}
	if ( ! function_exists( 'pw_shopee_get_conversations' ) ) {
		wp_send_json_error( array( 'message' => 'Função de mensagens indisponível.' ), 500 );
	}
	$result = pw_shopee_get_conversations( 25 );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ), 502 );
	}
	$raw = $result['response']['conversations'] ?? array();
	$conversations = array();
	foreach ( $raw as $c ) {
		$conversations[] = array(
			'conversationId'   => (string) ( $c['conversation_id'] ?? '' ),
			'toId'             => (int)    ( $c['to_id'] ?? 0 ),
			'toName'           => (string) ( $c['to_name'] ?? '' ),
			'unreadCount'      => (int)    ( $c['unread_count'] ?? 0 ),
			'latestText'       => (string) ( $c['latest_message_content']['text'] ?? '' ),
			'latestType'       => (string) ( $c['latest_message_type'] ?? 'text' ),
			'lastTimestamp'    => (int)    ( $c['last_message_timestamp'] ?? 0 ),
			'buyerPortrait'    => (string) ( $c['buyer_portrait'] ?? '' ),
		);
	}
	wp_send_json_success( array( 'conversations' => $conversations ) );
}
add_action( 'wp_ajax_pw_personalizados_shopee_conversations', 'pw_personalizados_shopee_conversations' );

/* ── Mensagens de uma conversa ───────────────────────────── */

function pw_personalizados_shopee_messages() {
	pw_personalizados_ajax_guard();
	if ( ! pw_personalizados_shopee_module_ready() || ! pw_shopee_is_connected() ) {
		wp_send_json_error( array( 'message' => 'Shopee não conectada.' ), 400 );
	}
	$conv_id = sanitize_text_field( wp_unslash( $_POST['conversation_id'] ?? '' ) );
	if ( ! $conv_id ) { wp_send_json_error( array( 'message' => 'conversation_id ausente.' ), 400 ); }
	$result = pw_shopee_get_messages_in_conversation( $conv_id, 20 );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ), 502 );
	}
	$settings  = pw_shopee_get_settings();
	$my_shop_id = (int) ( $settings['shop_id'] ?? 0 );
	$raw = $result['response']['messages'] ?? array();
	$messages = array();
	foreach ( array_reverse( $raw ) as $m ) {
		$messages[] = array(
			'messageId'  => (string) ( $m['message_id'] ?? '' ),
			'fromId'     => (int)    ( $m['from_id'] ?? 0 ),
			'toId'       => (int)    ( $m['to_id'] ?? 0 ),
			'isMine'     => ( (int) ( $m['from_id'] ?? 0 ) === $my_shop_id ),
			'type'       => (string) ( $m['type'] ?? 'text' ),
			'text'       => (string) ( $m['content']['text'] ?? '' ),
			'imageUrl'   => (string) ( $m['content']['url'] ?? '' ),
			'timestamp'  => (int)    ( $m['created_timestamp'] ?? 0 ),
		);
	}
	wp_send_json_success( array( 'messages' => $messages ) );
}
add_action( 'wp_ajax_pw_personalizados_shopee_messages', 'pw_personalizados_shopee_messages' );

/* ── Enviar mensagem ─────────────────────────────────────── */

function pw_personalizados_shopee_send_message() {
	pw_personalizados_ajax_guard();
	if ( ! pw_personalizados_shopee_module_ready() || ! pw_shopee_is_connected() ) {
		wp_send_json_error( array( 'message' => 'Shopee não conectada.' ), 400 );
	}
	$conv_id = sanitize_text_field( wp_unslash( $_POST['conversation_id'] ?? '' ) );
	$to_id   = (int) ( $_POST['to_id'] ?? 0 );
	$text    = sanitize_textarea_field( wp_unslash( $_POST['text'] ?? '' ) );
	if ( ! $conv_id || ! $to_id || ! $text ) {
		wp_send_json_error( array( 'message' => 'Parâmetros incompletos.' ), 400 );
	}
	$result = pw_shopee_send_chat_message( $conv_id, $to_id, $text );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ), 502 );
	}
	wp_send_json_success( array( 'ok' => true ) );
}
add_action( 'wp_ajax_pw_personalizados_shopee_send_message', 'pw_personalizados_shopee_send_message' );

/* ── Pedidos ─────────────────────────────────────────────── */

function pw_personalizados_shopee_orders() {
	pw_personalizados_ajax_guard();
	if ( ! pw_personalizados_shopee_module_ready() || ! pw_shopee_is_connected() ) {
		wp_send_json_error( array( 'message' => 'Shopee não conectada.' ), 400 );
	}
	$days   = max( 1, min( 90, (int) ( $_POST['days'] ?? 30 ) ) );
	$result = pw_shopee_get_order_list( $days );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ), 502 );
	}
	$raw    = $result['response']['order_list'] ?? array();
	$orders = array();
	foreach ( $raw as $o ) {
		$orders[] = array(
			'orderSn'       => (string) ( $o['order_sn'] ?? '' ),
			'status'        => pw_shopee_order_status_label( (string) ( $o['order_status'] ?? '' ) ),
			'statusRaw'     => (string) ( $o['order_status'] ?? '' ),
			'buyerUsername' => (string) ( $o['buyer_username'] ?? '' ),
			'totalAmount'   => (float)  ( $o['total_amount'] ?? 0 ),
			'currency'      => (string) ( $o['currency'] ?? 'BRL' ),
			'createTime'    => (int)    ( $o['create_time'] ?? 0 ),
			'updateTime'    => (int)    ( $o['update_time'] ?? 0 ),
		);
	}
	wp_send_json_success( array( 'orders' => $orders, 'days' => $days ) );
}
add_action( 'wp_ajax_pw_personalizados_shopee_orders', 'pw_personalizados_shopee_orders' );

function pw_shopee_order_status_label( $status ) {
	$map = array(
		'UNPAID'           => 'Aguardando pagamento',
		'READY_TO_SHIP'    => 'Pronto para enviar',
		'PROCESSED'        => 'Processado',
		'SHIPPED'          => 'Enviado',
		'TO_CONFIRM_RECEIVE' => 'Aguardando confirmação',
		'IN_CANCEL'        => 'Cancelamento em processo',
		'CANCELLED'        => 'Cancelado',
		'TO_RETURN'        => 'Devolução solicitada',
		'COMPLETED'        => 'Concluído',
	);
	return $map[ $status ] ?? $status;
}

/* ── Notificações ────────────────────────────────────────── */

function pw_personalizados_shopee_notification_status() {
	pw_personalizados_ajax_guard();
	if ( ! pw_personalizados_shopee_module_ready() || ! function_exists( 'pw_shopee_get_notification_status' ) ) {
		wp_send_json_success( array( 'hasNew' => false, 'hasAnything' => false ) );
	}
	$status = pw_shopee_get_notification_status( get_current_user_id() );
	wp_send_json_success( $status );
}
add_action( 'wp_ajax_pw_personalizados_shopee_notification_status', 'pw_personalizados_shopee_notification_status' );

function pw_personalizados_shopee_mark_notifications_seen() {
	pw_personalizados_ajax_guard();
	if ( function_exists( 'pw_shopee_mark_notifications_seen' ) ) {
		pw_shopee_mark_notifications_seen( get_current_user_id() );
	}
	wp_send_json_success( array( 'ok' => true ) );
}
add_action( 'wp_ajax_pw_personalizados_shopee_mark_notifications_seen', 'pw_personalizados_shopee_mark_notifications_seen' );

/* ── Sugestão de resposta (IA) ───────────────────────────── */

function pw_personalizados_shopee_ai_reply() {
	pw_personalizados_ajax_guard();
	$api_key = get_option( 'pw_ml_anthropic_api_key', '' );
	if ( ! $api_key ) { wp_send_json_error( array( 'message' => 'Chave da API Anthropic não configurada (use as mesmas configurações do Mercado Livre).' ), 400 ); }
	$raw_messages = isset( $_POST['messages'] ) ? wp_unslash( $_POST['messages'] ) : '';
	$messages     = is_string( $raw_messages ) ? json_decode( $raw_messages, true ) : null;
	$buyer_name   = sanitize_text_field( wp_unslash( $_POST['buyer_name'] ?? '' ) );
	if ( ! is_array( $messages ) || empty( $messages ) ) {
		wp_send_json_error( array( 'message' => 'Mensagens não fornecidas.' ), 400 );
	}
	$thread = '';
	foreach ( $messages as $m ) {
		$who     = ! empty( $m['isMine'] ) ? 'Vendedor' : ( $buyer_name ?: 'Comprador' );
		$text    = wp_strip_all_tags( (string) ( $m['text'] ?? '' ) );
		if ( '' === $text ) { continue; }
		$thread .= $who . ': ' . $text . "\n";
	}
	if ( ! $thread ) { wp_send_json_error( array( 'message' => 'Sem texto para analisar.' ), 400 ); }
	$prompt = 'Você é um assistente de atendimento ao cliente para uma loja que vende impressões DTF (transferência de calor para tecidos). ' .
	          'Abaixo está uma conversa entre o vendedor e o comprador na Shopee. ' .
	          'Sugira uma resposta clara, amigável e objetiva em português brasileiro para o vendedor enviar.' . "\n\n" .
	          'Conversa:' . "\n" . $thread . "\n" .
	          'Resposta sugerida (apenas o texto da resposta, sem prefixo):';
	$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
		'timeout' => 30,
		'headers' => array(
			'x-api-key'         => $api_key,
			'anthropic-version' => '2023-06-01',
			'Content-Type'      => 'application/json',
		),
		'body' => wp_json_encode( array(
			'model'      => 'claude-haiku-4-5-20251001',
			'max_tokens' => 400,
			'messages'   => array( array( 'role' => 'user', 'content' => $prompt ) ),
		) ),
	) );
	if ( is_wp_error( $response ) ) {
		wp_send_json_error( array( 'message' => 'Falha ao conectar com a IA.' ), 502 );
	}
	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	$text = $body['content'][0]['text'] ?? '';
	if ( ! $text ) {
		wp_send_json_error( array( 'message' => 'A IA não retornou uma sugestão.' ), 502 );
	}
	wp_send_json_success( array( 'suggestion' => trim( $text ) ) );
}
add_action( 'wp_ajax_pw_personalizados_shopee_ai_reply', 'pw_personalizados_shopee_ai_reply' );
