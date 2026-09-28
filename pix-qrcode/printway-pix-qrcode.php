<?php
/*
Módulo: PrintWay Pix QRCode
Description: Geração de QR Code Pix dinâmico para WooCommerce e páginas personalizadas.
Version: 2.3.0
Author: Rodrigo
Text Domain: printway-pix-qrcode
*/

if (!defined('ABSPATH')) {
    exit;
}

/* =========================================================
 * CONSTANTES
 * ========================================================= */

define('PRINTWAY_PIX_OPTION_LOGS', 'printway_pix_logs');
define('PRINTWAY_PIX_OPTION_CHAVE', 'printway_pix_chave');
define('PRINTWAY_PIX_OPTION_KEY_TYPE', 'printway_pix_key_type');
define('PRINTWAY_PIX_OPTION_NOME', 'printway_pix_nome');
define('PRINTWAY_PIX_OPTION_MERCHANT_CITY', 'printway_pix_merchant_city');


/* =========================================================
 * FUNÇÕES AUXILIARES
 * ========================================================= */

/**
 * Comprimento em bytes.
 */
function pw_bytes_len($str) {
    return strlen((string) $str);
}


/**
 * Campo EMV:
 * ID + tamanho + valor
 */
function pw_emv_field($id, $value) {

    $value = (string) $value;

    return $id .
        str_pad(
            pw_bytes_len($value),
            2,
            '0',
            STR_PAD_LEFT
        ) .
        $value;
}


/**
 * CRC16 CCITT-FALSE
 */
function pw_crc16_ccitt_false($payload) {

    $crc  = 0xFFFF;
    $poly = 0x1021;

    $bytes = unpack('C*', $payload);

    foreach ($bytes as $byte) {

        $crc ^= ($byte << 8);

        for ($i = 0; $i < 8; $i++) {

            if ($crc & 0x8000) {

                $crc = (($crc << 1) ^ $poly) & 0xFFFF;

            } else {

                $crc = ($crc << 1) & 0xFFFF;

            }
        }
    }

    return strtoupper(
        str_pad(
            dechex($crc),
            4,
            '0',
            STR_PAD_LEFT
        )
    );
}


/**
 * Normaliza textos para EMV.
 */
function pw_sanitize_text_for_emv($text) {

    $text = (string) $text;

    $text = preg_replace('/[\x00-\x1F\x7F]/', '', $text);

    $text = preg_replace('/\s+/', ' ', $text);

    $text = trim($text);

    if (function_exists('iconv')) {

        $converted = @iconv(
            'UTF-8',
            'ASCII//TRANSLIT//IGNORE',
            $text
        );

        if ($converted !== false) {
            $text = $converted;
        }
    }

    $text = strtoupper($text);

    $text = preg_replace('/[^A-Z0-9 .,\-\/&]/', '', $text);

    return $text;
}


/**
 * Normaliza chave Pix.
 */
function pw_normalize_chave($chave) {

    $chave = trim((string) $chave);

    if ($chave === '') {
        return '';
    }

    /*
     * Remove espaços acidentais.
     */
    $chave = preg_replace('/\s+/', '', $chave);

    if (strpos($chave, '@') !== false) {
        return strtolower($chave);
    }

    /*
     * Chave Pix do tipo telefone usa o formato E.164. O sinal de +
     * antes do código do país faz parte da chave registrada no DICT.
     */
    $digits = preg_replace('/\D+/', '', $chave);
    if (preg_match('/^55\d{10,11}$/', $digits)) {
        return '+' . $digits;
    }

    return $chave;
}


/**
 * Tipos de chave aceitos pelo Pix.
 */
function pw_pix_key_types() {

    return array(
        'phone' => 'Telefone',
        'cpf'   => 'CPF',
        'cnpj'  => 'CNPJ',
        'email' => 'E-mail',
        'evp'   => 'Chave aleatória'
    );
}


/**
 * Detecta o tipo de uma chave já configurada.
 * Mantém compatibilidade com versões anteriores do plugin.
 */
function pw_detect_pix_key_type($chave) {

    $raw = trim((string) $chave);

    if (strpos($raw, '@') !== false) {
        return 'email';
    }

    if (
        preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $raw
        )
    ) {
        return 'evp';
    }

    $digits = preg_replace('/\D+/', '', $raw);

    if (
        strpos($raw, '+') === 0 ||
        strpos($raw, '(') !== false ||
        preg_match('/^55\d{10,11}$/', $digits) ||
        strlen($digits) === 10
    ) {
        return 'phone';
    }

    if (strlen($digits) === 14) {
        return 'cnpj';
    }

    if (strlen($digits) === 11) {
        return 'cpf';
    }

    return '';
}


/**
 * Valida os dígitos verificadores de um CPF.
 */
function pw_is_valid_cpf($cpf) {

    $cpf = preg_replace('/\D+/', '', (string) $cpf);

    if (
        strlen($cpf) !== 11 ||
        preg_match('/^(\d)\1{10}$/', $cpf)
    ) {
        return false;
    }

    for ($digit = 9; $digit < 11; $digit++) {

        $sum = 0;

        for ($index = 0; $index < $digit; $index++) {
            $sum += ((int) $cpf[$index]) * (($digit + 1) - $index);
        }

        $check = (10 * $sum) % 11;

        if ($check === 10) {
            $check = 0;
        }

        if ($check !== (int) $cpf[$digit]) {
            return false;
        }
    }

    return true;
}


/**
 * Valida os dígitos verificadores de um CNPJ.
 */
function pw_is_valid_cnpj($cnpj) {

    $cnpj = preg_replace('/\D+/', '', (string) $cnpj);

    if (
        strlen($cnpj) !== 14 ||
        preg_match('/^(\d)\1{13}$/', $cnpj)
    ) {
        return false;
    }

    $weights = array(
        array(5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2),
        array(6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2)
    );

    for ($digit = 0; $digit < 2; $digit++) {

        $sum = 0;

        foreach ($weights[$digit] as $index => $weight) {
            $sum += ((int) $cnpj[$index]) * $weight;
        }

        $remainder = $sum % 11;
        $check = $remainder < 2 ? 0 : 11 - $remainder;
        $position = 12 + $digit;

        if ($check !== (int) $cnpj[$position]) {
            return false;
        }
    }

    return true;
}


/**
 * Valida e devolve a chave no formato exato esperado pelo Pix.
 */
function pw_validate_and_normalize_pix_key($chave, $type = '') {

    $raw = trim((string) $chave);

    if ($raw === '') {
        return new WP_Error(
            'empty_pix_key',
            'Informe a chave Pix.'
        );
    }

    $types = pw_pix_key_types();
    $type = sanitize_key((string) $type);

    if (!isset($types[$type])) {
        $type = pw_detect_pix_key_type($raw);
    }

    if (!isset($types[$type])) {
        return new WP_Error(
            'unknown_pix_key_type',
            'Selecione o tipo correto da chave Pix.'
        );
    }

    if ($type === 'email') {

        $email = strtolower($raw);

        if (!is_email($email)) {
            return new WP_Error(
                'invalid_pix_email',
                'A chave Pix informada não é um e-mail válido.'
            );
        }

        if (pw_bytes_len($email) > 77) {
            return new WP_Error(
                'pix_email_too_long',
                'O e-mail usado como chave Pix ultrapassa 77 caracteres.'
            );
        }

        return $email;
    }

    if ($type === 'evp') {

        $evp = strtolower($raw);

        if (
            !preg_match(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
                $evp
            )
        ) {
            return new WP_Error(
                'invalid_pix_evp',
                'A chave aleatória deve ser um UUID válido, com 36 caracteres.'
            );
        }

        return $evp;
    }

    $digits = preg_replace('/\D+/', '', $raw);

    if ($type === 'cpf') {

        if (!pw_is_valid_cpf($digits)) {
            return new WP_Error(
                'invalid_pix_cpf',
                'O CPF informado como chave Pix é inválido.'
            );
        }

        return $digits;
    }

    if ($type === 'cnpj') {

        if (!pw_is_valid_cnpj($digits)) {
            return new WP_Error(
                'invalid_pix_cnpj',
                'O CNPJ informado como chave Pix é inválido.'
            );
        }

        return $digits;
    }

    if (strpos($digits, '00') === 0) {
        $digits = substr($digits, 2);
    }

    if (strlen($digits) === 10 || strlen($digits) === 11) {
        $digits = '55' . $digits;
    }

    if (!preg_match('/^55[1-9]\d{9,10}$/', $digits)) {
        return new WP_Error(
            'invalid_pix_phone',
            'Informe o telefone Pix com DDD. O sistema salvará no formato +55DDDNÚMERO.'
        );
    }

    return '+' . $digits;
}


/**
 * Nome do recebedor.
 */
function pw_normalize_name($nome) {

    $nome = pw_sanitize_text_for_emv($nome);

    /*
     * Máximo 25 bytes.
     */
    while (pw_bytes_len($nome) > 25) {

        if (function_exists('mb_substr')) {

            $nome = mb_substr(
                $nome,
                0,
                mb_strlen($nome, 'UTF-8') - 1,
                'UTF-8'
            );

        } else {

            $nome = substr($nome, 0, -1);
        }
    }

    return $nome;
}


/**
 * Cidade.
 */
function pw_normalize_city($city) {

    $city = pw_sanitize_text_for_emv($city);

    /*
     * Máximo 15 bytes.
     */
    while (pw_bytes_len($city) > 15) {

        if (function_exists('mb_substr')) {

            $city = mb_substr(
                $city,
                0,
                mb_strlen($city, 'UTF-8') - 1,
                'UTF-8'
            );

        } else {

            $city = substr($city, 0, -1);
        }
    }

    return $city;
}


/**
 * Normaliza valor monetário.
 *
 * Aceita:
 *
 * 1
 * 1.4
 * 1.44
 * 1,44
 * R$ 1,44
 * R$ 1.234,56
 * 1.234,56
 * 1,234.56
 */
function pw_normalize_amount($amount) {

    if ($amount === null) {
        return null;
    }

    $amount = trim((string) $amount);

    if ($amount === '') {
        return null;
    }

    /*
     * Remove espaços.
     */
    $amount = preg_replace('/\s+/', '', $amount);

    /*
     * Remove moeda.
     */
    $amount = preg_replace('/[^\d,\.\-]/', '', $amount);

    if ($amount === '') {
        return null;
    }

    /*
     * Não permitir negativo.
     */
    $amount = str_replace('-', '', $amount);

    /*
     * Caso tenha ponto e vírgula:
     *
     * 1.234,56
     *
     * assume padrão brasileiro.
     */
    if (
        strpos($amount, '.') !== false &&
        strpos($amount, ',') !== false
    ) {

        $lastComma = strrpos($amount, ',');
        $lastDot   = strrpos($amount, '.');

        if ($lastComma > $lastDot) {

            /*
             * Brasileiro:
             * 1.234,56
             */
            $amount = str_replace('.', '', $amount);
            $amount = str_replace(',', '.', $amount);

        } else {

            /*
             * Internacional:
             * 1,234.56
             */
            $amount = str_replace(',', '', $amount);
        }

    } elseif (strpos($amount, ',') !== false) {

        /*
         * 1,44
         */
        $amount = str_replace(',', '.', $amount);

    } else {

        /*
         * Aqui temos somente ponto ou números.
         *
         * 1.44
         *
         * permanece.
         */
    }

    if (!is_numeric($amount)) {
        return null;
    }

    $number = (float) $amount;

    if ($number < 0) {
        return null;
    }

    return number_format(
        $number,
        2,
        '.',
        ''
    );
}


/* =========================================================
 * GERAÇÃO DO PIX
 * ========================================================= */

function pw_build_pix_payload_server(
    $chave_raw,
    $nome_raw,
    $amount_raw = null,
    $merchantCity_raw = '',
    $key_type = ''
) {

    $errors   = array();
    $warnings = array();

    /*
     * Chave
     */
    if ($key_type === '') {
        $key_type = get_option(
            PRINTWAY_PIX_OPTION_KEY_TYPE,
            ''
        );
    }

    $chave_result = pw_validate_and_normalize_pix_key(
        $chave_raw,
        $key_type
    );

    if (is_wp_error($chave_result)) {
        $errors[] = $chave_result->get_error_message();
        $chave = '';
    } else {
        $chave = $chave_result;
    }

    /*
     * Nome
     */
    $nome = pw_normalize_name($nome_raw);

    if ($nome === '') {
        $errors[] = 'Nome do recebedor não configurado.';
    }

    /*
     * Cidade
     */
    if ($merchantCity_raw !== '') {

        $merchantCity = pw_normalize_city($merchantCity_raw);

    } else {

        $merchantCity = pw_normalize_city(
            get_option(
                PRINTWAY_PIX_OPTION_MERCHANT_CITY,
                'SAO PAULO'
            )
        );
    }

    if ($merchantCity === '') {
        $errors[] = 'Cidade do recebedor não configurada.';
    }

    /*
     * Valor
     */
    $amount = null;

    if ($amount_raw !== null) {

        $amount = pw_normalize_amount($amount_raw);

        if ($amount === null || (float) $amount <= 0) {

            $errors[] =
                'O valor do Pix deve ser maior que zero.';

        }
    }

    if (!empty($errors)) {

        return array(
            'success'  => false,
            'errors'   => $errors,
            'warnings' => $warnings
        );
    }


    /*
     * =====================================================
     * MERCHANT ACCOUNT INFORMATION
     * =====================================================
     *
     * 26
     *   00 = BR.GOV.BCB.PIX
     *   01 = chave Pix
     *
     * Não colocamos o nome dentro do campo 26.
     */

    $merchantAccount =
        pw_emv_field(
            '00',
            'BR.GOV.BCB.PIX'
        ) .
        pw_emv_field(
            '01',
            $chave
        );


    /*
     * =====================================================
     * PAYLOAD
     * =====================================================
     */

    $payload = '';

    /*
     * Payload Format Indicator
     */
    $payload .= pw_emv_field(
        '00',
        '01'
    );

    /*
     * Merchant Account Information
     */
    $payload .= pw_emv_field(
        '26',
        $merchantAccount
    );

    /*
     * MCC
     */
    $payload .= pw_emv_field(
        '52',
        '0000'
    );

    /*
     * Moeda BRL
     */
    $payload .= pw_emv_field(
        '53',
        '986'
    );

    /*
     * Valor
     */
    if ($amount !== null) {

        $payload .= pw_emv_field(
            '54',
            $amount
        );
    }

    /*
     * País
     */
    $payload .= pw_emv_field(
        '58',
        'BR'
    );

    /*
     * Nome do recebedor
     */
    $payload .= pw_emv_field(
        '59',
        $nome
    );

    /*
     * Cidade
     */
    $payload .= pw_emv_field(
        '60',
        $merchantCity
    );

    /*
     * Additional Data Field Template
     *
     * 05 = Reference Label
     * *** = txid
     */
    $additionalData =
        pw_emv_field(
            '05',
            '***'
        );

    $payload .= pw_emv_field(
        '62',
        $additionalData
    );


    /*
     * =====================================================
     * CRC
     * =====================================================
     */

    $payload_for_crc =
        $payload . '6304';

    $crc = pw_crc16_ccitt_false(
        $payload_for_crc
    );

    $payload .=
        '6304' .
        $crc;


    return array(
        'success'  => true,
        'payload'  => $payload,
        'warnings' => $warnings
    );
}


/* =========================================================
 * AJAX
 * ========================================================= */

add_action(
    'wp_ajax_printway_pix_generate_payload',
    'pw_ajax_generate_payload'
);

add_action(
    'wp_ajax_nopriv_printway_pix_generate_payload',
    'pw_ajax_generate_payload'
);


function pw_ajax_generate_payload() {

    /*
     * O endpoint é público porque o QR Code também é
     * utilizado em páginas públicas.
     *
     * Ainda aceitamos nonce quando enviado.
     */
    if (
        isset($_POST['nonce']) &&
        $_POST['nonce'] !== ''
    ) {

        $nonce = sanitize_text_field(
            wp_unslash($_POST['nonce'])
        );

        if (
            !wp_verify_nonce(
                $nonce,
                'printway_pix_public'
            ) &&
            !wp_verify_nonce(
                $nonce,
                'printway_pix_nonce'
            )
        ) {

            wp_send_json(
                array(
                    'success' => false,
                    'errors' => array(
                        'Nonce inválido ou expirado. Atualize a página e tente novamente.'
                    )
                )
            );
        }
    }


    /*
     * Chave configurada.
     */
    $chave = get_option(
        PRINTWAY_PIX_OPTION_CHAVE,
        ''
    );


    /*
     * Nome configurado.
     */
    $nome = get_option(
        PRINTWAY_PIX_OPTION_NOME,
        ''
    );


    /*
     * Valor enviado pelo botão.
     */
    $amount = null;

    if (
        isset($_POST['amount']) &&
        $_POST['amount'] !== ''
    ) {

        $amount = sanitize_text_field(
            wp_unslash($_POST['amount'])
        );
    }

    /*
     * No fluxo DTF UV, o valor vem da sessão criada e validada pelo
     * servidor. Assim, alterar o valor ou os pontos no navegador não
     * modifica o QR que será associado ao pedido.
     */
    $payment_session_id = isset($_POST['payment_session'])
        ? sanitize_text_field(wp_unslash($_POST['payment_session']))
        : '';

    if ($payment_session_id !== '') {

        if (!function_exists('pw_dtf_get_payment_session')) {
            wp_send_json(array(
                'success' => false,
                'errors' => array('Não foi possível consultar a sessão segura do pagamento.')
            ));
        }

        $payment_session = pw_dtf_get_payment_session($payment_session_id);

        if (
            !$payment_session ||
            (int) $payment_session['user_id'] !== get_current_user_id() ||
            'pix' !== (string) $payment_session['payment_method']
        ) {
            wp_send_json(array(
                'success' => false,
                'errors' => array('A sessão do pagamento expirou ou não corresponde a este pedido.')
            ));
        }

        $amount = number_format(
            (float) $payment_session['amount'],
            2,
            '.',
            ''
        );
    }


    /*
     * Cidade.
     */
    $merchantCity = get_option(
        PRINTWAY_PIX_OPTION_MERCHANT_CITY,
        'SAO PAULO'
    );


    /*
     * Gera payload.
     */
    $result = pw_build_pix_payload_server(
        $chave,
        $nome,
        $amount,
        $merchantCity
    );

    if ($result['success'] && !pw_pix_payload_crc_is_valid($result['payload'])) {
        wp_send_json(array(
            'success' => false,
            'errors' => array('Não foi possível validar o código Pix gerado. Tente novamente.')
        ));
    }


    if (!$result['success']) {

        wp_send_json(
            array(
                'success'  => false,
                'errors'   => $result['errors'],
                'warnings' => $result['warnings']
            )
        );
    }


    /*
     * Salva relatório.
     */
    $logs = get_option(
        PRINTWAY_PIX_OPTION_LOGS,
        array()
    );

    if (!is_array($logs)) {
        $logs = array();
    }


    $logs[] = array(
        'datetime' => current_time('mysql'),
        'amount'   => $amount,
        'payload'  => $result['payload']
    );


    update_option(
        PRINTWAY_PIX_OPTION_LOGS,
        $logs,
        false
    );


    wp_send_json(
        array(
            'success'  => true,
            'payload'  => $result['payload'],
            'warnings' => $result['warnings']
        )
    );
}


/* =========================================================
 * SHORTCODE
 *
 * [printway_pix]
 *
 * [printway_pix valor="10.00"]
 *
 * [printway_pix valor_id="valor-total-dtf"]
 *
 * Também aceita:
 *
 * [printway_pix id="valor-total-dtf"]
 * ========================================================= */

add_shortcode(
    'printway_pix',
    'pw_shortcode_printway_pix'
);


function pw_shortcode_printway_pix($atts) {

    $atts = shortcode_atts(
        array(
            'valor'    => '',
            'valor_id' => '',
            'id'       => '',
            'texto'    => 'Gerar QR Code Pix'
        ),
        $atts,
        'printway_pix'
    );


    /*
     * Permite usar id="" da versão anterior.
     */
    $valor_id = trim(
        $atts['valor_id']
    );

    if ($valor_id === '') {
        $valor_id = trim(
            $atts['id']
        );
    }


    /*
     * Valor fixo.
     */
    $valor_fixo = trim(
        $atts['valor']
    );


    /*
     * ID único deste botão.
     */
    $uid = 'printway-pix-' . wp_rand(
        100000,
        999999
    );


    /*
     * Nonce específico para esta renderização.
     */
    $nonce = wp_create_nonce(
        'printway_pix_public'
    );


    /*
     * AJAX URL.
     */
    $ajax_url = admin_url(
        'admin-ajax.php'
    );


    /*
     * Escapes.
     */
    $button_text = esc_html(
        $atts['texto']
    );

    $data_value = esc_attr(
        $valor_fixo
    );

    $data_value_id = esc_attr(
        $valor_id
    );


    /*
     * HTML.
     */
    ob_start();
    ?>

    <div
        id="<?php echo esc_attr($uid); ?>"
        class="printway-pix-container"
        data-valor="<?php echo $data_value; ?>"
        data-valor-id="<?php echo $data_value_id; ?>"
        data-ajax="<?php echo esc_url($ajax_url); ?>"
        data-nonce="<?php echo esc_attr($nonce); ?>"
        style="margin-top:15px;"
    >

        <button
            type="button"
            class="printway-pix-button"
            style="
                cursor:pointer;
                padding:10px 18px;
                border:0;
                border-radius:5px;
                font-size:15px;
            "
        >
            <?php echo $button_text; ?>
        </button>

        <div
            class="printway-pix-loading"
            style="
                display:none;
                margin-top:10px;
            "
        >
            Gerando QR Code...
        </div>

        <div
            class="printway-pix-error"
            style="
                color:#a00;
                margin-top:10px;
            "
        ></div>

        <div
            class="printway-pix-warning"
            style="
                color:#a66a00;
                margin-top:10px;
            "
        ></div>

        <div
            class="printway-pix-result"
            style="margin-top:15px;"
        ></div>

    </div>

    <script>
    (function() {

        function iniciarPrintWayPix() {

            var wrapper = document.getElementById(
                <?php echo wp_json_encode($uid); ?>
            );

            if (!wrapper) {
                return;
            }

            /*
             * Evita registrar o evento duas vezes.
             */
            if (wrapper.dataset.pwInitialized === '1') {
                return;
            }

            wrapper.dataset.pwInitialized = '1';


            var button = wrapper.querySelector(
                '.printway-pix-button'
            );

            var loading = wrapper.querySelector(
                '.printway-pix-loading'
            );

            var error = wrapper.querySelector(
                '.printway-pix-error'
            );

            var warning = wrapper.querySelector(
                '.printway-pix-warning'
            );

            var result = wrapper.querySelector(
                '.printway-pix-result'
            );


            button.addEventListener(
                'click',
                function() {

                    error.textContent = '';
                    warning.textContent = '';
                    result.innerHTML = '';

                    /*
                     * =================================================
                     * PEGA O VALOR ATUAL
                     * =================================================
                     *
                     * Isto é propositalmente executado DENTRO do
                     * clique.
                     *
                     * Portanto, se outro JavaScript alterar:
                     *
                     * #valor-total-dtf
                     *
                     * antes do clique, pegaremos o novo valor.
                     */

                    var valorId = wrapper.getAttribute(
                        'data-valor-id'
                    );

                    var valorFixo = wrapper.getAttribute(
                        'data-valor'
                    );

                    var valor = '';


                    /*
                     * PRIORIDADE 1:
                     *
                     * ID dinâmico.
                     */
                    if (valorId) {

                        /*
                         * Aceita o usuário colocar acidentalmente
                         * # no shortcode.
                         */
                        valorId = valorId.replace(
                            /^#/,
                            ''
                        );

                        var elemento = document.getElementById(
                            valorId
                        );


                        if (!elemento) {

                            error.textContent =
                                'Não encontrei o elemento com ID "' +
                                valorId +
                                '".';

                            return;
                        }


                        /*
                         * Se for input/select/textarea,
                         * usa .value.
                         */
                        if (
                            elemento.tagName === 'INPUT' ||
                            elemento.tagName === 'SELECT' ||
                            elemento.tagName === 'TEXTAREA'
                        ) {

                            valor = elemento.value;

                        } else {

                            /*
                             * DIV, SPAN, P etc.
                             */
                            valor = elemento.textContent;
                        }


                        valor = (valor || '').trim();


                    } else {

                        /*
                         * PRIORIDADE 2:
                         *
                         * Valor fixo.
                         */
                        valor = valorFixo;
                    }


                    /*
                     * Se não encontrou valor.
                     */
                    if (!valor) {

                        error.textContent =
                            'Não foi possível encontrar um valor para gerar o Pix.';

                        return;
                    }


                    /*
                     * =================================================
                     * NORMALIZAÇÃO NO JAVASCRIPT
                     * =================================================
                     */

                    valor = valor
                        .replace(/\s/g, '')
                        .replace(/R\$/gi, '');


                    /*
                     * Trata:
                     *
                     * 1.234,56
                     */
                    if (
                        valor.indexOf('.') !== -1 &&
                        valor.indexOf(',') !== -1
                    ) {

                        var ultimaVirgula =
                            valor.lastIndexOf(',');

                        var ultimoPonto =
                            valor.lastIndexOf('.');


                        if (
                            ultimaVirgula >
                            ultimoPonto
                        ) {

                            valor = valor
                                .replace(/\./g, '')
                                .replace(',', '.');

                        } else {

                            valor = valor
                                .replace(/,/g, '');
                        }


                    } else if (
                        valor.indexOf(',') !== -1
                    ) {

                        /*
                         * 1,44
                         */
                        valor = valor.replace(
                            ',',
                            '.'
                        );
                    }


                    /*
                     * Remove qualquer caractere restante.
                     */
                    valor = valor.replace(
                        /[^0-9.]/g,
                        ''
                    );


                    /*
                     * Converte.
                     */
                    var numero = parseFloat(valor);


                    if (
                        isNaN(numero) ||
                        numero < 0
                    ) {

                        error.textContent =
                            'O valor encontrado "' +
                            valor +
                            '" não é válido.';

                        return;
                    }


                    /*
                     * Duas casas.
                     */
                    var valorFinal =
                        numero.toFixed(2);


                    /*
                     * =================================================
                     * AJAX
                     * =================================================
                     */

                    loading.style.display =
                        'block';

                    button.disabled = true;


                    var formData =
                        new URLSearchParams();


                    formData.append(
                        'action',
                        'printway_pix_generate_payload'
                    );

                    formData.append(
                        'nonce',
                        wrapper.getAttribute(
                            'data-nonce'
                        )
                    );

                    formData.append(
                        'amount',
                        valorFinal
                    );


                    fetch(
                        wrapper.getAttribute(
                            'data-ajax'
                        ),
                        {
                            method: 'POST',

                            headers: {
                                'Content-Type':
                                    'application/x-www-form-urlencoded; charset=UTF-8'
                            },

                            body:
                                formData.toString()
                        }
                    )

                    .then(
                        function(response) {

                            if (!response.ok) {

                                throw new Error(
                                    'HTTP ' +
                                    response.status
                                );
                            }

                            return response.json();
                        }
                    )

                    .then(
                        function(json) {

                            loading.style.display =
                                'none';

                            button.disabled =
                                false;


                            if (!json.success) {

                                if (
                                    json.errors &&
                                    json.errors.length
                                ) {

                                    error.textContent =
                                        json.errors.join(' ');
                                }

                                return;
                            }


                            if (
                                json.warnings &&
                                json.warnings.length
                            ) {

                                warning.textContent =
                                    json.warnings.join(' ');
                            }


                            /*
                             * QR Code
                             */
                            result.innerHTML = '';


                            var qrWrapper =
                                document.createElement(
                                    'div'
                                );

                            result.appendChild(
                                qrWrapper
                            );


                            /*
                             * Mostra o valor usado.
                             */
                            var valorLabel =
                                document.createElement(
                                    'div'
                                );

                            valorLabel.style.marginBottom =
                                '10px';

                            valorLabel.style.fontWeight =
                                'bold';

                            valorLabel.textContent =
                                'Pix: R$ ' +
                                valorFinal.replace(
                                    '.',
                                    ','
                                );


                            result.insertBefore(
                                valorLabel,
                                qrWrapper
                            );


                            /*
                             * QRCode.js
                             */
                            if (
                                typeof QRCode ===
                                'undefined'
                            ) {

                                error.textContent =
                                    'Biblioteca de QR Code não carregada.';

                                return;
                            }


                            new QRCode(
                                qrWrapper,
                                {
                                    text:
                                        json.payload,

                                    width: 256,

                                    height: 256,

                                    correctLevel:
                                        QRCode.CorrectLevel.M
                                }
                            );


                            /*
                             * Pix Copia e Cola.
                             */
                            var copyButton =
                                document.createElement(
                                    'button'
                                );

                            copyButton.type =
                                'button';

                            copyButton.textContent =
                                'Copiar Pix Copia e Cola';

                            copyButton.style.marginTop =
                                '10px';

                            copyButton.style.cursor =
                                'pointer';

                            copyButton.style.padding =
                                '8px 12px';


                            copyButton.addEventListener(
                                'click',
                                function() {

                                    navigator.clipboard
                                        .writeText(
                                            json.payload
                                        )
                                        .then(
                                            function() {

                                                copyButton.textContent =
                                                    'Copiado!';

                                                setTimeout(
                                                    function() {

                                                        copyButton.textContent =
                                                            'Copiar Pix Copia e Cola';

                                                    },
                                                    2000
                                                );
                                            }
                                        );
                                }
                            );


                            result.appendChild(
                                copyButton
                            );

                        }
                    )

                    .catch(
                        function(err) {

                            loading.style.display =
                                'none';

                            button.disabled =
                                false;

                            console.error(
                                err
                            );

                            error.textContent =
                                'Erro ao gerar o Pix. Verifique o console do navegador.';
                        }
                    );
                }
            );
        }


        /*
         * Se o DOM já estiver carregado.
         */
        if (
            document.readyState ===
            'loading'
        ) {

            document.addEventListener(
                'DOMContentLoaded',
                iniciarPrintWayPix
            );

        } else {

            iniciarPrintWayPix();
        }

    })();
    </script>

    <?php

    /*
     * Carrega QRCode.js.
     */
    wp_enqueue_script(
        'printway-qrcode-js',
        'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js',
        array(),
        '1.0.0',
        true
    );


    return ob_get_clean();
}


/* =========================================================
 * ADMIN MENU
 * ========================================================= */

add_action(
    'admin_menu',
    'printway_pix_admin_menu',
    20
);


function printway_pix_admin_menu() {

    add_submenu_page(
        'pw-printway',
        'Pix',
        'Pix',
        'manage_options',
        'printway-pix-qrcode',
        'printway_pix_admin_page'
    );
}


/* =========================================================
 * ADMIN
 * ========================================================= */

function printway_pix_admin_page() {

    if (
        !current_user_can(
            'manage_options'
        )
    ) {

        wp_die(
            'Acesso negado.'
        );
    }


    $tab = isset($_GET['tab'])
        ? sanitize_text_field(
            wp_unslash($_GET['tab'])
        )
        : 'config';


    echo '<div class="wrap">';

    echo '<h1>PrintWay Pix QRCode</h1>';

    echo '<h2 class="nav-tab-wrapper">';

    echo '<a href="' .
        esc_url(
            admin_url(
                'admin.php?page=printway-pix-qrcode&tab=config'
            )
        ) .
        '" class="nav-tab ' .
        (
            $tab === 'config'
                ? 'nav-tab-active'
                : ''
        ) .
        '">Configurações</a>';


    echo '<a href="' .
        esc_url(
            admin_url(
                'admin.php?page=printway-pix-qrcode&tab=report'
            )
        ) .
        '" class="nav-tab ' .
        (
            $tab === 'report'
                ? 'nav-tab-active'
                : ''
        ) .
        '">Relatório</a>';

    echo '</h2>';


    if ($tab === 'report') {

        printway_pix_report_page();

    } else {

        printway_pix_config_page();
    }


    echo '</div>';
}


/* =========================================================
 * CONFIGURAÇÕES
 * ========================================================= */

function printway_pix_config_page() {

    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset(
            $_POST['printway_pix_config_submit']
        )
    ) {

        if (
            !isset($_POST['_wpnonce']) ||
            !wp_verify_nonce(
                sanitize_text_field(
                    wp_unslash(
                        $_POST['_wpnonce']
                    )
                ),
                'printway_pix_config'
            )
        ) {

            echo '<div class="notice notice-error"><p>Nonce inválido.</p></div>';

        } else {

            $key_type =
                isset($_POST['pix_key_type'])
                    ? sanitize_key(
                        wp_unslash(
                            $_POST['pix_key_type']
                        )
                    )
                    : '';

            $chave_raw =
                isset($_POST['pix_chave'])
                    ? sanitize_text_field(
                        wp_unslash(
                            $_POST['pix_chave']
                        )
                    )
                    : '';

            $nome =
                isset($_POST['pix_nome'])
                    ? sanitize_text_field(
                        wp_unslash(
                            $_POST['pix_nome']
                        )
                    )
                    : '';

            $city =
                isset($_POST['pix_merchant_city'])
                    ? sanitize_text_field(
                        wp_unslash(
                            $_POST['pix_merchant_city']
                        )
                    )
                    : 'SAO PAULO';

            $config_errors = array();
            $key_types = pw_pix_key_types();

            if (!isset($key_types[$key_type])) {
                $config_errors[] = 'Selecione um tipo de chave Pix válido.';
            }

            $chave_result = pw_validate_and_normalize_pix_key(
                $chave_raw,
                $key_type
            );

            if (is_wp_error($chave_result)) {
                $config_errors[] = $chave_result->get_error_message();
                $chave = '';
            } else {
                $chave = $chave_result;
            }

            $nome = pw_normalize_name($nome);
            $city = pw_normalize_city($city);

            if ($nome === '') {
                $config_errors[] = 'Informe o nome do recebedor.';
            }

            if ($city === '') {
                $config_errors[] = 'Informe a cidade do recebedor.';
            }

            if (empty($config_errors)) {

                $test_payload = pw_build_pix_payload_server(
                    $chave,
                    $nome,
                    '1.00',
                    $city,
                    $key_type
                );

                if (
                    !$test_payload['success'] ||
                    !pw_pix_payload_crc_is_valid($test_payload['payload'])
                ) {
                    $config_errors[] = 'Não foi possível validar a configuração informada.';
                }
            }

            if (!empty($config_errors)) {

                echo '<div class="notice notice-error"><p><strong>Não foi possível salvar:</strong></p><ul><li>' .
                    implode(
                        '</li><li>',
                        array_map('esc_html', $config_errors)
                    ) .
                    '</li></ul></div>';

            } else {

                update_option(
                    PRINTWAY_PIX_OPTION_KEY_TYPE,
                    $key_type
                );

                update_option(
                    PRINTWAY_PIX_OPTION_CHAVE,
                    $chave
                );

                update_option(
                    PRINTWAY_PIX_OPTION_NOME,
                    $nome
                );

                update_option(
                    PRINTWAY_PIX_OPTION_MERCHANT_CITY,
                    $city
                );


                echo '<div class="notice notice-success is-dismissible"><p>Configurações salvas.</p></div>';
            }
        }
    }


    $chave = get_option(
        PRINTWAY_PIX_OPTION_CHAVE,
        ''
    );

    $key_types = pw_pix_key_types();

    $key_type = get_option(
        PRINTWAY_PIX_OPTION_KEY_TYPE,
        ''
    );

    if (!isset($key_types[$key_type])) {
        $key_type = pw_detect_pix_key_type($chave);
    }

    if (!isset($key_types[$key_type])) {
        $key_type = 'phone';
    }

    $normalized_saved_key = pw_validate_and_normalize_pix_key(
        $chave,
        $key_type
    );

    $saved_key_error = '';

    if (!is_wp_error($normalized_saved_key)) {
        $chave = $normalized_saved_key;
    } elseif ($chave !== '') {
        $saved_key_error = $normalized_saved_key->get_error_message();
    }

    $nome = get_option(
        PRINTWAY_PIX_OPTION_NOME,
        ''
    );

    $city = get_option(
        PRINTWAY_PIX_OPTION_MERCHANT_CITY,
        'SAO PAULO'
    );

    $nome = pw_normalize_name($nome);
    $city = pw_normalize_city($city);


    ?>

    <h2>Configuração do Pix</h2>

    <?php if ($saved_key_error !== '') : ?>
        <div class="notice notice-warning inline">
            <p>
                <strong>A configuração atual da chave Pix precisa ser corrigida:</strong>
                <?php echo esc_html($saved_key_error); ?>
            </p>
        </div>
    <?php endif; ?>

    <form method="post">

        <?php
        wp_nonce_field(
            'printway_pix_config'
        );
        ?>

        <table class="form-table">

            <tr>

                <th>
                    <label for="pix_key_type">
                        Tipo de chave Pix
                    </label>
                </th>

                <td>

                    <select
                        name="pix_key_type"
                        id="pix_key_type"
                        required
                    >
                        <?php foreach ($key_types as $type_value => $type_label) : ?>
                            <option
                                value="<?php echo esc_attr($type_value); ?>"
                                <?php selected($key_type, $type_value); ?>
                            >
                                <?php echo esc_html($type_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <p class="description">
                        Escolha o tipo exato da chave cadastrada no banco.
                    </p>

                </td>

            </tr>


            <tr>

                <th>
                    <label for="pix_chave">
                        Chave Pix
                    </label>
                </th>

                <td>

                    <input
                        type="text"
                        name="pix_chave"
                        id="pix_chave"
                        value="<?php echo esc_attr($chave); ?>"
                        class="regular-text"
                        maxlength="77"
                        autocomplete="off"
                        spellcheck="false"
                        required
                    >

                    <p class="description" id="pix_key_help"></p>

                </td>

            </tr>


            <tr>

                <th>
                    <label for="pix_nome">
                        Nome do Recebedor
                    </label>
                </th>

                <td>

                    <input
                        type="text"
                        name="pix_nome"
                        id="pix_nome"
                        value="<?php echo esc_attr($nome); ?>"
                        class="regular-text"
                        maxlength="25"
                        required
                    >

                    <p class="description">
                        Será salvo em letras maiúsculas, sem acentos, com no máximo 25 caracteres.
                    </p>

                </td>

            </tr>


            <tr>

                <th>
                    <label for="pix_merchant_city">
                        Cidade do recebedor
                    </label>
                </th>

                <td>

                    <input
                        type="text"
                        name="pix_merchant_city"
                        id="pix_merchant_city"
                        value="<?php echo esc_attr($city); ?>"
                        class="regular-text"
                        maxlength="15"
                        required
                    >

                    <p class="description">
                        Será salva em letras maiúsculas, sem acentos, com no máximo 15 caracteres. Exemplo: AMERICANA.
                    </p>

                </td>

            </tr>

        </table>


        <p class="submit">

            <input
                type="submit"
                name="printway_pix_config_submit"
                class="button button-primary"
                value="Salvar"
            >

        </p>

    </form>

    <script>
    (function() {
        var typeField = document.getElementById('pix_key_type');
        var keyField = document.getElementById('pix_chave');
        var helpField = document.getElementById('pix_key_help');

        if (!typeField || !keyField || !helpField) {
            return;
        }

        var settings = {
            phone: {
                placeholder: '+5519999999999',
                help: 'Informe telefone com DDD. O plugin completará e salvará no padrão internacional +55.',
                inputmode: 'tel',
                maxlength: 20
            },
            cpf: {
                placeholder: '000.000.000-00',
                help: 'O CPF será validado e salvo somente com números.',
                inputmode: 'numeric',
                maxlength: 14
            },
            cnpj: {
                placeholder: '00.000.000/0000-00',
                help: 'O CNPJ será validado e salvo somente com números.',
                inputmode: 'numeric',
                maxlength: 18
            },
            email: {
                placeholder: 'financeiro@empresa.com.br',
                help: 'Informe exatamente o e-mail cadastrado como chave Pix.',
                inputmode: 'email',
                maxlength: 77
            },
            evp: {
                placeholder: '00000000-0000-4000-8000-000000000000',
                help: 'Cole a chave aleatória completa com 36 caracteres.',
                inputmode: 'text',
                maxlength: 36
            }
        };

        function updateKeyField() {
            var current = settings[typeField.value] || settings.phone;
            keyField.placeholder = current.placeholder;
            keyField.inputMode = current.inputmode;
            keyField.maxLength = current.maxlength;
            helpField.textContent = current.help;
        }

        typeField.addEventListener('change', updateKeyField);
        updateKeyField();
    }());
    </script>


    <hr>


    <h2>Como colocar o botão Pix em uma página</h2>

    <p>
        O plugin <strong>não adiciona automaticamente</strong>
        nenhum botão ao checkout.
        Você decide exatamente onde o botão aparecerá.
    </p>


    <h3>1. Valor fixo</h3>

    <pre style="background:#fff;border:1px solid #ddd;padding:15px;">[printway_pix valor="10.00"]</pre>


    <h3>2. Valor vindo de outro elemento HTML</h3>

    <p>
        Se sua página possui:
    </p>

    <pre style="background:#fff;border:1px solid #ddd;padding:15px;">&lt;div id="valor-total-dtf"&gt;1.44&lt;/div&gt;</pre>

    <p>
        coloque o shortcode:
    </p>

    <pre style="background:#fff;border:1px solid #ddd;padding:15px;">[printway_pix valor_id="valor-total-dtf"]</pre>


    <p>
        <strong>Não coloque # no shortcode.</strong>
    </p>


    <h3>3. O valor pode mudar automaticamente</h3>

    <pre style="background:#fff;border:1px solid #ddd;padding:15px;">&lt;div id="valor-total-dtf"&gt;1.44&lt;/div&gt;</pre>

    <p>
        Se seu sistema alterar esse elemento para:
    </p>

    <pre style="background:#fff;border:1px solid #ddd;padding:15px;">&lt;div id="valor-total-dtf"&gt;8.90&lt;/div&gt;</pre>

    <p>
        ao clicar no botão o plugin pegará
        <strong>8,90</strong>, pois o valor é lido
        novamente no momento do clique.
    </p>


    <h3>Também funciona com input</h3>

    <pre style="background:#fff;border:1px solid #ddd;padding:15px;">&lt;input id="valor-total-dtf" value="5,75"&gt;

[printway_pix valor_id="valor-total-dtf"]</pre>


    <hr>


    <h2>Teste do QR Code</h2>

    <p>
        O teste utiliza as configurações salvas acima.
    </p>

    <p>

        <input
            type="text"
            id="pw_admin_test_value"
            value="1.44"
            style="width:150px;"
        >

        <button
            type="button"
            class="button button-primary"
            id="pw_admin_test_button"
        >
            TESTAR
        </button>

    </p>

    <div
        id="pw_admin_test_result"
        style="margin-top:20px;"
    ></div>

    <pre
        id="pw_admin_test_payload"
        style="
            display:none;
            background:#fff;
            border:1px solid #ddd;
            padding:15px;
            max-width:900px;
            white-space:pre-wrap;
            word-break:break-all;
        "
    ></pre>


    <script>

    document.addEventListener(
        'DOMContentLoaded',
        function() {

            var button =
                document.getElementById(
                    'pw_admin_test_button'
                );

            if (!button) {
                return;
            }


            button.addEventListener(
                'click',
                function() {

                    var value =
                        document.getElementById(
                            'pw_admin_test_value'
                        ).value;


                    var formData =
                        new URLSearchParams();


                    formData.append(
                        'action',
                        'printway_pix_generate_payload'
                    );

                    formData.append(
                        'nonce',
                        <?php
                        echo wp_json_encode(
                            wp_create_nonce(
                                'printway_pix_public'
                            )
                        );
                        ?>
                    );

                    formData.append(
                        'amount',
                        value
                    );


                    fetch(
                        <?php
                        echo wp_json_encode(
                            admin_url(
                                'admin-ajax.php'
                            )
                        );
                        ?>,
                        {
                            method:'POST',

                            headers:{
                                'Content-Type':
                                'application/x-www-form-urlencoded; charset=UTF-8'
                            },

                            body:
                                formData.toString()
                        }
                    )

                    .then(
                        function(r) {
                            return r.json();
                        }
                    )

                    .then(
                        function(json) {

                            var result =
                                document.getElementById(
                                    'pw_admin_test_result'
                                );

                            var payload =
                                document.getElementById(
                                    'pw_admin_test_payload'
                                );


                            result.innerHTML = '';

                            payload.style.display =
                                'none';


                            if (!json.success) {

                                result.innerHTML =
                                    '<div style="color:#a00;">' +
                                    (
                                        json.errors
                                            ? json.errors.join(' ')
                                            : 'Erro.'
                                    ) +
                                    '</div>';

                                return;
                            }


                            var qr =
                                document.createElement(
                                    'div'
                                );

                            result.appendChild(
                                qr
                            );


                            new QRCode(
                                qr,
                                {
                                    text:
                                        json.payload,

                                    width:256,

                                    height:256,

                                    correctLevel:
                                        QRCode.CorrectLevel.M
                                }
                            );


                            payload.textContent =
                                json.payload;

                            payload.style.display =
                                'block';
                        }
                    );
                }
            );
        }
    );

    </script>

    <?php
}


/* =========================================================
 * CARREGA QRCODE NO ADMIN
 * ========================================================= */

add_action(
    'admin_enqueue_scripts',
    function($hook) {

        if (
            $hook ===
            'printway_page_printway-pix-qrcode'
        ) {

            wp_enqueue_script(
                'printway-qrcode-admin',
                'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js',
                array(),
                '1.0.0',
                true
            );
        }
    }
);


/* =========================================================
 * RELATÓRIO
 * ========================================================= */

function printway_pix_report_page() {

    if (
        !current_user_can(
            'manage_options'
        )
    ) {

        wp_die(
            'Acesso negado.'
        );
    }


    /*
     * Exclusão de todos os registros do relatório.
     */
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset($_POST['printway_delete_all_logs'])
    ) {

        if (
            !isset($_POST['_wpnonce_delete_all_logs']) ||
            !wp_verify_nonce(
                sanitize_text_field(
                    wp_unslash(
                        $_POST['_wpnonce_delete_all_logs']
                    )
                ),
                'printway_pix_delete_all_logs'
            )
        ) {

            wp_die('Solicitação inválida.');
        }


        delete_option(
            PRINTWAY_PIX_OPTION_LOGS
        );


        wp_safe_redirect(
            admin_url(
                'admin.php?page=printway-pix-qrcode&tab=report&printway_pix_notice=logs_deleted'
            )
        );

        exit;
    }


    $logs = get_option(
        PRINTWAY_PIX_OPTION_LOGS,
        array()
    );


    if (!is_array($logs)) {
        $logs = array();
    }


    echo '<h2>Relatório de Pix gerados</h2>';


    if (
        isset($_GET['printway_pix_notice']) &&
        'logs_deleted' === $_GET['printway_pix_notice']
    ) {

        echo '<div class="notice notice-success is-dismissible"><p>Todos os registros do relatório foram apagados.</p></div>';
    }


    if (empty($logs)) {

        echo '<p>Nenhum Pix gerado ainda.</p>';

        return;
    }


    echo '<form method="post" style="margin:0 0 15px;">';

    wp_nonce_field(
        'printway_pix_delete_all_logs',
        '_wpnonce_delete_all_logs'
    );

    echo '<input type="hidden" name="printway_delete_all_logs" value="1">';

    echo '<button
        type="submit"
        class="button button-secondary"
        style="color:#b32d2e;border-color:#b32d2e;"
        onclick="return confirm(\'Apagar todos os registros do relatório? Esta ação não pode ser desfeita.\')"
    >Apagar todos os registros</button>';

    echo '</form>';


    echo '<table class="widefat fixed striped printway-pix-sortable-table">';

    echo '<thead>';

    echo '<tr>';

    echo '<th width="160">Data / Hora</th>';

    echo '<th width="120">Valor</th>';

    echo '<th>Pix Copia e Cola</th>';

    echo '<th width="110">Ações</th>';

    echo '</tr>';

    echo '</thead>';

    echo '<tbody>';


    foreach ($logs as $index => $log) {

        $datetime =
            isset($log['datetime'])
                ? $log['datetime']
                : '';

        $amount =
            isset($log['amount'])
                ? $log['amount']
                : '';

        $payload =
            isset($log['payload'])
                ? $log['payload']
                : '';


        /*
         * Data brasileira.
         */
        $date_br = '';

        if ($datetime !== '') {

            $timestamp = strtotime(
                $datetime
            );

            if ($timestamp) {

                $date_br =
                    wp_date(
                        'd/m/Y H:i:s',
                        $timestamp,
                        wp_timezone()
                    );
            }
        }


        /*
         * Valor.
         */
        $amount_br = '';

        if ($amount !== '') {

            $amount_normalized =
                pw_normalize_amount(
                    $amount
                );

            if ($amount_normalized !== null) {

                $amount_br =
                    'R$ ' .
                    str_replace(
                        '.',
                        ',',
                        $amount_normalized
                    );
            }
        }


        echo '<tr>';

        echo '<td>' .
            esc_html($date_br) .
            '</td>';

        echo '<td>' .
            esc_html($amount_br) .
            '</td>';

        echo '<td style="word-break:break-all;">' .
            esc_html($payload) .
            '</td>';


        echo '<td>';


        /*
         * BOTÃO VER QR
         */
        echo '<button
            type="button"
            class="button pw-view-qr"
            data-payload="' .
            esc_attr($payload) .
            '"
            title="Ver QR Code"
            aria-label="Ver QR Code"
            style="
                width:32px;
                height:32px;
                min-width:32px;
                padding:0;
                margin-right:4px;
                display:inline-flex;
                align-items:center;
                justify-content:center;
            "
        >';

        /*
         * Dashicon QR / visibility
         */
        echo '<span
            class="dashicons dashicons-visibility"
            style="font-size:18px;width:18px;height:18px;"
        ></span>';

        echo '</button>';


        /*
         * BOTÃO DELETAR
         */
        echo '<form
            method="post"
            style="display:inline;"
        >';

        wp_nonce_field(
            'printway_pix_delete_item',
            '_wpnonce_delete_item'
        );


        echo '<input
            type="hidden"
            name="printway_delete_item"
            value="' .
            intval($index) .
            '"
        >';


        echo '<button
            type="submit"
            class="button"
            title="Excluir"
            aria-label="Excluir"
            style="
                width:32px;
                height:32px;
                min-width:32px;
                padding:0;
                display:inline-flex;
                align-items:center;
                justify-content:center;
            "
            onclick="return confirm(\'Apagar este registro?\')"
        >';

        echo '<span
            class="dashicons dashicons-trash"
            style="font-size:18px;width:18px;height:18px;"
        ></span>';

        echo '</button>';

        echo '</form>';


        echo '</td>';

        echo '</tr>';
    }


    echo '</tbody>';

    echo '</table>';
    echo '<style>.printway-pix-sortable-table th[data-pw-sort]{cursor:pointer;user-select:none}.printway-pix-sortable-table th[data-pw-sort]::after{content:" ↕";color:#777;font-size:12px}.printway-pix-sortable-table th[data-pw-sort="asc"]::after{content:" ↑"}.printway-pix-sortable-table th[data-pw-sort="desc"]::after{content:" ↓"}</style>';
    echo '<script>(function(){function normalized(raw){var date=raw.match(/^(\\d{2})\\/(\\d{2})\\/(\\d{4})(?:\\s+(\\d{2}):(\\d{2}))?/);if(date){return "D"+date[3]+date[2]+date[1]+(date[4]||"00")+(date[5]||"00");}var number=raw.replace(/R\\$|[^0-9,.-]/gi,"").replace(/\\./g,"").replace(",",".");return /^-?\\d+(?:\\.\\d+)?$/.test(number)?"N"+String(parseFloat(number)).padStart(20,"0"):"T"+raw.toLocaleLowerCase("pt-BR");}function setup(){document.querySelectorAll("table.printway-pix-sortable-table").forEach(function(table){table.querySelectorAll("thead th").forEach(function(header,index){if(index===3){return;}header.dataset.pwSort="";header.tabIndex=0;header.setAttribute("role","button");header.setAttribute("title","Ordenar por esta coluna");function sort(){var direction=header.dataset.pwSort==="asc"?"desc":"asc",body=table.tBodies[0];Array.from(body.rows).sort(function(left,right){var a=normalized((left.cells[index]||document.createElement("td")).textContent.trim()),b=normalized((right.cells[index]||document.createElement("td")).textContent.trim());return (a<b?-1:a>b?1:0)*(direction==="asc"?1:-1);}).forEach(function(row){body.appendChild(row);});table.querySelectorAll("thead th").forEach(function(item){if(item!==header){item.dataset.pwSort="";}});header.dataset.pwSort=direction;}header.addEventListener("click",sort);header.addEventListener("keydown",function(event){if(event.key==="Enter"||event.key===" "){event.preventDefault();sort();}});});});}if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",setup);}else{setup();}})();</script>';


    /*
     * Área do QR.
     */
    echo '<div
        id="pw-report-qr"
        style="margin-top:25px;"
    ></div>';


    /*
     * JavaScript.
     */
    ?>

    <script>

    document.addEventListener(
        'DOMContentLoaded',
        function() {

            var buttons =
                document.querySelectorAll(
                    '.pw-view-qr'
                );


            buttons.forEach(
                function(button) {

                    button.addEventListener(
                        'click',
                        function() {

                            var payload =
                                this.getAttribute(
                                    'data-payload'
                                );


                            var container =
                                document.getElementById(
                                    'pw-report-qr'
                                );


                            container.innerHTML =
                                '<h3>QR Code</h3>';


                            var qr =
                                document.createElement(
                                    'div'
                                );


                            container.appendChild(
                                qr
                            );


                            new QRCode(
                                qr,
                                {
                                    text:payload,

                                    width:256,

                                    height:256,

                                    correctLevel:
                                        QRCode.CorrectLevel.M
                                }
                            );


                            var pre =
                                document.createElement(
                                    'pre'
                                );

                            pre.style.maxWidth =
                                '700px';

                            pre.style.whiteSpace =
                                'pre-wrap';

                            pre.style.wordBreak =
                                'break-all';

                            pre.style.background =
                                '#fff';

                            pre.style.padding =
                                '10px';

                            pre.style.border =
                                '1px solid #ddd';

                            pre.textContent =
                                payload;


                            container.appendChild(
                                pre
                            );


                            container.scrollIntoView({
                                behavior:'smooth'
                            });

                        }
                    );
                }
            );

        }
    );

    </script>

    <?php


    /*
     * Exclusão individual.
     */
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset(
            $_POST['printway_delete_item']
        )
    ) {

        if (
            !isset(
                $_POST['_wpnonce_delete_item']
            ) ||
            !wp_verify_nonce(
                sanitize_text_field(
                    wp_unslash(
                        $_POST['_wpnonce_delete_item']
                    )
                ),
                'printway_pix_delete_item'
            )
        ) {

            return;
        }


        $index = intval(
            $_POST['printway_delete_item']
        );


        $logs = get_option(
            PRINTWAY_PIX_OPTION_LOGS,
            array()
        );


        if (
            is_array($logs) &&
            isset($logs[$index])
        ) {

            unset(
                $logs[$index]
            );

            $logs = array_values(
                $logs
            );


            update_option(
                PRINTWAY_PIX_OPTION_LOGS,
                $logs,
                false
            );


            echo '<script>
                location.reload();
            </script>';
        }
    }
}


/* =========================================================
 * DEACTIVATE
 * ========================================================= */

register_deactivation_hook(
    __FILE__,
    'printway_pix_deactivate_cleanup'
);


function printway_pix_deactivate_cleanup() {

    /*
     * Não apaga configurações.
     */
}

function pw_pix_payload_crc_is_valid($payload) {
    $payload = (string) $payload;
    if (!preg_match('/^(.*6304)([A-F0-9]{4})$/', $payload, $matches)) {
        return false;
    }
    return hash_equals(strtoupper($matches[2]), pw_crc16_ccitt_false($matches[1]));
}
