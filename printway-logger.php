<?php
/**
 * Log central do PrintWay.
 *
 * Junta em um único lugar os avisos/erros de todos os módulos (pedidos,
 * backup, email, pix, editor de imagens), que antes só apareciam espalhados
 * em chamadas soltas de error_log() (visíveis apenas no log do servidor).
 *
 * Uso em qualquer módulo:
 *   pw_printway_log( 'backup', 'error', 'Falha ao gerar pacote', array( 'arquivo' => $file ) );
 *
 * Níveis aceitos: 'info', 'warning', 'error'.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PW_PRINTWAY_LOG_FILE', trailingslashit( WP_CONTENT_DIR ) . 'printway-logs/printway.log' );
define( 'PW_PRINTWAY_LOG_MAX_BYTES', 2 * 1024 * 1024 ); // 2 MB por arquivo, mantém 1 anterior.
define( 'PW_PRINTWAY_LOG_MAX_LINES_ADMIN', 300 ); // quantas linhas a tela de admin lê no máximo.

function pw_printway_log_dir_ensure() {
	$dir = dirname( PW_PRINTWAY_LOG_FILE );
	if ( ! file_exists( $dir ) ) {
		wp_mkdir_p( $dir );
		// Evita listagem/execução direta da pasta de logs pelo navegador.
		@file_put_contents( trailingslashit( $dir ) . 'index.php', "<?php\n// Silence is golden.\n" );
		@file_put_contents( trailingslashit( $dir ) . '.htaccess', "Deny from all\n" );
	}
	return $dir;
}

/**
 * Registra uma linha de log. Nunca lança exceção nem interrompe a página —
 * uma falha ao gravar o log não pode derrubar o restante do sistema.
 *
 * @param string $module  Nome curto do módulo de origem: 'pedidos', 'backup', 'email', 'pix', 'editor', 'core'.
 * @param string $level   'info' | 'warning' | 'error'.
 * @param string $message Mensagem legível, sem dados sensíveis (sem token, senha, cartão etc.).
 * @param array  $context Dados extras opcionais (ex.: id do pedido). Também não deve conter segredos.
 */
function pw_printway_log( $module, $level, $message, $context = array() ) {
	$level = in_array( $level, array( 'info', 'warning', 'error' ), true ) ? $level : 'info';

	try {
		pw_printway_log_dir_ensure();

		if ( file_exists( PW_PRINTWAY_LOG_FILE ) && filesize( PW_PRINTWAY_LOG_FILE ) > PW_PRINTWAY_LOG_MAX_BYTES ) {
			@rename( PW_PRINTWAY_LOG_FILE, dirname( PW_PRINTWAY_LOG_FILE ) . '/printway-previous.log' );
		}

		$entry = array(
			'id'       => wp_generate_uuid4(),
			'time_utc' => gmdate( 'Y-m-d H:i:s' ),
			'module'   => (string) $module,
			'level'    => $level,
			'message'  => (string) $message,
			'context'  => (array) $context,
			'user_id'  => get_current_user_id(),
		);

		@file_put_contents(
			PW_PRINTWAY_LOG_FILE,
			wp_json_encode( $entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . PHP_EOL,
			FILE_APPEND | LOCK_EX
		);
	} catch ( Throwable $error ) {
		// Nunca deixa o log quebrar o fluxo principal.
	}
}

/**
 * Lê as últimas $limit entradas do log (mais recentes primeiro).
 * Também mistura o printway-previous.log quando o atual tiver poucas linhas,
 * pra tela de admin não ficar vazia logo depois de uma rotação.
 */
function pw_printway_log_read( $limit = 200, $level_filter = '' ) {
	$files = array( PW_PRINTWAY_LOG_FILE, dirname( PW_PRINTWAY_LOG_FILE ) . '/printway-previous.log' );
	$lines = array();

	foreach ( $files as $file ) {
		if ( ! file_exists( $file ) ) {
			continue;
		}
		$content = @file_get_contents( $file );
		if ( ! $content ) {
			continue;
		}
		$lines = array_merge( $lines, array_filter( explode( PHP_EOL, $content ) ) );
	}

	$entries = array();
	foreach ( $lines as $line ) {
		$decoded = json_decode( $line, true );
		if ( is_array( $decoded ) ) {
			// Registros gravados antes desta versão não tinham "id" — usa um hash
			// estável da própria linha, pra que a seleção/limpeza por id continue
			// funcionando com entradas antigas também.
			if ( empty( $decoded['id'] ) ) {
				$decoded['id'] = md5( $line );
			}
			$entries[] = $decoded;
		}
	}

	// Mais recente primeiro.
	$entries = array_reverse( $entries );

	if ( $level_filter && in_array( $level_filter, array( 'info', 'warning', 'error' ), true ) ) {
		$entries = array_values( array_filter( $entries, function ( $e ) use ( $level_filter ) {
			return isset( $e['level'] ) && $e['level'] === $level_filter;
		} ) );
	}

	return array_slice( $entries, 0, max( 1, (int) $limit ) );
}

/** Apaga todo o registro de erros (arquivo atual e o de rotação anterior). */
function pw_printway_log_clear_all() {
	foreach ( array( PW_PRINTWAY_LOG_FILE, dirname( PW_PRINTWAY_LOG_FILE ) . '/printway-previous.log' ) as $file ) {
		if ( file_exists( $file ) ) {
			@unlink( $file );
		}
	}
	return true;
}

/**
 * Apaga só as entradas com os IDs informados (mesma regra de ID de
 * pw_printway_log_read(): usa o "id" gravado ou, pra linhas antigas sem
 * esse campo, o hash da própria linha). Reescreve os dois arquivos do log
 * mantendo tudo que não foi selecionado.
 */
function pw_printway_log_clear_ids( $ids ) {
	$ids = array_values( array_filter( array_map( 'strval', (array) $ids ) ) );
	if ( ! $ids ) {
		return 0;
	}
	$ids_lookup = array_flip( $ids );
	$removed = 0;
	foreach ( array( PW_PRINTWAY_LOG_FILE, dirname( PW_PRINTWAY_LOG_FILE ) . '/printway-previous.log' ) as $file ) {
		if ( ! file_exists( $file ) ) {
			continue;
		}
		$content = @file_get_contents( $file );
		if ( ! $content ) {
			continue;
		}
		$lines = array_filter( explode( PHP_EOL, $content ), function ( $line ) { return '' !== trim( $line ); } );
		$kept = array();
		$changed = false;
		foreach ( $lines as $line ) {
			$decoded = json_decode( $line, true );
			$id = is_array( $decoded ) && ! empty( $decoded['id'] ) ? (string) $decoded['id'] : md5( $line );
			if ( isset( $ids_lookup[ $id ] ) ) {
				$removed++;
				$changed = true;
				continue;
			}
			$kept[] = $line;
		}
		if ( $changed ) {
			@file_put_contents( $file, $kept ? ( implode( PHP_EOL, $kept ) . PHP_EOL ) : '', LOCK_EX );
		}
	}
	return $removed;
}

/**
 * Tela de admin: PrintWay → Logs.
 */
function pw_printway_register_logs_menu() {
	add_submenu_page(
		'pw-printway',
		'Logs',
		'Logs',
		'manage_options',
		'pw-printway-logs',
		'pw_printway_render_logs_page'
	);
}
/* Item "Logs" separado no menu removido em 2026-09-14 a pedido do usuário —
 * o mesmo registro (mesma função pw_printway_log_read()) já aparece na aba
 * "Erros" dentro de Sistema interno → Configurações, sem precisar de uma
 * tela própria no admin do WordPress. A função de renderização continua
 * abaixo, só não é mais registrada como página. */
// add_action( 'admin_menu', 'pw_printway_register_logs_menu', 1001 );

function pw_printway_render_logs_page( $embedded = false ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$level_filter = isset( $_GET['pw_log_level'] ) ? sanitize_key( wp_unslash( $_GET['pw_log_level'] ) ) : '';
	$entries = pw_printway_log_read( PW_PRINTWAY_LOG_MAX_LINES_ADMIN, $level_filter );

	if ( ! $embedded ) { echo '<div class="wrap"><h1>PrintWay — Logs</h1>'; }
	echo '<p>Registro unificado de avisos e erros de todos os módulos (pedidos, backup, e-mail, pix, editor de imagens). Guarda até ' . esc_html( PW_PRINTWAY_LOG_MAX_LINES_ADMIN ) . ' entradas mais recentes nesta tela.</p>';

	echo '<form method="get" style="margin-bottom:12px;">';
	if ( $embedded ) {
		echo '<input type="hidden" name="page" value="pw-personalizados" /><input type="hidden" name="aba" value="logs" />';
	} else {
		echo '<input type="hidden" name="page" value="pw-printway-logs" />';
	}
	echo '<select name="pw_log_level" onchange="this.form.submit()">';
	foreach ( array( '' => 'Todos os níveis', 'error' => 'Somente erros', 'warning' => 'Somente avisos', 'info' => 'Somente informativos' ) as $value => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $value ), selected( $level_filter, $value, false ), esc_html( $label ) );
	}
	echo '</select></form>';

	if ( empty( $entries ) ) {
		echo '<p>Nenhum evento registrado ainda.</p>';
		if ( ! $embedded ) { echo '</div>'; }
		return;
	}

	echo '<table class="widefat striped"><thead><tr>';
	echo '<th style="width:160px;">Data/hora (UTC)</th><th style="width:100px;">Módulo</th><th style="width:90px;">Nível</th><th>Mensagem</th><th>Contexto</th>';
	echo '</tr></thead><tbody>';

	foreach ( $entries as $entry ) {
		$level = isset( $entry['level'] ) ? $entry['level'] : 'info';
		$color = array( 'error' => '#b32d2e', 'warning' => '#996800', 'info' => '#2271b1' );
		printf(
			'<tr><td>%s</td><td>%s</td><td><strong style="color:%s;">%s</strong></td><td>%s</td><td><code>%s</code></td></tr>',
			esc_html( $entry['time_utc'] ?? '' ),
			esc_html( $entry['module'] ?? '' ),
			esc_attr( $color[ $level ] ?? '#333' ),
			esc_html( strtoupper( $level ) ),
			esc_html( $entry['message'] ?? '' ),
			esc_html( wp_json_encode( $entry['context'] ?? array() ) )
		);
	}

	echo '</tbody></table>';
	if ( ! $embedded ) { echo '</div>'; }
}
