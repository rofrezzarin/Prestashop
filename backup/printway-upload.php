<?php
/**
 * Receptor leve de blocos para importacao de backups grandes.
 *
 * Este arquivo nao carrega o WordPress de proposito. Assim, uma oscilacao do
 * banco de dados nao interrompe a transferencia de um arquivo que pode levar
 * varios minutos. A autorizacao e feita por um segredo temporario criado na
 * pagina administrativa do plugin.
 */

declare(strict_types=1);

header( 'Content-Type: application/json; charset=UTF-8' );
header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
header( 'X-Content-Type-Options: nosniff' );

function pw_upload_log( string $upload_id, string $message ): void {
	$log_path = __DIR__ . DIRECTORY_SEPARATOR . 'import.log';
	/* O diagnostico nunca pode disputar o ultimo espaco em disco com o site. */
	if ( is_file( $log_path ) && (int) @filesize( $log_path ) > 2 * 1024 * 1024 ) {
		$previous = __DIR__ . DIRECTORY_SEPARATOR . 'import-previous.log';
		@unlink( $previous );
		@rename( $log_path, $previous );
	}
	$line = '[' . gmdate( 'Y-m-d H:i:s' ) . ' UTC] [' . preg_replace( '/[^a-zA-Z0-9_-]/', '', $upload_id ) . '] ' . preg_replace( '/[\r\n]+/', ' ', $message ) . PHP_EOL;
	@file_put_contents( $log_path, $line, FILE_APPEND | LOCK_EX );
}

function pw_upload_capacity( string $directory, int $total, int $current ): array {
	$free = @disk_free_space( $directory );
	$disk = @disk_total_space( $directory );
	/* Durante a inclusao, o ZIP unico e os volumes extraidos coexistem. A
	 * reserva adicional preserva PHP, banco, sessoes e logs do WordPress. */
	$reserve    = max( 1024 * 1024 * 1024, (int) ceil( $total * 0.10 ) );
	$remaining  = max( 0, $total - $current );
	$extraction = (int) ceil( $total * 1.10 );
	$required   = $remaining + $extraction + $reserve;
	return array(
		'free'       => false === $free ? -1 : (int) $free,
		'disk_total' => false === $disk ? -1 : (int) $disk,
		'required'   => $required,
		'reserve'    => $reserve,
		'shortfall'  => false === $free ? 0 : max( 0, $required - (int) $free ),
		'ok'         => false === $free || (int) $free >= $required,
	);
}

function pw_upload_reply( bool $success, array $data = array(), int $status = 200 ): void {
	http_response_code( $status );
	echo json_encode( array( 'success' => $success, 'data' => $data ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	exit;
}

if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
	pw_upload_reply( false, array( 'message' => 'Metodo nao permitido.' ), 405 );
}

$upload_id = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) ( $_SERVER['HTTP_X_PW_UPLOAD_ID'] ?? '' ) );
$secret    = (string) ( $_SERVER['HTTP_X_PW_UPLOAD_TOKEN'] ?? '' );
if ( ! $upload_id || strlen( $secret ) < 32 ) {
	pw_upload_reply( false, array( 'message' => 'Autorizacao de envio invalida.' ), 403 );
}

/* Localiza wp-content sem depender da estrutura exata da pasta do plugin. */
$cursor  = __DIR__;
$content = '';
for ( $level = 0; $level < 8; $level++ ) {
	if ( 'wp-content' === basename( $cursor ) ) {
		$content = $cursor;
		break;
	}
	$parent = dirname( $cursor );
	if ( $parent === $cursor ) {
		break;
	}
	$cursor = $parent;
}
if ( ! $content ) {
	pw_upload_reply( false, array( 'message' => 'Nao foi possivel localizar a instalacao do WordPress.' ), 500 );
}

$root       = dirname( $content );
$candidates = array(
	dirname( $root ) . DIRECTORY_SEPARATOR . 'printway-backups',
	$content . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'printway-backups',
);
$directory = '';
$auth_path = '';
foreach ( $candidates as $candidate ) {
	$possible = $candidate . DIRECTORY_SEPARATOR . '.upload-auth-' . $upload_id . '.json';
	if ( is_file( $possible ) ) {
		$directory = $candidate;
		$auth_path = $possible;
		break;
	}
}
if ( ! $auth_path ) {
	pw_upload_log( $upload_id, 'ERRO: sessão de envio não localizada.' );
	pw_upload_reply( false, array( 'message' => 'A sessao deste envio nao existe ou expirou.' ), 403 );
}

$auth = json_decode( (string) @file_get_contents( $auth_path ), true );
if ( ! is_array( $auth ) || empty( $auth['secret_hash'] ) || empty( $auth['expires'] ) || time() > (int) $auth['expires'] || ! hash_equals( (string) $auth['secret_hash'], hash( 'sha256', $secret ) ) ) {
	pw_upload_log( $upload_id, 'ERRO: autorização expirada ou inválida.' );
	pw_upload_reply( false, array( 'message' => 'A sessao deste envio expirou ou nao e valida.' ), 403 );
}

$temp = $directory . DIRECTORY_SEPARATOR . '.upload-' . $upload_id . '.tmp';
clearstatcache( true, $temp );
$current = is_file( $temp ) ? (int) filesize( $temp ) : 0;

$operation = (string) ( $_GET['operation'] ?? '' );
$declared_total = max( 0, (int) ( $_SERVER['HTTP_X_PW_UPLOAD_TOTAL'] ?? ( $auth['total'] ?? 0 ) ) );
if ( 'preflight' === $operation ) {
	if ( $declared_total < 1 ) {
		pw_upload_reply( false, array( 'message' => 'O tamanho total do backup nao foi informado.', 'fatal' => true ), 400 );
	}
	$capacity = pw_upload_capacity( $directory, $declared_total, $current );
	pw_upload_log( $upload_id, 'PRÉ-VERIFICAÇÃO: arquivo=' . $declared_total . '; recebido=' . $current . '; livre=' . $capacity['free'] . '; necessário=' . $capacity['required'] . '; reserva=' . $capacity['reserve'] . '; resultado=' . ( $capacity['ok'] ? 'permitido' : 'bloqueado' ) . '.' );
	if ( ! $capacity['ok'] ) {
		pw_upload_reply( false, array(
			'message'       => 'O servidor nao possui espaco operacional suficiente para importar este backup com seguranca. Libere espaco ou envie os volumes menores por FTP.',
			'fatal'         => true,
			'received'      => $current,
			'disk_free'     => $capacity['free'],
			'disk_required' => $capacity['required'],
			'shortfall'     => $capacity['shortfall'],
		), 507 );
	}
	pw_upload_reply( true, array_merge( array( 'received' => $current ), $capacity ) );
}
if ( 'status' === $operation ) {
	pw_upload_reply( true, array( 'received' => $current, 'complete' => ! empty( $auth['complete'] ) ) );
}
if ( 'log' === $operation ) {
	$log_path = __DIR__ . DIRECTORY_SEPARATOR . 'import.log';
	pw_upload_reply( true, array( 'log' => is_file( $log_path ) ? (string) @file_get_contents( $log_path ) : 'Ainda não há registro de importação.' ) );
}
if ( 'init-log' === $operation || 'client-log' === $operation ) {
	$raw = (string) @file_get_contents( 'php://input' );
	$data = json_decode( $raw, true );
	$data = is_array( $data ) ? $data : array( 'raw' => substr( $raw, 0, 2000 ) );
	pw_upload_log( $upload_id, ( 'init-log' === $operation ? 'INÍCIO DA IMPORTAÇÃO: ' : 'NAVEGADOR: ' ) . json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	pw_upload_reply( true );
}
if ( 'cancel' === $operation ) {
	pw_upload_log( $upload_id, 'IMPORTAÇÃO CANCELADA pelo usuário; arquivo parcial removido com ' . $current . ' bytes.' );
	if ( is_file( $temp ) ) { @unlink( $temp ); }
	if ( is_file( $auth_path ) ) { @unlink( $auth_path ); }
	pw_upload_reply( true, array( 'removed' => true ) );
}

$offset = max( 0, (int) ( $_SERVER['HTTP_X_PW_UPLOAD_OFFSET'] ?? 0 ) );
$total  = max( 0, (int) ( $_SERVER['HTTP_X_PW_UPLOAD_TOTAL'] ?? 0 ) );
$last   = '1' === (string) ( $_SERVER['HTTP_X_PW_UPLOAD_LAST'] ?? '' );
if ( $total < 1 || $offset > $total ) {
	pw_upload_reply( false, array( 'message' => 'Posicao ou tamanho do envio invalido.', 'received' => $current ), 400 );
}
if ( $current === $total ) {
	pw_upload_reply( true, array( 'received' => $current, 'complete' => true ) );
}
if ( $offset !== $current ) {
	pw_upload_log( $upload_id, 'POSIÇÃO DIVERGENTE: navegador=' . $offset . '; servidor=' . $current . '; total=' . $total . '.' );
	pw_upload_reply( false, array( 'message' => 'Posicao divergente; o navegador deve retomar do ponto confirmado.', 'received' => $current ), 409 );
}

/* Nunca permita que uma importação consuma o espaço necessário para o PHP,
 * banco e painel continuarem funcionando. */
$capacity = pw_upload_capacity( $directory, $total, $current );
if ( ! $capacity['ok'] ) {
	pw_upload_log( $upload_id, 'IMPORTAÇÃO INTERROMPIDA POR SEGURANÇA: espaço livre=' . $capacity['free'] . '; necessário=' . $capacity['required'] . '; falta=' . $capacity['shortfall'] . '; recebido=' . $current . '; total=' . $total . '.' );
	pw_upload_reply( false, array(
		'message'       => 'O envio foi interrompido antes de comprometer o WordPress: falta espaco operacional para receber e extrair o backup.',
		'received'      => $current,
		'fatal'         => true,
		'disk_free'     => $capacity['free'],
		'disk_required' => $capacity['required'],
		'shortfall'     => $capacity['shortfall'],
	), 507 );
}

$source = @fopen( 'php://input', 'rb' );
$target = @fopen( $temp, 0 === $offset ? 'wb' : 'ab' );
if ( ! $source || ! $target ) {
	if ( $source ) { fclose( $source ); }
	if ( $target ) { fclose( $target ); }
	pw_upload_log( $upload_id, 'ERRO: não foi possível abrir o bloco para gravação; posição=' . $offset . '.' );
	pw_upload_reply( false, array( 'message' => 'Nao foi possivel abrir o bloco para gravacao.', 'received' => $current ), 500 );
}

@flock( $target, LOCK_EX );
$written = stream_copy_to_stream( $source, $target );
@fflush( $target );
@flock( $target, LOCK_UN );
fclose( $source );
fclose( $target );
clearstatcache( true, $temp );
$current = is_file( $temp ) ? (int) filesize( $temp ) : 0;
if ( false === $written || $current <= $offset || $current > $total ) {
	pw_upload_log( $upload_id, 'ERRO DE GRAVAÇÃO: posição=' . $offset . '; gravados=' . ( false === $written ? 'false' : $written ) . '; servidor=' . $current . '; total=' . $total . '.' );
	pw_upload_reply( false, array( 'message' => 'O bloco nao foi gravado integralmente.', 'received' => $current ), 500 );
}
if ( $last && $current !== $total ) {
	pw_upload_reply( false, array( 'message' => 'O ultimo bloco terminou em uma posicao inesperada.', 'received' => $current ), 409 );
}

$auth['total']         = $total;
$auth['original_name'] = basename( (string) ( $_SERVER['HTTP_X_PW_FILE_NAME'] ?? 'backup.zip' ) );
$auth['updated']       = time();
$auth['complete']      = ( $current === $total );
@file_put_contents( $auth_path, json_encode( $auth, JSON_UNESCAPED_SLASHES ), LOCK_EX );

if ( 0 === $offset ) {
	pw_upload_log( $upload_id, 'PRIMEIRO BLOCO recebido; total=' . $total . '; bloco=' . $written . '.' );
} elseif ( $current === $total ) {
	pw_upload_log( $upload_id, 'UPLOAD COMPLETO recebido: ' . $current . ' bytes.' );
} elseif ( 0 === ( $current % ( 100 * 1024 * 1024 ) ) ) {
	pw_upload_log( $upload_id, 'PROGRESSO: ' . $current . ' de ' . $total . ' bytes.' );
}

pw_upload_reply( true, array( 'received' => $current, 'complete' => $current === $total ) );
