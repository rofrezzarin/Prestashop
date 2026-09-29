<?php
/**
 * Plugin Name: Editor de imagens
 * Description: Editor de imagens para preparação de artes DTF UV, com PNG/PDF, remoção de fundo, montagem de objetos, borracha e exportações.
 * Version: 1.0.457
 * Author: Print Way
 * License: GPL-2.0-or-later
 * Text Domain: editor-de-imagens
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* Evita declarações duplicadas caso uma instalação antiga do editor ainda
 * esteja ativa separadamente durante a atualização. */
if ( ! function_exists( 'dtf_uv_editor_shortcode' ) ) {

if ( ! defined( 'DTF_UV_EDITOR_VERSION' ) ) {
    define( 'DTF_UV_EDITOR_VERSION', '1.0.457' );
}
if ( ! defined( 'DTF_UV_EDITOR_GALLERY_QUOTA_BYTES' ) ) {
    define( 'DTF_UV_EDITOR_GALLERY_QUOTA_BYTES', 1073741824 ); // 1 GB por usuário.
}
if ( ! defined( 'DTF_UV_EDITOR_FILE' ) ) {
    define( 'DTF_UV_EDITOR_FILE', __FILE__ );
}
if ( ! defined( 'DTF_UV_EDITOR_DIR' ) ) {
    define( 'DTF_UV_EDITOR_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'DTF_UV_EDITOR_URL' ) ) {
    define( 'DTF_UV_EDITOR_URL', plugin_dir_url( __FILE__ ) );
}

/**
 * Registers plugin assets. Content hashes invalidate older cached assets.
 */
function dtf_uv_editor_asset_version( $relative_path ) {
    $path = DTF_UV_EDITOR_DIR . $relative_path;
    $hash = is_readable( $path ) ? hash_file( 'sha256', $path ) : false;
    return DTF_UV_EDITOR_VERSION . ( $hash ? '-' . substr( $hash, 0, 12 ) : '' );
}

/**
 * Lightweight public endpoint used by the editor to detect newer builds.
 * It intentionally returns only the current version and disables caching.
 */
function dtf_uv_editor_version_endpoint() {
    nocache_headers();
    wp_send_json_success( array( 'version' => DTF_UV_EDITOR_VERSION ) );
}
add_action( 'wp_ajax_dtf_uv_editor_version', 'dtf_uv_editor_version_endpoint' );
add_action( 'wp_ajax_nopriv_dtf_uv_editor_version', 'dtf_uv_editor_version_endpoint' );

/** Persiste a altura padrão da faixa de ferramentas definida por um administrador. */
function dtf_uv_editor_save_divider_height_endpoint() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Apenas administradores podem ajustar a altura da faixa.' ), 403 );
    }
    check_ajax_referer( 'dtf_uv_editor_media', 'nonce' );
    $height = isset( $_POST['height'] ) ? (int) $_POST['height'] : 150;
    $height = max( 100, min( 300, $height ) );
    update_option( 'dtf_uv_editor_divider_height', $height, false );
    nocache_headers();
    wp_send_json_success( array( 'height' => $height ) );
}
add_action( 'wp_ajax_dtf_uv_editor_save_divider_height', 'dtf_uv_editor_save_divider_height_endpoint' );

/** Persiste globalmente as larguras das divisórias ajustadas pelo administrador. */
function dtf_uv_editor_save_divider_widths_endpoint() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Apenas administradores podem ajustar as divisórias.' ), 403 );
    }
    check_ajax_referer( 'dtf_uv_editor_media', 'nonce' );
    $raw   = isset( $_POST['widths'] ) ? json_decode( wp_unslash( $_POST['widths'] ), true ) : array();
    $clean = array();
    if ( is_array( $raw ) ) {
        foreach ( $raw as $panel => $widths ) {
            $panel = sanitize_key( $panel );
            if ( '' === $panel || ! is_array( $widths ) ) {
                continue;
            }
            foreach ( $widths as $id => $width ) {
                $id = sanitize_key( $id );
                if ( ! preg_match( '/^g\d+$/', $id ) ) {
                    continue;
                }
                $clean[ $panel ][ $id ] = max( 56, min( 1600, (int) $width ) );
            }
        }
    }
    update_option( 'dtf_uv_editor_divider_widths', $clean, false );
    nocache_headers();
    wp_send_json_success( array( 'widths' => $clean ) );
}
add_action( 'wp_ajax_dtf_uv_editor_save_divider_widths', 'dtf_uv_editor_save_divider_widths_endpoint' );

/** Persiste preferências da área de trabalho por usuário, para sincronizar navegadores. */
function dtf_uv_editor_save_user_settings_endpoint() {
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => 'É necessário estar conectado.' ), 403 );
    }
    check_ajax_referer( 'dtf_uv_editor_media', 'nonce' );
    $raw      = isset( $_POST['settings'] ) ? json_decode( wp_unslash( $_POST['settings'] ), true ) : array();
    $previous = get_user_meta( get_current_user_id(), 'dtf_uv_editor_user_settings', true );
    $previous = is_array( $previous ) ? $previous : array();
    $clean    = $previous;

    if ( is_array( $raw ) && isset( $raw['workspace'] ) && is_array( $raw['workspace'] ) ) {
        $workspace = $raw['workspace'];
        $clean['workspace'] = array(
            'w'   => max( 10, min( 1000, (float) ( $workspace['w'] ?? 280 ) ) ),
            'h'   => max( 10, min( 10000, (float) ( $workspace['h'] ?? 100 ) ) ),
            'dpi' => max( 1, min( 2400, (int) ( $workspace['dpi'] ?? 300 ) ) ),
        );
    }
    if ( is_array( $raw ) && isset( $raw['fillColor'] ) && is_string( $raw['fillColor'] ) ) {
        $fill_color = strtolower( trim( $raw['fillColor'] ) );
        if ( preg_match( '/^#[0-9a-f]{6}$/', $fill_color ) ) {
            $clean['fillColor'] = $fill_color;
        }
    }
    update_user_meta( get_current_user_id(), 'dtf_uv_editor_user_settings', $clean );
    nocache_headers();
    wp_send_json_success( array( 'settings' => $clean ) );
}
add_action( 'wp_ajax_dtf_uv_editor_save_user_settings', 'dtf_uv_editor_save_user_settings_endpoint' );

/** Mantém somente os erros do Editor de imagens, separados por usuário. */
function dtf_uv_editor_error_logs_endpoint() {
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => 'É necessário estar conectado.' ), 403 );
    }
    check_ajax_referer( 'dtf_uv_editor_media', 'nonce' );
    $meta_key = 'dtf_uv_editor_error_logs';
    $mode     = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'get';
    if ( 'get' === $mode ) {
        $logs = get_user_meta( get_current_user_id(), $meta_key, true );
        wp_send_json_success( array( 'logs' => is_array( $logs ) ? array_slice( $logs, 0, 100 ) : array() ) );
    }
    $raw   = isset( $_POST['logs'] ) ? json_decode( wp_unslash( $_POST['logs'] ), true ) : array();
    $clean = array();
    if ( is_array( $raw ) ) {
        foreach ( array_slice( $raw, 0, 100 ) as $entry ) {
            if ( ! is_array( $entry ) || empty( $entry['message'] ) ) {
                continue;
            }
            $clean[] = array(
                'time'    => sanitize_text_field( $entry['time'] ?? '' ),
                'context' => sanitize_text_field( $entry['context'] ?? 'Erro do editor' ),
                'message' => substr( sanitize_text_field( $entry['message'] ), 0, 1000 ),
                'stack'   => substr( sanitize_textarea_field( $entry['stack'] ?? '' ), 0, 6000 ),
            );
        }
    }
    update_user_meta( get_current_user_id(), $meta_key, $clean );
    nocache_headers();
    wp_send_json_success( array( 'logs' => $clean ) );
}
add_action( 'wp_ajax_dtf_uv_editor_error_logs', 'dtf_uv_editor_error_logs_endpoint' );

/** Configuração privada das bibliotecas externas da aba Desenhos. */
function dtf_uv_editor_media_settings_sanitize( $input ) {
    $previous = get_option( 'dtf_uv_editor_media_settings', array() );
    $input    = is_array( $input ) ? $input : array();
    $keys     = array( 'pexels_key', 'pixabay_key', 'b2_key_id', 'b2_application_key', 'b2_bucket_id', 'b2_bucket_name' );
    $clean    = array();

    foreach ( $keys as $key ) {
        $value         = isset( $input[ $key ] ) ? trim( sanitize_text_field( wp_unslash( $input[ $key ] ) ) ) : '';
        $clean[ $key ] = '' !== $value ? $value : ( isset( $previous[ $key ] ) ? $previous[ $key ] : '' );
    }

    return $clean;
}

function dtf_uv_editor_register_media_settings() {
    register_setting( 'dtf_uv_editor_media', 'dtf_uv_editor_media_settings', 'dtf_uv_editor_media_settings_sanitize' );
}
add_action( 'admin_init', 'dtf_uv_editor_register_media_settings' );

function dtf_uv_editor_media_settings() {
    $settings = get_option( 'dtf_uv_editor_media_settings', array() );
    return is_array( $settings ) ? $settings : array();
}

/** Backblaze B2 stays private: credentials are used only by WordPress. */
function dtf_uv_editor_b2_settings() {
    $settings = dtf_uv_editor_media_settings();
    return array(
        'key_id'          => isset( $settings['b2_key_id'] ) ? trim( (string) $settings['b2_key_id'] ) : '',
        'application_key' => isset( $settings['b2_application_key'] ) ? trim( (string) $settings['b2_application_key'] ) : '',
        'bucket_id'       => isset( $settings['b2_bucket_id'] ) ? trim( (string) $settings['b2_bucket_id'] ) : '',
        'bucket_name'     => isset( $settings['b2_bucket_name'] ) ? trim( (string) $settings['b2_bucket_name'] ) : '',
    );
}

function dtf_uv_editor_b2_configured() {
    $settings = dtf_uv_editor_b2_settings();
    return '' !== $settings['key_id'] && '' !== $settings['application_key'] && '' !== $settings['bucket_id'] && '' !== $settings['bucket_name'];
}

function dtf_uv_editor_b2_prefix( $user_id = 0 ) {
    $user_id = absint( $user_id ?: get_current_user_id() );
    return 'editor/users/' . $user_id . '/';
}

function dtf_uv_editor_b2_gallery_quota_bytes() {
    return (int) DTF_UV_EDITOR_GALLERY_QUOTA_BYTES;
}

/** Soma apenas imagens da pasta do usuário atual, usada pela barra e pelo limite de envio. */
function dtf_uv_editor_b2_gallery_used_bytes( $auth, $settings, $prefix ) {
    $data = dtf_uv_editor_b2_api_json( $auth, 'b2_list_file_names', array( 'bucketId' => $settings['bucket_id'], 'prefix' => $prefix, 'maxFileCount' => 1000 ) );
    if ( is_wp_error( $data ) ) {
        return $data;
    }
    $used = 0;
    foreach ( (array) ( $data['files'] ?? array() ) as $file ) {
        if ( ! is_array( $file ) || 'upload' !== ( $file['action'] ?? 'upload' ) ) {
            continue;
        }
        $content_type = strtolower( (string) ( $file['contentType'] ?? '' ) );
        if ( 0 === strpos( $content_type, 'image/' ) ) {
            $used += absint( $file['contentLength'] ?? 0 );
        }
    }
    return $used;
}

function dtf_uv_editor_b2_authorize() {
    if ( ! dtf_uv_editor_b2_configured() ) {
        return new WP_Error( 'b2_not_configured', 'Configure o Backblaze B2 nas configurações do Editor de imagens.' );
    }
    $settings = dtf_uv_editor_b2_settings();
    $cache_key = 'dtf_uv_b2_auth_' . md5( $settings['key_id'] . '|' . $settings['application_key'] );
    $cached = get_transient( $cache_key );
    if ( is_array( $cached ) && ! empty( $cached['authorizationToken'] ) && ! empty( $cached['apiUrl'] ) ) {
        return $cached;
    }
    $response = wp_remote_get(
        'https://api.backblazeb2.com/b2api/v4/b2_authorize_account',
        array(
            'timeout' => 20,
            'headers' => array( 'Authorization' => 'Basic ' . base64_encode( $settings['key_id'] . ':' . $settings['application_key'] ) ),
        )
    );
    if ( is_wp_error( $response ) ) {
        return new WP_Error( 'b2_authorize_failed', 'Não foi possível conectar ao Backblaze B2: ' . $response->get_error_message() );
    }
    $code = (int) wp_remote_retrieve_response_code( $response );
    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( $code < 200 || $code >= 300 || ! is_array( $data ) || empty( $data['authorizationToken'] ) ) {
        $message = is_array( $data ) && ! empty( $data['message'] ) ? sanitize_text_field( $data['message'] ) : 'As credenciais do Backblaze B2 foram recusadas.';
        return new WP_Error( 'b2_authorize_invalid', $message, array( 'status' => $code ) );
    }
    $storage = isset( $data['apiInfo']['storageApi'] ) && is_array( $data['apiInfo']['storageApi'] ) ? $data['apiInfo']['storageApi'] : array();
    $auth = array(
        'authorizationToken' => (string) $data['authorizationToken'],
        'apiUrl'             => (string) ( $storage['apiUrl'] ?? $data['apiUrl'] ?? '' ),
        'downloadUrl'        => (string) ( $storage['downloadUrl'] ?? $data['downloadUrl'] ?? '' ),
    );
    if ( '' === $auth['apiUrl'] || '' === $auth['downloadUrl'] ) {
        return new WP_Error( 'b2_authorize_invalid', 'O Backblaze B2 não devolveu os endereços da API.' );
    }
    set_transient( $cache_key, $auth, 45 * MINUTE_IN_SECONDS );
    return $auth;
}

function dtf_uv_editor_b2_api_json( $auth, $action, $payload ) {
    $url = trailingslashit( $auth['apiUrl'] ) . 'b2api/v4/' . sanitize_key( $action );
    $response = wp_remote_post(
        $url,
        array(
            'timeout' => 45,
            'headers' => array( 'Authorization' => $auth['authorizationToken'], 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( $payload ),
        )
    );
    if ( is_wp_error( $response ) ) {
        return new WP_Error( 'b2_api_failed', 'O Backblaze B2 não respondeu: ' . $response->get_error_message() );
    }
    $code = (int) wp_remote_retrieve_response_code( $response );
    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
        $message = is_array( $data ) && ! empty( $data['message'] ) ? sanitize_text_field( $data['message'] ) : 'O Backblaze B2 devolveu uma resposta inválida.';
        return new WP_Error( 'b2_api_invalid', $message, array( 'status' => $code ) );
    }
    return $data;
}

function dtf_uv_editor_b2_download_url( $auth, $file_name, $duration = 3600 ) {
    $settings = dtf_uv_editor_b2_settings();
    $data = dtf_uv_editor_b2_api_json(
        $auth,
        'b2_get_download_authorization',
        array( 'bucketId' => $settings['bucket_id'], 'fileNamePrefix' => $file_name, 'validDurationInSeconds' => max( 60, min( 86400, absint( $duration ) ) ) )
    );
    if ( is_wp_error( $data ) || empty( $data['authorizationToken'] ) ) {
        return is_wp_error( $data ) ? $data : new WP_Error( 'b2_download_token_failed', 'Não foi possível criar o acesso temporário à imagem.' );
    }
    $encoded_name = implode( '/', array_map( 'rawurlencode', explode( '/', ltrim( $file_name, '/' ) ) ) );
    return trailingslashit( $auth['downloadUrl'] ) . 'file/' . rawurlencode( $settings['bucket_name'] ) . '/' . $encoded_name . '?Authorization=' . rawurlencode( $data['authorizationToken'] );
}

function dtf_uv_editor_b2_error( $error, $status = 500 ) {
    $status = absint( $status ) ?: 500;
    wp_send_json_error( array( 'message' => is_wp_error( $error ) ? $error->get_error_message() : (string) $error ), $status );
}

function dtf_uv_editor_b2_list_endpoint() {
    check_ajax_referer( 'dtf_uv_editor_media', 'nonce' );
    if ( ! is_user_logged_in() ) {
        dtf_uv_editor_b2_error( 'Entre na sua conta para abrir sua galeria.', 401 );
    }
    $auth = dtf_uv_editor_b2_authorize();
    if ( is_wp_error( $auth ) ) {
        dtf_uv_editor_b2_error( $auth, 503 );
    }
    $settings = dtf_uv_editor_b2_settings();
    $prefix = dtf_uv_editor_b2_prefix();
    $data = dtf_uv_editor_b2_api_json( $auth, 'b2_list_file_names', array( 'bucketId' => $settings['bucket_id'], 'prefix' => $prefix, 'maxFileCount' => 1000 ) );
    if ( is_wp_error( $data ) ) {
        dtf_uv_editor_b2_error( $data, 502 );
    }
    $items = array();
    $used_bytes = 0;
    foreach ( (array) ( $data['files'] ?? array() ) as $file ) {
        if ( ! is_array( $file ) || empty( $file['fileName'] ) || 'upload' !== ( $file['action'] ?? 'upload' ) ) {
            continue;
        }
        $content_type = strtolower( (string) ( $file['contentType'] ?? '' ) );
        if ( 0 !== strpos( $content_type, 'image/' ) ) {
            continue;
        }
        $used_bytes += absint( $file['contentLength'] ?? 0 );
        $url = dtf_uv_editor_b2_download_url( $auth, $file['fileName'], 3600 );
        if ( is_wp_error( $url ) ) {
            continue;
        }
        $items[] = array(
            'id'        => sanitize_text_field( $file['fileId'] ?? '' ),
            'fileName'  => sanitize_text_field( $file['fileName'] ),
            'name'      => sanitize_text_field( basename( $file['fileName'] ) ),
            'mime'      => $content_type,
            'size'      => absint( $file['contentLength'] ?? 0 ),
            'updatedAt' => absint( $file['uploadTimestamp'] ?? 0 ),
            'url'       => esc_url_raw( $url ),
        );
    }
    usort( $items, static function ( $a, $b ) { return ( $b['updatedAt'] ?? 0 ) <=> ( $a['updatedAt'] ?? 0 ); } );
    $quota_bytes = dtf_uv_editor_b2_gallery_quota_bytes();
    $free_bytes = max( 0, $quota_bytes - $used_bytes );
    wp_send_json_success( array( 'items' => $items, 'prefix' => $prefix, 'usage' => array( 'usedBytes' => $used_bytes, 'freeBytes' => $free_bytes, 'quotaBytes' => $quota_bytes, 'usedPercent' => $quota_bytes > 0 ? round( min( 100, ( $used_bytes / $quota_bytes ) * 100 ), 2 ) : 0 ) ) );
}
add_action( 'wp_ajax_dtf_uv_editor_b2_list', 'dtf_uv_editor_b2_list_endpoint' );

function dtf_uv_editor_b2_upload_endpoint() {
    check_ajax_referer( 'dtf_uv_editor_media', 'nonce' );
    if ( ! is_user_logged_in() ) {
        dtf_uv_editor_b2_error( 'Entre na sua conta para enviar imagens.', 401 );
    }
    if ( empty( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) || UPLOAD_ERR_OK !== (int) ( $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
        dtf_uv_editor_b2_error( 'Selecione uma imagem válida.', 400 );
    }
    $file = $_FILES['file'];
    $size = absint( $file['size'] ?? 0 );
    if ( $size < 1 || $size > 25 * MB_IN_BYTES ) {
        dtf_uv_editor_b2_error( 'Cada imagem deve ter até 25 MB.', 413 );
    }
    $mime = function_exists( 'wp_get_image_mime' ) ? wp_get_image_mime( $file['tmp_name'] ) : false;
    $allowed = array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif' );
    if ( ! $mime || ! in_array( $mime, $allowed, true ) ) {
        dtf_uv_editor_b2_error( 'Formato não permitido. Use JPEG, PNG, WebP, GIF ou AVIF.', 415 );
    }
    $auth = dtf_uv_editor_b2_authorize();
    if ( is_wp_error( $auth ) ) {
        dtf_uv_editor_b2_error( $auth, 503 );
    }
    $settings = dtf_uv_editor_b2_settings();
    $used_bytes = dtf_uv_editor_b2_gallery_used_bytes( $auth, $settings, dtf_uv_editor_b2_prefix() );
    if ( is_wp_error( $used_bytes ) ) {
        dtf_uv_editor_b2_error( $used_bytes, 502 );
    }
    $quota_bytes = dtf_uv_editor_b2_gallery_quota_bytes();
    if ( $used_bytes + $size > $quota_bytes ) {
        dtf_uv_editor_b2_error( sprintf( 'O limite da sua Galeria é de %s. Você ainda tem %s livres.', size_format( $quota_bytes ), size_format( max( 0, $quota_bytes - $used_bytes ) ) ), 413 );
    }
    $original_name = sanitize_file_name( $file['name'] ?? 'imagem' );
    $base_name = pathinfo( $original_name, PATHINFO_FILENAME );
    $extension = strtolower( pathinfo( $original_name, PATHINFO_EXTENSION ) );
    $extension = $extension ?: ( 'image/jpeg' === $mime ? 'jpg' : str_replace( 'image/', '', $mime ) );
    $file_name = dtf_uv_editor_b2_prefix() . gmdate( 'Ymd_His' ) . '_' . wp_generate_password( 8, false, false ) . '_' . sanitize_title( $base_name ?: 'imagem' ) . '.' . $extension;
    $upload = dtf_uv_editor_b2_api_json( $auth, 'b2_get_upload_url', array( 'bucketId' => $settings['bucket_id'] ) );
    if ( is_wp_error( $upload ) || empty( $upload['uploadUrl'] ) || empty( $upload['authorizationToken'] ) ) {
        dtf_uv_editor_b2_error( is_wp_error( $upload ) ? $upload : 'Não foi possível preparar o envio para o Backblaze B2.', 502 );
    }
    $body = file_get_contents( $file['tmp_name'] );
    $sha1 = sha1_file( $file['tmp_name'] );
    $response = wp_remote_post(
        $upload['uploadUrl'],
        array(
            'timeout' => 120,
            'headers' => array(
                'Authorization'                  => $upload['authorizationToken'],
                'X-Bz-File-Name'                 => rawurlencode( $file_name ),
                'Content-Type'                   => $mime,
                'Content-Length'                 => (string) $size,
                'X-Bz-Content-Sha1'              => $sha1,
                'X-Bz-Info-src_last_modified_millis' => (string) round( (float) ( filemtime( $file['tmp_name'] ) ?: time() ) * 1000 ),
            ),
            'body'    => $body,
        )
    );
    if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) < 200 || (int) wp_remote_retrieve_response_code( $response ) >= 300 ) {
        dtf_uv_editor_b2_error( is_wp_error( $response ) ? $response : 'O Backblaze B2 recusou o envio da imagem.', 502 );
    }
    $uploaded = json_decode( wp_remote_retrieve_body( $response ), true );
    $url = dtf_uv_editor_b2_download_url( $auth, $file_name, 3600 );
    if ( is_wp_error( $url ) ) {
        dtf_uv_editor_b2_error( $url, 502 );
    }
    wp_send_json_success( array( 'item' => array( 'id' => sanitize_text_field( $uploaded['fileId'] ?? '' ), 'fileName' => $file_name, 'name' => basename( $file_name ), 'mime' => $mime, 'size' => $size, 'updatedAt' => absint( $uploaded['uploadTimestamp'] ?? time() * 1000 ), 'url' => esc_url_raw( $url ) ) ) );
}
add_action( 'wp_ajax_dtf_uv_editor_b2_upload', 'dtf_uv_editor_b2_upload_endpoint' );

function dtf_uv_editor_b2_delete_endpoint() {
    check_ajax_referer( 'dtf_uv_editor_media', 'nonce' );
    if ( ! is_user_logged_in() ) {
        dtf_uv_editor_b2_error( 'Entre na sua conta para excluir imagens.', 401 );
    }
    $file_id = isset( $_POST['fileId'] ) ? sanitize_text_field( wp_unslash( $_POST['fileId'] ) ) : '';
    $file_name = isset( $_POST['fileName'] ) ? sanitize_text_field( wp_unslash( $_POST['fileName'] ) ) : '';
    if ( '' === $file_id || '' === $file_name || 0 !== strpos( $file_name, dtf_uv_editor_b2_prefix() ) ) {
        dtf_uv_editor_b2_error( 'Imagem da galeria inválida.', 400 );
    }
    $auth = dtf_uv_editor_b2_authorize();
    if ( is_wp_error( $auth ) ) {
        dtf_uv_editor_b2_error( $auth, 503 );
    }
    $data = dtf_uv_editor_b2_api_json( $auth, 'b2_delete_file_version', array( 'fileName' => $file_name, 'fileId' => $file_id ) );
    if ( is_wp_error( $data ) ) {
        dtf_uv_editor_b2_error( $data, 502 );
    }
    wp_send_json_success( array( 'deleted' => true ) );
}
add_action( 'wp_ajax_dtf_uv_editor_b2_delete', 'dtf_uv_editor_b2_delete_endpoint' );

function dtf_uv_editor_b2_download_endpoint() {
    check_ajax_referer( 'dtf_uv_editor_media', 'nonce' );
    if ( ! is_user_logged_in() ) {
        wp_die( 'Acesso não autorizado.', 401 );
    }
    $file_name = isset( $_POST['fileName'] ) ? sanitize_text_field( wp_unslash( $_POST['fileName'] ) ) : '';
    if ( '' === $file_name || 0 !== strpos( $file_name, dtf_uv_editor_b2_prefix() ) ) {
        wp_die( 'Imagem da galeria inválida.', 400 );
    }
    $auth = dtf_uv_editor_b2_authorize();
    if ( is_wp_error( $auth ) ) {
        wp_die( esc_html( $auth->get_error_message() ), 503 );
    }
    $url = dtf_uv_editor_b2_download_url( $auth, $file_name, 300 );
    if ( is_wp_error( $url ) ) {
        wp_die( esc_html( $url->get_error_message() ), 502 );
    }
    $response = wp_remote_get( $url, array( 'timeout' => 45, 'limit_response_size' => 25 * MB_IN_BYTES ) );
    if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
        wp_die( 'Não foi possível baixar a imagem da galeria.', 502 );
    }
    $mime = strtolower( trim( explode( ';', (string) wp_remote_retrieve_header( $response, 'content-type' ) )[0] ) );
    if ( 0 !== strpos( $mime, 'image/' ) ) {
        wp_die( 'O arquivo da galeria não é uma imagem.', 415 );
    }
    $body = wp_remote_retrieve_body( $response );
    nocache_headers();
    header( 'Content-Type: ' . $mime );
    header( 'Content-Length: ' . strlen( $body ) );
    echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Private B2 image bytes.
    exit;
}
add_action( 'wp_ajax_dtf_uv_editor_b2_download', 'dtf_uv_editor_b2_download_endpoint' );

function dtf_uv_editor_media_remote_json( $url, $headers = array() ) {
    $response = wp_safe_remote_get(
        $url,
        array(
            'timeout'     => 14,
            'redirection' => 0,
            'headers'     => $headers,
        )
    );

    if ( is_wp_error( $response ) ) {
        return $response;
    }
    if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
        return new WP_Error( 'remote_media_error', 'O banco de imagens não respondeu corretamente.' );
    }

    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    return is_array( $data ) ? $data : new WP_Error( 'remote_media_invalid', 'O banco de imagens devolveu uma resposta inválida.' );
}

function dtf_uv_editor_media_search_endpoint() {
    check_ajax_referer( 'dtf_uv_editor_media', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => 'Entre na sua conta para pesquisar desenhos.' ), 401 );
    }

    $query       = isset( $_POST['query'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['query'] ) ) ) : '';
    $category    = isset( $_POST['category'] ) ? sanitize_key( wp_unslash( $_POST['category'] ) ) : 'photos';
    $transparent = ! empty( $_POST['transparent'] );
    $page        = isset( $_POST['page'] ) ? max( 1, min( 100, absint( $_POST['page'] ) ) ) : 1;
    $categories  = array( 'photos', 'illustrations', 'vectors', 'cliparts' );

    if ( ! in_array( $category, $categories, true ) ) {
        $category = 'photos';
    }
    if ( '' === $query ) {
        wp_send_json_error( array( 'message' => 'Digite uma palavra para buscar.' ), 400 );
    }
    $query = function_exists( 'mb_substr' ) ? mb_substr( $query, 0, 100 ) : substr( $query, 0, 100 );

    $settings  = dtf_uv_editor_media_settings();
    if ( 'photos' === $category && empty( $settings['pexels_key'] ) && empty( $settings['pixabay_key'] ) ) {
        wp_send_json_error( array( 'message' => 'Cadastre uma chave do Pixabay ou do Pexels nas configurações do Editor de imagens.' ), 503 );
    }
    if ( 'photos' !== $category && empty( $settings['pixabay_key'] ) ) {
        wp_send_json_error( array( 'message' => 'Cadastre a chave da API Pixabay nas configurações do Editor de imagens para pesquisar esta biblioteca.' ), 503 );
    }

    $cache_key = 'dtf_uv_media_' . md5( wp_json_encode( array( $category, $query, $page, ! empty( $settings['pexels_key'] ), ! empty( $settings['pixabay_key'] ) ) ) );
    $cached    = get_transient( $cache_key );
    if ( is_array( $cached ) ) {
        wp_send_json_success( $cached );
    }

    $items    = array();
    $warnings = array();
    $data     = array();
    if ( 'photos' === $category && empty( $settings['pixabay_key'] ) && ! empty( $settings['pexels_key'] ) ) {
        if ( $transparent ) {
            wp_send_json_success( array( 'items' => array(), 'warnings' => array( 'O Pexels não oferece filtro de fundo transparente. Desmarque esse filtro para pesquisar fotos.' ) ) );
        }
        $url  = add_query_arg( array( 'query' => $query, 'locale' => 'pt-BR', 'per_page' => 36, 'page' => $page ), 'https://api.pexels.com/v1/search' );
        $data = dtf_uv_editor_media_remote_json( $url, array( 'Authorization' => $settings['pexels_key'] ) );
        if ( is_wp_error( $data ) ) {
            $warnings[] = 'Pexels indisponível neste momento.';
        } else {
        foreach ( array_slice( isset( $data['photos'] ) && is_array( $data['photos'] ) ? $data['photos'] : array(), 0, 36 ) as $photo ) {
            $src = isset( $photo['src'] ) && is_array( $photo['src'] ) ? $photo['src'] : array();
            $url = ! empty( $src['large2x'] ) ? $src['large2x'] : ( ! empty( $src['large'] ) ? $src['large'] : '' );
            if ( '' === $url || empty( $src['medium'] ) ) {
                continue;
            }
            $items[] = array(
                'id'        => 'pexels-' . absint( $photo['id'] ?? 0 ),
                'provider'  => 'Pexels',
                'title'     => sanitize_text_field( $photo['alt'] ?? 'Foto Pexels' ),
                'author'    => sanitize_text_field( $photo['photographer'] ?? 'Pexels' ),
                'page_url'  => esc_url_raw( $photo['url'] ?? 'https://www.pexels.com/' ),
                'thumbnail' => esc_url_raw( $src['medium'] ),
                'url'       => esc_url_raw( $url ),
            );
        }
        }
    } else {
        $image_type = 'photos' === $category ? 'photo' : ( 'illustrations' === $category ? 'illustration' : 'vector' );
        $url        = add_query_arg( array( 'key' => $settings['pixabay_key'], 'q' => $query, 'lang' => 'pt', 'image_type' => $image_type, 'safesearch' => 'true', 'per_page' => 36, 'page' => $page ), 'https://pixabay.com/api/' );
        $data       = dtf_uv_editor_media_remote_json( $url );
        if ( is_wp_error( $data ) ) {
            $warnings[] = 'Pixabay indisponível neste momento.';
        } else {
            foreach ( array_slice( isset( $data['hits'] ) && is_array( $data['hits'] ) ? $data['hits'] : array(), 0, 36 ) as $hit ) {
                if ( ! is_array( $hit ) || empty( $hit['webformatURL'] ) ) {
                    continue;
                }
                $items[] = array(
                    'id'        => 'pixabay-' . absint( $hit['id'] ?? 0 ),
                    'provider'  => 'Pixabay',
                    'title'     => sanitize_text_field( $hit['tags'] ?? 'Imagem Pixabay' ),
                    'author'    => sanitize_text_field( $hit['user'] ?? 'Pixabay' ),
                    'page_url'  => esc_url_raw( $hit['pageURL'] ?? 'https://pixabay.com/' ),
                    'thumbnail' => esc_url_raw( $hit['previewURL'] ?? $hit['webformatURL'] ),
                    'url'       => esc_url_raw( $hit['largeImageURL'] ?? $hit['webformatURL'] ),
                );
            }
        }
    }

    $payload = array(
        'items'    => $items,
        'warnings' => $warnings,
        'page'     => $page,
        'has_more' => is_array( $data ) && ( ! empty( $data['next_page'] ) || ( ! empty( $data['totalHits'] ) && ( $page * 36 ) < absint( $data['totalHits'] ) ) ),
    );
    set_transient( $cache_key, $payload, 5 * MINUTE_IN_SECONDS );
    wp_send_json_success( $payload );
}
add_action( 'wp_ajax_dtf_uv_editor_media_search', 'dtf_uv_editor_media_search_endpoint' );

/** Catálogo Lucide: entregue pelo próprio WordPress para não depender de CORS no navegador. */
function dtf_uv_editor_icon_catalog_endpoint() {
    check_ajax_referer( 'dtf_uv_editor_media', 'nonce' );
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => 'Entre na sua conta para abrir a biblioteca de ícones.' ), 401 );
    }
    $cache_key = 'dtf_uv_editor_lucide_catalog_v1';
    $catalog   = get_transient( $cache_key );
    if ( ! is_array( $catalog ) ) {
        $catalog = dtf_uv_editor_media_remote_json( 'https://api.iconify.design/lucide.json' );
        if ( is_wp_error( $catalog ) || empty( $catalog['icons'] ) || ! is_array( $catalog['icons'] ) ) {
            wp_send_json_error( array( 'message' => 'Não foi possível carregar o catálogo de ícones agora.' ), 502 );
        }
        set_transient( $cache_key, $catalog, 7 * DAY_IN_SECONDS );
    }
    wp_send_json_success( array( 'icons' => $catalog['icons'], 'width' => absint( $catalog['width'] ?? 24 ), 'height' => absint( $catalog['height'] ?? 24 ) ) );
}
add_action( 'wp_ajax_dtf_uv_editor_icon_catalog', 'dtf_uv_editor_icon_catalog_endpoint' );

function dtf_uv_editor_media_proxy_endpoint() {
    check_ajax_referer( 'dtf_uv_editor_media', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_die( 'Acesso não autorizado.', 401 );
    }
    $url   = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
    $parts = wp_parse_url( $url );
    $host  = strtolower( (string) ( $parts['host'] ?? '' ) );
    $allowed_hosts = array( 'images.pexels.com', 'pixabay.com', 'cdn.pixabay.com' );
    if ( empty( $parts['scheme'] ) || 'https' !== $parts['scheme'] || ! in_array( $host, $allowed_hosts, true ) ) {
        wp_die( 'Origem de imagem não permitida.', 400 );
    }

    $response = wp_safe_remote_get( $url, array( 'timeout' => 25, 'redirection' => 0, 'limit_response_size' => 18 * MB_IN_BYTES ) );
    if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
        wp_die( 'Não foi possível baixar a imagem escolhida.', 502 );
    }
    $mime = strtolower( trim( explode( ';', (string) wp_remote_retrieve_header( $response, 'content-type' ) )[0] ) );
    if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ), true ) ) {
        wp_die( 'O banco não devolveu uma imagem compatível.', 415 );
    }
    $body = wp_remote_retrieve_body( $response );
    if ( ! is_string( $body ) || '' === $body ) {
        wp_die( 'A imagem escolhida está vazia.', 502 );
    }

    nocache_headers();
    header( 'Content-Type: ' . $mime );
    header( 'Content-Length: ' . strlen( $body ) );
    echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary fetched from restricted image hosts.
    exit;
}
add_action( 'wp_ajax_dtf_uv_editor_media_proxy', 'dtf_uv_editor_media_proxy_endpoint' );

/**
 * Proxies the selected artwork to the private rembg service on the same VPS.
 * Keeping the container on 127.0.0.1 prevents the AI endpoint from being
 * exposed publicly. Override DTF_UV_REMBG_ENDPOINT in wp-config.php when the
 * container is hosted elsewhere on the private network.
 */
function dtf_uv_editor_magic_remove_endpoint() {
    check_ajax_referer( 'dtf_uv_editor_magic', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => 'Você precisa estar logado para usar a remoção inteligente.' ), 401 );
    }

    if ( empty( $_FILES['image'] ) || ! is_array( $_FILES['image'] ) ) {
        wp_send_json_error( array( 'message' => 'Nenhuma imagem foi recebida pela remoção inteligente.' ), 400 );
    }

    $upload = $_FILES['image'];
    $error  = isset( $upload['error'] ) ? (int) $upload['error'] : UPLOAD_ERR_NO_FILE;
    $size   = isset( $upload['size'] ) ? (int) $upload['size'] : 0;
    $tmp    = isset( $upload['tmp_name'] ) ? $upload['tmp_name'] : '';

    if ( UPLOAD_ERR_OK !== $error || ! is_uploaded_file( $tmp ) ) {
        wp_send_json_error( array( 'message' => 'A imagem não pôde ser preparada para a IA.' ), 400 );
    }
    if ( $size < 1 || $size > 25 * MB_IN_BYTES ) {
        wp_send_json_error( array( 'message' => 'A imagem deve ter no máximo 25 MB.' ), 413 );
    }

    $image_info = @getimagesize( $tmp );
    $mime       = is_array( $image_info ) && ! empty( $image_info['mime'] ) ? $image_info['mime'] : '';
    if ( ! in_array( $mime, array( 'image/png', 'image/jpeg', 'image/webp' ), true ) ) {
        wp_send_json_error( array( 'message' => 'Formato inválido. Envie PNG, JPEG ou WebP.' ), 415 );
    }
    if ( ! function_exists( 'curl_init' ) || ! class_exists( 'CURLFile' ) ) {
        wp_send_json_error( array( 'message' => 'O PHP cURL precisa estar habilitado para usar o Botão Mágico.' ), 503 );
    }

    $default_endpoint = defined( 'DTF_UV_REMBG_ENDPOINT' ) ? DTF_UV_REMBG_ENDPOINT : 'http://127.0.0.1:7000/api/remove';
    $endpoint         = apply_filters( 'dtf_uv_editor_rembg_endpoint', $default_endpoint );

    $request = curl_init( $endpoint );
    curl_setopt_array(
        $request,
        array(
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => array(
                'file'  => new CURLFile( $tmp, $mime, 'arte.png' ),
                'model' => 'birefnet-general',
                'dc'    => 'true',
            ),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 180,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER     => array( 'Accept: image/png' ),
        )
    );
    $body        = curl_exec( $request );
    $curl_error  = curl_error( $request );
    $status_code = (int) curl_getinfo( $request, CURLINFO_RESPONSE_CODE );
    curl_close( $request );

    if ( false === $body || $status_code < 200 || $status_code >= 300 ) {
        $message = $curl_error ? 'O serviço de IA não respondeu: ' . $curl_error : 'O serviço de IA retornou o código ' . $status_code . '.';
        wp_send_json_error( array( 'message' => $message ), 502 );
    }
    if ( ! is_string( $body ) || '' === $body || false === @getimagesizefromstring( $body ) ) {
        wp_send_json_error( array( 'message' => 'A IA respondeu, mas não devolveu uma imagem válida.' ), 502 );
    }

    nocache_headers();
    header( 'Content-Type: image/png' );
    header( 'Content-Length: ' . strlen( $body ) );
    echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- PNG binary validated above.
    wp_die();
}
add_action( 'wp_ajax_dtf_uv_editor_magic_remove', 'dtf_uv_editor_magic_remove_endpoint' );
add_action( 'wp_ajax_nopriv_dtf_uv_editor_magic_remove', 'dtf_uv_editor_magic_remove_endpoint' );

function dtf_uv_editor_register_assets() {
    wp_register_style(
        'editor-de-imagens',
        DTF_UV_EDITOR_URL . 'assets/css/editor.css',
        array(),
        dtf_uv_editor_asset_version( 'assets/css/editor.css' )
    );

    // The editor already has a fallback loader for PDF.js, but registering it
    // as a WordPress dependency makes the normal path deterministic.
    wp_register_script(
        'dtf-uv-pdfjs',
        'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js',
        array(),
        '3.11.174',
        true
    );

    wp_register_script(
        'dtf-uv-pako',
        DTF_UV_EDITOR_URL . 'assets/vendor/pako.min.js',
        array(),
        '1.0.11',
        true
    );

    wp_register_script(
        'dtf-uv-utif',
        DTF_UV_EDITOR_URL . 'assets/vendor/UTIF.min.js',
        array( 'dtf-uv-pako' ),
        '3.1.0',
        true
    );

    wp_register_script(
        'editor-de-imagens',
        DTF_UV_EDITOR_URL . 'assets/js/editor.js',
        array( 'dtf-uv-utif' ),
        dtf_uv_editor_asset_version( 'assets/js/editor.js' ),
        true
    );
}
add_action( 'wp_enqueue_scripts', 'dtf_uv_editor_register_assets' );
/* No administrador os arquivos precisam ser registrados antes de serem
 * enfileirados pela tela do editor. */
add_action( 'admin_enqueue_scripts', 'dtf_uv_editor_register_assets', 1 );


/**
 * Front-end access gate. The editor must only load for logged-in users.
 */
function dtf_uv_editor_login_required_message() {
    $current_url  = is_singular() ? get_permalink() : ( is_ssl() ? 'https://' : 'http://' ) . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ?? '' ) ) . sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
    $login_url    = wp_login_url( $current_url );
    $register_url = wp_registration_url();

    ob_start();
    ?>
    <div class="dtf-uv-login-required" style="max-width:720px;margin:24px auto;padding:22px 24px;border:1px solid #d7dbe0;border-left:4px solid #963d00;border-radius:10px;background:#fff;box-shadow:0 2px 10px rgba(0,0,0,.06);font-family:system-ui,-apple-system,Segoe UI,Arial,sans-serif;color:#26323f;">
        <h2 style="margin:0 0 8px;font-size:22px;line-height:1.2;color:#963d00;">Acesso restrito</h2>
        <p style="margin:0 0 16px;font-size:15px;line-height:1.5;">Para usar o Editor de imagens, entre na sua conta ou crie uma conta antes de continuar.</p>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="<?php echo esc_url( $login_url ); ?>" style="display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:0 16px;border-radius:7px;background:#963d00;color:#fff;text-decoration:none;font-weight:700;">Entrar</a>
            <a href="<?php echo esc_url( $register_url ); ?>" style="display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:0 16px;border:1px solid #963d00;border-radius:7px;background:#fff;color:#963d00;text-decoration:none;font-weight:700;">Criar conta</a>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render the editor through a shortcode.
 *
 * Use in Elementor with a Shortcode widget:
 * [dtf_uv_editor]
 */
function dtf_uv_editor_shortcode( $atts = array(), $content = null ) {
    if ( ! is_user_logged_in() ) {
        return dtf_uv_editor_login_required_message();
    }

    dtf_uv_editor_register_assets();
    wp_enqueue_style( 'editor-de-imagens' );
    wp_enqueue_script( 'editor-de-imagens' );

    ob_start();
    include DTF_UV_EDITOR_DIR . 'views/editor.php';
    return ob_get_clean();
}
/**
 * Registra o shortcode no momento padrão do WordPress. Quando o módulo for
 * incluído depois de init (por exemplo, em uma atualização), registra também
 * imediatamente para a requisição atual.
 */
function dtf_uv_editor_register_shortcode() {
    add_shortcode( 'dtf_uv_editor', 'dtf_uv_editor_shortcode' );
}
add_action( 'init', 'dtf_uv_editor_register_shortcode', 20 );
if ( did_action( 'init' ) ) {
    dtf_uv_editor_register_shortcode();
}


/**
 * WordPress admin integration.
 *
 * When the main PrintWay plugin is active, the editor appears as:
 * PrintWay -> Editor de imagens
 *
 * If the PrintWay parent menu is not available, the editor falls back to its
 * own top-level menu so the application is still accessible from wp-admin.
 */
function dtf_uv_editor_has_printway_parent_menu() {
    global $menu;

    if ( ! is_array( $menu ) ) {
        return false;
    }

    foreach ( $menu as $item ) {
        if ( ! empty( $item[2] ) && 'pw-printway' === $item[2] ) {
            return true;
        }
    }

    return false;
}

function dtf_uv_editor_admin_menu() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( dtf_uv_editor_has_printway_parent_menu() ) {
        add_submenu_page(
            'pw-printway',
            'Editor de imagens',
            'Editor de imagens',
            'manage_options',
            'dtf-uv-editor',
            'dtf_uv_editor_render_admin_page'
        );
        return;
    }

    add_menu_page(
        'Editor de imagens',
        'Editor de imagens',
        'manage_options',
        'dtf-uv-editor',
        'dtf_uv_editor_render_admin_page',
        'dashicons-format-image',
        57
    );
}
add_action( 'admin_menu', 'dtf_uv_editor_admin_menu', 20 );

function dtf_uv_editor_admin_assets( $hook_suffix ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $is_editor_page = isset( $_GET['page'] ) && 'dtf-uv-editor' === sanitize_key( wp_unslash( $_GET['page'] ) );
    if ( ! $is_editor_page ) {
        return;
    }

    wp_enqueue_style( 'editor-de-imagens' );
    wp_enqueue_script( 'dtf-uv-pdfjs' );
    wp_enqueue_script( 'editor-de-imagens' );

    /*
     * The editor is intentionally scoped inside this wrapper. This small
     * normalization prevents wp-admin's generic styles from changing buttons
     * and form controls without touching the rest of the dashboard.
     */
    wp_add_inline_style(
        'editor-de-imagens',
        '
        #dtf-uv-editor-admin .button,
        #dtf-uv-editor-admin input,
        #dtf-uv-editor-admin select,
        #dtf-uv-editor-admin button { box-sizing: border-box; }
        #dtf-uv-editor-admin { margin-right: 20px; }
        #dtf-uv-editor-admin .notice,
        #dtf-uv-editor-admin .updated,
        #dtf-uv-editor-admin .error { display: none; }
        '
    );
}
add_action( 'admin_enqueue_scripts', 'dtf_uv_editor_admin_assets' );

function dtf_uv_editor_render_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Você não tem permissão para acessar este editor.', 'editor-de-imagens' ) );
    }

    echo '<div class="wrap" id="dtf-uv-editor-admin">';
    echo '<div style="display:flex;align-items:center;justify-content:space-between;gap:16px;margin:10px 0 14px;">';
    echo '<div><h1 style="margin:0;">Editor de imagens</h1><p style="margin:5px 0 0;color:#666;">Montagem e preparação de artes para DTF UV. <strong>Versão ' . esc_html( DTF_UV_EDITOR_VERSION ) . '</strong></p></div>';
    echo '</div>';

    echo '<div class="dtf-uv-shortcode-box" style="max-width:900px;margin:0 0 18px;padding:18px 20px;border:1px solid #c3c4c7;border-left:4px solid #963d00;border-radius:8px;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.04);">';
    echo '<h2 style="margin:0 0 8px;font-size:18px;">Código para colocar na página</h2>';
    echo '<p style="margin:0 0 12px;color:#50575e;">Copie o shortcode abaixo e cole em um bloco <strong>Shortcode</strong> do WordPress ou no widget <strong>Shortcode</strong> do Elementor:</p>';
    echo '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">';
    echo '<input id="dtf-uv-shortcode" type="text" readonly value="[dtf_uv_editor]" aria-label="Shortcode do Editor de imagens" style="width:260px;max-width:100%;padding:9px 12px;font-family:monospace;font-size:16px;">';
    echo '<button id="dtf-uv-copy-shortcode" type="button" class="button button-primary">Copiar código</button>';
    echo '<span id="dtf-uv-copy-status" role="status" aria-live="polite" style="font-weight:600;color:#2271b1;"></span>';
    echo '</div>';
    $shortcode_status = shortcode_exists( 'dtf_uv_editor' ) ? 'Shortcode ativo.' : 'Shortcode aguardando inicialização.';
    echo '<p style="margin:10px 0 0;color:#646970;font-size:13px;">Depois de publicar a página, o conteúdo completo do aplicativo será exibido nesse local. <strong style="color:#16803a;">' . esc_html( $shortcode_status ) . '</strong></p>';
    echo '</div>';

    echo '<script>(function(){var button=document.getElementById("dtf-uv-copy-shortcode"),field=document.getElementById("dtf-uv-shortcode"),status=document.getElementById("dtf-uv-copy-status");if(!button||!field){return;}button.addEventListener("click",function(){function done(){if(status){status.textContent="Código copiado!";}button.textContent="Copiado";setTimeout(function(){button.textContent="Copiar código";if(status){status.textContent="";}},2000);}if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(field.value).then(done).catch(function(){field.select();document.execCommand("copy");done();});}else{field.select();document.execCommand("copy");done();}});}());</script>';

    echo '<div style="max-width:900px;margin:0 0 18px;padding:18px 20px;border:1px solid #c3c4c7;border-left:4px solid #087eae;border-radius:8px;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.04);">';
    echo '<h2 style="margin:0 0 8px;font-size:18px;">Biblioteca de imagem</h2>';
    echo '<p style="margin:0 0 14px;color:#50575e;">As chaves ficam apenas neste WordPress e nunca são enviadas ao navegador. Pixabay ativa Fotos, Ilustrações, Vetores e Cliparts; Pexels fica como alternativa para Fotos.</p>';
    $media_settings = dtf_uv_editor_media_settings();
    $pexels_saved   = ! empty( $media_settings['pexels_key'] );
    $pixabay_saved  = ! empty( $media_settings['pixabay_key'] );
    $b2_key_saved   = ! empty( $media_settings['b2_key_id'] );
    $b2_app_saved   = ! empty( $media_settings['b2_application_key'] );
    $b2_bucket_saved = ! empty( $media_settings['b2_bucket_id'] ) && ! empty( $media_settings['b2_bucket_name'] );
    $pexels_hint    = $pexels_saved ? '•••••••••• Chave já salva' : 'Cole a chave do Pexels';
    $pixabay_hint   = $pixabay_saved ? '•••••••••• Chave já salva' : 'Cole a chave do Pixabay';
    echo '<div style="max-width:430px;">';
    echo '<form method="post" action="options.php" style="margin:0;padding:14px;border:1px solid #d8e1e8;border-radius:7px;background:#fbfdff;">';
    settings_fields( 'dtf_uv_editor_media' );
    echo '<h3 style="margin:0 0 8px;font-size:15px;">Pexels</h3><label for="dtf-uv-pexels-key" style="display:block;font-weight:600;margin-bottom:5px;">Chave API</label><input id="dtf-uv-pexels-key" name="dtf_uv_editor_media_settings[pexels_key]" type="password" value="" placeholder="' . esc_attr( $pexels_hint ) . '" autocomplete="off" class="regular-text" style="width:100%;"><p class="description">' . ( $pexels_saved ? '<strong style="color:#16803a;">Chave já salva.</strong> Deixe vazio para mantê-la ou digite outra para substituir. ' : '<strong style="color:#8a5600;">Nenhuma chave salva.</strong> ' ) . 'Alternativa para Fotos quando não houver chave do Pixabay. <a href="https://www.pexels.com/api/" target="_blank" rel="noopener noreferrer">Criar chave gratuita no Pexels</a>.</p>';
    echo '<hr style="margin:16px 0;border:0;border-top:1px solid #d8e1e8;"><h3 style="margin:0 0 8px;font-size:15px;">Pixabay</h3><label for="dtf-uv-pixabay-key" style="display:block;font-weight:600;margin-bottom:5px;">Chave API</label><input id="dtf-uv-pixabay-key" name="dtf_uv_editor_media_settings[pixabay_key]" type="password" value="" placeholder="' . esc_attr( $pixabay_hint ) . '" autocomplete="off" class="regular-text" style="width:100%;"><p class="description">' . ( $pixabay_saved ? '<strong style="color:#16803a;">Chave já salva.</strong> Deixe vazio para mantê-la ou digite outra para substituir. ' : '<strong style="color:#8a5600;">Nenhuma chave salva.</strong> ' ) . 'Ilustrações, vetores e cliparts. <a href="https://pixabay.com/api/docs/" target="_blank" rel="noopener noreferrer">Criar chave gratuita no Pixabay</a>.</p>';
    echo '<hr style="margin:16px 0;border:0;border-top:1px solid #d8e1e8;"><h3 style="margin:0 0 8px;font-size:15px;">Backblaze B2 — Galeria dos usuários</h3><p class="description" style="margin:0 0 12px;">Crie um bucket <strong>privado</strong> no Backblaze B2 e uma Application Key com acesso de leitura e gravação nesse bucket, incluindo as permissões de listar, excluir e compartilhar arquivos. As credenciais ficam somente no servidor.</p><label for="dtf-uv-b2-key-id" style="display:block;font-weight:600;margin-bottom:5px;">Key ID</label><input id="dtf-uv-b2-key-id" name="dtf_uv_editor_media_settings[b2_key_id]" type="text" value="" placeholder="' . esc_attr( $b2_key_saved ? '•••••••••• Key ID já salvo' : 'Application Key ID' ) . '" autocomplete="off" class="regular-text" style="width:100%;"><p class="description">' . ( $b2_key_saved ? '<strong style="color:#16803a;">Key ID já salvo.</strong> Deixe vazio para manter.' : '<strong style="color:#8a5600;">Nenhum Key ID salvo.</strong>' ) . '</p><label for="dtf-uv-b2-app-key" style="display:block;font-weight:600;margin:10px 0 5px;">Application Key</label><input id="dtf-uv-b2-app-key" name="dtf_uv_editor_media_settings[b2_application_key]" type="password" value="" placeholder="' . esc_attr( $b2_app_saved ? '•••••••••• Application Key já salva' : 'Application Key' ) . '" autocomplete="new-password" class="regular-text" style="width:100%;"><p class="description">' . ( $b2_app_saved ? '<strong style="color:#16803a;">Application Key já salva.</strong> Deixe vazio para manter.' : '<strong style="color:#8a5600;">Nenhuma Application Key salva.</strong>' ) . '</p><label for="dtf-uv-b2-bucket-id" style="display:block;font-weight:600;margin:10px 0 5px;">Bucket ID</label><input id="dtf-uv-b2-bucket-id" name="dtf_uv_editor_media_settings[b2_bucket_id]" type="text" value="" placeholder="ID do bucket" autocomplete="off" class="regular-text" style="width:100%;"><label for="dtf-uv-b2-bucket-name" style="display:block;font-weight:600;margin:10px 0 5px;">Nome do bucket</label><input id="dtf-uv-b2-bucket-name" name="dtf_uv_editor_media_settings[b2_bucket_name]" type="text" value="" placeholder="Nome exato do bucket" autocomplete="off" class="regular-text" style="width:100%;"><p class="description">' . ( $b2_bucket_saved ? '<strong style="color:#16803a;">Bucket já configurado.</strong> Deixe os campos vazios para manter.' : '<strong style="color:#8a5600;">Bucket ainda não configurado.</strong>' ) . ' Cada usuário terá uma pasta privada própria dentro do bucket.</p>';
    submit_button( 'Salvar configurações das bibliotecas e da Galeria', 'primary', 'submit', false );
    echo '</form></div></div>';

    include DTF_UV_EDITOR_DIR . 'views/editor.php';

    echo '</div>';
}

/**
 * Add a small Settings > Plugins description for quick discovery.
 */
function dtf_uv_editor_plugin_action_links( $links ) {
    if ( current_user_can( 'manage_options' ) ) {
        $links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=dtf-uv-editor' ) ) . '">Abrir editor</a>';
    }
    $links[] = '<span style="color:#666">Shortcode: <code>[dtf_uv_editor]</code></span>';
    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'dtf_uv_editor_plugin_action_links' );
} // Declarações condicionais: permite inclusão repetida sem interromper a primeira carga.
