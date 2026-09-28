<?php
/**
 * Relatórios → Marketplace → Mercado Livre.
 *
 * Só chama funções do módulo mercadolivre/ (dono da conexão/token) — nunca
 * duplica credenciais aqui. Se aquele módulo não estiver ativo, cada
 * handler devolve um erro amigável em vez de fatal (mesmo espírito
 * defensivo do restante do plugin ao cruzar módulos).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function pw_personalizados_ml_module_ready() {
	return function_exists( 'pw_ml_get_valid_access_token' ) && function_exists( 'pw_ml_is_connected' );
}

function pw_personalizados_ml_send_error( $error ) {
	if ( is_wp_error( $error ) ) {
		wp_send_json_error( array( 'message' => $error->get_error_message(), 'code' => $error->get_error_code() ), 422 );
	}
	wp_send_json_error( array( 'message' => 'O módulo Mercado Livre não está disponível.', 'code' => 'ml_module_missing' ), 422 );
}

function pw_personalizados_ml_guard_ready() {
	pw_personalizados_ajax_guard();
	if ( ! pw_personalizados_ml_module_ready() ) { pw_personalizados_ml_send_error( null ); }
	if ( ! pw_ml_is_connected() ) { pw_personalizados_ml_send_error( new WP_Error( 'ml_not_connected', 'Conecte a conta em PrintWay → Mercado Livre antes de usar este relatório.' ) ); }
}

/* ---------- Mensagens: perguntas pré-venda ---------- */

function pw_personalizados_ml_pending_questions() {
	pw_personalizados_ml_guard_ready();
	$result = pw_ml_get_unanswered_questions();
	if ( is_wp_error( $result ) ) { pw_personalizados_ml_send_error( $result ); }
	wp_send_json_success( $result );
}
add_action( 'wp_ajax_pw_personalizados_ml_pending_questions', 'pw_personalizados_ml_pending_questions' );

function pw_personalizados_ml_answer_question() {
	pw_personalizados_ml_guard_ready();
	$question_id = isset( $_POST['question_id'] ) ? sanitize_text_field( wp_unslash( $_POST['question_id'] ) ) : '';
	$text = isset( $_POST['text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['text'] ) ) : '';
	$result = pw_ml_answer_question( $question_id, $text );
	if ( is_wp_error( $result ) ) { pw_personalizados_ml_send_error( $result ); }
	wp_send_json_success( array( 'answered' => true ) );
}
add_action( 'wp_ajax_pw_personalizados_ml_answer_question', 'pw_personalizados_ml_answer_question' );

/* ---------- Mensagens: pós-venda (packs) ---------- */

function pw_personalizados_ml_unread_messages() {
	pw_personalizados_ml_guard_ready();
	$result = pw_ml_get_unread_message_packs();
	if ( is_wp_error( $result ) ) { pw_personalizados_ml_send_error( $result ); }
	wp_send_json_success( array( 'packs' => $result ) );
}
add_action( 'wp_ajax_pw_personalizados_ml_unread_messages', 'pw_personalizados_ml_unread_messages' );

function pw_personalizados_ml_pack_conversation() {
	pw_personalizados_ml_guard_ready();
	$pack_id = isset( $_POST['pack_id'] ) ? sanitize_text_field( wp_unslash( $_POST['pack_id'] ) ) : '';
	$result = pw_ml_get_pack_conversation( $pack_id, true );
	if ( is_wp_error( $result ) ) { pw_personalizados_ml_send_error( $result ); }
	wp_send_json_success( $result );
}
add_action( 'wp_ajax_pw_personalizados_ml_pack_conversation', 'pw_personalizados_ml_pack_conversation' );

function pw_personalizados_ml_reply_message() {
	pw_personalizados_ml_guard_ready();
	$pack_id = isset( $_POST['pack_id'] ) ? sanitize_text_field( wp_unslash( $_POST['pack_id'] ) ) : '';
	$text = isset( $_POST['text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['text'] ) ) : '';
	$result = pw_ml_send_pack_reply( $pack_id, $text );
	if ( is_wp_error( $result ) ) { pw_personalizados_ml_send_error( $result ); }
	wp_send_json_success( array( 'sent' => true ) );
}
add_action( 'wp_ajax_pw_personalizados_ml_reply_message', 'pw_personalizados_ml_reply_message' );

function pw_personalizados_ml_replied_messages_log() {
	pw_personalizados_ml_guard_ready();
	$local_entries = function_exists( 'pw_ml_get_replied_messages_log' ) ? pw_ml_get_replied_messages_log( 200 ) : array();
	$local_pack_ids = array_map( function ( $e ) { return (string) ( $e['packId'] ?? '' ); }, $local_entries );

	// Detecta respostas feitas diretamente no site do ML e as persiste no log
	// local imediatamente — assim ficam gravadas mesmo depois que o comprador
	// ler a mensagem (o endpoint /messages/unread é efêmero).
	// ATENÇÃO: /messages/unread?role=buyer retorna packs onde o comprador não
	// leu — mas inclui packs onde SOMOS o comprador (não o vendedor). Por isso
	// filtramos pelo nosso próprio seller_id antes de consultar a conversa.
	$ml_settings   = function_exists( 'pw_ml_get_settings' ) ? pw_ml_get_settings() : array();
	$our_seller_id = (string) ( $ml_settings['user_id'] ?? '' );
	$new_external = array();
	$buyer_unread = function_exists( 'pw_ml_api_request' ) ? pw_ml_api_request( 'GET', '/messages/unread?role=buyer&tag=post_sale' ) : new WP_Error( 'unavailable', '' );
	if ( ! is_wp_error( $buyer_unread ) && is_array( $buyer_unread['results'] ?? null ) ) {
		$processed = 0;
		foreach ( $buyer_unread['results'] as $entry ) {
			if ( $processed >= 15 ) { break; }
			$resource = (string) ( $entry['resource'] ?? '' );
			if ( ! preg_match( '#/packs/(\d+)/sellers/(\d+)#', $resource, $match ) ) { continue; }
			// Pula packs onde somos o comprador (seller na URL é diferente do nosso).
			if ( $our_seller_id && $match[2] !== $our_seller_id ) { continue; }
			$pack_id = $match[1];
			if ( in_array( $pack_id, $local_pack_ids, true ) ) { continue; }
			$conversation = pw_ml_get_pack_conversation( $pack_id, false );
			if ( is_wp_error( $conversation ) ) { continue; }
			$messages = is_array( $conversation['messages'] ?? null ) ? $conversation['messages'] : array();
			$last_seller = null;
			foreach ( array_reverse( $messages ) as $msg ) {
				if ( ! empty( $msg['fromSeller'] ) ) { $last_seller = $msg; break; }
			}
			if ( ! $last_seller ) { continue; }
			$ts = ! empty( $last_seller['date'] ) ? (int) strtotime( (string) $last_seller['date'] ) : time();
			$new_external[] = array(
				'packId'      => $pack_id,
				'repliedAt'   => $ts,
				'repliedBy'   => 'Site Mercado Livre',
				'textPreview' => mb_substr( (string) ( $last_seller['text'] ?? '' ), 0, 140 ),
				'external'    => true,
			);
			$processed++;
		}
	}

	// Grava no WP as entradas externas recém-detectadas para que persistam.
	if ( $new_external ) {
		$full_log = get_option( 'pw_ml_replied_messages_log', array() );
		if ( ! is_array( $full_log ) ) { $full_log = array(); }
		$full_pack_ids = array_map( function ( $e ) { return (string) ( $e['packId'] ?? '' ); }, $full_log );
		foreach ( array_reverse( $new_external ) as $ext ) {
			if ( ! in_array( (string) $ext['packId'], $full_pack_ids, true ) ) {
				array_unshift( $full_log, $ext );
				array_unshift( $full_pack_ids, (string) $ext['packId'] );
			}
		}
		update_option( 'pw_ml_replied_messages_log', array_slice( $full_log, 0, 200 ), false );
		$local_entries = function_exists( 'pw_ml_get_replied_messages_log' ) ? pw_ml_get_replied_messages_log( 200 ) : $full_log;
	}

	usort( $local_entries, function ( $a, $b ) { return (int) ( $b['repliedAt'] ?? 0 ) - (int) ( $a['repliedAt'] ?? 0 ); } );
	wp_send_json_success( array( 'entries' => array_slice( $local_entries, 0, 60 ) ) );
}
add_action( 'wp_ajax_pw_personalizados_ml_replied_messages_log', 'pw_personalizados_ml_replied_messages_log' );

/* ---------- Respostas prontas e sugestão por IA ---------- */

function pw_ml_get_saved_responses() {
	$data = get_option( 'pw_ml_saved_responses', array() );
	return is_array( $data ) ? $data : array();
}

function pw_ml_keyword_score( $a, $b ) {
	static $sw = null;
	if ( $sw === null ) {
		$sw = array_flip( array( 'o','a','os','as','de','da','do','das','dos','em','no','na','nos','nas',
			'um','uma','uns','umas','para','por','com','que','qual','quais','como','onde','quando','tem',
			'ser','ter','me','te','se','eu','tu','nos','vcs','e','sao','posso','preciso','precisa','ha',
			'vai','ja','ainda','tambem','so','mais','muito','bem','aqui','la','isso','isto','este','esta',
			'ao','pelo','pela','pelos','pelas','num','numa','sobre','ate','apos','desde','entre','sem' ) );
	}
	$norm = function( $text ) use ( $sw ) {
		$text = mb_strtolower( $text, 'UTF-8' );
		$from = array( 'á','à','ã','â','é','ê','í','ó','õ','ô','ú','ç','ü','ï','ë','ä','ñ' );
		$to   = array( 'a','a','a','a','e','e','i','o','o','o','u','c','u','i','e','a','n' );
		$text = str_replace( $from, $to, $text );
		$text = preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $text );
		$words = preg_split( '/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY );
		return array_values( array_filter( $words, function( $w ) use ( $sw ) {
			return mb_strlen( $w ) >= 3 && ! isset( $sw[ $w ] );
		} ) );
	};
	$w1 = $norm( $a );
	$w2 = $norm( $b );
	if ( empty( $w1 ) || empty( $w2 ) ) { return 0.0; }
	$stem  = function( $w ) { return mb_substr( $w, 0, min( 5, mb_strlen( $w ) ), 'UTF-8' ); };
	$s1    = array_map( $stem, $w1 );
	$s2    = array_map( $stem, $w2 );
	$inter = count( array_intersect( $s1, $s2 ) );
	$union = count( array_unique( array_merge( $s1, $s2 ) ) );
	return $union > 0 ? (float) $inter / $union : 0.0;
}

function pw_ml_ai_suggest_responses_batch( $questions, $saved ) {
	$api_key = get_option( 'pw_ml_anthropic_api_key', '' );
	if ( ! $api_key || empty( $saved ) || empty( $questions ) ) { return null; }
	$tmpl_lines = array_map( function( $r ) {
		return '- ID "' . $r['id'] . '": "' . wp_strip_all_tags( $r['question'] ) . '"';
	}, $saved );
	$q_lines = array();
	foreach ( $questions as $i => $q ) {
		$q_lines[] = ( $i + 1 ) . '. ID "' . $q['id'] . '": "' . wp_strip_all_tags( $q['text'] ) . '"';
	}
	$prompt = "Analise as perguntas dos clientes e para cada uma identifique quais respostas cadastradas se aplicam semanticamente (considere sinônimos, variações e intenção similar).\n\n" .
	          "Respostas cadastradas:\n" . implode( "\n", $tmpl_lines ) . "\n\n" .
	          "Perguntas dos clientes:\n" . implode( "\n", $q_lines ) . "\n\n" .
	          'Retorne APENAS um JSON object: chave = ID da pergunta, valor = array de até 2 IDs de respostas relevantes (por ordem de relevância). Se nenhuma se aplica, use []. Ex: {"qid1":["rid1"],"qid2":[]}';
	$resp = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
		'timeout' => 20,
		'headers' => array(
			'x-api-key'         => $api_key,
			'anthropic-version' => '2023-06-01',
			'content-type'      => 'application/json',
		),
		'body' => wp_json_encode( array(
			'model'      => 'claude-haiku-4-5-20251001',
			'max_tokens' => 400,
			'messages'   => array( array( 'role' => 'user', 'content' => $prompt ) ),
		) ),
	) );
	if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) { return null; }
	$data = json_decode( wp_remote_retrieve_body( $resp ), true );
	$text = (string) ( $data['content'][0]['text'] ?? '' );
	if ( preg_match( '/\{.*\}/s', $text, $match ) ) {
		$map = json_decode( $match[0], true );
		if ( is_array( $map ) ) { return $map; }
	}
	return null;
}

function pw_personalizados_ml_saved_responses_get() {
	pw_personalizados_ajax_guard();
	$has_key = '' !== (string) get_option( 'pw_ml_anthropic_api_key', '' );
	wp_send_json_success( array( 'responses' => pw_ml_get_saved_responses(), 'hasApiKey' => $has_key ) );
}
add_action( 'wp_ajax_pw_personalizados_ml_saved_responses_get', 'pw_personalizados_ml_saved_responses_get' );

function pw_personalizados_ml_saved_responses_save() {
	pw_personalizados_ajax_guard();
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Sem permissão.' ), 403 ); }
	$id       = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
	$question = isset( $_POST['question'] ) ? sanitize_text_field( wp_unslash( $_POST['question'] ) ) : '';
	$answer   = isset( $_POST['answer'] ) ? sanitize_textarea_field( wp_unslash( $_POST['answer'] ) ) : '';
	if ( ! $question || ! $answer ) { wp_send_json_error( array( 'message' => 'Preencha a pergunta-modelo e a resposta.' ) ); }
	$all = pw_ml_get_saved_responses();
	$found = false;
	foreach ( $all as &$r ) {
		if ( $r['id'] === $id ) {
			$r['question'] = $question;
			$r['answer']   = $answer;
			$r['updated_at'] = time();
			$found = true;
			break;
		}
	}
	unset( $r );
	if ( ! $found ) {
		$all[] = array(
			'id'         => wp_generate_password( 12, false ),
			'question'   => $question,
			'answer'     => $answer,
			'created_at' => time(),
			'updated_at' => time(),
		);
	}
	update_option( 'pw_ml_saved_responses', $all, false );
	wp_send_json_success( array( 'responses' => $all ) );
}
add_action( 'wp_ajax_pw_personalizados_ml_saved_responses_save', 'pw_personalizados_ml_saved_responses_save' );

function pw_personalizados_ml_saved_responses_delete() {
	pw_personalizados_ajax_guard();
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Sem permissão.' ), 403 ); }
	$id  = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
	$all = array_values( array_filter( pw_ml_get_saved_responses(), function( $r ) use ( $id ) {
		return $r['id'] !== $id;
	} ) );
	update_option( 'pw_ml_saved_responses', $all, false );
	wp_send_json_success( array( 'responses' => $all ) );
}
add_action( 'wp_ajax_pw_personalizados_ml_saved_responses_delete', 'pw_personalizados_ml_saved_responses_delete' );

function pw_personalizados_ml_suggest_responses_batch() {
	pw_personalizados_ajax_guard();
	$raw = isset( $_POST['questions'] ) ? wp_unslash( $_POST['questions'] ) : '';
	$questions = is_string( $raw ) ? json_decode( $raw, true ) : null;
	if ( ! is_array( $questions ) || empty( $questions ) ) {
		wp_send_json_success( array( 'map' => array(), 'method' => 'none' ) );
	}
	$saved = pw_ml_get_saved_responses();
	if ( empty( $saved ) ) { wp_send_json_success( array( 'map' => array(), 'method' => 'none' ) ); }

	$ai_map = pw_ml_ai_suggest_responses_batch( $questions, $saved );
	if ( is_array( $ai_map ) ) {
		$result = array();
		foreach ( $questions as $q ) {
			$qid = (string) ( $q['id'] ?? '' );
			$ids = is_array( $ai_map[ $qid ] ?? null ) ? $ai_map[ $qid ] : array();
			$matches = array();
			foreach ( $ids as $rid ) {
				foreach ( $saved as $r ) {
					if ( $r['id'] === $rid ) { $matches[] = $r; break; }
				}
			}
			$result[ $qid ] = $matches;
		}
		wp_send_json_success( array( 'map' => $result, 'method' => 'ai' ) );
	}

	$result = array();
	foreach ( $questions as $q ) {
		$qid = (string) ( $q['id'] ?? '' );
		$text = (string) ( $q['text'] ?? '' );
		$scored = array();
		foreach ( $saved as $r ) {
			$score = pw_ml_keyword_score( $text, $r['question'] );
			if ( $score >= 0.25 ) { $scored[] = array( 'score' => $score, 'r' => $r ); }
		}
		usort( $scored, function( $a, $b ) { return $b['score'] <=> $a['score']; } );
		$result[ $qid ] = array_map( function( $s ) { return $s['r']; }, array_slice( $scored, 0, 2 ) );
	}
	wp_send_json_success( array( 'map' => $result, 'method' => 'keyword' ) );
}
add_action( 'wp_ajax_pw_personalizados_ml_suggest_responses_batch', 'pw_personalizados_ml_suggest_responses_batch' );

function pw_personalizados_ml_ai_key_save() {
	pw_personalizados_ajax_guard();
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Sem permissão.' ), 403 ); }
	$key = isset( $_POST['key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['key'] ) ) ) : '';
	if ( $key === '' ) { delete_option( 'pw_ml_anthropic_api_key' ); wp_send_json_success( array( 'hasKey' => false ) ); }
	update_option( 'pw_ml_anthropic_api_key', $key, false );
	wp_send_json_success( array( 'hasKey' => true ) );
}
add_action( 'wp_ajax_pw_personalizados_ml_ai_key_save', 'pw_personalizados_ml_ai_key_save' );

/* ---------- Reputação ---------- */

function pw_personalizados_ml_reputation() {
	pw_personalizados_ml_guard_ready();
	$result = pw_ml_get_reputation_summary();
	if ( is_wp_error( $result ) ) { pw_personalizados_ml_send_error( $result ); }
	wp_send_json_success( $result );
}
add_action( 'wp_ajax_pw_personalizados_ml_reputation', 'pw_personalizados_ml_reputation' );

/* ---------- Financeiro (dados sensíveis: só administradores) ---------- */

function pw_personalizados_ml_financial_summary() {
	pw_personalizados_ml_guard_ready();
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Somente administradores podem ver o financeiro do Mercado Livre.', 'code' => 'forbidden' ), 403 ); }
	$period = isset( $_POST['period'] ) ? sanitize_key( wp_unslash( $_POST['period'] ) ) : '30';
	$custom_from = isset( $_POST['custom_from'] ) ? sanitize_text_field( wp_unslash( $_POST['custom_from'] ) ) : '';
	$custom_to = isset( $_POST['custom_to'] ) ? sanitize_text_field( wp_unslash( $_POST['custom_to'] ) ) : '';
	$result = pw_ml_get_financial_summary( $period, $custom_from, $custom_to );
	if ( is_wp_error( $result ) ) { pw_personalizados_ml_send_error( $result ); }
	wp_send_json_success( $result );
}
add_action( 'wp_ajax_pw_personalizados_ml_financial_summary', 'pw_personalizados_ml_financial_summary' );

/* ---------- Ícone de notificação no menu (só administradores) ---------- */

/**
 * Roda em segundo plano a cada poucos minutos — nunca deve gerar erro visível
 * pro usuário, então qualquer problema (não conectado, token expirado, etc.)
 * simplesmente responde "sem novidade" em vez de propagar o erro.
 */
function pw_personalizados_ml_notification_status() {
	pw_personalizados_ajax_guard();
	if ( ! current_user_can( 'manage_options' ) || ! pw_personalizados_ml_module_ready() || ! pw_ml_is_connected() ) {
		wp_send_json_success( array( 'hasNew' => false ) );
	}
	$result = pw_ml_get_notification_status( get_current_user_id() );
	if ( is_wp_error( $result ) ) { wp_send_json_success( array( 'hasNew' => false ) ); }
	wp_send_json_success( $result );
}
add_action( 'wp_ajax_pw_personalizados_ml_notification_status', 'pw_personalizados_ml_notification_status' );

function pw_personalizados_ml_mark_notifications_seen() {
	pw_personalizados_ajax_guard();
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Somente administradores.', 'code' => 'forbidden' ), 403 ); }
	if ( ! pw_personalizados_ml_module_ready() ) { wp_send_json_success( array( 'marked' => false ) ); }
	pw_ml_mark_notifications_seen( get_current_user_id() );
	wp_send_json_success( array( 'marked' => true ) );
}
add_action( 'wp_ajax_pw_personalizados_ml_mark_notifications_seen', 'pw_personalizados_ml_mark_notifications_seen' );

/* ---------- Nota fiscal e etiqueta (por pedido, direto na coluna do Financeiro) ---------- */

function pw_personalizados_ml_send_fiscal_document() {
	pw_personalizados_ml_guard_ready();
	$pack_id = isset( $_POST['pack_id'] ) ? sanitize_text_field( wp_unslash( $_POST['pack_id'] ) ) : '';
	$shipment_id = isset( $_POST['shipment_id'] ) ? sanitize_text_field( wp_unslash( $_POST['shipment_id'] ) ) : '';
	if ( empty( $_FILES['xml_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['xml_file']['tmp_name'] ) ) {
		pw_personalizados_ml_send_error( new WP_Error( 'ml_input', 'Selecione o arquivo XML da nota fiscal.' ) );
	}
	$filename = sanitize_file_name( (string) $_FILES['xml_file']['name'] );
	if ( ! preg_match( '/\.xml$/i', $filename ) ) {
		pw_personalizados_ml_send_error( new WP_Error( 'ml_input', 'O arquivo precisa ser um XML.' ) );
	}
	$contents = file_get_contents( $_FILES['xml_file']['tmp_name'] );
	// pw_ml_attach_fiscal_document() decide sozinho, a partir do shipment_id,
	// se esse envio exige o fluxo de "importar pela etiqueta" (logísticas
	// drop_off/xd_drop_off/cross_docking/xd_same_day) em vez do padrão de
	// anexar ao pacote — ver o comentário da função pra detalhes.
	$result = pw_ml_attach_fiscal_document( $pack_id, $contents, $filename, $shipment_id );
	if ( is_wp_error( $result ) ) { pw_personalizados_ml_send_error( $result ); }
	// alreadyAdvanced: o Mercado Livre recusou por o envio já ter avançado de
	// etapa (não é mais um erro de verdade) — pw_ml_attach_fiscal_document()
	// já leu os dados da própria NFe enviada agora e gravou como "já enviado".
	wp_send_json_success( array(
		'sent'           => true,
		'alreadyAdvanced' => ! empty( $result['alreadyAdvanced'] ),
		'number'         => (string) ( $result['number'] ?? '' ),
		'series'         => (string) ( $result['series'] ?? '' ),
	) );
}
add_action( 'wp_ajax_pw_personalizados_ml_send_fiscal_document', 'pw_personalizados_ml_send_fiscal_document' );

/** Marca manualmente a NF de um pacote como já enviada FORA do sistema (ex.:
 * etiqueta impressa e nota já anexada direto pelo painel do Mercado Livre) —
 * grava no mesmo registro usado quando o envio funciona por aqui, então o
 * botão "Enviar XML" fica escondido em definitivo e mostra o número
 * informado, sem precisar subir nenhum arquivo. */
function pw_personalizados_ml_mark_fiscal_document_manual() {
	pw_personalizados_ml_guard_ready();
	$pack_id = isset( $_POST['pack_id'] ) ? sanitize_text_field( wp_unslash( $_POST['pack_id'] ) ) : '';
	$number  = isset( $_POST['number'] ) ? sanitize_text_field( wp_unslash( $_POST['number'] ) ) : '';
	$series  = isset( $_POST['series'] ) ? sanitize_text_field( wp_unslash( $_POST['series'] ) ) : '';
	if ( ! $pack_id ) { pw_personalizados_ml_send_error( new WP_Error( 'ml_input', 'Pedido inválido.' ) ); }
	if ( ! $number ) { pw_personalizados_ml_send_error( new WP_Error( 'ml_input', 'Informe o número da nota fiscal.' ) ); }
	// O anexo do XML é opcional aqui — o admin pode não ter o arquivo em mãos,
	// só o número. Se anexar, também fica salvo pra poder baixar depois.
	if ( ! empty( $_FILES['xml_file']['tmp_name'] ) && is_uploaded_file( $_FILES['xml_file']['tmp_name'] ) ) {
		$xml_contents = file_get_contents( $_FILES['xml_file']['tmp_name'] );
		if ( $xml_contents ) { pw_ml_save_fiscal_document_xml( $pack_id, $xml_contents ); }
	}
	pw_ml_record_fiscal_document_sent( $pack_id, $number, $series );
	wp_send_json_success( array( 'sent' => true, 'hasXml' => (bool) pw_ml_get_fiscal_document_xml_path( $pack_id ) ) );
}
add_action( 'wp_ajax_pw_personalizados_ml_mark_fiscal_document_manual', 'pw_personalizados_ml_mark_fiscal_document_manual' );

/** Apaga o registro local de "NF enviada" pra um pacote — pro caso da nota
 * enviada estar errada e precisar tentar de novo. Traz de volta o botão
 * "Enviar XML" no lugar do número da nota; não mexe em nada do lado do
 * Mercado Livre (é só o registro local que controla o que este sistema
 * mostra, já que a API do Mercado Livre não lista notas já enviadas). */
function pw_personalizados_ml_clear_fiscal_document() {
	pw_personalizados_ml_guard_ready();
	$pack_id = isset( $_POST['pack_id'] ) ? sanitize_text_field( wp_unslash( $_POST['pack_id'] ) ) : '';
	if ( ! $pack_id ) { pw_personalizados_ml_send_error( new WP_Error( 'ml_input', 'Pedido inválido.' ) ); }
	pw_ml_clear_fiscal_document_sent( $pack_id );
	wp_send_json_success( array( 'cleared' => true ) );
}
add_action( 'wp_ajax_pw_personalizados_ml_clear_fiscal_document', 'pw_personalizados_ml_clear_fiscal_document' );

function pw_personalizados_ml_check_shipment_status() {
	pw_personalizados_ml_guard_ready();
	$shipment_id = isset( $_POST['shipment_id'] ) ? sanitize_text_field( wp_unslash( $_POST['shipment_id'] ) ) : '';
	$result = pw_ml_get_shipment_status( $shipment_id );
	if ( is_wp_error( $result ) ) { pw_personalizados_ml_send_error( $result ); }
	if ( is_array( $result ) ) { $result['labelPrinted'] = pw_ml_get_label_print_info( $shipment_id ); }
	wp_send_json_success( $result );
}
add_action( 'wp_ajax_pw_personalizados_ml_check_shipment_status', 'pw_personalizados_ml_check_shipment_status' );

/**
 * Serve o PDF da etiqueta direto (não é JSON) — pensado pra ser aberto numa
 * nova aba via GET (window.open), já que o navegador não tem o Access Token
 * do Mercado Livre pra buscar a etiqueta diretamente de lá.
 */
function pw_personalizados_ml_shipment_label() {
	pw_personalizados_ml_guard_ready();
	$shipment_id = isset( $_GET['shipment_id'] ) ? sanitize_text_field( wp_unslash( $_GET['shipment_id'] ) ) : '';
	$pdf = pw_ml_fetch_shipment_label_pdf( $shipment_id );
	if ( is_wp_error( $pdf ) ) { wp_die( esc_html( $pdf->get_error_message() ) ); }
	pw_ml_record_label_printed_if_new( $shipment_id );
	nocache_headers();
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: inline; filename="etiqueta-' . preg_replace( '/[^0-9]/', '', $shipment_id ) . '.pdf"' );
	header( 'Content-Length: ' . strlen( $pdf ) );
	echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput -- binário de PDF vindo direto da API do Mercado Livre.
	exit;
}
add_action( 'wp_ajax_pw_personalizados_ml_shipment_label', 'pw_personalizados_ml_shipment_label' );

/** Serve o XML da NFe salvo localmente pra um pack — mesmo padrão da etiqueta
 * acima (GET direto, aberto numa nova aba). Só existe se o XML foi enviado
 * (com sucesso ou pelo fallback de "já enviado") por este sistema. */
function pw_personalizados_ml_download_fiscal_xml() {
	pw_personalizados_ml_guard_ready();
	$pack_id = isset( $_GET['pack_id'] ) ? sanitize_text_field( wp_unslash( $_GET['pack_id'] ) ) : '';
	$path = pw_ml_get_fiscal_document_xml_path( $pack_id );
	if ( ! $path ) { wp_die( esc_html( 'Nenhum XML de nota fiscal salvo para este pedido.' ) ); }
	$xml = file_get_contents( $path );
	nocache_headers();
	header( 'Content-Type: application/xml' );
	header( 'Content-Disposition: attachment; filename="nota-fiscal-' . preg_replace( '/[^0-9]/', '', $pack_id ) . '.xml"' );
	header( 'Content-Length: ' . strlen( $xml ) );
	echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput -- XML salvo localmente, gerado pela própria emissora fiscal do usuário.
	exit;
}
add_action( 'wp_ajax_pw_personalizados_ml_download_fiscal_xml', 'pw_personalizados_ml_download_fiscal_xml' );
