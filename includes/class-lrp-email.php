<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LRP_Email {

    /**
     * Initialize SMTP configuration hook
     * Called once when the class is loaded
     */
    public static function init() {
        // Configure SMTP if settings are present
        add_action( 'phpmailer_init', array( __CLASS__, 'configure_smtp' ) );

        // Log wp_mail failures for debugging
        add_action( 'wp_mail_failed', array( __CLASS__, 'log_mail_error' ) );
    }

    /**
     * Configure PHPMailer with SMTP settings from plugin options
     */
    public static function configure_smtp( $phpmailer ) {
        $smtp_host = get_option( 'lrp_smtp_host', '' );

        // Only configure if SMTP host is set
        if ( empty( $smtp_host ) ) {
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host       = $smtp_host;
        $phpmailer->Port       = intval( get_option( 'lrp_smtp_port', 587 ) );
        $phpmailer->SMTPSecure = get_option( 'lrp_smtp_encryption', 'tls' );

        $smtp_user = get_option( 'lrp_smtp_user', '' );
        $smtp_pass = get_option( 'lrp_smtp_pass', '' );

        if ( ! empty( $smtp_user ) && ! empty( $smtp_pass ) ) {
            $phpmailer->SMTPAuth = true;
            $phpmailer->Username = $smtp_user;
            $phpmailer->Password = $smtp_pass;
        } else {
            $phpmailer->SMTPAuth = false;
        }

        // Set From address from settings
        $from_email = get_option( 'lrp_smtp_from_email', '' );
        $from_name  = get_option( 'lrp_smtp_from_name', '' );

        if ( ! empty( $from_email ) ) {
            $phpmailer->From     = $from_email;
            $phpmailer->FromName = ! empty( $from_name ) ? $from_name : get_bloginfo( 'name' );
        }
    }

    /**
     * Log wp_mail errors for debugging
     */
    public static function log_mail_error( $wp_error ) {
        if ( is_wp_error( $wp_error ) ) {
            error_log( 'LRP Email Error: ' . $wp_error->get_error_message() );
            $data = $wp_error->get_error_data();
            if ( ! empty( $data ) ) {
                error_log( 'LRP Email Error Data: ' . print_r( $data, true ) );
            }
        }
    }

    /**
     * Send notifications after a new claim is created
     * - Email to consumer (with PDF attachment)
     * - Email to admin
     */
    public static function send_notifications( $claim_id, $pdf_path ) {
        if ( ! $claim_id ) return;

        $claim = LRP_DB::get_claim( $claim_id );
        if ( ! $claim ) return;
        $claim = (array) $claim;

        // 1. Send to Consumer
        self::send_consumer_email( $claim, $pdf_path );

        // 2. Send to Admin
        self::send_admin_email( $claim );
    }

    /**
     * Send confirmation email to the consumer who filed the claim
     */
    private static function send_consumer_email( $claim, $pdf_path ) {
        $to = $claim['consumer_email'];
        if ( empty( $to ) || ! is_email( $to ) ) {
            error_log( 'LRP: Cannot send consumer email - invalid email address for claim ' . $claim['claim_code'] );
            return false;
        }
        
        // Prepare variables
        $type_label = ucfirst( $claim['claim_type'] ); 
        $fullname = isset($claim['consumer_firstname']) ? $claim['consumer_firstname'] . ' ' . $claim['consumer_lastname'] : $claim['consumer_name'];
        $blog_name = get_bloginfo( 'name' );

        // Defaults (Fallback)
        $def_sub = 'Copia de su {type_label} - {blog_name}';
        $def_body = "Estimado(a) {fullname},\n\nHemos recibido su {type_label_lower} con código: {claim_code}.\nAdjuntamos una copia en PDF de su hoja de reclamación.\n\nAtentamente,\n{blog_name}";

        // Get Template from Options
        $subject_tpl = get_option( 'lrp_email_consumer_subject', $def_sub );
        $body_tpl = get_option( 'lrp_email_consumer_body', $def_body );

        // Replacements
        $search = array( '{claim_code}', '{fullname}', '{type_label}', '{type_label_lower}', '{blog_name}' );
        $replace = array( 
            $claim['claim_code'], 
            $fullname, 
            $type_label, 
            strtolower($type_label), 
            $blog_name 
        );

        $subject = str_replace( $search, $replace, $subject_tpl );
        $message = str_replace( $search, $replace, $body_tpl );

        // Apply formatting
        $message = wpautop( $message );

        // Attachments
        $attachments = array();
        if ( ! empty( $pdf_path ) && file_exists( $pdf_path ) ) {
            $attachments[] = $pdf_path;
        } else {
            error_log( 'LRP: PDF not found at path: ' . ( $pdf_path ?: 'empty' ) . ' for claim ' . $claim['claim_code'] );
        }

        $headers = array( 'Content-Type: text/html; charset=UTF-8' );

        $result = wp_mail( $to, $subject, $message, $headers, $attachments );
        
        if ( ! $result ) {
            error_log( 'LRP: Failed to send consumer email to ' . $to . ' for claim ' . $claim['claim_code'] );
        } else {
            error_log( 'LRP: Consumer email sent successfully to ' . $to . ' for claim ' . $claim['claim_code'] );
        }

        return $result;
    }

    /**
     * Send notification email to admin when a new claim is registered
     */
    private static function send_admin_email( $claim ) {
        $admin_email = get_option( 'lrp_notification_email', get_option( 'admin_email' ) );
        if ( empty( $admin_email ) || ! is_email( $admin_email ) ) {
            error_log( 'LRP: Cannot send admin email - invalid admin email for claim ' . $claim['claim_code'] );
            return false;
        }

        $subject = 'Nuevo Reclamo Registrado - ' . $claim['claim_code'];
        
        $fullname = isset($claim['consumer_firstname']) ? $claim['consumer_firstname'] . ' ' . $claim['consumer_lastname'] : $claim['consumer_name'];

        $message  = "Se ha registrado un nuevo reclamo en el sitio.\n\n";
        $message .= "Código: " . $claim['claim_code'] . "\n";
        $message .= "Cliente: " . $fullname . "\n";
        $message .= "Email: " . $claim['consumer_email'] . "\n";
        $message .= "Teléfono: " . $claim['consumer_phone'] . "\n";
        $message .= "Tipo: " . ucfirst( $claim['claim_type'] ) . "\n";
        $message .= "Bien contratado: " . $claim['contracted_good_type'] . "\n";
        $message .= "Detalle: " . wp_trim_words( $claim['claim_detail'], 30 ) . "\n\n";
        $message .= "Puede ver y gestionar el reclamo aquí:\n";
        $message .= admin_url( 'admin.php?page=lrp-inbox&action=view&id=' . $claim['id'] );
        
        $result = wp_mail( $admin_email, $subject, $message );
        
        if ( ! $result ) {
            error_log( 'LRP: Failed to send admin notification email to ' . $admin_email . ' for claim ' . $claim['claim_code'] );
        } else {
            error_log( 'LRP: Admin notification email sent successfully to ' . $admin_email . ' for claim ' . $claim['claim_code'] );
        }
        
        return $result;
    }

    /**
     * Send response email to consumer when admin replies to a claim
     */
    public static function send_response_email( $claim_id, $response, $evidence_file_url = '', $pdf_path = '' ) {
        if ( ! $claim_id ) return false;

        $claim = LRP_DB::get_claim( $claim_id );
        if ( ! $claim ) return false;
        $claim = (array) $claim;

        $to = $claim['consumer_email'];
        if ( empty( $to ) || ! is_email( $to ) ) {
            error_log( 'LRP: Cannot send response email - invalid email for claim ' . $claim['claim_code'] );
            return false;
        }
        
        // Prepare variables
        $type_label = ucfirst( $claim['claim_type'] );
        $fullname = isset($claim['consumer_firstname']) ? $claim['consumer_firstname'] . ' ' . $claim['consumer_lastname'] : $claim['consumer_name'];
        $blog_name = get_bloginfo( 'name' );
        
        // Get provider signature
        $signature_url = get_option( 'lrp_provider_signature_url', '' );
        $signature_html = '';
        if ( ! empty( $signature_url ) ) {
            $signature_html = '<div style="text-align: center; margin: 20px 0;"><img src="' . esc_url( $signature_url ) . '" alt="Firma del Proveedor" style="max-width: 300px; height: auto;"></div>';
        }

        // Defaults (Fallback)
        $def_sub = 'Respuesta a su {type_label} {claim_code} - {blog_name}';
        $def_body = "Estimado(a) {fullname},\n\nHemos atendido su {type_label_lower} con código: {claim_code}.\n\nRespuesta del Proveedor:\n--------------------------------------------------\n{response}\n--------------------------------------------------\n\n{signature}\n\nAtentamente,\n{blog_name}";

        // Get Template
        $subject_tpl = get_option( 'lrp_email_response_subject', $def_sub );
        $body_tpl = get_option( 'lrp_email_response_body', $def_body );

        // Replacements
        $search = array( '{claim_code}', '{fullname}', '{type_label}', '{type_label_lower}', '{blog_name}', '{response}', '{signature}' );
        $replace = array( 
            $claim['claim_code'], 
            $fullname, 
            $type_label, 
            strtolower($type_label), 
            $blog_name,
            nl2br( esc_html( $response ) ),
            $signature_html
        );

        $subject = str_replace( $search, $replace, $subject_tpl );
        $message = str_replace( $search, $replace, $body_tpl );

        // Apply formatting
        $message = wpautop( $message );

        $attachments = array();
        
        // Handle Evidence attachment
        if ( ! empty( $evidence_file_url ) ) {
            $upload_dir = wp_upload_dir();
            $base_url = $upload_dir['baseurl'];
            $base_dir = $upload_dir['basedir'];
            $local_path = str_replace( $base_url, $base_dir, $evidence_file_url );
            if ( file_exists( $local_path ) ) {
                $attachments[] = $local_path;
            }
        }

        // Attach Updated PDF
        if ( ! empty( $pdf_path ) && file_exists( $pdf_path ) ) {
            $attachments[] = $pdf_path;
        }

        $headers = array( 'Content-Type: text/html; charset=UTF-8' );

        $result = wp_mail( $to, $subject, $message, $headers, $attachments );

        if ( ! $result ) {
            error_log( 'LRP: Failed to send response email to ' . $to . ' for claim ' . $claim['claim_code'] );
        } else {
            error_log( 'LRP: Response email sent successfully to ' . $to . ' for claim ' . $claim['claim_code'] );
        }

        return $result;
    }
}

// Initialize SMTP hooks
LRP_Email::init();
