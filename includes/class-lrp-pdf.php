<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LRP_PDF {

    public static function generate_pdf( $claim ) {
        if ( ! class_exists( 'TCPDF' ) ) {
            error_log( 'LRP Error: TCPDF class not found.' );
            return false;
        }

        $pdf = new TCPDF( PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false );
        $pdf->SetCreator( 'Libro Reclamaciones Plugin' );
        $pdf->SetTitle( 'Hoja de Reclamación ' . $claim['claim_code'] );
        $pdf->setPrintHeader( false );
        $pdf->setPrintFooter( false );
        $pdf->SetMargins( 10, 10, 10 );
        $pdf->SetAutoPageBreak( TRUE, 10 );
        $pdf->AddPage();

        $html = self::get_pdf_html( $claim );

        $pdf->SetFont( 'helvetica', '', 9 ); // Slightly smaller default font for better fit
        $pdf->writeHTML( $html, true, false, true, false, '' );

        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/libro_reclamaciones/';
        if ( ! file_exists( $base_dir ) ) {
            wp_mkdir_p( $base_dir );
        }

        $filename = $claim['claim_code'] . '.pdf';
        $filepath = $base_dir . $filename;
        $pdf->Output( $filepath, 'F' );

        return $filepath;
    }

    private static function get_pdf_html( $claim ) {
        $blog_name = get_bloginfo( 'name' );
        $date = date( 'd/m/Y', strtotime( $claim['created_at'] ) );
        
        // Get provider signature
        $signature_url = get_option( 'lrp_provider_signature_url', '' );

        $consumer_firstname = isset($claim['consumer_firstname']) ? $claim['consumer_firstname'] : $claim['consumer_name']; 
        $consumer_lastname  = isset($claim['consumer_lastname']) ? $claim['consumer_lastname'] : ''; 

        $guardian_firstname = isset($claim['guardian_firstname']) ? $claim['guardian_firstname'] : $claim['guardian_name'];
        $guardian_lastname  = isset($claim['guardian_lastname']) ? $claim['guardian_lastname'] : '';

        // Determine Claim Type selection visual (X mark)
        $is_claim = ( stripos( $claim['claim_type'], 'reclamo' ) !== false );
        $is_complaint = ( stripos( $claim['claim_type'], 'queja' ) !== false );
        
        $checkbox_checked = '<span style="font-family:helvetica; font-weight:bold;">X</span>';
        $checkbox_empty = '&nbsp;';

        ob_start();
        ?>
        <style>
            table { width: 100%; border-collapse: collapse; font-family: helvetica; font-size: 8pt; color: #000; }
            td { padding: 5px 4px; vertical-align: middle; }
            
            .header-title { font-size: 14pt; font-weight: bold; text-align: left; margin: 0; }
            .header-subtitle { font-size: 9pt; margin: 2px 0 0 0; }
            
            .section-header { 
                background-color: #f2f2f2; 
                font-weight: bold; 
                font-size: 9pt; 
                padding: 4px; 
                border-top: 1px solid #ccc;
                border-bottom: 1px solid #ccc;
                margin-top: 12px;
                margin-bottom: 6px;
            }
            
            /* Form Fields Style */
            .label { font-weight: bold; font-size: 8pt; white-space: nowrap; padding-right: 5px; padding-bottom: 2px; padding-top: 8px; }
            .input-box { border: 1px solid #bbb; background-color: #fff; padding: 5px; min-height: 12px; }
            .input-box-area { border: 1px solid #bbb; background-color: #fff; padding: 6px; vertical-align: top; }
            
            .footer-note { font-size: 6pt; color: #444; margin-top: 15px; }
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .v-top { vertical-align: top; }
        </style>

        <!-- HEADER -->
        <table border="0" cellpadding="0" cellspacing="0">
            <tr>
                <td width="65%" class="v-top">
                    <div class="header-title">FORMATO DE LIBRO DE RECLAMACIONES</div>
                    <div class="header-subtitle">Según Ley 29571 y D.S. 011-2011-PCM.</div>
                    <div style="font-size:8pt; margin-top:5px; margin-bottom:8px; font-weight:bold;"><?php echo esc_html( strtoupper($blog_name) ); ?></div>
                </td>
                <td width="35%" class="v-top">
                    <table border="0" cellpadding="2" cellspacing="2">
                        <tr>
                            <td class="label text-right" width="60%">Hoja de Reclamación N.º</td>
                            <td class="input-box text-center" width="40%"><?php echo esc_html( $claim['claim_code'] ); ?></td>
                        </tr>
                        <tr>
                            <td class="label text-right">Fecha</td>
                            <td class="input-box text-center"><?php echo esc_html( $date ); ?></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Spacer Row -->
        <table border="0" cellpadding="0" cellspacing="0">
            <tr>
                <td style="height: 8px; border: none;">&nbsp;</td>
            </tr>
        </table>

        <!-- I. IDENTIFICACIÓN -->
        <div class="section-header">I. IDENTIFICACIÓN DEL CONSUMIDOR RECLAMANTE</div>
        <table border="0" cellpadding="3" cellspacing="2">
            <tr> <!-- Name Row -->
                <td width="10%" class="label">Nombre(s)</td>
                <td width="32%" class="input-box"><?php echo esc_html( $consumer_firstname ); ?></td>
                <td width="10%" class="label">Apellido(s)</td>
                <td width="48%" class="input-box"><?php echo esc_html( $consumer_lastname ); ?></td>
            </tr>
        </table>
        <table border="0" cellpadding="3" cellspacing="2">
            <tr> <!-- Docs Row -->
                <td width="14%" class="label" style="white-space: nowrap;">Tipo documento</td>
                <td width="9%" class="input-box"><?php echo esc_html( $claim['consumer_document_type'] ); ?></td>
                <td width="12%" class="label" style="white-space: nowrap;">N° documento</td>
                <td width="12%" class="input-box"><?php echo esc_html( $claim['consumer_document_number'] ); ?></td>
                <td width="8%" class="label">Teléfono</td>
                <td width="11%" class="input-box"><?php echo esc_html( $claim['consumer_phone'] ); ?></td>
                <td width="7%" class="label">Correo</td>
                <td width="27%" class="input-box" style="font-size:7pt;"><?php echo esc_html( $claim['consumer_email'] ); ?></td>
            </tr>
        </table>
        <table border="0" cellpadding="3" cellspacing="2">
            <tr> <!-- Location Row -->
                <td width="9%" class="label" style="white-space: nowrap;">Distrito</td>
                <td width="24%" class="input-box"><?php echo esc_html( $claim['consumer_district'] ); ?></td>
                <td width="10%" class="label" style="white-space: nowrap;">Provincia</td>
                <td width="24%" class="input-box"><?php echo esc_html( $claim['consumer_province'] ); ?></td>
                <td width="13%" class="label" style="white-space: nowrap;">Departamento</td>
                <td width="20%" class="input-box"><?php echo esc_html( $claim['consumer_department'] ); ?></td>
            </tr>
        </table>
        <table border="0" cellpadding="3" cellspacing="2">
            <tr> <!-- Address Row -->
                <td width="10%" class="label">Dirección</td>
                <td width="90%" class="input-box"><?php echo esc_html( $claim['consumer_address'] ); ?></td>
            </tr>
        </table>
        <table border="0" cellpadding="3" cellspacing="2">
            <tr> <!-- Minor Row -->
                <td width="15%" class="label">¿Menor de edad?</td>
                <td width="5%" class="input-box text-center"><?php echo ($claim['is_minor']) ? 'SI' : 'NO'; ?></td>
                <td width="80%"></td>
            </tr>
        </table>
        <?php if ( $claim['is_minor'] ) : ?>
        <div style="font-size:7pt; margin:2px 0; border-top:1px dashed #ccc; padding-top:2px;">
            <strong>Datos del padre, madre o representante legal:</strong>
        </div>
        <table border="0" cellpadding="3" cellspacing="2">
            <tr>
                <td width="10%" class="label">Nombre(s)</td>
                <td width="30%" class="input-box"><?php echo esc_html( $guardian_firstname ); ?></td>
                <td width="10%" class="label">Apellido(s)</td>
                <td width="30%" class="input-box"><?php echo esc_html( $guardian_lastname ); ?></td>
                <td width="20%">
                     <span class="label"><?php echo esc_html( $claim['guardian_document_type'] ); ?>:</span> <?php echo esc_html( $claim['guardian_document_number'] ); ?>
                </td>
            </tr>
        </table>
        <?php endif; ?>

        <!-- II. BIEN CONTRATADO -->
        <div class="section-header">II. IDENTIFICACIÓN DEL BIEN CONTRATADO</div>
        <table border="0" cellpadding="0" cellspacing="2">
            <tr>
                <!-- Left Column -->
                <td width="40%" class="v-top">
                    <table border="0" cellpadding="3" cellspacing="2">
                        <tr>
                            <td width="40%" class="label">Fecha de compra</td>
                            <td width="60%" class="input-box"><?php echo esc_html( $claim['contracted_good_date'] ?? '' ); ?></td>
                        </tr>
                        <tr>
                            <td class="label">Monto reclamado</td>
                            <td class="input-box">S/. <?php echo esc_html( $claim['contracted_good_amount'] ?? '' ); ?></td>
                        </tr>
                        <tr>
                            <td class="label">Tipo de bien</td>
                            <td class="input-box"><?php echo esc_html( strtoupper($claim['contracted_good_type']) ); ?></td>
                        </tr>
                        <tr>
                            <td class="label">Local</td>
                            <td class="input-box"><?php echo esc_html( $claim['contracted_good_local'] ?? '' ); ?></td>
                        </tr>
                    </table>
                </td>
                <!-- Right Column (Description) -->
                <td width="60%" class="v-top" style="padding-left:10px;">
                    <div style="font-weight:bold; font-size:8pt; margin-bottom:2px;">Descripción</div>
                    <div class="input-box-area" style="height: 65px;">
                        <?php echo nl2br( esc_html( $claim['contracted_good_description'] ) ); ?>
                    </div>
                </td>
            </tr>
        </table>

        <!-- III. DETALLE -->
        <div class="section-header">III. DETALLE DE LA RECLAMACIÓN Y PEDIDO DEL CONSUMIDOR</div>
        
        <!-- Selection -->
        <table border="0" cellpadding="3" cellspacing="2">
            <tr>
                <td width="30%">
                    <table border="0" cellpadding="2">
                        <tr>
                            <td class="label" width="30%">Reclamo</td>
                            <td class="input-box text-center" width="20%"><?php echo $is_claim ? $checkbox_checked : $checkbox_empty; ?></td>
                            <td width="50%"></td>
                        </tr>
                        <tr>
                            <td class="label">Queja</td>
                            <td class="input-box text-center"><?php echo $is_complaint ? $checkbox_checked : $checkbox_empty; ?></td>
                             <td width="50%"></td>
                        </tr>
                    </table>
                </td>
                <td width="70%" style="font-size:6pt; color:#444;">
                    <strong>Reclamo:</strong> Disconformidad relacionada a los productos o servicios.<br>
                    <strong>Queja:</strong> Disconformidad no relacionada a los productos o servicios; o malestar o descontento respecto a la atención al público.
                </td>
            </tr>
        </table>
        
        <!-- Boxes -->
        <table border="0" cellpadding="0" cellspacing="5" style="margin-top:5px;">
             <tr>
                <td width="50%" class="v-top">
                    <div style="font-weight:bold; font-size:8pt; margin-bottom:2px;">Detalle</div>
                    <div class="input-box-area" style="height: 140px;">
                         <?php echo nl2br( esc_html( $claim['claim_detail'] ) ); ?>
                    </div>
                </td>
                <td width="50%" class="v-top">
                    <div style="font-weight:bold; font-size:8pt; margin-bottom:2px;">Pedido del reclamante</div>
                    <div class="input-box-area" style="height: 140px;">
                         <?php echo nl2br( esc_html( $claim['claim_order'] ) ); ?>
                    </div>
                </td>
             </tr>
        </table>

        <!-- IV. OBSERVACIONES -->
        <div class="section-header">IV. OBSERVACIONES Y ACCIONES ADOPTADAS POR EL PROVEEDOR</div>
        <table border="0" cellpadding="3" cellspacing="2">
            <tr>
                 <td class="label" width="30%">Fecha de Comunicación de la Respuesta</td>
                 <td class="input-box" width="20%"><?php echo ( !empty($claim['response_date']) ) ? date('d/m/Y', strtotime($claim['response_date'])) : '&nbsp;'; ?></td>
                 <td width="50%"></td>
            </tr>
        </table>
        
        <div class="input-box-area" style="height: 100px; margin-top:5px; position:relative;">
             <!-- Content -->
             <?php echo ( ! empty( $claim['admin_response'] ) ) ? nl2br( esc_html( $claim['admin_response'] ) ) : '&nbsp;'; ?>
             
             <!-- Signature Section -->
             <br><br><br><br>
             <table border="0" width="100%">
                <tr>
                    <td width="60%"></td>
                    <td width="40%" style="text-align:center;">
                        <?php 
                        // Only show signature if there is an admin response
                        if ( ! empty( $claim['admin_response'] ) && ! empty( $signature_url ) ) : 
                            // Convert URL to local path for TCPDF
                            $upload_dir = wp_upload_dir();
                            $base_url = $upload_dir['baseurl'];
                            $base_dir = $upload_dir['basedir'];
                            $signature_path = str_replace( $base_url, $base_dir, $signature_url );
                            
                            // Check if file exists
                            if ( file_exists( $signature_path ) ) :
                            ?>
                                <img src="<?php echo $signature_path; ?>" style="max-height: 90px; width: auto; display: block; margin: 0 auto 5px auto;">
                            <?php 
                            endif;
                        endif; 
                        ?>
                        <div style="border-top:1px solid #000; padding-top:3px; font-size:7pt;">Firma del proveedor</div>
                    </td>
                </tr>
             </table>
        </div>

        <!-- FOOTER -->
        <div class="footer-note">
            * La formulación del reclamo no impide acudir a otras vías de solución de controversias ni constituye requisito previo para interponer una denuncia ante el INDECOPI.<br>
            * El proveedor deberá dar respuesta al reclamo en un plazo no mayor a quince (15) días hábiles.<br>
            * La respuesta al reclamo o queja será enviada al correo electrónico consignado por el consumidor en el formulario.
        </div>

        <?php
        return ob_get_clean();
    }
}
