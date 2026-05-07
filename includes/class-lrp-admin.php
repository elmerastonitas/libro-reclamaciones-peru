<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LRP_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_init', array( $this, 'process_admin_actions' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
    }

    public function register_menu() {
        // Main Menu -> Dashboard (Inicio)
        add_menu_page(
            'Libro de Reclamaciones',
            'Libro Reclamaciones',
            'manage_options',
            'libro-reclamaciones-peru',
            array( $this, 'render_dashboard_ui' ), // Default to Dashboard
            'dashicons-book',
            6
        );
        
        // Submenu: Inicio (Dashboard) - Explicit
        add_submenu_page(
            'libro-reclamaciones-peru',
            'Inicio',
            'Inicio',
            'manage_options',
            'libro-reclamaciones-peru',
            array( $this, 'render_dashboard_ui' )
        );

        // Submenu: Inbox (Bandeja de Entrada) - New Slug
        add_submenu_page(
            'libro-reclamaciones-peru',
            'Quejas & Reclamos',
            'Quejas & Reclamos',
            'manage_options',
            'lrp-inbox',
            array( $this, 'render_inbox_ui' )
        );

        // Submenu: Settings
        add_submenu_page(
            'libro-reclamaciones-peru',
            'Ajustes',
            'Ajustes',
            'manage_options',
            'lrp-settings',
            array( $this, 'render_settings_page_wrapper' )
        );
    }

    public function enqueue_admin_scripts( $hook ) {
        // Only load on settings page
        if ( 'libro-reclamaciones_page_lrp-settings' !== $hook ) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_script( 'lrp-admin-settings', plugin_dir_url( dirname(__FILE__) ) . 'assets/admin-settings.js', array('jquery'), '1.0', true );
    }

    public function process_admin_actions() {
        // Handle Reply
        if ( isset( $_POST['lrp_admin_action'] ) && $_POST['lrp_admin_action'] === 'reply_claim' ) {
            if ( ! current_user_can( 'manage_options' ) ) return;
            check_admin_referer( 'lrp_reply_claim', 'lrp_nonce' );

            $claim_id = intval( $_POST['claim_id'] );
            $response = sanitize_textarea_field( $_POST['admin_response'] );
            $status = 'atendido';
            
            $evidence_url = '';
            if ( ! empty( $_FILES['evidence_file']['name'] ) ) {
                require_once( ABSPATH . 'wp-admin/includes/file.php' );
                $uploaded = wp_handle_upload( $_FILES['evidence_file'], array( 'test_form' => false ) );
                if ( ! isset( $uploaded['error'] ) ) {
                    $evidence_url = $uploaded['url'];
                }
            }

            LRP_DB::update_response( $claim_id, $response, $status, $evidence_url );

            // Regenerate PDF
            $updated_claim = LRP_DB::get_claim( $claim_id );
            $updated_claim_arr = (array) $updated_claim;
            $pdf_path = LRP_PDF::generate_pdf( $updated_claim_arr );

            // Send Response Email
            LRP_Email::send_response_email( $claim_id, $response, $evidence_url, $pdf_path );
            
            // Redirect to Inbox View
            wp_redirect( admin_url( 'admin.php?page=lrp-inbox&action=view&id=' . $claim_id . '&updated=1' ) );
            exit;
        }

        // Handle Settings Save
        if ( isset( $_POST['lrp_admin_action'] ) && $_POST['lrp_admin_action'] === 'save_settings' ) {
            if ( ! current_user_can( 'manage_options' ) ) return;
            check_admin_referer( 'lrp_save_settings', 'lrp_nonce' );

            // Tab: General
            if ( isset( $_POST['lrp_notification_email'] ) ) {
                update_option( 'lrp_notification_email', sanitize_email( $_POST['lrp_notification_email'] ) );
            }
            if ( isset( $_POST['lrp_locales_list'] ) ) {
                $locales = sanitize_textarea_field( $_POST['lrp_locales_list'] );
                update_option( 'lrp_locales_list', $locales );
            }
            if ( isset( $_POST['lrp_document_types_consumer'] ) ) {
                $document_types_consumer = sanitize_textarea_field( $_POST['lrp_document_types_consumer'] );
                update_option( 'lrp_document_types_consumer', $document_types_consumer );
            }
            if ( isset( $_POST['lrp_document_types_guardian'] ) ) {
                $document_types_guardian = sanitize_textarea_field( $_POST['lrp_document_types_guardian'] );
                update_option( 'lrp_document_types_guardian', $document_types_guardian );
            }
            
            // Provider Signature
            if ( isset( $_POST['lrp_provider_signature_url'] ) ) {
                $signature_url = esc_url_raw( $_POST['lrp_provider_signature_url'] );
                
                // Validate PNG format
                if ( ! empty( $signature_url ) ) {
                    $file_ext = strtolower( pathinfo( parse_url( $signature_url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
                    if ( $file_ext === 'png' ) {
                        update_option( 'lrp_provider_signature_url', $signature_url );
                    }
                } else {
                    update_option( 'lrp_provider_signature_url', '' );
                }
            }
            
            // Handle signature deletion
            if ( isset( $_POST['lrp_delete_signature'] ) && $_POST['lrp_delete_signature'] === '1' ) {
                delete_option( 'lrp_provider_signature_url' );
            }

            // Tab: Captcha
            if ( isset( $_POST['lrp_turnstile_site_key'] ) ) {
                update_option( 'lrp_turnstile_site_key', sanitize_text_field( $_POST['lrp_turnstile_site_key'] ) );
            }
            if ( isset( $_POST['lrp_turnstile_secret_key'] ) ) {
                update_option( 'lrp_turnstile_secret_key', sanitize_text_field( $_POST['lrp_turnstile_secret_key'] ) );
            }

            // Tab: Email
            if ( isset( $_POST['lrp_email_consumer_subject'] ) ) {
                update_option( 'lrp_email_consumer_subject', sanitize_text_field( $_POST['lrp_email_consumer_subject'] ) );
            }
            if ( isset( $_POST['lrp_email_consumer_body'] ) ) {
                update_option( 'lrp_email_consumer_body', wp_kses_post( $_POST['lrp_email_consumer_body'] ) );
            }
            if ( isset( $_POST['lrp_email_response_subject'] ) ) {
                update_option( 'lrp_email_response_subject', sanitize_text_field( $_POST['lrp_email_response_subject'] ) );
            }
            if ( isset( $_POST['lrp_email_response_body'] ) ) {
                update_option( 'lrp_email_response_body', wp_kses_post( $_POST['lrp_email_response_body'] ) );
            }

            // Tab: Email - SMTP Settings
            if ( isset( $_POST['lrp_smtp_host'] ) ) {
                update_option( 'lrp_smtp_host', sanitize_text_field( $_POST['lrp_smtp_host'] ) );
            }
            if ( isset( $_POST['lrp_smtp_port'] ) ) {
                update_option( 'lrp_smtp_port', intval( $_POST['lrp_smtp_port'] ) );
            }
            if ( isset( $_POST['lrp_smtp_encryption'] ) ) {
                update_option( 'lrp_smtp_encryption', sanitize_text_field( $_POST['lrp_smtp_encryption'] ) );
            }
            if ( isset( $_POST['lrp_smtp_user'] ) ) {
                update_option( 'lrp_smtp_user', sanitize_text_field( $_POST['lrp_smtp_user'] ) );
            }
            if ( isset( $_POST['lrp_smtp_pass'] ) ) {
                // Only update password if a new value is provided (not the placeholder)
                $new_pass = $_POST['lrp_smtp_pass'];
                if ( ! empty( $new_pass ) ) {
                    update_option( 'lrp_smtp_pass', $new_pass );
                }
            }
            if ( isset( $_POST['lrp_smtp_from_email'] ) ) {
                update_option( 'lrp_smtp_from_email', sanitize_email( $_POST['lrp_smtp_from_email'] ) );
            }
            if ( isset( $_POST['lrp_smtp_from_name'] ) ) {
                update_option( 'lrp_smtp_from_name', sanitize_text_field( $_POST['lrp_smtp_from_name'] ) );
            }

            $current_tab = isset($_POST['current_tab']) ? $_POST['current_tab'] : 'general';
            wp_redirect( admin_url( 'admin.php?page=lrp-settings&tab=' . $current_tab . '&updated=1' ) );
            exit;
        }
    }

    // Dashboard Page (Inicio)
    public function render_dashboard_ui() {
        // Backwards compatibility: Checks for action=view (legacy email links)
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'view' ) {
            $this->render_inbox_ui();
            return;
        }

        // Dashboard Content
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Libro de Reclamaciones</h1>
            
            <div style="background:#fff; padding:30px; border:1px solid #ccc; max-width:800px; margin-top:20px; box-shadow:0 1px 1px rgba(0,0,0,.04);">
                <p style="font-size:16px;">
                    En cumplimiento del Reglamento del Libro de Reclamaciones y la Ley N.º 29571, Código de Protección y Defensa del Consumidor.<br>
                    Esta herramienta permite <strong>Gestionar los Reclamos y Quejas</strong> que los usuarios registren.
                </p>

                <p style="margin-top:30px; font-size:15px;">
                <strong>Uso</strong><br>
                Usa el shortcode <code>[libro_reclamaciones]</code> para agregar el formulario en cualquier página.
            </p>

            <h2 style="margin-top:30px; border-bottom:1px solid #eee; padding-bottom:10px;">Accesos directos</h2>
                <div style="display:flex; gap:20px; margin-top:20px;">
                    <a href="<?php echo admin_url('admin.php?page=lrp-inbox'); ?>" class="button button-primary button-hero">
                        Quejas & Reclamos
                    </a>
                    
                    <a href="<?php echo admin_url('admin.php?page=lrp-settings'); ?>" class="button button-secondary button-hero">
                        Ajustes
                    </a>
                </div>
            </div>

            <div style="margin-top:50px; color:#666; font-style:italic;">
                <hr>
                <p>Powered by Elmer Astonitas</p>
            </div>
        </div>
        <?php
    }

    // Inbox Page (Bandeja de Entrada)
    public function render_inbox_ui() {
        // Determine URL base for links (Use current page slug)
        $page_slug = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 'lrp-inbox';
        $action = isset( $_GET['action'] ) ? $_GET['action'] : 'list';
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Libro de Reclamaciones - Quejas & Reclamos</h1>
            
            <?php if ( $action === 'view' ) : ?>
                <!-- Breadcrumb or back button -->
            <?php else : ?>
                <!-- List View specific actions -->
            <?php endif; ?>
            <hr class="wp-header-end">

            <?php
            if ( $action === 'view' ) {
                $this->render_detail_view( $page_slug );
            } else {
                $this->render_list_view( $page_slug );
            }
            ?>
        </div>
        <?php
    }

    // New Settings Page Wrapper with Tabs
    public function render_settings_page_wrapper() {
        $active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'general';
        ?>
        <div class="wrap">
            <h1>Ajustes de Libro de Reclamaciones</h1>
            <nav class="nav-tab-wrapper">
                <a href="?page=lrp-settings&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">General</a>
                <a href="?page=lrp-settings&tab=email" class="nav-tab <?php echo $active_tab == 'email' ? 'nav-tab-active' : ''; ?>">Correo</a>
                <a href="?page=lrp-settings&tab=personalizar" class="nav-tab <?php echo $active_tab == 'personalizar' ? 'nav-tab-active' : ''; ?>">Personalizar</a>
                <a href="?page=lrp-settings&tab=captcha" class="nav-tab <?php echo $active_tab == 'captcha' ? 'nav-tab-active' : ''; ?>">Captcha</a>
            </nav>

            <form method="post" action="">
                <input type="hidden" name="lrp_admin_action" value="save_settings">
                <input type="hidden" name="current_tab" value="<?php echo esc_attr($active_tab); ?>">
                <?php wp_nonce_field( 'lrp_save_settings', 'lrp_nonce' ); ?>

                <div class="tab-content" style="background:#fff; padding:20px; border:1px solid #ccc; border-top:none;">
                    <?php
                    if ( isset( $_GET['updated'] ) ) {
                        echo '<div class="notice notice-success is-dismissible"><p>Ajustes guardados.</p></div>';
                    }

                    switch ( $active_tab ) {
                        case 'general':
                            $this->render_tab_general();
                            break;
                        case 'email':
                            $this->render_tab_email();
                            break;
                        case 'personalizar':
                            $this->render_tab_personalizar();
                            break;
                        case 'captcha':
                            $this->render_tab_captcha();
                            break;
                        default:
                            $this->render_tab_general();
                            break;
                    }
                    ?>
                    
                    <?php if ( $active_tab !== 'personalizar' ) : ?>
                    <p class="submit">
                        <input type="submit" name="submit" id="submit" class="button button-primary" value="Guardar Cambios">
                    </p>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <?php
    }

    private function render_tab_general() {
        $email = get_option( 'lrp_notification_email', get_option( 'admin_email' ) );
        $locales = get_option( 'lrp_locales_list', '' );
        $document_types_consumer = get_option( 'lrp_document_types_consumer', '' );
        $document_types_guardian = get_option( 'lrp_document_types_guardian', '' );
        $signature_url = get_option( 'lrp_provider_signature_url', '' );
        ?>
        <h3>Configuración General</h3>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="lrp_notification_email">Email para Notificaciones</label></th>
                <td>
                    <input name="lrp_notification_email" type="email" id="lrp_notification_email" value="<?php echo esc_attr( $email ); ?>" class="regular-text">
                    <p class="description">A este correo llegarán los avisos de nuevos reclamos.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="lrp_locales_list">Locales Disponibles</label></th>
                <td>
                    <textarea name="lrp_locales_list" id="lrp_locales_list" rows="5" class="large-text code"><?php echo esc_textarea( $locales ); ?></textarea>
                    <p class="description">Ingrese un local por línea. Si se deja vacío, el campo será un texto libre.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="lrp_document_types_consumer">Tipos de Documento – Titular</label></th>
                <td>
                    <textarea name="lrp_document_types_consumer" id="lrp_document_types_consumer" rows="5" class="large-text code"><?php echo esc_textarea( $document_types_consumer ); ?></textarea>
                    <p class="description">Ingrese un tipo de documento por línea.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="lrp_document_types_guardian">Tipos de Documento – Representante (menor de edad)</label></th>
                <td>
                    <textarea name="lrp_document_types_guardian" id="lrp_document_types_guardian" rows="5" class="large-text code"><?php echo esc_textarea( $document_types_guardian ); ?></textarea>
                    <p class="description">Ingrese un tipo de documento por línea.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Firma del Proveedor</label></th>
                <td>
                    <input type="hidden" name="lrp_provider_signature_url" id="lrp_provider_signature_url" value="<?php echo esc_attr( $signature_url ); ?>">
                    <input type="hidden" name="lrp_delete_signature" id="lrp_delete_signature" value="0">
                    
                    <div style="margin-bottom: 10px;">
                        <button type="button" class="button" id="lrp_upload_signature_button">
                            <?php echo ! empty( $signature_url ) ? 'Cambiar Firma' : 'Subir Firma'; ?>
                        </button>
                        <?php if ( ! empty( $signature_url ) ) : ?>
                            <button type="button" class="button" id="lrp_remove_signature_button" style="margin-left: 5px;">Eliminar Firma</button>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ( ! empty( $signature_url ) ) : ?>
                        <div id="lrp_signature_preview" style="margin: 10px 0; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; max-width: 400px;">
                            <img src="<?php echo esc_url( $signature_url ); ?>" style="max-width: 100%; height: auto; display: block;">
                        </div>
                    <?php else : ?>
                        <div id="lrp_signature_preview" style="display: none; margin: 10px 0; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; max-width: 400px;">
                            <img src="" style="max-width: 100%; height: auto; display: block;">
                        </div>
                    <?php endif; ?>
                    
                    <p class="description">
                        <strong>Formato:</strong> Solo PNG con fondo transparente<br>
                        <strong>Tamaño recomendado:</strong> Ancho máximo 300px, Alto máximo 120px<br>
                        <strong>Resolución sugerida:</strong> 150-300 DPI<br>
                        <strong>Tamaño máximo de archivo:</strong> 2MB
                    </p>
                    <p class="description">
                        Esta firma se insertará automáticamente en el PDF del libro de reclamaciones y en el correo de respuesta.
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    private function render_tab_email() {
        // Defaults
        $def_cons_sub = 'Copia de su {type_label} - {blog_name}';
        $def_cons_body = "Estimado(a) {fullname},\n\nHemos recibido su {type_label_lower} con código: {claim_code}.\nAdjuntamos una copia en PDF de su hoja de reclamación.\n\nAtentamente,\n{blog_name}";
        
        $def_resp_sub = 'Respuesta a su {type_label} {claim_code} - {blog_name}';
        $def_resp_body = "Estimado(a) {fullname},\n\nHemos atendido su {type_label_lower} con código: {claim_code}.\n\nRespuesta del Proveedor:\n--------------------------------------------------\n{response}\n--------------------------------------------------\n\nAtentamente,\n{blog_name}";

        $cons_sub = get_option( 'lrp_email_consumer_subject', $def_cons_sub );
        $cons_body = get_option( 'lrp_email_consumer_body', $def_cons_body );
        $resp_sub = get_option( 'lrp_email_response_subject', $def_resp_sub );
        $resp_body = get_option( 'lrp_email_response_body', $def_resp_body );
        ?>
        <h3>Personalización de Correos</h3>
        <p class="description">
            Variables disponibles: <code>{claim_code}</code>, <code>{fullname}</code>, <code>{type_label}</code> (Ej: Reclamo/Queja), 
            <code>{type_label_lower}</code> (Ej: reclamo/queja), <code>{blog_name}</code>, <code>{response}</code> (Solo para respuesta), 
            <code>{signature}</code> (Firma del proveedor, solo para respuesta).
        </p>
        <hr>

        <h4>1. Correo de Copia al Usuario (Confirmación)</h4>
        <table class="form-table">
            <tr>
                <th scope="row"><label>Asunto</label></th>
                <td><input name="lrp_email_consumer_subject" type="text" value="<?php echo esc_attr( $cons_sub ); ?>" class="large-text"></td>
            </tr>
            <tr>
                <th scope="row"><label>Cuerpo del Mensaje</label></th>
                <td>
                    <?php 
                    wp_editor( $cons_body, 'lrp_email_consumer_body', array( 'textarea_name' => 'lrp_email_consumer_body', 'textarea_rows' => 8, 'media_buttons' => false ) ); 
                    ?>
                </td>
            </tr>
        </table>
        <hr>

        <h4>2. Correo de Respuesta del Proveedor</h4>
        <table class="form-table">
            <tr>
                <th scope="row"><label>Asunto</label></th>
                <td><input name="lrp_email_response_subject" type="text" value="<?php echo esc_attr( $resp_sub ); ?>" class="large-text"></td>
            </tr>
            <tr>
                <th scope="row"><label>Cuerpo del Mensaje</label></th>
                <td>
                    <?php 
                    wp_editor( $resp_body, 'lrp_email_response_body', array( 'textarea_name' => 'lrp_email_response_body', 'textarea_rows' => 8, 'media_buttons' => false ) ); 
                    ?>
                </td>
            </tr>
        </table>

        <hr>
        <h3>Configuración SMTP</h3>
        <p class="description">
            <?php _e( 'Configura un servidor SMTP para garantizar el envío de correos electrónicos. Si usas Gmail, el host es <code>smtp.gmail.com</code>, puerto <code>587</code>, cifrado <code>TLS</code>.', 'libro-reclamaciones-peru' ); ?>
        </p>

        <?php
        $smtp_host       = get_option( 'lrp_smtp_host', '' );
        $smtp_port       = get_option( 'lrp_smtp_port', 587 );
        $smtp_encryption = get_option( 'lrp_smtp_encryption', 'tls' );
        $smtp_user       = get_option( 'lrp_smtp_user', '' );
        $smtp_pass       = get_option( 'lrp_smtp_pass', '' );
        $smtp_from_email = get_option( 'lrp_smtp_from_email', '' );
        $smtp_from_name  = get_option( 'lrp_smtp_from_name', '' );

        if ( empty( $smtp_host ) ) {
            echo '<div class="notice notice-warning inline" style="margin:10px 0;"><p><strong>' . __( 'SMTP no está configurado.', 'libro-reclamaciones-peru' ) . '</strong> ' . __( 'Los correos electrónicos podrían no enviarse correctamente sin una configuración SMTP válida.', 'libro-reclamaciones-peru' ) . '</p></div>';
        } else {
            echo '<div class="notice notice-success inline" style="margin:10px 0;"><p><strong>' . __( 'SMTP configurado:', 'libro-reclamaciones-peru' ) . '</strong> ' . esc_html( $smtp_host ) . ':' . esc_html( $smtp_port ) . ' (' . esc_html( strtoupper( $smtp_encryption ) ) . ')</p></div>';
        }
        ?>

        <table class="form-table">
            <tr>
                <th scope="row"><label for="lrp_smtp_host"><?php _e( 'Servidor SMTP (Host)', 'libro-reclamaciones-peru' ); ?></label></th>
                <td><input name="lrp_smtp_host" id="lrp_smtp_host" type="text" value="<?php echo esc_attr( $smtp_host ); ?>" class="regular-text" placeholder="smtp.gmail.com"></td>
            </tr>
            <tr>
                <th scope="row"><label for="lrp_smtp_port"><?php _e( 'Puerto', 'libro-reclamaciones-peru' ); ?></label></th>
                <td><input name="lrp_smtp_port" id="lrp_smtp_port" type="number" value="<?php echo esc_attr( $smtp_port ); ?>" class="small-text" placeholder="587"></td>
            </tr>
            <tr>
                <th scope="row"><label for="lrp_smtp_encryption"><?php _e( 'Cifrado', 'libro-reclamaciones-peru' ); ?></label></th>
                <td>
                    <select name="lrp_smtp_encryption" id="lrp_smtp_encryption">
                        <option value="" <?php selected( $smtp_encryption, '' ); ?>><?php _e( 'Ninguno', 'libro-reclamaciones-peru' ); ?></option>
                        <option value="ssl" <?php selected( $smtp_encryption, 'ssl' ); ?>>SSL</option>
                        <option value="tls" <?php selected( $smtp_encryption, 'tls' ); ?>>TLS</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="lrp_smtp_user"><?php _e( 'Usuario SMTP', 'libro-reclamaciones-peru' ); ?></label></th>
                <td><input name="lrp_smtp_user" id="lrp_smtp_user" type="text" value="<?php echo esc_attr( $smtp_user ); ?>" class="regular-text" placeholder="tu@gmail.com"></td>
            </tr>
            <tr>
                <th scope="row"><label for="lrp_smtp_pass"><?php _e( 'Contraseña SMTP', 'libro-reclamaciones-peru' ); ?></label></th>
                <td><input name="lrp_smtp_pass" id="lrp_smtp_pass" type="password" value="" class="regular-text" placeholder="<?php echo ! empty( $smtp_pass ) ? '••••••••' : ''; ?>"><p class="description"><?php _e( 'Para Gmail, usa una Contraseña de Aplicación (no tu contraseña normal).', 'libro-reclamaciones-peru' ); ?> <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener">Crear contraseña de aplicación →</a></p></td>
            </tr>
            <tr>
                <th scope="row"><label for="lrp_smtp_from_email"><?php _e( 'Correo del Remitente (From)', 'libro-reclamaciones-peru' ); ?></label></th>
                <td><input name="lrp_smtp_from_email" id="lrp_smtp_from_email" type="email" value="<?php echo esc_attr( $smtp_from_email ); ?>" class="regular-text" placeholder="tu@gmail.com"></td>
            </tr>
            <tr>
                <th scope="row"><label for="lrp_smtp_from_name"><?php _e( 'Nombre del Remitente', 'libro-reclamaciones-peru' ); ?></label></th>
                <td><input name="lrp_smtp_from_name" id="lrp_smtp_from_name" type="text" value="<?php echo esc_attr( $smtp_from_name ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"></td>
            </tr>
        </table>
        <?php
    }

    private function render_tab_personalizar() {
        echo '<h3>Personalizar Diseño</h3>';
        echo '<p>Esta sección está reservada para futuras opciones de personalización visual del formulario.</p>';
    }

    private function render_tab_captcha() {
        $site_key   = get_option( 'lrp_turnstile_site_key', '' );
        $secret_key = get_option( 'lrp_turnstile_secret_key', '' );
        ?>
        <h3><?php _e( 'Cloudflare Turnstile', 'libro-reclamaciones-peru' ); ?></h3>
        <p class="description">
            <?php _e( 'Turnstile es la alternativa CAPTCHA inteligente de Cloudflare, que confirma que los visitantes de tu sitio web sean reales y bloquea bots indeseados sin ralentizar la experiencia web.', 'libro-reclamaciones-peru' ); ?>
        </p>
        <p>
            <a href="https://dash.cloudflare.com/sign-up?to=/:account/turnstile" target="_blank" rel="noopener noreferrer">
                <?php _e( 'Crear claves en Cloudflare Turnstile', 'libro-reclamaciones-peru' ); ?> &rarr;
            </a>
        </p>

        <?php if ( empty( $site_key ) || empty( $secret_key ) ) : ?>
            <div class="notice notice-warning inline" style="margin: 15px 0;">
                <p>
                    <strong><?php _e( 'Turnstile no está configurado.', 'libro-reclamaciones-peru' ); ?></strong>
                    <?php _e( 'El formulario funcionará sin protección anti-spam hasta que configure ambas claves.', 'libro-reclamaciones-peru' ); ?>
                </p>
            </div>
        <?php else : ?>
            <div class="notice notice-success inline" style="margin: 15px 0;">
                <p>&#10004; <?php _e( 'Turnstile está activo en este sitio.', 'libro-reclamaciones-peru' ); ?></p>
            </div>
        <?php endif; ?>

        <table class="form-table">
            <tr>
                <th scope="row"><label for="lrp_turnstile_site_key"><?php _e( 'Clave del sitio (Site Key)', 'libro-reclamaciones-peru' ); ?></label></th>
                <td>
                    <input name="lrp_turnstile_site_key" type="text" id="lrp_turnstile_site_key" value="<?php echo esc_attr( $site_key ); ?>" class="regular-text" autocomplete="off">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="lrp_turnstile_secret_key"><?php _e( 'Clave secreta (Secret Key)', 'libro-reclamaciones-peru' ); ?></label></th>
                <td>
                    <input name="lrp_turnstile_secret_key" type="password" id="lrp_turnstile_secret_key" value="<?php echo esc_attr( $secret_key ); ?>" class="regular-text" autocomplete="off">
                </td>
            </tr>
        </table>
        <?php
    }

    private function render_list_view( $page_slug ) {
        global $wpdb;
        $table_name = LRP_DB::get_table_name();
        $claims = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY created_at DESC" );

        echo '<table class="wp-list-table widefat fixed striped" style="margin-top:20px;">';
        echo '<thead><tr>
            <th>Código</th>
            <th>Fecha</th>
            <th>Cliente</th>
            <th>Motivo</th>
            <th>Estado</th>
            <th>Acciones</th>
        </tr></thead>';
        echo '<tbody>';
        
        if ( $claims ) {
            foreach ( $claims as $claim ) {
                $status_label = ucfirst( $claim->status );
                // Use page_slug to determine edit link (Keeps in 'lrp-inbox' context)
                $edit_link = admin_url( 'admin.php?page=' . $page_slug . '&action=view&id=' . $claim->id );
                
                echo '<tr>';
                echo '<td><strong><a href="' . $edit_link . '">' . esc_html( $claim->claim_code ) . '</a></strong></td>';
                echo '<td>' . esc_html( $claim->created_at ) . '</td>';
                
                $fullname = isset($claim->consumer_firstname) ? $claim->consumer_firstname . ' ' . $claim->consumer_lastname : $claim->consumer_name;
                echo '<td>' . esc_html( $fullname ) . '</td>';
                
                echo '<td>' . esc_html( $claim->claim_type ) . '</td>';
                echo '<td>' . esc_html( $status_label ) . '</td>';
                // Generate PDF URL (new format: CODE.pdf, fallback to old: Prefix-CODE.pdf)
                $upload_dir = wp_upload_dir();
                $pdf_filename = $claim->claim_code . '.pdf';
                $pdf_path = $upload_dir['basedir'] . '/libro_reclamaciones/' . $pdf_filename;
                // Fallback for old records with Queja-/Reclamo- prefix
                if ( ! file_exists( $pdf_path ) ) {
                    $old_prefix = ( stripos( $claim->claim_type, 'queja' ) !== false ) ? 'Queja' : 'Reclamo';
                    $pdf_filename = $old_prefix . '-' . $claim->claim_code . '.pdf';
                    $pdf_path = $upload_dir['basedir'] . '/libro_reclamaciones/' . $pdf_filename;
                }
                $cache_buster = file_exists( $pdf_path ) ? filemtime( $pdf_path ) : time();
                $pdf_url = $upload_dir['baseurl'] . '/libro_reclamaciones/' . $pdf_filename . '?v=' . $cache_buster;
                
                echo '<td>';
                echo '<a href="' . esc_url($pdf_url) . '" target="_blank" class="button button-small">Ver el PDF</a> ';
                echo '<a href="' . $edit_link . '" class="button button-small">Gestionar</a>';
                echo '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="6">No se encontraron reclamos.</td></tr>';
        }

        echo '</tbody></table>';
    }

    private function render_detail_view( $page_slug ) {
        $id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
        $claim = LRP_DB::get_claim( $id );

        if ( ! $claim ) {
            echo '<div class="error"><p>Reclamo no encontrado.</p></div>';
            return;
        }

        if ( isset( $_GET['updated'] ) ) {
            echo '<div class="updated"><p>Respuesta guardada y notificada al cliente.</p></div>';
        }

        $upload_dir = wp_upload_dir();
        $pdf_filename = $claim->claim_code . '.pdf';
        $pdf_path = $upload_dir['basedir'] . '/libro_reclamaciones/' . $pdf_filename;
        // Fallback for old records with Queja-/Reclamo- prefix
        if ( ! file_exists( $pdf_path ) ) {
            $old_prefix = ( stripos( $claim->claim_type, 'queja' ) !== false ) ? 'Queja' : 'Reclamo';
            $pdf_filename = $old_prefix . '-' . $claim->claim_code . '.pdf';
            $pdf_path = $upload_dir['basedir'] . '/libro_reclamaciones/' . $pdf_filename;
        }
        
        // Add timestamp to prevent caching - use file modification time if exists, otherwise current time
        $cache_buster = file_exists( $pdf_path ) ? filemtime( $pdf_path ) : time();
        $pdf_url = $upload_dir['baseurl'] . '/libro_reclamaciones/' . $pdf_filename . '?v=' . $cache_buster;

        // Back link uses the same page_slug (Inbox or Main)
        echo '<h2>Detalle del Reclamo: ' . esc_html( $claim->claim_code ) . ' <a href="' . admin_url('admin.php?page=' . $page_slug) . '" class="page-title-action">Volver a la lista</a>  <a href="' . esc_url($pdf_url) . '" target="_blank" class="page-title-action" style="background:#0073aa; color:#fff;">Ver Hoja de Reclamación (PDF)</a></h2>';
        
        ?>
        <div style="display:flex; gap:20px; flex-wrap:wrap; margin-top:20px;">
            <div style="flex:2; min-width:300px; background:#fff; padding:20px; border:1px solid #ccc; box-shadow:0 1px 1px rgba(0,0,0,.04);">
                <h3>Información del Reclamo (Solo Lectura)</h3>
                <?php
                $fullname = isset($claim->consumer_firstname) ? $claim->consumer_firstname . ' ' . $claim->consumer_lastname : $claim->consumer_name;
                ?>
                <table class="form-table">
                    <tr><th>Fecha Reclamo:</th><td><?php echo $claim->created_at; ?></td></tr>
                    <tr><th>Nombre:</th><td><?php echo esc_html( $fullname ); ?></td></tr>
                    <tr><th>Documento:</th><td><?php echo esc_html( $claim->consumer_document_type . ' ' . $claim->consumer_document_number ); ?></td></tr>
                    <tr><th>Email:</th><td><a href="mailto:<?php echo esc_attr( $claim->consumer_email ); ?>"><?php echo esc_html( $claim->consumer_email ); ?></a></td></tr>
                    <tr><th>Teléfono:</th><td><?php echo esc_html( $claim->consumer_phone ); ?></td></tr>
                    <tr><th>Ubicación:</th><td><?php echo esc_html( $claim->consumer_department . ' / ' . $claim->consumer_province . ' / ' . $claim->consumer_district ); ?></td></tr>
                    <tr><th>Dirección:</th><td><?php echo esc_html( $claim->consumer_address ); ?></td></tr>
                    
                    <tr><th colspan="2"><hr></th></tr>
                    <tr><th colspan="2"><strong>Bien Contratado:</strong></th></tr>
                    <tr><th>Tipo:</th><td><?php echo esc_html( strtoupper($claim->contracted_good_type) ); ?></td></tr>
                    <tr><th>Fecha Compra/Incidente:</th><td><?php echo esc_html( $claim->contracted_good_date ); ?></td></tr>
                    <tr><th>Monto Reclamado:</th><td>S/ <?php echo esc_html( $claim->contracted_good_amount ); ?></td></tr>
                    <tr><th>Local:</th><td><?php echo esc_html( $claim->contracted_good_local ); ?></td></tr>
                    <tr><th>Descripción (Bien):</th><td><div style="background:#f9f9f9; padding:10px; border:1px solid #ddd;"><?php echo nl2br( esc_html( $claim->contracted_good_description ) ); ?></div></td></tr>

                    <?php 
                    // Logic to handle Name/Surname display matching PDF fallback and preventing "0"
                    // First Name Logic
                    $g_fname = isset($claim->guardian_firstname) ? $claim->guardian_firstname : '';
                    if ( empty($g_fname) || $g_fname == '0' ) {
                         $g_fname = isset($claim->guardian_name) ? $claim->guardian_name : '';
                    }
                    if ( empty($g_fname) || $g_fname == '0' ) { 
                        $g_fname = '—'; 
                    }
                    // Last Name Logic
                    $g_lname = isset($claim->guardian_lastname) ? $claim->guardian_lastname : '';
                    if ( empty($g_lname) || $g_lname == '0' ) { 
                        $g_lname = '—'; 
                    }

                    if ( ( isset($claim->is_minor) && $claim->is_minor == 1 ) || $g_fname !== '—' ) : 
                    ?>
                        <tr><th colspan="2"><hr></th></tr>
                        <tr><th colspan="2"><strong>Datos del Apoderado (Menor de Edad):</strong></th></tr>
                        <tr><th>Documento:</th><td><?php echo esc_html( $claim->guardian_document_type . ' ' . $claim->guardian_document_number ); ?></td></tr>
                        <tr><th>Nombre:</th><td><?php echo esc_html( $g_fname ); ?></td></tr>
                        <tr><th>Apellido:</th><td><?php echo esc_html( $g_lname ); ?></td></tr>
                    <?php endif; ?>

                    <tr><th colspan="2"><hr></th></tr>
                    <tr><th colspan="2"><strong>Detalle del Reclamo:</strong></th></tr>
                    <tr><th>Tipo:</th><td><strong><?php echo esc_html( strtoupper($claim->claim_type) ); ?></strong></td></tr>
                    <tr><th>Detalle:</th><td><div style="background:#f9f9f9; padding:10px; border:1px solid #ddd;"><?php echo nl2br( esc_html( $claim->claim_detail ) ); ?></div></td></tr>
                    <tr><th>Pedido:</th><td><div style="background:#f9f9f9; padding:10px; border:1px solid #ddd;"><?php echo nl2br( esc_html( $claim->claim_order ) ); ?></div></td></tr>
                </table>
            </div>
            
            <div style="flex:1; min-width:300px; background:#fff; padding:20px; border:1px solid #ccc; box-shadow:0 1px 1px rgba(0,0,0,.04); height:fit-content;">
                <h3>Respuesta del Proveedor</h3>
                <form method="post" action="" enctype="multipart/form-data">
                    <input type="hidden" name="lrp_admin_action" value="reply_claim">
                    <input type="hidden" name="claim_id" value="<?php echo $claim->id; ?>">
                    <?php wp_nonce_field( 'lrp_reply_claim', 'lrp_nonce' ); ?>
                    
                    <p>Escriba la respuesta que se guardará en el sistema. Nota: Esto no envía el email respuesta automáticamente (se debe enviar aparte o integrar), pero registra la acción.</p>
                    <textarea name="admin_response" rows="10" style="width:100%;" placeholder="Escriba su respuesta aquí..."><?php echo esc_textarea( $claim->admin_response ); ?></textarea>
                    
                    <?php if ( ! empty( $claim->evidence_file ) ) : ?>
                        <p style="margin-top:10px;"><strong>Evidencia Adjunta:</strong> <br> <a href="<?php echo esc_url( $claim->evidence_file ); ?>" target="_blank" class="button">Ver Archivo</a></p>
                    <?php endif; ?>
                    
                    <p style="margin-top:20px;">
                        <label><strong>Adjuntar Nueva Evidencia:</strong></label><br>
                        <input type="file" name="evidence_file">
                    </p>
                    
                    <hr>
                    <button type="submit" class="button button-primary button-large" style="width:100%;">Guardar Respuesta</button>
                    <p class="description" style="text-align:center;">Estado cambiará a "Atendido"</p>
                </form>
            </div>
        </div>
        <?php
    }
}
