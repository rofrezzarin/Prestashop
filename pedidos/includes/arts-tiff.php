<?php
/**
 * "Montar Tiff" — pega várias artes em PDF selecionadas no Relatório de
 * Artes, renderiza cada uma em alta resolução (900 DPI, o mesmo padrão já
 * usado na conversão individual de PDF→TIFF), remove o fundo branco
 * conectado às bordas e empilha todas verticalmente num único arquivo
 * TIFF, com um pequeno espaço entre cada uma.
 *
 * Processamento em etapas (uma arte por chamada AJAX) para dar progresso
 * real (processed/total) e permitir cancelar no meio, em vez de uma
 * chamada única que trava a barra em 0% até terminar tudo.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PW_ARTS_TIFF_MAX_ITEMS', 60 );
define( 'PW_ARTS_TIFF_JOB_TTL', HOUR_IN_SECONDS );
define( 'PW_ARTS_TIFF_GAP_MM', 5 );

function pw_personalizados_arts_tiff_base_dir() {
	return trailingslashit( wp_upload_dir()['basedir'] ) . 'pw-arts-tiff-tmp';
}

function pw_personalizados_arts_tiff_rrmdir( $dir ) {
	if ( ! $dir || ! is_dir( $dir ) ) {
		return;
	}
	foreach ( scandir( $dir ) ?: array() as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}
		$path = $dir . '/' . $item;
		is_dir( $path ) ? pw_personalizados_arts_tiff_rrmdir( $path ) : @unlink( $path );
	}
	@rmdir( $dir );
}

/** Limpa pastas temporárias órfãs (job abandonado: aba fechada, queda de conexão etc.). */
function pw_personalizados_arts_tiff_gc() {
	$base = pw_personalizados_arts_tiff_base_dir();
	if ( ! is_dir( $base ) ) {
		return;
	}
	foreach ( glob( $base . '/*', GLOB_ONLYDIR ) ?: array() as $dir ) {
		if ( filemtime( $dir ) < time() - 2 * HOUR_IN_SECONDS ) {
			pw_personalizados_arts_tiff_rrmdir( $dir );
		}
	}
}

/**
 * Renderiza a primeira página do PDF em alta resolução (900 DPI) e remove
 * o fundo branco conectado às bordas — mesmo processo já usado na
 * conversão individual "PDF em TIFF", só que reutilizável para o lote.
 * Retorna um objeto Imagick pronto (chamador deve dar clear() nele) ou
 * WP_Error.
 */
function pw_personalizados_arts_tiff_render_page( $source_path ) {
	if ( ! class_exists( 'Imagick' ) ) {
		return new WP_Error( 'arts_tiff_no_imagick', 'O servidor precisa ter o módulo Imagick habilitado.' );
	}
	if ( ! $source_path || ! file_exists( $source_path ) ) {
		return new WP_Error( 'arts_tiff_missing_file', 'O arquivo original não foi localizado na biblioteca de mídia.' );
	}
	// Renderizar PDF a 900 DPI consome bastante memória — a mesma função que
	// o WordPress usa antes de editar imagens grandes, evita que um PDF
	// maior no meio do lote estoure o limite de memória do PHP.
	if ( function_exists( 'pw_personalizados_raise_memory_for_large_render' ) ) {
		pw_personalizados_raise_memory_for_large_render();
	} elseif ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'image' );
	}
	try {
		$geometry = pw_personalizados_pdf_geometry( $source_path );
		$target_width_mm = ! empty( $geometry['page_widths_mm'][0] ) ? (float) $geometry['page_widths_mm'][0] : (float) ( $geometry['width_mm'] ?? 0 );
		$target_height_mm = ! empty( $geometry['page_heights_mm'][0] ) ? (float) $geometry['page_heights_mm'][0] : (float) ( $geometry['height_mm'] ?? 0 );

		// DTF UV costuma ter largura fixa (~28cm) mas comprimento livre — uma
		// arte muito comprida a 900 DPI pode passar de 1 bilhão de pixels e
		// estourar a memória do PHP mesmo com wp_raise_memory_limit(). Em vez
		// de crashar, reduz o DPI só o necessário pra caber num limite seguro
		// (mesma função usada na conversão individual de PDF em TIFF/PNG).
		$render_dpi = function_exists( 'pw_personalizados_adaptive_render_dpi' )
			? pw_personalizados_adaptive_render_dpi( $target_width_mm, $target_height_mm, 900, 220 )
			: 900;
		if ( $render_dpi < 900 && function_exists( 'pw_printway_log' ) ) {
			pw_printway_log( 'pedidos', 'info', 'Montagem de TIFF: arte grande, DPI reduzido de 900 para ' . $render_dpi . ' pra evitar estouro de memória.', array( 'arquivo' => basename( $source_path ), 'largura_mm' => $target_width_mm, 'altura_mm' => $target_height_mm ) );
		}

		// Tenta primeiro preservar a transparência que o próprio PDF já
		// carrega (a mesma que o CorelDRAW mostra ao importar direto) — só
		// cai no floodfill de fundo branco se o PDF não tiver transparência
		// de verdade. Evita a perda de qualidade e as bordas brancas
		// falhadas perto da arte que o floodfill causa.
		$native = function_exists( 'pw_personalizados_render_pdf_native_alpha' )
			? pw_personalizados_render_pdf_native_alpha( $source_path, $render_dpi )
			: false;
		if ( is_wp_error( $native ) ) {
			return $native;
		}
		if ( false === $native ) {
			$image = new Imagick();
			$image->setResolution( $render_dpi, $render_dpi );
			$image->readImage( $source_path . '[0]' );
			$image->setIteratorIndex( 0 );
			$processed = pw_personalizados_remove_connected_white_background( $image );
		} else {
			$processed = $native;
		}

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
		return $processed;
	} catch ( Throwable $error ) {
		// Throwable (não só Exception) porque falta de memória e outros erros
		// do Imagick às vezes chegam como Error, não Exception, em PHP 7+.
		return new WP_Error(
			'arts_tiff_render_failed',
			sprintf(
				'%s (arquivo: %s; classe do erro: %s; memória em uso no momento: %s de %s)',
				$error->getMessage(),
				basename( $source_path ),
				get_class( $error ),
				size_format( memory_get_usage( true ) ),
				ini_get( 'memory_limit' )
			)
		);
	}
}

/** Inicia o job: valida a seleção e prepara a pasta temporária. */
function pw_personalizados_arts_tiff_start() {
	pw_personalizados_ajax_guard();
	try {
		pw_personalizados_arts_tiff_start_run();
	} catch ( Throwable $error ) {
		if ( function_exists( 'pw_printway_log' ) ) {
			pw_printway_log( 'pedidos', 'error', 'Montagem de TIFF: erro inesperado ao iniciar — ' . get_class( $error ) . ': ' . $error->getMessage() . ' em ' . $error->getFile() . ':' . $error->getLine() );
		}
		wp_send_json_error( array(
			'message' => 'Erro inesperado ao iniciar a montagem: ' . $error->getMessage() . ' (' . get_class( $error ) . ', linha ' . $error->getLine() . ' de ' . basename( $error->getFile() ) . ').',
		), 500 );
	}
}
add_action( 'wp_ajax_pw_personalizados_arts_tiff_start', 'pw_personalizados_arts_tiff_start' );

function pw_personalizados_arts_tiff_start_run() {
	pw_personalizados_arts_tiff_gc();

	$raw_ids = isset( $_POST['attachment_ids'] ) ? (array) wp_unslash( $_POST['attachment_ids'] ) : array();
	$ids = array();
	foreach ( $raw_ids as $raw ) {
		$id = absint( $raw );
		if ( $id ) {
			$ids[] = $id;
		}
	}
	$ids = array_values( array_unique( $ids ) );

	if ( ! $ids ) {
		wp_send_json_error( array( 'message' => 'Selecione ao menos uma arte em PDF para montar o TIFF.' ), 400 );
	}
	if ( count( $ids ) > PW_ARTS_TIFF_MAX_ITEMS ) {
		wp_send_json_error( array( 'message' => 'Selecione no máximo ' . PW_ARTS_TIFF_MAX_ITEMS . ' artes por vez.' ), 400 );
	}
	if ( ! class_exists( 'Imagick' ) ) {
		wp_send_json_error( array( 'message' => 'O servidor precisa ter o módulo Imagick habilitado.' ), 501 );
	}
	foreach ( $ids as $id ) {
		if ( 'application/pdf' !== get_post_mime_type( $id ) ) {
			wp_send_json_error( array( 'message' => 'Só é possível montar o TIFF a partir de artes em PDF — o anexo ' . $id . ' não é PDF (mime: ' . get_post_mime_type( $id ) . ').' ), 400 );
		}
	}

	$job_id = wp_generate_uuid4();
	$dir = trailingslashit( pw_personalizados_arts_tiff_base_dir() ) . $job_id;
	if ( ! wp_mkdir_p( $dir ) ) {
		wp_send_json_error( array( 'message' => 'Não foi possível criar a pasta temporária "' . $dir . '" no servidor. Confira as permissões de escrita em wp-content/uploads.' ), 500 );
	}

	$job = array(
		'attachment_ids' => $ids,
		'total'          => count( $ids ),
		'processed'      => 0,
		'cancelled'      => false,
		'dir'            => $dir,
		'frames'         => array(),
		'skipped'        => array(),
		'user_id'        => get_current_user_id(),
		'created'        => time(),
	);
	set_transient( 'pw_arts_tiff_job_' . $job_id, $job, PW_ARTS_TIFF_JOB_TTL );

	if ( function_exists( 'pw_printway_log' ) ) {
		pw_printway_log( 'pedidos', 'info', 'Montagem de TIFF iniciada: ' . count( $ids ) . ' arte(s).' );
	}

	wp_send_json_success( array( 'job_id' => $job_id, 'total' => $job['total'] ) );
}

/** Processa UMA arte por chamada (dá progresso real) e, na última, monta o TIFF final. */
function pw_personalizados_arts_tiff_step() {
	pw_personalizados_ajax_guard();
	try {
		pw_personalizados_arts_tiff_step_run();
	} catch ( Throwable $error ) {
		// Rede de segurança final: qualquer coisa inesperada que escape das
		// funções internas (elas já têm seus próprios try/catch) cai aqui em
		// vez de virar uma resposta quebrada/sem JSON no navegador — o que
		// antes aparecia como "Falha ao processar uma das artes." sem
		// nenhum detalhe. Agora sempre volta uma mensagem com a causa real.
		if ( function_exists( 'pw_printway_log' ) ) {
			pw_printway_log( 'pedidos', 'error', 'Montagem de TIFF: erro inesperado no passo — ' . get_class( $error ) . ': ' . $error->getMessage() . ' em ' . $error->getFile() . ':' . $error->getLine() );
		}
		wp_send_json_error( array(
			'message' => 'Erro inesperado ao montar o TIFF: ' . $error->getMessage() . ' (' . get_class( $error ) . ', linha ' . $error->getLine() . ' de ' . basename( $error->getFile() ) . ').',
		), 500 );
	}
}
add_action( 'wp_ajax_pw_personalizados_arts_tiff_step', 'pw_personalizados_arts_tiff_step' );

function pw_personalizados_arts_tiff_step_run() {
	$job_id = isset( $_POST['job_id'] ) ? sanitize_text_field( wp_unslash( $_POST['job_id'] ) ) : '';
	$key = 'pw_arts_tiff_job_' . $job_id;
	$job = get_transient( $key );
	if ( ! is_array( $job ) ) {
		wp_send_json_error( array( 'message' => 'Essa montagem expirou ou não existe mais (job "' . $job_id . '" não encontrado). Selecione as artes novamente.' ), 404 );
	}
	if ( ! empty( $job['cancelled'] ) ) {
		pw_personalizados_arts_tiff_rrmdir( $job['dir'] );
		delete_transient( $key );
		wp_send_json_success( array( 'cancelled' => true ) );
	}

	$index = (int) $job['processed'];

	if ( $index >= (int) $job['total'] ) {
		$result = pw_personalizados_arts_tiff_compose( $job );
		pw_personalizados_arts_tiff_rrmdir( $job['dir'] );
		delete_transient( $key );
		if ( is_wp_error( $result ) ) {
			if ( function_exists( 'pw_printway_log' ) ) {
				pw_printway_log( 'pedidos', 'error', 'Montagem de TIFF: falha ao compor o arquivo final — ' . $result->get_error_message() );
			}
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		}
		wp_send_json_success( array(
			'done'      => true,
			'processed' => $job['total'],
			'total'     => $job['total'],
			'id'        => $result['id'],
			'url'       => $result['url'],
			'name'      => $result['name'],
			'skipped'   => isset( $job['skipped'] ) ? array_values( $job['skipped'] ) : array(),
		) );
	}

	if ( empty( $job['attachment_ids'][ $index ] ) ) {
		wp_send_json_error( array( 'message' => 'Índice ' . $index . ' não corresponde a nenhuma arte deste lote (total: ' . $job['total'] . ').' ), 500 );
	}

	$attachment_id = (int) $job['attachment_ids'][ $index ];
	$source = get_attached_file( $attachment_id );
	$processed = pw_personalizados_arts_tiff_render_page( $source );
	if ( is_wp_error( $processed ) ) {
		// Não aborta o lote inteiro por causa de uma arte problemática (PDF
		// grande/corrompido, memória insuficiente etc.) — pula essa arte e
		// segue com as demais, avisando no fim quais foram puladas e por quê.
		if ( function_exists( 'pw_printway_log' ) ) {
			pw_printway_log( 'pedidos', 'warning', 'Montagem de TIFF: arte pulada (attachment ' . $attachment_id . ', arquivo "' . basename( (string) $source ) . '"): ' . $processed->get_error_message() );
		}
		$job['processed'] = $index + 1;
		$job['skipped'][] = array( 'attachment_id' => $attachment_id, 'message' => $processed->get_error_message() );
		set_transient( $key, $job, PW_ARTS_TIFF_JOB_TTL );
		wp_send_json_success( array(
			'done'           => false,
			'processed'      => $job['processed'],
			'total'          => $job['total'],
			'percent'        => (int) round( $job['processed'] / $job['total'] * 100 ),
			'skipped_id'     => $attachment_id,
			'skipped_reason' => $processed->get_error_message(),
		) );
	}

	$frame_path = trailingslashit( $job['dir'] ) . sprintf( '%04d.png', $index );
	$processed->setImageFormat( 'png' );
	$written = $processed->writeImage( $frame_path );
	$resolution = $processed->getImageResolution();
	$processed->clear();

	if ( ! $written || ! file_exists( $frame_path ) ) {
		pw_personalizados_arts_tiff_rrmdir( $job['dir'] );
		delete_transient( $key );
		wp_send_json_error( array( 'message' => 'Não foi possível gravar uma das páginas processadas no servidor.' ), 500 );
	}

	$job['processed'] = $index + 1;
	$job['frames'][ $index ] = array(
		'path'  => $frame_path,
		'dpi_x' => ! empty( $resolution['x'] ) ? (float) $resolution['x'] : 900,
		'dpi_y' => ! empty( $resolution['y'] ) ? (float) $resolution['y'] : 900,
	);
	set_transient( $key, $job, PW_ARTS_TIFF_JOB_TTL );

	wp_send_json_success( array(
		'done'      => false,
		'processed' => $job['processed'],
		'total'     => $job['total'],
		'percent'   => (int) round( $job['processed'] / $job['total'] * 100 ),
	) );
}

/** Cancela: apaga a pasta temporária e o job na hora, sem esperar o próximo poll. */
function pw_personalizados_arts_tiff_cancel() {
	pw_personalizados_ajax_guard();
	$job_id = isset( $_POST['job_id'] ) ? sanitize_text_field( wp_unslash( $_POST['job_id'] ) ) : '';
	$key = 'pw_arts_tiff_job_' . $job_id;
	$job = get_transient( $key );
	if ( is_array( $job ) ) {
		pw_personalizados_arts_tiff_rrmdir( $job['dir'] );
		delete_transient( $key );
	}
	wp_send_json_success();
}
add_action( 'wp_ajax_pw_personalizados_arts_tiff_cancel', 'pw_personalizados_arts_tiff_cancel' );

/** Empilha verticalmente as páginas já processadas num único TIFF, com um pequeno espaço entre elas. */
function pw_personalizados_arts_tiff_compose( $job ) {
	if ( function_exists( 'pw_personalizados_raise_memory_for_large_render' ) ) {
		pw_personalizados_raise_memory_for_large_render();
	} elseif ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'image' );
	}
	try {
		$frames = isset( $job['frames'] ) && is_array( $job['frames'] ) ? $job['frames'] : array();
		ksort( $frames );
		if ( ! $frames ) {
			return new WP_Error( 'arts_tiff_empty', 'Nenhuma página foi processada com sucesso.' );
		}

		$images = array();
		$max_width = 0;
		$total_height = 0;
		$dpi_x = 900;
		$dpi_y = 900;
		foreach ( $frames as $frame ) {
			if ( empty( $frame['path'] ) || ! file_exists( $frame['path'] ) ) {
				continue;
			}
			$im = new Imagick( $frame['path'] );
			$images[] = $im;
			$max_width = max( $max_width, (int) $im->getImageWidth() );
			$total_height += (int) $im->getImageHeight();
			$dpi_x = ! empty( $frame['dpi_x'] ) ? (float) $frame['dpi_x'] : $dpi_x;
			$dpi_y = ! empty( $frame['dpi_y'] ) ? (float) $frame['dpi_y'] : $dpi_y;
		}
		if ( ! $images ) {
			return new WP_Error( 'arts_tiff_empty', 'Nenhuma página foi processada com sucesso.' );
		}

		// Pequeno espaço entre cada arte, calculado na resolução real das páginas
		// (não um valor fixo de pixels, que ficaria errado em resoluções diferentes).
		$gap_px = max( 10, (int) round( ( PW_ARTS_TIFF_GAP_MM / 25.4 ) * $dpi_y ) );
		$total_height += $gap_px * ( count( $images ) - 1 );

		$canvas = new Imagick();
		$canvas->newImage( $max_width, $total_height, new ImagickPixel( 'transparent' ) );
		// Sem isto, o canvas fica sem canal alfa "de verdade" e a transparência
		// vira preto sólido ao gravar em TIFF — mesmo passo que
		// pw_personalizados_remove_connected_white_background() já faz em
		// cada página individualmente.
		if ( defined( 'Imagick::ALPHACHANNEL_ACTIVATE' ) ) {
			$canvas->setImageAlphaChannel( Imagick::ALPHACHANNEL_ACTIVATE );
		}
		$canvas->setImageFormat( 'png' ); // mantém alfa total durante a composição; vira tiff só no final.

		$y = 0;
		foreach ( $images as $im ) {
			$canvas->compositeImage( $im, Imagick::COMPOSITE_OVER, 0, $y );
			$y += (int) $im->getImageHeight() + $gap_px;
			$im->clear();
		}

		$canvas->setImageFormat( 'tiff' );
		// Mesmo ajuste do endpoint individual: força o tipo de imagem pra
		// RGBA de verdade antes de gravar, senão o Imagick pode reclassificar
		// o canvas pra tons de cinza/paleta e o TIFF final sai todo preto
		// mesmo com a transparência correta na memória.
		if ( defined( 'Imagick::IMGTYPE_TRUECOLORMATTE' ) ) {
			$canvas->setImageType( Imagick::IMGTYPE_TRUECOLORMATTE );
		}
		if ( defined( 'Imagick::ALPHACHANNEL_ACTIVATE' ) ) {
			$canvas->setImageAlphaChannel( Imagick::ALPHACHANNEL_ACTIVATE );
		}
		if ( method_exists( $canvas, 'setImageUnits' ) && defined( 'Imagick::RESOLUTION_PIXELSPERINCH' ) ) {
			$canvas->setImageUnits( Imagick::RESOLUTION_PIXELSPERINCH );
		}
		if ( method_exists( $canvas, 'setImageResolution' ) ) {
			$canvas->setImageResolution( $dpi_x, $dpi_y );
		}
		$canvas->setImageCompression( Imagick::COMPRESSION_ZIP );
		$canvas->setImageCompressionQuality( 100 );
		$canvas->setOption( 'tiff:alpha', 'unassociated' );

		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return new WP_Error( 'arts_tiff_uploads', $uploads['error'] );
		}
		$filename = wp_unique_filename( $uploads['path'], 'artes-montadas-' . gmdate( 'Ymd-His' ) . '.tiff' );
		$destination = trailingslashit( $uploads['path'] ) . $filename;
		$canvas->writeImage( $destination );
		$canvas->clear();

		if ( ! file_exists( $destination ) ) {
			return new WP_Error( 'arts_tiff_write_failed', 'O arquivo final não foi gravado no servidor.' );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attachment_id = wp_insert_attachment( array(
			'post_mime_type' => 'image/tiff',
			'post_title'     => pathinfo( $filename, PATHINFO_FILENAME ),
			'post_status'    => 'inherit',
		), $destination );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $destination ) );

		if ( function_exists( 'pw_printway_log' ) ) {
			pw_printway_log( 'pedidos', 'info', 'Montagem de TIFF concluída: ' . count( $images ) . ' página(s) em ' . $filename . '.' );
		}

		return array(
			'id'   => (int) $attachment_id,
			'url'  => wp_get_attachment_url( $attachment_id ),
			'name' => $filename,
		);
	} catch ( Throwable $error ) {
		return new WP_Error(
			'arts_tiff_compose_failed',
			sprintf(
				'Não foi possível montar o arquivo final: %s (classe do erro: %s; memória em uso: %s de %s)',
				$error->getMessage(),
				get_class( $error ),
				size_format( memory_get_usage( true ) ),
				ini_get( 'memory_limit' )
			)
		);
	}
}
