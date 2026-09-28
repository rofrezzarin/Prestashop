<?php
/**
 * "Montar PDF" — a mesma ideia do "Montar Tiff", só que sem rasterizar
 * nada: usa a biblioteca FPDI (gratuita, licença MIT, mesma licença do
 * FPDF que ela usa por baixo — ver pedidos/vendor/fpdi/LICENSE.txt e
 * pedidos/vendor/fpdf/LICENSE.txt) pra importar a página de cada PDF
 * selecionado como um "molde" vetorial e colar num PDF novo, uma
 * embaixo da outra, com um pequeno espaço entre elas — preserva 100%
 * da qualidade original (texto continua texto, vetor continua vetor),
 * exatamente como abrir o PDF original direto no CorelDRAW.
 *
 * Limite conhecido da versão gratuita do FPDI: ela não lê PDFs que usam
 * referência cruzada comprimida ("cross-reference stream"), um formato
 * comum em exportações mais novas do Illustrator/InDesign/CorelDraw.
 * Quando isso acontece, tentamos normalizar o arquivo com o Ghostscript
 * (se o servidor permitir rodar comandos externos) antes de tentar de
 * novo; se mesmo assim não der, aquela arte é pulada (mesma lógica de
 * resiliência do "Montar Tiff") em vez de derrubar o lote inteiro.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PW_ARTS_PDF_JOB_TTL', HOUR_IN_SECONDS );
define( 'PW_ARTS_PDF_GAP_MM', 5 );
define( 'PW_ARTS_PDF_MAX_ITEMS', 60 );

function pw_personalizados_arts_pdf_base_dir() {
	return trailingslashit( wp_upload_dir()['basedir'] ) . 'pw-arts-pdf-tmp';
}

function pw_personalizados_arts_pdf_rrmdir( $dir ) {
	if ( ! $dir || ! is_dir( $dir ) ) {
		return;
	}
	foreach ( scandir( $dir ) ?: array() as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}
		$path = $dir . '/' . $item;
		is_dir( $path ) ? pw_personalizados_arts_pdf_rrmdir( $path ) : @unlink( $path );
	}
	@rmdir( $dir );
}

function pw_personalizados_arts_pdf_gc() {
	$base = pw_personalizados_arts_pdf_base_dir();
	if ( ! is_dir( $base ) ) {
		return;
	}
	foreach ( glob( $base . '/*', GLOB_ONLYDIR ) ?: array() as $dir ) {
		if ( filemtime( $dir ) < time() - 2 * HOUR_IN_SECONDS ) {
			pw_personalizados_arts_pdf_rrmdir( $dir );
		}
	}
}

/** Carrega o FPDI/FPDF sob demanda (só quando esta função é realmente usada). */
function pw_personalizados_arts_pdf_load_library() {
	static $loaded = false;
	if ( $loaded ) {
		return true;
	}
	$fpdf = PW_PERSONALIZADOS_DIR . 'vendor/fpdf/fpdf.php';
	$fpdi_src = PW_PERSONALIZADOS_DIR . 'vendor/fpdi/src';
	if ( ! file_exists( $fpdf ) || ! is_dir( $fpdi_src ) ) {
		return false;
	}
	require_once $fpdf;
	spl_autoload_register( function ( $class ) use ( $fpdi_src ) {
		$prefix = 'setasign\\Fpdi\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$file = $fpdi_src . '/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( file_exists( $file ) ) {
			require $file;
		}
	} );
	$loaded = true;
	return true;
}

/**
 * Tenta "limpar" um PDF com referência cruzada comprimida (o único tipo
 * de arquivo que a versão gratuita do FPDI não consegue ler sozinha),
 * reescrevendo-o com o Ghostscript — o mesmo motor que já faz a
 * renderização em outras partes do sistema, só que aqui mantendo tudo
 * vetorial (não rasteriza nada). Só funciona se o servidor permitir
 * `shell_exec`; se não permitir, devolve false e a arte é pulada.
 */
function pw_personalizados_arts_pdf_normalize_with_ghostscript( $source, $destination ) {
	if ( ! function_exists( 'shell_exec' ) || ! function_exists( 'escapeshellarg' ) ) {
		return false;
	}
	$disabled = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );
	if ( in_array( 'shell_exec', $disabled, true ) ) {
		return false;
	}
	$gs_binaries = array( 'gs', '/usr/bin/gs', '/usr/local/bin/gs' );
	foreach ( $gs_binaries as $gs ) {
		$cmd = escapeshellcmd( $gs ) . ' -q -dNOPAUSE -dBATCH -dCompatibilityLevel=1.4 -sDEVICE=pdfwrite -sOutputFile=' . escapeshellarg( $destination ) . ' ' . escapeshellarg( $source ) . ' 2>&1';
		@shell_exec( $cmd );
		if ( file_exists( $destination ) && filesize( $destination ) > 0 ) {
			return true;
		}
	}
	return false;
}

/**
 * Importa a primeira página de um PDF como molde vetorial dentro do
 * objeto Fpdi já aberto. Tenta direto primeiro; se falhar por causa de
 * referência cruzada comprimida, tenta normalizar com Ghostscript e
 * importar de novo. Retorna array com o id do molde e o tamanho (mm),
 * ou WP_Error explicando por que não deu.
 */
function pw_personalizados_arts_pdf_import_page( $fpdi, $source_path, $work_dir ) {
	if ( ! $source_path || ! file_exists( $source_path ) ) {
		return new WP_Error( 'arts_pdf_missing_file', 'O arquivo original não foi localizado na biblioteca de mídia.' );
	}
	$attempt = function ( $path ) use ( $fpdi ) {
		$fpdi->setSourceFile( $path );
		$templateId = $fpdi->importPage( 1 );
		$size = $fpdi->getTemplateSize( $templateId );
		return array( 'templateId' => $templateId, 'width' => $size['width'], 'height' => $size['height'] );
	};
	try {
		return $attempt( $source_path );
	} catch ( Throwable $first_error ) {
		$normalized = trailingslashit( $work_dir ) . 'normalizado-' . wp_generate_uuid4() . '.pdf';
		if ( pw_personalizados_arts_pdf_normalize_with_ghostscript( $source_path, $normalized ) ) {
			try {
				$result = $attempt( $normalized );
				return $result;
			} catch ( Throwable $second_error ) {
				return new WP_Error(
					'arts_pdf_import_failed',
					sprintf( 'Não foi possível ler o PDF mesmo depois de tentar normalizar: %s (arquivo: %s)', $second_error->getMessage(), basename( $source_path ) )
				);
			}
		}
		return new WP_Error(
			'arts_pdf_import_failed',
			sprintf(
				'%s (arquivo: %s). Isso costuma acontecer com PDFs que usam referência cruzada comprimida — o servidor precisaria ter o Ghostscript acessível via linha de comando pra normalizar automaticamente, e ele não está disponível aqui.',
				$first_error->getMessage(),
				basename( $source_path )
			)
		);
	}
}

function pw_personalizados_arts_pdf_start() {
	pw_personalizados_ajax_guard();
	try {
		pw_personalizados_arts_pdf_start_run();
	} catch ( Throwable $error ) {
		if ( function_exists( 'pw_printway_log' ) ) {
			pw_printway_log( 'pedidos', 'error', 'Montagem de PDF: erro inesperado ao iniciar — ' . get_class( $error ) . ': ' . $error->getMessage() . ' em ' . $error->getFile() . ':' . $error->getLine() );
		}
		wp_send_json_error( array(
			'message' => 'Erro inesperado ao iniciar a montagem: ' . $error->getMessage() . ' (' . get_class( $error ) . ', linha ' . $error->getLine() . ' de ' . basename( $error->getFile() ) . ').',
		), 500 );
	}
}
add_action( 'wp_ajax_pw_personalizados_arts_pdf_start', 'pw_personalizados_arts_pdf_start' );

function pw_personalizados_arts_pdf_start_run() {
	pw_personalizados_arts_pdf_gc();
	if ( ! pw_personalizados_arts_pdf_load_library() ) {
		wp_send_json_error( array( 'message' => 'A biblioteca de montagem de PDF não foi encontrada no servidor (pedidos/vendor/fpdi e pedidos/vendor/fpdf).' ), 501 );
	}

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
		wp_send_json_error( array( 'message' => 'Selecione ao menos uma arte em PDF para montar o PDF.' ), 400 );
	}
	if ( count( $ids ) > PW_ARTS_PDF_MAX_ITEMS ) {
		wp_send_json_error( array( 'message' => 'Selecione no máximo ' . PW_ARTS_PDF_MAX_ITEMS . ' artes por vez.' ), 400 );
	}
	foreach ( $ids as $id ) {
		if ( 'application/pdf' !== get_post_mime_type( $id ) ) {
			wp_send_json_error( array( 'message' => 'Só é possível montar o PDF a partir de artes em PDF — um dos itens selecionados não é PDF.' ), 400 );
		}
	}

	$job_id = wp_generate_uuid4();
	$dir = trailingslashit( pw_personalizados_arts_pdf_base_dir() ) . $job_id;
	if ( ! wp_mkdir_p( $dir ) ) {
		wp_send_json_error( array( 'message' => 'Não foi possível criar a pasta temporária no servidor.' ), 500 );
	}

	$job = array(
		'attachment_ids' => $ids,
		'total'          => count( $ids ),
		'processed'      => 0,
		'cancelled'      => false,
		'dir'            => $dir,
		'skipped'        => array(),
		'user_id'        => get_current_user_id(),
		'created'        => time(),
	);
	set_transient( 'pw_arts_pdf_job_' . $job_id, $job, PW_ARTS_PDF_JOB_TTL );

	if ( function_exists( 'pw_printway_log' ) ) {
		pw_printway_log( 'pedidos', 'info', 'Montagem de PDF iniciada: ' . count( $ids ) . ' arte(s).' );
	}

	wp_send_json_success( array( 'job_id' => $job_id, 'total' => $job['total'] ) );
}

function pw_personalizados_arts_pdf_step() {
	pw_personalizados_ajax_guard();
	try {
		pw_personalizados_arts_pdf_step_run();
	} catch ( Throwable $error ) {
		if ( function_exists( 'pw_printway_log' ) ) {
			pw_printway_log( 'pedidos', 'error', 'Montagem de PDF: erro inesperado no passo — ' . get_class( $error ) . ': ' . $error->getMessage() . ' em ' . $error->getFile() . ':' . $error->getLine() );
		}
		wp_send_json_error( array(
			'message' => 'Erro inesperado ao montar o PDF: ' . $error->getMessage() . ' (' . get_class( $error ) . ', linha ' . $error->getLine() . ' de ' . basename( $error->getFile() ) . ').',
		), 500 );
	}
}
add_action( 'wp_ajax_pw_personalizados_arts_pdf_step', 'pw_personalizados_arts_pdf_step' );

function pw_personalizados_arts_pdf_step_run() {
	$job_id = isset( $_POST['job_id'] ) ? sanitize_text_field( wp_unslash( $_POST['job_id'] ) ) : '';
	$key = 'pw_arts_pdf_job_' . $job_id;
	$job = get_transient( $key );
	if ( ! is_array( $job ) ) {
		wp_send_json_error( array( 'message' => 'Essa montagem expirou ou não existe mais. Selecione as artes novamente.' ), 404 );
	}
	if ( ! empty( $job['cancelled'] ) ) {
		pw_personalizados_arts_pdf_rrmdir( $job['dir'] );
		delete_transient( $key );
		wp_send_json_success( array( 'cancelled' => true ) );
	}

	$index = (int) $job['processed'];

	if ( $index >= (int) $job['total'] ) {
		$result = pw_personalizados_arts_pdf_compose( $job );
		pw_personalizados_arts_pdf_rrmdir( $job['dir'] );
		delete_transient( $key );
		if ( is_wp_error( $result ) ) {
			if ( function_exists( 'pw_printway_log' ) ) {
				pw_printway_log( 'pedidos', 'error', 'Montagem de PDF: falha ao compor o arquivo final — ' . $result->get_error_message() );
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
			'skipped'   => array_merge( isset( $job['skipped'] ) ? array_values( $job['skipped'] ) : array(), isset( $result['skipped'] ) ? array_values( $result['skipped'] ) : array() ),
		) );
	}

	if ( empty( $job['attachment_ids'][ $index ] ) ) {
		wp_send_json_error( array( 'message' => 'Índice ' . $index . ' não corresponde a nenhuma arte deste lote.' ), 500 );
	}
	$attachment_id = (int) $job['attachment_ids'][ $index ];
	$source = get_attached_file( $attachment_id );

	// Nesta etapa só copiamos e medimos o arquivo (rápido) — a
	// composição vetorial de verdade com o FPDI acontece de uma vez na
	// etapa final, porque o objeto do FPDI não sobrevive entre
	// requisições HTTP separadas (cada chamada AJAX é independente).
	if ( ! $source || ! file_exists( $source ) ) {
		$job['processed'] = $index + 1;
		$job['skipped'][] = array( 'attachment_id' => $attachment_id, 'message' => 'Arquivo original não encontrado na biblioteca de mídia.' );
		set_transient( $key, $job, PW_ARTS_PDF_JOB_TTL );
		wp_send_json_success( array( 'done' => false, 'processed' => $job['processed'], 'total' => $job['total'], 'percent' => (int) round( $job['processed'] / $job['total'] * 100 ), 'skipped_id' => $attachment_id, 'skipped_reason' => 'Arquivo não encontrado.' ) );
	}
	$copy_path = trailingslashit( $job['dir'] ) . sprintf( '%04d-%d.pdf', $index, $attachment_id );
	if ( ! copy( $source, $copy_path ) ) {
		$job['processed'] = $index + 1;
		$job['skipped'][] = array( 'attachment_id' => $attachment_id, 'message' => 'Não foi possível copiar o arquivo para a pasta temporária.' );
		set_transient( $key, $job, PW_ARTS_PDF_JOB_TTL );
		wp_send_json_success( array( 'done' => false, 'processed' => $job['processed'], 'total' => $job['total'], 'percent' => (int) round( $job['processed'] / $job['total'] * 100 ), 'skipped_id' => $attachment_id, 'skipped_reason' => 'Falha ao copiar arquivo.' ) );
	}

	$job['processed'] = $index + 1;
	$job['files'][ $index ] = array( 'attachment_id' => $attachment_id, 'path' => $copy_path );
	set_transient( $key, $job, PW_ARTS_PDF_JOB_TTL );

	wp_send_json_success( array(
		'done'      => false,
		'processed' => $job['processed'],
		'total'     => $job['total'],
		'percent'   => (int) round( $job['processed'] / $job['total'] * 100 ),
	) );
}

function pw_personalizados_arts_pdf_cancel() {
	pw_personalizados_ajax_guard();
	$job_id = isset( $_POST['job_id'] ) ? sanitize_text_field( wp_unslash( $_POST['job_id'] ) ) : '';
	$key = 'pw_arts_pdf_job_' . $job_id;
	$job = get_transient( $key );
	if ( is_array( $job ) ) {
		pw_personalizados_arts_pdf_rrmdir( $job['dir'] );
		delete_transient( $key );
	}
	wp_send_json_success();
}
add_action( 'wp_ajax_pw_personalizados_arts_pdf_cancel', 'pw_personalizados_arts_pdf_cancel' );

/** Monta o PDF final: importa cada página como molde vetorial e cola uma embaixo da outra, com espaço entre elas. */
function pw_personalizados_arts_pdf_compose( $job ) {
	if ( function_exists( 'pw_personalizados_raise_memory_for_large_render' ) ) {
		pw_personalizados_raise_memory_for_large_render();
	}
	if ( ! pw_personalizados_arts_pdf_load_library() ) {
		return new WP_Error( 'arts_pdf_no_library', 'A biblioteca de montagem de PDF não foi encontrada no servidor.' );
	}
	$files = isset( $job['files'] ) && is_array( $job['files'] ) ? $job['files'] : array();
	ksort( $files );
	if ( ! $files ) {
		return new WP_Error( 'arts_pdf_empty', 'Nenhuma página foi preparada com sucesso.' );
	}

	try {
		$fpdiClass = 'setasign\\Fpdi\\Fpdi';
		$fpdi = new $fpdiClass();
		$fpdi->SetAutoPageBreak( false );
		$fpdi->SetMargins( 0, 0, 0 );

		$pages = array();
		$skipped = isset( $job['skipped'] ) && is_array( $job['skipped'] ) ? $job['skipped'] : array();
		foreach ( $files as $file ) {
			$imported = pw_personalizados_arts_pdf_import_page( $fpdi, $file['path'], $job['dir'] );
			if ( is_wp_error( $imported ) ) {
				$skipped[] = array( 'attachment_id' => $file['attachment_id'], 'message' => $imported->get_error_message() );
				if ( function_exists( 'pw_printway_log' ) ) {
					pw_printway_log( 'pedidos', 'warning', 'Montagem de PDF: arte pulada (attachment ' . $file['attachment_id'] . '): ' . $imported->get_error_message() );
				}
				continue;
			}
			$pages[] = $imported;
		}
		if ( ! $pages ) {
			return new WP_Error( 'arts_pdf_empty', 'Nenhuma página foi importada com sucesso — veja os motivos na lista de puladas.' );
		}

		$gap_mm = PW_ARTS_PDF_GAP_MM;
		$max_width_mm = 0;
		$total_height_mm = 0;
		foreach ( $pages as $page ) {
			$max_width_mm = max( $max_width_mm, $page['width'] );
			$total_height_mm += $page['height'];
		}
		$total_height_mm += $gap_mm * max( 0, count( $pages ) - 1 );
		if ( $max_width_mm <= 0 || $total_height_mm <= 0 ) {
			return new WP_Error( 'arts_pdf_invalid_size', 'As páginas importadas não têm um tamanho válido.' );
		}

		// Uma página só, do tamanho exato da soma de todas as artes —
		// assim fica tudo numa folha só, pronto pra imprimir e cortar.
		$fpdi->AddPage( $max_width_mm > $total_height_mm ? 'L' : 'P', array( $max_width_mm, $total_height_mm ) );
		$y = 0;
		foreach ( $pages as $page ) {
			$fpdi->useTemplate( $page['templateId'], 0, $y, $page['width'], $page['height'] );
			$y += $page['height'] + $gap_mm;
		}

		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return new WP_Error( 'arts_pdf_uploads', $uploads['error'] );
		}
		$filename = wp_unique_filename( $uploads['path'], 'artes-montadas-' . gmdate( 'Ymd-His' ) . '.pdf' );
		$destination = trailingslashit( $uploads['path'] ) . $filename;
		$fpdi->Output( 'F', $destination );

		if ( ! file_exists( $destination ) ) {
			return new WP_Error( 'arts_pdf_write_failed', 'O arquivo final não foi gravado no servidor.' );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attachment_id = wp_insert_attachment( array(
			'post_mime_type' => 'application/pdf',
			'post_title'     => pathinfo( $filename, PATHINFO_FILENAME ),
			'post_status'    => 'inherit',
		), $destination );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $destination ) );

		if ( function_exists( 'pw_printway_log' ) ) {
			pw_printway_log( 'pedidos', 'info', 'Montagem de PDF concluída: ' . count( $pages ) . ' página(s) em ' . $filename . '.' );
		}

		$job['skipped'] = $skipped;
		return array(
			'id'      => (int) $attachment_id,
			'url'     => wp_get_attachment_url( $attachment_id ),
			'name'    => $filename,
			'skipped' => $skipped,
		);
	} catch ( Throwable $error ) {
		return new WP_Error(
			'arts_pdf_compose_failed',
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
