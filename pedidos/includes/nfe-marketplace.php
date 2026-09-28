<?php
/**
 * NF-e Marketplace — geração de PDF de dados e gestão de XMLs por pedido.
 *
 * Handlers AJAX:
 *   pw_personalizados_nfe_generate_pdf  — gera PDF com dados dos pedidos selecionados
 *   pw_personalizados_nfe_upload_xml    — faz upload e vincula o XML ao pedido
 *   pw_personalizados_nfe_download_xml  — faz download do XML vinculado
 *   pw_personalizados_nfe_remove_xml    — remove o XML vinculado
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Diretório de upload dos XMLs de NF-e (fora do acesso web direto). */
function pw_personalizados_nfe_dir() {
	$upload = wp_upload_dir();
	return trailingslashit( $upload['basedir'] ) . 'printway-nfe';
}

/** Garante que o diretório exista e esteja protegido por .htaccess. */
function pw_personalizados_nfe_ensure_dir() {
	$dir = pw_personalizados_nfe_dir();
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	$htaccess = $dir . '/.htaccess';
	if ( ! file_exists( $htaccess ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $htaccess, "deny from all\n" );
	}
	return is_dir( $dir );
}

/** Lê um pedido do banco pelo número. */
function pw_personalizados_nfe_get_order( $order_number ) {
	global $wpdb;
	$table = pw_personalizados_table_map()['pw_personalizados_orders'];
	$json  = $wpdb->get_var( $wpdb->prepare(
		"SELECT payload FROM {$table} WHERE object_id = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		(string) $order_number
	) );
	if ( ! $json ) {
		return null;
	}
	$order = json_decode( $json, true );
	return is_array( $order ) ? $order : null;
}

/** Atualiza o payload de um pedido no banco. */
function pw_personalizados_nfe_update_order( $order ) {
	global $wpdb;
	$table   = pw_personalizados_table_map()['pw_personalizados_orders'];
	$payload = wp_json_encode( $order, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	return $wpdb->update(
		$table,
		array(
			'payload'    => $payload,
			'updated_at' => current_time( 'mysql', true ),
		),
		array( 'object_id' => (string) $order['orderNumber'] ),
		array( '%s', '%s' ),
		array( '%s' )
	);
}

// =============================================================================
// Gerar PDF com dados dos pedidos para emissão manual de NF-e
// =============================================================================

function pw_personalizados_nfe_generate_pdf() {
	pw_personalizados_ajax_guard();
	ob_start(); // captura qualquer saída espúria (notices PHP, hooks WP, FPDF debug)

	$raw_numbers = isset( $_POST['order_numbers'] ) ? (array) wp_unslash( $_POST['order_numbers'] ) : array();
	$numbers     = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $raw_numbers ) ) ) );
	if ( ! $numbers ) {
		wp_send_json_error( array( 'message' => 'Nenhum pedido selecionado.' ), 400 );
	}
	if ( count( $numbers ) > 50 ) {
		wp_send_json_error( array( 'message' => 'Selecione no maximo 50 pedidos por vez.' ), 400 );
	}

	$fpdf_file = PW_PERSONALIZADOS_DIR . 'vendor/fpdf/fpdf.php';
	if ( ! file_exists( $fpdf_file ) ) {
		wp_send_json_error( array( 'message' => 'A biblioteca de PDF (FPDF) nao foi encontrada no servidor.' ), 501 );
	}
	require_once $fpdf_file;

	pw_personalizados_install_tables();

	$orders = array();
	foreach ( $numbers as $num ) {
		$order = pw_personalizados_nfe_get_order( $num );
		if ( $order ) {
			$orders[] = $order;
		}
	}
	if ( ! $orders ) {
		wp_send_json_error( array( 'message' => 'Nenhum dos pedidos informados foi encontrado.' ), 404 );
	}

	// Converte UTF-8 para ISO-8859-1 (necessario para fontes core do FPDF)
	// Substitui caracteres sem equivalente ISO antes de converter
	$e = function( $s ) {
		$s = (string) $s;
		$s = str_replace( array( "\u{2014}", "\u{2013}", "\u{00D7}" ), array( '-', '-', 'x' ), $s );
		return iconv( 'UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $s );
	};

	// Larguras: area util = 210 - 8 - 8 = 194mm
	$W   = 194.0;   // largura total da area util
	$LW  = 97.0;    // largura de cada coluna (duas colunas)
	$LBW = 32.0;    // largura do label dentro da coluna
	$LVW = $LW - $LBW; // largura do valor dentro da coluna (65mm)
	$H   = 5.0;     // altura de linha padrao
	$HH  = 5.5;     // altura de linha do cabecalho de secao

	$pdf = new FPDF( 'P', 'mm', 'A4' );
	$pdf->SetMargins( 8, 8, 8 );
	$pdf->SetAutoPageBreak( true, 10 );
	$pdf->AddPage();

	$first = true;
	foreach ( $orders as $order ) {
		$client  = is_array( $order['client'] ?? null ) ? $order['client'] : array();
		$addr    = is_array( $client['address'] ?? null ) ? $client['address'] : array();
		$items   = is_array( $order['items'] ?? null ) ? $order['items'] : array();
		$num_ped = (string) ( $order['orderNumber'] ?? '' );
		$origin  = (string) ( $order['origin'] ?? 'Marketplace' );
		$mktp    = (string) ( $order['marketplaceOrderNumber'] ?? '' );
		$date    = (string) ( $order['createdDate'] ?? '' );

		// Separador entre pedidos (nao na primeira)
		if ( ! $first ) {
			$pdf->SetDrawColor( 180, 180, 180 );
			$pdf->SetLineWidth( 0.3 );
			// Garante espaco antes do proximo pedido — se menos de 55mm livres, nova pagina
			if ( $pdf->GetY() > 230 ) {
				$pdf->AddPage();
			} else {
				$pdf->Ln( 3 );
				$pdf->Line( $pdf->GetX(), $pdf->GetY(), $pdf->GetX() + $W, $pdf->GetY() );
				$pdf->Ln( 3 );
			}
			$pdf->SetDrawColor( 0, 0, 0 );
			$pdf->SetLineWidth( 0.2 );
		}
		$first = false;

		// ── Cabecalho do pedido ──────────────────────────────────────────────
		$pdf->SetFillColor( 50, 50, 50 );
		$pdf->SetTextColor( 255, 255, 255 );
		$pdf->SetFont( 'Arial', 'B', 9 );
		$title = 'NF-e  #' . $num_ped . '  |  ' . $e( $origin );
		if ( $date ) { $title .= '  |  ' . $date; }
		if ( $mktp )  { $title .= '  |  Marketplace: ' . $e( $mktp ); }
		$pdf->Cell( $W, $HH, $e( $title ), 0, 1, 'L', true );
		$pdf->SetTextColor( 0, 0, 0 );

		// ── Dados do destinatario (2 colunas) ────────────────────────────────
		$pdf->SetFont( 'Arial', 'B', 7.5 );
		$pdf->SetFillColor( 230, 230, 230 );
		$pdf->Cell( $W, 4.5, 'DESTINATARIO', 'B', 1, 'L', true );

		$pdf->SetFont( 'Arial', '', 8 );
		$name  = $e( $client['name']     ?? '' );
		$doc   = $e( $client['document'] ?? '' );
		$email = $e( $client['email']    ?? '' );
		$phone = $e( $client['phone']    ?? '' );

		// Linha 1: Nome | CPF/CNPJ
		$pdf->SetFont( 'Arial', 'B', 7.5 ); $pdf->Cell( $LBW, $H, 'Nome / Razao Social:', 0, 0, 'L' );
		$pdf->SetFont( 'Arial', '', 8 );     $pdf->Cell( $LVW, $H, $name,  0, 0, 'L' );
		$pdf->SetFont( 'Arial', 'B', 7.5 ); $pdf->Cell( $LBW, $H, 'CPF / CNPJ:',         0, 0, 'L' );
		$pdf->SetFont( 'Arial', '', 8 );     $pdf->Cell( $LVW, $H, $doc,   0, 1, 'L' );
		// Linha 2: Email | Telefone
		$pdf->SetFont( 'Arial', 'B', 7.5 ); $pdf->Cell( $LBW, $H, 'E-mail:',    0, 0, 'L' );
		$pdf->SetFont( 'Arial', '', 8 );     $pdf->Cell( $LVW, $H, $email, 0, 0, 'L' );
		$pdf->SetFont( 'Arial', 'B', 7.5 ); $pdf->Cell( $LBW, $H, 'Telefone:',  0, 0, 'L' );
		$pdf->SetFont( 'Arial', '', 8 );     $pdf->Cell( $LVW, $H, $phone, 0, 1, 'L' );

		// ── Endereco (2 colunas) ─────────────────────────────────────────────
		$pdf->SetFont( 'Arial', 'B', 7.5 );
		$pdf->SetFillColor( 230, 230, 230 );
		$pdf->Cell( $W, 4.5, 'ENDERECO', 'B', 1, 'L', true );

		$street_full = trim( ( $addr['street'] ?? '' ) . ', ' . ( $addr['number'] ?? '' ) );
		if ( ! empty( $addr['complement'] ) ) { $street_full .= ' ' . $addr['complement']; }
		$street   = $e( $street_full );
		$bairro   = $e( $addr['neighborhood'] ?? '' );
		$city_uf  = $e( trim( ( $addr['city'] ?? '' ) . ' / ' . ( $addr['state'] ?? '' ) ) );
		$cep      = $e( $addr['cep'] ?? '' );

		$pdf->SetFont( 'Arial', '', 8 );
		// Linha 1: Logradouro em largura total (evita sobreposicao em enderecos longos)
		$pdf->SetFont( 'Arial', 'B', 7.5 ); $pdf->Cell( $LBW,        $H, 'Logradouro / Num.:', 0, 0, 'L' );
		$pdf->SetFont( 'Arial', '', 8 );     $pdf->Cell( $W - $LBW,  $H, $street,               0, 1, 'L' );
		// Linha 2: Bairro | CEP
		$pdf->SetFont( 'Arial', 'B', 7.5 ); $pdf->Cell( $LBW, $H, 'Bairro:', 0, 0, 'L' );
		$pdf->SetFont( 'Arial', '', 8 );     $pdf->Cell( $LVW, $H, $bairro,  0, 0, 'L' );
		$pdf->SetFont( 'Arial', 'B', 7.5 ); $pdf->Cell( $LBW, $H, 'CEP:',   0, 0, 'L' );
		$pdf->SetFont( 'Arial', '', 8 );     $pdf->Cell( $LVW, $H, $cep,     0, 1, 'L' );
		// Linha 3: Municipio/UF em largura total
		$pdf->SetFont( 'Arial', 'B', 7.5 ); $pdf->Cell( $LBW,       $H, 'Municipio / UF:', 0, 0, 'L' );
		$pdf->SetFont( 'Arial', '', 8 );     $pdf->Cell( $W - $LBW, $H, $city_uf,           0, 1, 'L' );

		// ── Itens ────────────────────────────────────────────────────────────
		$pdf->SetFont( 'Arial', 'B', 7.5 );
		$pdf->SetFillColor( 230, 230, 230 );
		$pdf->Cell( $W, 4.5, 'ITENS', 'B', 1, 'L', true );

		// Larguras das colunas da tabela de itens: total = 194mm
		$ci = 8; $cd = 110; $cq = 15; $cu = 30; $cs = 31;
		$pdf->SetFont( 'Arial', 'B', 7.5 );
		$pdf->SetFillColor( 245, 245, 245 );
		$pdf->Cell( $ci, $H, '#',          1, 0, 'C', true );
		$pdf->Cell( $cd, $H, 'Descricao',  1, 0, 'L', true );
		$pdf->Cell( $cq, $H, 'Qtd',        1, 0, 'C', true );
		$pdf->Cell( $cu, $H, 'Vl. Unit.',  1, 0, 'R', true );
		$pdf->Cell( $cs, $H, 'Subtotal',   1, 1, 'R', true );

		$pdf->SetFont( 'Arial', '', 8 );
		$total_items = 0.0;
		foreach ( $items as $idx => $item ) {
			$desc  = $e( $item['description'] ?? '' );
			$qty   = (float) ( $item['quantity'] ?? 0 );
			$price = (float) ( $item['salePrice'] ?? $item['unitPrice'] ?? 0 );
			$sub   = $qty * $price;
			$total_items += $sub;
			// Truncar descricao para caber na coluna (aprox 110mm a 8pt ~= 70 chars)
			// Usar strlen/substr pois $desc ja esta em ISO-8859-1 (single-byte)
			if ( strlen( $desc ) > 70 ) { $desc = substr( $desc, 0, 67 ) . '...'; }
			$pdf->Cell( $ci, $H, (string) ( $idx + 1 ),                        1, 0, 'C' );
			$pdf->Cell( $cd, $H, $desc,                                         1, 0, 'L' );
			$pdf->Cell( $cq, $H, number_format( $qty, 0, ',', '.' ),           1, 0, 'C' );
			$pdf->Cell( $cu, $H, 'R$ ' . number_format( $price, 2, ',', '.' ), 1, 0, 'R' );
			$pdf->Cell( $cs, $H, 'R$ ' . number_format( $sub, 2, ',', '.' ),   1, 1, 'R' );
		}
		$grand = $total_items; // total bruto calculado pelos salePrice dos itens (valor para NF-e)
		$pdf->SetFont( 'Arial', 'B', 8 );
		$pdf->Cell( $ci + $cd + $cq + $cu, $H, 'TOTAL DO PEDIDO', 1, 0, 'R' );
		$pdf->Cell( $cs, $H, 'R$ ' . number_format( $grand, 2, ',', '.' ), 1, 1, 'R' );

		// ── Observacoes ──────────────────────────────────────────────────────
		$notes = (string) ( $order['personalizationNotes'] ?? '' );
		if ( $notes ) {
			$pdf->SetFont( 'Arial', 'B', 7.5 );
			$pdf->SetFillColor( 230, 230, 230 );
			$pdf->Cell( $W, 4.5, 'OBSERVACOES', 'B', 1, 'L', true );
			$pdf->SetFont( 'Arial', '', 8 );
			$pdf->MultiCell( $W, $H, $e( $notes ), 0, 'L' );
		}
	}

	// Rodape na ultima pagina
	$pdf->SetFont( 'Arial', '', 6.5 );
	$pdf->SetTextColor( 150, 150, 150 );
	$pdf->Ln( 2 );
	$pdf->Cell( 0, 3.5, $e( 'Gerado em ' . wp_date( 'd/m/Y H:i' ) . ' - PrintWay Sistema de Pedidos' ), 0, 1, 'L' );
	$pdf->SetTextColor( 0, 0, 0 );

	$pdf_output = $pdf->Output( 'S' );
	$filename   = count( $orders ) === 1
		? 'nfe-pedido-' . sanitize_file_name( $orders[0]['orderNumber'] ) . '.pdf'
		: 'nfe-pedidos-' . count( $orders ) . '.pdf';

	ob_end_clean(); // descarta qualquer saida capturada antes de enviar o JSON
	wp_send_json_success( array( 'pdf' => base64_encode( $pdf_output ), 'filename' => $filename ) );
}
add_action( 'wp_ajax_pw_personalizados_nfe_generate_pdf', 'pw_personalizados_nfe_generate_pdf' );

// =============================================================================
// Upload de XML de NF-e
// =============================================================================

function pw_personalizados_nfe_upload_xml() {
	pw_personalizados_ajax_guard();

	$order_number = isset( $_POST['order_number'] )
		? sanitize_text_field( wp_unslash( $_POST['order_number'] ) )
		: '';
	if ( ! $order_number ) {
		wp_send_json_error( array( 'message' => 'Número do pedido não informado.' ), 400 );
	}

	if ( empty( $_FILES['xml_file'] ) || (int) $_FILES['xml_file']['error'] !== UPLOAD_ERR_OK ) {
		$upload_err = isset( $_FILES['xml_file']['error'] ) ? (int) $_FILES['xml_file']['error'] : 0;
		$msg        = in_array( $upload_err, array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true )
			? 'O arquivo XML excede o tamanho máximo permitido.'
			: 'Nenhum arquivo XML recebido.';
		wp_send_json_error( array( 'message' => $msg ), 400 );
	}

	$file = $_FILES['xml_file'];
	$size = (int) $file['size'];
	if ( $size > 2 * MB_IN_BYTES ) {
		wp_send_json_error( array( 'message' => 'O arquivo XML não pode ser maior que 2 MB.' ), 413 );
	}

	$original_name = sanitize_file_name( $file['name'] );
	$ext           = strtolower( pathinfo( $original_name, PATHINFO_EXTENSION ) );
	if ( $ext !== 'xml' ) {
		wp_send_json_error( array( 'message' => 'Somente arquivos .xml são aceitos.' ), 415 );
	}

	// Valida se é XML bem formado
	libxml_use_internal_errors( true );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$xml_content = file_get_contents( $file['tmp_name'] );
	$parsed      = simplexml_load_string( (string) $xml_content );
	libxml_use_internal_errors( false );
	if ( false === $parsed ) {
		wp_send_json_error( array( 'message' => 'O arquivo XML está mal formado ou corrompido.' ), 422 );
	}

	pw_personalizados_install_tables();
	$order = pw_personalizados_nfe_get_order( $order_number );
	if ( ! $order ) {
		wp_send_json_error( array( 'message' => 'Pedido ' . $order_number . ' não encontrado.' ), 404 );
	}

	// Valida se o destinatário do XML corresponde ao cliente do pedido.
	$order_doc = preg_replace( '/\D/', '', (string) ( $order['client']['document'] ?? '' ) );
	if ( $order_doc ) {
		$xml_dest_doc = '';
		// NF-e usa namespace http://www.portalfiscal.inf.br/nfe
		$parsed->registerXPathNamespace( 'nfe', 'http://www.portalfiscal.inf.br/nfe' );
		foreach ( array( '//nfe:dest/nfe:CPF', '//nfe:dest/nfe:CNPJ' ) as $xpath ) {
			$nodes = $parsed->xpath( $xpath );
			if ( $nodes ) {
				$xml_dest_doc = preg_replace( '/\D/', '', (string) $nodes[0] );
				break;
			}
		}
		// Fallback sem namespace (XML sem declaração de namespace)
		if ( ! $xml_dest_doc ) {
			foreach ( array( '//dest/CPF', '//dest/CNPJ' ) as $xpath ) {
				$nodes = $parsed->xpath( $xpath );
				if ( $nodes ) {
					$xml_dest_doc = preg_replace( '/\D/', '', (string) $nodes[0] );
					break;
				}
			}
		}
		if ( $xml_dest_doc && $xml_dest_doc !== $order_doc ) {
			wp_send_json_error( array(
				'message' => 'O CPF/CNPJ do destinatário no XML (' . $xml_dest_doc . ') não corresponde ao do pedido ' . $order_number . ' (' . $order_doc . '). Verifique se está anexando o XML correto.',
			), 422 );
		}
	}

	if ( ! pw_personalizados_nfe_ensure_dir() ) {
		wp_send_json_error( array( 'message' => 'Não foi possível criar o diretório de armazenamento dos XMLs.' ), 500 );
	}

	$dir      = pw_personalizados_nfe_dir();
	$filename = 'nfe-' . preg_replace( '/[^a-zA-Z0-9\-_]/', '-', $order_number ) . '.xml';
	$dest     = $dir . '/' . $filename;

	if ( ! move_uploaded_file( $file['tmp_name'], $dest ) ) {
		wp_send_json_error( array( 'message' => 'Não foi possível salvar o arquivo no servidor.' ), 500 );
	}

	$order['nfeXml'] = array(
		'at'       => gmdate( 'c' ),
		'filename' => $filename,
		'size'     => $size,
		'by'       => (string) wp_get_current_user()->display_name,
	);

	if ( false === pw_personalizados_nfe_update_order( $order ) ) {
		wp_send_json_error( array( 'message' => 'Arquivo salvo mas não foi possível atualizar o registro do pedido.' ), 500 );
	}

	wp_send_json_success( array(
		'message' => 'XML anexado ao pedido ' . $order_number . '.',
		'nfeXml'  => $order['nfeXml'],
	) );
}
add_action( 'wp_ajax_pw_personalizados_nfe_upload_xml', 'pw_personalizados_nfe_upload_xml' );

// =============================================================================
// Download do XML de NF-e
// =============================================================================

function pw_personalizados_nfe_download_xml() {
	if ( ! is_user_logged_in() ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		wp_die( 'Acesso negado.', '', array( 'response' => 403 ) );
	}
	pw_personalizados_require_ajax_access();
	if ( ! check_ajax_referer( 'pw_personalizados_storage', 'nonce', false ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		wp_die( 'Sessão expirada. Recarregue a página.', '', array( 'response' => 403 ) );
	}

	$order_number = isset( $_GET['order_number'] )
		? sanitize_text_field( wp_unslash( $_GET['order_number'] ) )
		: '';
	if ( ! $order_number ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		wp_die( 'Número do pedido não informado.', '', array( 'response' => 400 ) );
	}

	pw_personalizados_install_tables();
	$order = pw_personalizados_nfe_get_order( $order_number );
	if ( ! $order || empty( $order['nfeXml']['filename'] ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		wp_die( 'XML não encontrado para este pedido.', '', array( 'response' => 404 ) );
	}

	$dir      = pw_personalizados_nfe_dir();
	$filename = basename( (string) $order['nfeXml']['filename'] );
	$filepath = $dir . '/' . $filename;

	if ( ! file_exists( $filepath ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		wp_die( 'Arquivo XML não encontrado no servidor.', '', array( 'response' => 404 ) );
	}

	header( 'Content-Type: application/xml; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	header( 'Content-Length: ' . (int) filesize( $filepath ) );
	header( 'Cache-Control: no-store, no-cache, must-revalidate' );
	header( 'Pragma: no-cache' );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
	readfile( $filepath );
	exit;
}
add_action( 'wp_ajax_pw_personalizados_nfe_download_xml', 'pw_personalizados_nfe_download_xml' );

// =============================================================================
// Remover XML de NF-e
// =============================================================================

function pw_personalizados_nfe_remove_xml() {
	pw_personalizados_ajax_guard();

	$order_number = isset( $_POST['order_number'] )
		? sanitize_text_field( wp_unslash( $_POST['order_number'] ) )
		: '';
	if ( ! $order_number ) {
		wp_send_json_error( array( 'message' => 'Número do pedido não informado.' ), 400 );
	}

	pw_personalizados_install_tables();
	$order = pw_personalizados_nfe_get_order( $order_number );
	if ( ! $order ) {
		wp_send_json_error( array( 'message' => 'Pedido não encontrado.' ), 404 );
	}
	if ( empty( $order['nfeXml'] ) ) {
		wp_send_json_success( array( 'message' => 'Nenhum XML para remover.' ) );
		return;
	}

	$dir      = pw_personalizados_nfe_dir();
	$filename = basename( (string) ( $order['nfeXml']['filename'] ?? '' ) );
	if ( $filename ) {
		$filepath = $dir . '/' . $filename;
		if ( file_exists( $filepath ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			@unlink( $filepath );
		}
	}

	unset( $order['nfeXml'] );
	pw_personalizados_nfe_update_order( $order );

	wp_send_json_success( array( 'message' => 'XML removido do pedido ' . $order_number . '.' ) );
}
add_action( 'wp_ajax_pw_personalizados_nfe_remove_xml', 'pw_personalizados_nfe_remove_xml' );

// =============================================================================
// Notificações NF-e — grupos de usuários e status por usuário
// =============================================================================

/** Retorna os IDs dos grupos de notificação a partir da tabela de meta. */
function pw_personalizados_nfe_get_notify_settings() {
	global $wpdb;
	$meta_table = pw_personalizados_meta_table();
	$raw        = $wpdb->get_var( $wpdb->prepare(
		"SELECT meta_value FROM {$meta_table} WHERE meta_key = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		'pw_personalizados_nfe_settings'
	) );
	$settings = $raw ? json_decode( (string) $raw, true ) : array();
	if ( ! is_array( $settings ) ) {
		$settings = array();
	}
	return array(
		'notifyPendingUserIds' => array_values( array_map( 'strval', (array) ( $settings['notifyPendingUserIds'] ?? array() ) ) ),
		'notifyReadyUserIds'   => array_values( array_map( 'strval', (array) ( $settings['notifyReadyUserIds'] ?? array() ) ) ),
	);
}

/** Verifica se um pedido é de marketplace (replica orderOriginIsMarketplace do JS). */
function pw_personalizados_nfe_is_marketplace_order( $order ) {
	$origin = strtolower( preg_replace( '/[^a-z0-9]/i', '', (string) ( $order['origin'] ?? '' ) ) );
	return $origin === 'ml'
		|| strpos( $origin, 'mercadolivre' ) !== false
		|| strpos( $origin, 'shopee' ) !== false
		|| $origin === 'marketplace';
}

/** Verifica se um pedido está finalizado. */
function pw_personalizados_nfe_is_order_finalized( $order ) {
	return ( isset( $order['status'] ) && $order['status'] === 'Entregue' ) || ! empty( $order['finalized'] );
}

/** Conta pedidos marketplace não finalizados: sem XML (pending) e com XML (ready). */
function pw_personalizados_nfe_count_pending_ready() {
	global $wpdb;
	pw_personalizados_install_tables();
	$table   = pw_personalizados_table_map()['pw_personalizados_orders'];
	$rows    = $wpdb->get_col( "SELECT payload FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$pending = 0;
	$ready   = 0;
	foreach ( (array) $rows as $json ) {
		$order = json_decode( (string) $json, true );
		if ( ! is_array( $order ) ) {
			continue;
		}
		if ( ! pw_personalizados_nfe_is_marketplace_order( $order ) ) {
			continue;
		}
		if ( pw_personalizados_nfe_is_order_finalized( $order ) ) {
			continue;
		}
		if ( ! empty( $order['nfeXml'] ) ) {
			$ready++;
		} else {
			$pending++;
		}
	}
	return array( 'pending' => $pending, 'ready' => $ready );
}

// Handler: status das notificações NF-e para o usuário atual
function pw_personalizados_nfe_notification_status_ajax() {
	pw_personalizados_ajax_guard();
	$user_id         = get_current_user_id();
	$notify          = pw_personalizados_nfe_get_notify_settings();
	$user_in_pending = in_array( (string) $user_id, $notify['notifyPendingUserIds'], true );
	$user_in_ready   = in_array( (string) $user_id, $notify['notifyReadyUserIds'], true );
	$counts          = pw_personalizados_nfe_count_pending_ready();
	$ack_pend        = (int) get_user_meta( $user_id, '_pw_nfe_pending_ack_count', true );
	$ack_ready       = (int) get_user_meta( $user_id, '_pw_nfe_ready_ack_count', true );
	wp_send_json_success( array(
		'pendingHasNew' => $user_in_pending && $counts['pending'] > $ack_pend,
		'pendingCount'  => $counts['pending'],
		'readyHasNew'   => $user_in_ready && $counts['ready'] > $ack_ready,
		'readyCount'    => $counts['ready'],
		'userInPending' => $user_in_pending,
		'userInReady'   => $user_in_ready,
	) );
}
add_action( 'wp_ajax_pw_personalizados_nfe_notification_status', 'pw_personalizados_nfe_notification_status_ajax' );

// Handler: marcar notificações "aguardando XML" como vistas
function pw_personalizados_nfe_mark_pending_seen_ajax() {
	pw_personalizados_ajax_guard();
	$counts = pw_personalizados_nfe_count_pending_ready();
	update_user_meta( get_current_user_id(), '_pw_nfe_pending_ack_count', $counts['pending'] );
	wp_send_json_success( array( 'marked' => true ) );
}
add_action( 'wp_ajax_pw_personalizados_nfe_mark_pending_seen', 'pw_personalizados_nfe_mark_pending_seen_ajax' );

// Handler: marcar notificações "XML pronto" como vistas
function pw_personalizados_nfe_mark_ready_seen_ajax() {
	pw_personalizados_ajax_guard();
	$counts = pw_personalizados_nfe_count_pending_ready();
	update_user_meta( get_current_user_id(), '_pw_nfe_ready_ack_count', $counts['ready'] );
	wp_send_json_success( array( 'marked' => true ) );
}
add_action( 'wp_ajax_pw_personalizados_nfe_mark_ready_seen', 'pw_personalizados_nfe_mark_ready_seen_ajax' );

// Handler: obter listas de usuários para notificação NF-e
function pw_personalizados_nfe_notify_users_get_ajax() {
	pw_personalizados_ajax_guard();
	wp_send_json_success( pw_personalizados_nfe_get_notify_settings() );
}
add_action( 'wp_ajax_pw_personalizados_nfe_notify_users_get', 'pw_personalizados_nfe_notify_users_get_ajax' );

// Handler: salvar listas de usuários para notificação NF-e
function pw_personalizados_nfe_notify_users_save_ajax() {
	pw_personalizados_ajax_guard();
	if ( ! current_user_can( 'administrator' ) ) {
		wp_send_json_error( array( 'message' => 'Apenas administradores podem alterar as configurações de notificação.' ), 403 );
	}
	global $wpdb;
	$meta_table  = pw_personalizados_meta_table();
	$pending_raw = isset( $_POST['notifyPendingUserIds'] ) ? (array) wp_unslash( $_POST['notifyPendingUserIds'] ) : array();
	$ready_raw   = isset( $_POST['notifyReadyUserIds'] )   ? (array) wp_unslash( $_POST['notifyReadyUserIds'] )   : array();
	$pending_ids = array_values( array_unique( array_map( 'strval', array_filter( array_map( 'intval', $pending_raw ) ) ) ) );
	$ready_ids   = array_values( array_unique( array_map( 'strval', array_filter( array_map( 'intval', $ready_raw ) ) ) ) );
	$raw         = $wpdb->get_var( $wpdb->prepare(
		"SELECT meta_value FROM {$meta_table} WHERE meta_key = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		'pw_personalizados_nfe_settings'
	) );
	$settings = $raw ? json_decode( (string) $raw, true ) : array();
	if ( ! is_array( $settings ) ) {
		$settings = array();
	}
	$settings['notifyPendingUserIds'] = $pending_ids;
	$settings['notifyReadyUserIds']   = $ready_ids;
	$result = $wpdb->replace(
		$meta_table,
		array(
			'meta_key'   => 'pw_personalizados_nfe_settings',
			'meta_value' => wp_json_encode( $settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			'updated_at' => current_time( 'mysql', true ),
		),
		array( '%s', '%s', '%s' )
	);
	if ( false === $result ) {
		wp_send_json_error( array( 'message' => 'Não foi possível gravar as configurações de notificação.' ), 500 );
	}
	wp_send_json_success( array( 'saved' => true ) );
}
add_action( 'wp_ajax_pw_personalizados_nfe_notify_users_save', 'pw_personalizados_nfe_notify_users_save_ajax' );
