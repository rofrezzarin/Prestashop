<?php
/**
 * Plugin Name: Editor de imagens
 * Description: Editor de imagens para preparação de artes DTF UV, com PNG/PDF, remoção de fundo, montagem de objetos, borracha e exportações.
 * Version: 1.0.211
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
    define( 'DTF_UV_EDITOR_VERSION', '1.0.211' );
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

/**
 * Proxies the selected artwork to the private rembg service on the same VPS.
 * Keeping the container on 127.0.0.1 prevents the AI endpoint from being
 * exposed publicly. Override DTF_UV_REMBG_ENDPOINT in wp-config.php when the
 * container is hosted elsewhere on the private network.
 */
function dtf_uv_editor_magic_remove_endpoint() {
    check_ajax_referer( 'dtf_uv_editor_magic', 'nonce' );

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
 * Render the editor through a shortcode.
 *
 * Use in Elementor with a Shortcode widget:
 * [dtf_uv_editor]
 */
function dtf_uv_editor_shortcode( $atts = array(), $content = null ) {
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
