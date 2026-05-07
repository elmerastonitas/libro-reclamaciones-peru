<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LRP_Form {

    public function __construct() {
        add_shortcode( 'libro_reclamaciones', array( $this, 'render_shortcode' ) );
        add_action( 'init', array( $this, 'process_submission' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function enqueue_assets() {
        $ver = time(); // Dev: Force cache bust
        wp_register_style( 'lrp-style', LRP_PLUGIN_URL . 'assets/lrp-style.css', array(), $ver );
        wp_register_script( 'lrp-script', LRP_PLUGIN_URL . 'assets/lrp-script.js', array(), $ver, true );

        // Turnstile: only register if site key is configured
        $turnstile_site_key = get_option( 'lrp_turnstile_site_key', '' );
        if ( ! empty( $turnstile_site_key ) ) {
            wp_register_script( 'lrp-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true );
        }
    }

    public function process_submission() {
        if ( isset( $_POST['lrp_action'] ) && $_POST['lrp_action'] === 'submit_claim' ) {
            
            // Verify nonce
            if ( ! isset( $_POST['lrp_form_nonce'] ) || ! wp_verify_nonce( $_POST['lrp_form_nonce'], 'lrp_submit_claim_action' ) ) {
                wp_die( __( 'Error de seguridad. Por favor, recarga la página e intenta nuevamente.', 'libro-reclamaciones-peru' ) );
            }

            $errors = array();

            // Turnstile verification
            $turnstile_secret = get_option( 'lrp_turnstile_secret_key', '' );
            $turnstile_site_key = get_option( 'lrp_turnstile_site_key', '' );
            if ( ! empty( $turnstile_secret ) && ! empty( $turnstile_site_key ) ) {
                $turnstile_response = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( $_POST['cf-turnstile-response'] ) : '';
                if ( empty( $turnstile_response ) ) {
                    $errors[] = __( 'Por favor, complete la verificación anti-spam.', 'libro-reclamaciones-peru' );
                } else {
                    $verify = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
                        'body' => array(
                            'secret'   => $turnstile_secret,
                            'response' => $turnstile_response,
                            'remoteip' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '',
                        ),
                    ) );

                    if ( is_wp_error( $verify ) ) {
                        $errors[] = __( 'Verificación anti-spam fallida. Intenta nuevamente.', 'libro-reclamaciones-peru' );
                    } else {
                        $result_body = json_decode( wp_remote_retrieve_body( $verify ), true );
                        if ( empty( $result_body['success'] ) ) {
                            $errors[] = __( 'Verificación anti-spam fallida. Intenta nuevamente.', 'libro-reclamaciones-peru' );
                        }
                    }
                }
            }
            // Default required fields (All except guardian and is_minor)
            $required_fields = array(
                'consumer_firstname', 
                'consumer_lastname', 
                'consumer_document_type',
                'consumer_document_number', 
                'consumer_email', 
                'consumer_phone',
                'consumer_department',
                'consumer_province',
                'consumer_district',
                'consumer_address',
                'contracted_good_date',
                'contracted_good_type',
                'contracted_good_local',
                'contracted_good_amount',
                'contracted_good_description',
                'claim_type',
                'claim_detail',
                'claim_order'
            );

            // Conditional Validation: Guardian fields if minor is checked
            if ( isset( $_POST['is_minor'] ) && $_POST['is_minor'] == '1' ) {
                $guardian_fields = array(
                    'guardian_document_type', 
                    'guardian_document_number', 
                    'guardian_firstname', 
                    'guardian_lastname'
                );
                $required_fields = array_merge( $required_fields, $guardian_fields );
            }

            foreach ( $required_fields as $field ) {
                if ( empty( $_POST[$field] ) ) {
                    $errors[] = __( 'El campo ', 'libro-reclamaciones-peru' ) . str_replace('_', ' ', $field) . __( ' es obligatorio.', 'libro-reclamaciones-peru' );
                }
            }

            // Validate amount >= 1
            if ( ! empty( $_POST['contracted_good_amount'] ) ) {
                $amount = floatval( $_POST['contracted_good_amount'] );
                if ( $amount < 1 ) {
                    $errors[] = __( 'El monto reclamado debe ser mayor o igual a 1.', 'libro-reclamaciones-peru' );
                }
            }

            // Validate document number by type (consumer)
            if ( ! empty( $_POST['consumer_document_type'] ) && ! empty( $_POST['consumer_document_number'] ) ) {
                $doc_type = strtoupper( sanitize_text_field( $_POST['consumer_document_type'] ) );
                $doc_number = sanitize_text_field( $_POST['consumer_document_number'] );
                $doc_error = self::validate_document_number( $doc_type, $doc_number );
                if ( $doc_error ) {
                    $errors[] = $doc_error;
                }
            }

            // Validate guardian document number if minor
            if ( isset( $_POST['is_minor'] ) && $_POST['is_minor'] == '1' ) {
                if ( ! empty( $_POST['guardian_document_type'] ) && ! empty( $_POST['guardian_document_number'] ) ) {
                    $g_doc_type = strtoupper( sanitize_text_field( $_POST['guardian_document_type'] ) );
                    $g_doc_number = sanitize_text_field( $_POST['guardian_document_number'] );
                    $g_doc_error = self::validate_document_number( $g_doc_type, $g_doc_number );
                    if ( $g_doc_error ) {
                        $errors[] = __( 'Representante: ', 'libro-reclamaciones-peru' ) . $g_doc_error;
                    }
                }
            }

            // Validate purchase date is not in the future
            if ( ! empty( $_POST['contracted_good_date'] ) ) {
                $purchase_date = sanitize_text_field( $_POST['contracted_good_date'] );
                $today = current_time( 'Y-m-d' );
                if ( $purchase_date > $today ) {
                    $errors[] = __( 'La fecha de compra no puede ser posterior a la fecha actual.', 'libro-reclamaciones-peru' );
                }
            }

            if ( empty( $errors ) ) {
                $data = array(
                    'consumer_firstname'       => sanitize_text_field( $_POST['consumer_firstname'] ),
                    'consumer_lastname'        => sanitize_text_field( $_POST['consumer_lastname'] ),
                    'consumer_document_type'   => sanitize_text_field( $_POST['consumer_document_type'] ),
                    'consumer_document_number' => sanitize_text_field( $_POST['consumer_document_number'] ),
                    'consumer_email'           => sanitize_email( $_POST['consumer_email'] ),
                    'consumer_phone'           => sanitize_text_field( $_POST['consumer_phone'] ),
                    'consumer_address'         => sanitize_textarea_field( $_POST['consumer_address'] ),
                    'consumer_district'        => sanitize_text_field( $_POST['consumer_district'] ),
                    'consumer_province'        => sanitize_text_field( $_POST['consumer_province'] ),
                    'consumer_department'      => sanitize_text_field( $_POST['consumer_department'] ),
                    'is_minor'                 => isset( $_POST['is_minor'] ) ? 1 : 0,
                    'guardian_firstname'       => sanitize_text_field( $_POST['guardian_firstname'] ),
                    'guardian_lastname'        => sanitize_text_field( $_POST['guardian_lastname'] ),
                    'guardian_document_type'   => sanitize_text_field( $_POST['guardian_document_type'] ),
                    'guardian_document_number' => sanitize_text_field( $_POST['guardian_document_number'] ),
                    'contracted_good_type'     => sanitize_text_field( $_POST['contracted_good_type'] ),
                    'contracted_good_description' => sanitize_textarea_field( $_POST['contracted_good_description'] ),
                    'contracted_good_amount'   => sanitize_text_field( $_POST['contracted_good_amount'] ),
                    'contracted_good_date'     => sanitize_text_field( $_POST['contracted_good_date'] ),
                    'contracted_good_local'    => sanitize_text_field( $_POST['contracted_good_local'] ),
                    'claim_type'               => sanitize_text_field( $_POST['claim_type'] ),
                    'claim_detail'             => sanitize_textarea_field( $_POST['claim_detail'] ),
                    'claim_order'              => sanitize_textarea_field( $_POST['claim_order'] ),
                );

                // Insert into DB
                $result = LRP_DB::insert_claim( $data );

                if ( $result ) {
                    $data['id'] = $result['id'];
                    $data['claim_code'] = $result['code'];
                    $data['created_at'] = current_time('mysql');

                    // Generate PDF
                    $pdf_path = LRP_PDF::generate_pdf( $data );

                    // Send Emails
                    LRP_Email::send_notifications( $result['id'], $pdf_path );

                    // Redirect
                    $redirect_url = add_query_arg( array(
                        'lrp_success' => '1',
                        'code' => urlencode( $result['code'] ),
                        'type' => urlencode( $data['claim_type'] )
                    ), get_permalink() );
                    wp_redirect( $redirect_url );
                    exit;
                } else {
                    $errors[] = "Hubo un error al guardar el reclamo.";
                }
            }
        }
    }

    public function render_shortcode( $atts ) {
        // ... (Styles/Scripts enqueue same) ...
        wp_enqueue_style( 'lrp-style' );
        wp_enqueue_script( 'lrp-script' );

        // Enqueue Turnstile script only when the form is rendered
        $turnstile_site_key = get_option( 'lrp_turnstile_site_key', '' );
        if ( ! empty( $turnstile_site_key ) && wp_script_is( 'lrp-turnstile', 'registered' ) ) {
            wp_enqueue_script( 'lrp-turnstile' );
        }
        
        $locales_opt = get_option('lrp_locales_list', '');
        $locales = array();
        if ( ! empty( $locales_opt ) ) {
            $locales = array_map( 'trim', explode( "\n", $locales_opt ) );
            $locales = array_filter( $locales );
        }
        
        // Get document types from settings
        $document_types_consumer_opt = get_option('lrp_document_types_consumer', '');
        $document_types_consumer = array();
        if ( ! empty( $document_types_consumer_opt ) ) {
            $document_types_consumer = array_map( 'trim', explode( "\n", $document_types_consumer_opt ) );
            $document_types_consumer = array_filter( $document_types_consumer );
        }
        // Default values if empty
        if ( empty( $document_types_consumer ) ) {
            $document_types_consumer = array( 'DNI', 'CE', 'PAS');
        }
        
        $document_types_guardian_opt = get_option('lrp_document_types_guardian', '');
        $document_types_guardian = array();
        if ( ! empty( $document_types_guardian_opt ) ) {
            $document_types_guardian = array_map( 'trim', explode( "\n", $document_types_guardian_opt ) );
            $document_types_guardian = array_filter( $document_types_guardian );
        }
        // Default values if empty
        if ( empty( $document_types_guardian ) ) {
            $document_types_guardian = array( 'DNI', 'CE', 'PAS');
        }

        ob_start();

        if ( isset( $_GET['lrp_success'] ) && $_GET['lrp_success'] === '1' ) {
            // ... (Success message same) ...
            ?>
            <div class="lrp-success-wrapper">
                <div class="lrp-success-icon">
                    <svg width="80" height="80" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="50" cy="50" r="45" fill="#28A745"/>
                        <path d="M28 50L43 65L72 32" stroke="white" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <h2 class="lrp-success-title">¡Formulario enviado con éxito!</h2>
                <?php
                // Show claim code if available
                if ( isset( $_GET['code'] ) && isset( $_GET['type'] ) ) {
                    $claim_code = sanitize_text_field( $_GET['code'] );
                    $claim_type = sanitize_text_field( $_GET['type'] );
                    $type_label = ( stripos( $claim_type, 'queja' ) !== false ) ? 'Queja' : 'Reclamo';
                    ?>
                    <div class="lrp-success-code" style="margin: 20px auto; padding: 15px; background: #f0f8ff; border: 2px solid #28A745; border-radius: 8px; max-width: 400px;">
                        <p style="margin: 0; font-size: 16px; color: #333;">
                            <strong>Código de <?php echo esc_html( $type_label ); ?>:</strong><br>
                            <span style="font-size: 24px; font-weight: bold; color: #28A745; letter-spacing: 2px;"><?php echo esc_html( $claim_code ); ?></span>
                        </p>
                    </div>
                    <?php
                }
                ?>
                <div class="lrp-success-text">
                    <p>Responderemos dentro del plazo máximo de 15 días hábiles, conforme a lo establecido.</p>
                    <p class="lrp-success-sub">Copia enviada a tu correo electrónico (revisa spam si no la encuentras.)</p>
                </div>
                <a href="<?php echo esc_url( remove_query_arg( array( 'lrp_success', 'code', 'type' ) ) ); ?>" class="lrp-btn lrp-submit-btn" style="display:inline-block; text-decoration:none; margin-top:20px;">Volver</a>
            </div>
            <?php
            return ob_get_clean();
        }
        ?>

        <div class="lrp-form-container">
            
            <div class="lrp-progress-bar">
                <div class="lrp-progress-item active">
                    <div class="lrp-step-circle">1</div>
                    <div class="lrp-step-label"></div>
                </div>
                <div class="lrp-progress-item">
                    <div class="lrp-step-circle">2</div>
                    <div class="lrp-step-label"></div>
                </div>
                <div class="lrp-progress-item">
                    <div class="lrp-step-circle">3</div>
                    <div class="lrp-step-label"></div>
                </div>
                 <div class="lrp-progress-item">
                    <div class="lrp-step-circle">4</div>
                    <div class="lrp-step-label"></div>
                </div>
            </div>

            <form method="post" action="" id="lrp-claim-form">
                <input type="hidden" name="lrp_action" value="submit_claim">
                <?php wp_nonce_field( 'lrp_submit_claim_action', 'lrp_form_nonce' ); ?>

                <!-- STEP 1 -->
                <div class="lrp-step">
                    <div class="lrp-step-title">1. IDENTIFICACIÓN DEL CONSUMIDOR RECLAMANTE</div>
                    
                    <div class="lrp-full lrp-checkbox-group">
                        <label style="margin-bottom:5px;">
                            <input type="checkbox" name="is_minor" value="1" id="lrp-is-minor-check">
                            ¿Es menor de edad?
                        </label>
                    </div>

                    <div id="guardian_box" style="display:none; background:#f9f9f9; padding:15px; border:1px solid #eee; margin-bottom:20px;">
                        <p style="margin:0 0 10px 0; font-weight:bold;">Complete los datos del padre, madre o representante:</p>
                        <div class="lrp-row">
                            <div class="lrp-col">
                                 <label>Tipo Documento</label>
                                 <select name="guardian_document_type" class="guardian-input" id="lrp-guardian-doc-type">
                                    <?php foreach ( $document_types_guardian as $doc_type ) : ?>
                                        <option value="<?php echo esc_attr( $doc_type ); ?>"><?php echo esc_html( $doc_type ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="lrp-col">
                                <label>N° de Documento</label>
                                <input type="text" name="guardian_document_number" id="lrp-guardian-doc-number" placeholder="Ingrese N° documento" class="guardian-input">
                            </div>
                        </div>
                        <div class="lrp-row">
                            <div class="lrp-col">
                                <label>Nombre(s)</label>
                                <input type="text" name="guardian_firstname" placeholder="Ingrese nombre(s)" class="guardian-input">
                            </div>
                            <div class="lrp-col">
                                <label>Apellido(s)</label>
                                <input type="text" name="guardian_lastname" placeholder="Ingrese apellido(s)" class="guardian-input">
                            </div>
                        </div>
                    </div>

                    <p style="margin:0 0 10px 0; font-weight:bold;">Datos del Titular reclamante:</p>
                    <div class="lrp-row">
                        <div class="lrp-col">
                            <label>Nombre(s)</label>
                            <input type="text" name="consumer_firstname" required placeholder="Ingrese nombre(s)">
                        </div>
                        <div class="lrp-col">
                            <label>Apellido(s)</label>
                            <input type="text" name="consumer_lastname" required placeholder="Ingrese apellido(s)">
                        </div>
                    </div>
                    
                     <div class="lrp-row">
                        <div class="lrp-col" style="flex: 0 0 auto; min-width: auto;">
                            <label>Tipo de Documento</label>
                            <div style="display:flex; gap:5px;">
                                <select name="consumer_document_type" id="lrp-consumer-doc-type" required style="width: 100%; min-width: 90px; padding-right: 30px;">
                                    <?php foreach ( $document_types_consumer as $doc_type ) : ?>
                                        <option value="<?php echo esc_attr( $doc_type ); ?>"><?php echo esc_html( $doc_type ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="lrp-col" style="min-width: 150px;">
                            <label>N° Documento</label>
                            <input type="text" name="consumer_document_number" id="lrp-consumer-doc-number" required placeholder="Ingrese N° documento">
                        </div>
                        <div class="lrp-col" style="min-width: 150px;">
                            <label>Teléfono</label>
                            <input type="tel" name="consumer_phone" required placeholder="Ingrese teléfono" pattern="[0-9]*" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                        </div>
                         <div class="lrp-col" style="min-width: 150px;">
                            <label>Correo Electrónico</label>
                            <input type="email" name="consumer_email" required placeholder="Ingrese correo electrónico">
                        </div>
                    </div>
                    
                    <div class="lrp-row">
                        <div class="lrp-col">
                            <label>Departamento</label>
                            <input type="text" name="consumer_department" required placeholder="Ingrese departamento">
                        </div>
                        <div class="lrp-col">
                            <label>Provincia</label>
                            <input type="text" name="consumer_province" required placeholder="Ingrese provincia">
                        </div>
                        <div class="lrp-col">
                            <label>Distrito</label>
                            <input type="text" name="consumer_district" required placeholder="Ingrese distrito">
                        </div>
                    </div>
                    
                    <div class="lrp-full">
                        <label>Dirección</label>
                        <input type="text" name="consumer_address" required style="width:100%;" placeholder="Ingrese dirección actual">
                    </div>
                    
                    <div class="lrp-nav-buttons" style="justify-content: flex-end;">
                        <button type="button" class="lrp-btn lrp-next-btn">Siguiente &raquo;</button>
                    </div>
                </div>

                <!-- STEP 2 -->
                <div class="lrp-step">
                    <div class="lrp-step-title">2. IDENTIFICACIÓN DEL BIEN CONTRATADO</div>
                    
                     <div class="lrp-row">
                         <div class="lrp-col">
                            <label>Fecha de compra</label>
                            <input type="date" name="contracted_good_date" id="lrp-purchase-date" required max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>">
                         </div>
                        <div class="lrp-col">
                            <label>Tipo bien contratado</label>
                             <select name="contracted_good_type" required>
                                <option value="">-- Por favor, elija una opción --</option>
                                <option value="producto">Producto</option>
                                <option value="servicio">Servicio</option>
                            </select>
                        </div>
                        <div class="lrp-col">
                            <label>Local</label>
                            <?php if ( ! empty( $locales ) ) : ?>
                                <select name="contracted_good_local" required>
                                    <option value="">-- Seleccione Local --</option>
                                    <?php foreach ( $locales as $local ) : ?>
                                        <option value="<?php echo esc_attr( $local ); ?>"><?php echo esc_html( $local ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else : ?>
                                <input type="text" name="contracted_good_local" required placeholder="Ingrese Local">
                            <?php endif; ?>
                         </div>
                      </div>

                      <div class="lrp-full">
                            <label>Monto reclamado S/</label>
                            <input type="number" step="0.01" min="1" name="contracted_good_amount" required placeholder="Ejm: 120.25">
                      </div>

                    <div class="lrp-full">
                        <label>Descripción</label>
                        <textarea name="contracted_good_description" required style="height:80px;" placeholder="Por favor, proporciona una descripción detallada del producto o servicio..."></textarea>
                    </div>
                    
                    <div class="lrp-nav-buttons">
                        <button type="button" class="lrp-btn lrp-prev-btn">&laquo; Atrás</button>
                        <button type="button" class="lrp-btn lrp-next-btn">Siguiente &raquo;</button>
                    </div>
                </div>

                <!-- STEP 3 -->
                <div class="lrp-step">
                    <div class="lrp-step-title">3. DETALLE DE LA RECLAMACIÓN Y PEDIDO DEL CONSUMIDOR</div>
                     <div class="lrp-full">
                        <label>Motivo</label>
                        <div class="lrp-radio-group">
                            <label class="lrp-radio-option">
                                <input type="radio" name="claim_type" value="queja" required> 
                                <strong>QUEJA:</strong> Disconformidad no relacionada a los productos o servicios; o malestar o descontento respecto a la atención al público.

                            </label>
                            <label class="lrp-radio-option">
                                <input type="radio" name="claim_type" value="reclamo" required> 
                                <strong>RECLAMO:</strong> Disconformidad relacionada a los productos o servicios.
                            </label>
                        </div>
                    </div>

                     <div class="lrp-full">
                        <label>Detalle</label>
                        <textarea name="claim_detail" required placeholder="Cuéntanos sobre la situación..."></textarea>
                    </div>
                     <div class="lrp-full">
                        <label>Pedido del Reclamante</label>
                        <textarea name="claim_order" required placeholder="¿Qué solicitas? Por favor, especifica tu petición."></textarea>
                    </div>
                    
                    <div class="lrp-nav-buttons">
                        <button type="button" class="lrp-btn lrp-prev-btn">&laquo; Atrás</button>
                        <button type="button" class="lrp-btn lrp-next-btn">Siguiente &raquo;</button>
                    </div>
                </div>

                <!-- STEP 4 -->
                <div class="lrp-step">
                     <div class="lrp-step-title">4. OBSERVACIONES Y ACCIONES ADOPTADAS POR EL PROVEEDOR</div>
                     <div style="font-size:14px; color:#555; margin-bottom:20px; line-height: 1.6;">
                         <ul>
                             <li>La respuesta a su reclamo o queja, presentado en Libro de Reclamaciones, se enviará a la dirección de correo electrónico que haya proporcionado.</li>
                             <li>La formulación del reclamo no impide acudir a otras vías de solución de controversias ni es requisito previo para interponer una denuncia ante el INDECOPI.</li>
                             <li>El proveedor debe dar respuesta al reclamo o queja en un plazo no mayor a 15 días hábiles.</li>
                         </ul>
                     </div>
                     
                     <div style="text-align: center; margin-top: 30px;">
                        <p style="font-weight: bold;">Al hacer clic en "ENVIAR RECLAMO", usted acepta lo indicado anteriormente y la veracidad de los datos consignados.</p>
                     </div>

                     <?php
                     $turnstile_site_key = get_option( 'lrp_turnstile_site_key', '' );
                     $turnstile_secret_key = get_option( 'lrp_turnstile_secret_key', '' );
                     if ( ! empty( $turnstile_site_key ) && ! empty( $turnstile_secret_key ) ) :
                     ?>
                     <div class="lrp-turnstile-wrapper">
                        <div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $turnstile_site_key ); ?>"></div>
                        <div class="lrp-turnstile-error" style="display:none;"></div>
                     </div>
                     <?php endif; ?>

                    <div class="lrp-nav-buttons">
                        <button type="button" class="lrp-btn lrp-prev-btn">&laquo; Atrás</button>
                        <button type="submit" class="lrp-btn lrp-submit-btn">ENVIAR RECLAMO</button>
                    </div>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Validate document number based on document type
     * Returns error message string or null if valid
     */
    private static function validate_document_number( $doc_type, $doc_number ) {
        switch ( $doc_type ) {
            case 'DNI':
                if ( ! ctype_digit( $doc_number ) || strlen( $doc_number ) !== 8 ) {
                    return __( 'DNI inválido. Debe tener 8 dígitos numéricos.', 'libro-reclamaciones-peru' );
                }
                break;
            case 'CE':
                if ( ! ctype_digit( $doc_number ) || strlen( $doc_number ) !== 9 ) {
                    return __( 'Carnet de extranjería inválido. Debe tener 9 dígitos numéricos.', 'libro-reclamaciones-peru' );
                }
                break;
            case 'PAS':
                $doc_number_upper = strtoupper( $doc_number );
                if ( ! preg_match( '/^[A-Z0-9]{6,10}$/', $doc_number_upper ) ) {
                    return __( 'Pasaporte inválido. Debe tener entre 6 y 10 caracteres alfanuméricos.', 'libro-reclamaciones-peru' );
                }
                break;
        }
        return null;
    }
}
