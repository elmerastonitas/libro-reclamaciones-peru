<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LRP_DB {

	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'libro_reclamaciones';
	}

	/**
	 * Create Database Table on Activation
	 */
	public static function create_table() {
		global $wpdb;
		$table_name = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			claim_code varchar(25) NOT NULL UNIQUE,
			consumer_firstname varchar(100) NOT NULL,
            consumer_lastname varchar(100) NOT NULL,
			consumer_document_type varchar(50) NOT NULL,
			consumer_document_number varchar(50) NOT NULL,
			consumer_email varchar(100) NOT NULL,
			consumer_phone varchar(50),
            consumer_address text,
            consumer_district varchar(100),
            consumer_province varchar(100),
            consumer_department varchar(100),
            is_minor tinyint(1) DEFAULT 0,
            guardian_firstname varchar(100),
            guardian_lastname varchar(100),
            guardian_document_type varchar(50),
            guardian_document_number varchar(50),
            contracted_good_type varchar(50),
            contracted_good_description text,
            contracted_good_amount decimal(10,2),
            contracted_good_date date,
            contracted_good_local varchar(255),
			claim_type varchar(50) NOT NULL,
			claim_detail longtext NOT NULL,
            claim_order text,
            status varchar(50) DEFAULT 'pendiente',
			admin_response longtext,
            response_date datetime,
            evidence_file varchar(255),
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	/**
	 * Generate Next Claim Code (Global Sequential)
     * Format: RQYYYY-NNNNN
     * - RQ = Reclamo/Queja (unified prefix)
     * - YYYY = year of creation (informational only)
     * - NNNNN = global sequential counter (never resets)
     * 
     * Uses wp_options with atomic increment to prevent duplicates.
	 */
	public static function generate_claim_code( $created_at = '' ) {
		global $wpdb;

        // Determine year from created_at or current time
        $year = ! empty( $created_at ) ? date( 'Y', strtotime( $created_at ) ) : date( 'Y' );

        // Atomic increment of global counter in wp_options
        // This uses a single UPDATE with +1 which is atomic at the DB level
        $option_name = 'lrp_global_counter';
        
        // Ensure the option exists (first-time initialization)
        $current_val = get_option( $option_name, false );
        if ( $current_val === false ) {
            // Initialize counter based on existing records to avoid conflicts
            $table_name = self::get_table_name();
            $max_seq = $wpdb->get_var( "SELECT MAX(id) FROM $table_name" );
            $initial = $max_seq ? intval( $max_seq ) : 0;
            add_option( $option_name, $initial, '', 'no' );
        }

        // Atomic increment: UPDATE directly in the DB to avoid race conditions
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->options} SET option_value = option_value + 1 WHERE option_name = %s",
            $option_name
        ) );

        // Read the incremented value
        $sequence = intval( $wpdb->get_var( $wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
            $option_name
        ) ) );

        return 'RQ' . $year . '-' . str_pad( $sequence, 5, '0', STR_PAD_LEFT );
	}

    /**
     * Insert new claim
     */
    public static function insert_claim( $data ) {
        global $wpdb;
        $table_name = self::get_table_name();

        // Generate code (unified global sequential)
        $claim_code = self::generate_claim_code();
        $data = array_merge( array( 'claim_code' => $claim_code ), $data );
        
        $format = array(
            '%s', // claim_code
            '%s', // consumer_firstname
            '%s', // consumer_lastname
            '%s', // consumer_document_type
            '%s', // consumer_document_number
            '%s', // consumer_email
            '%s', // consumer_phone
            '%s', // consumer_address
            '%s', // consumer_district
            '%s', // consumer_province
            '%s', // consumer_department
            '%d', // is_minor
            '%s', // guardian_firstname
            '%s', // guardian_lastname
            '%s', // guardian_document_type
            '%s', // guardian_document_number
            '%s', // contracted_good_type
            '%s', // contracted_good_description
            '%s', // contracted_good_amount
            '%s', // contracted_good_date
            '%s', // contracted_good_local
            '%s', // claim_type
            '%s', // claim_detail
            '%s', // claim_order
        );

        $inserted = $wpdb->insert( $table_name, $data, $format );

        if ( $inserted ) {
            return array( 'id' => $wpdb->insert_id, 'code' => $data['claim_code'] );
        }
        
        return false;
    }

    /**
     * Get Claim by ID
     */
    public static function get_claim( $id ) {
        global $wpdb;
        $table_name = self::get_table_name();
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id ) );
    }

    /**
     * Update Status and Response (Admin only)
     * Note: We do NOT allow updating the consumer data or claim detail as per requirements.
     */
    public static function update_response( $id, $response, $status = 'atenido', $evidence_url = '' ) {
        global $wpdb;
        $table_name = self::get_table_name();
        
        $data = array(
            'admin_response' => $response,
            'status'         => $status,
            'response_date'  => current_time( 'mysql' )
        );
        
        if ( ! empty( $evidence_url ) ) {
            $data['evidence_file'] = $evidence_url;
        }

        return $wpdb->update( 
            $table_name, 
            $data, 
            array( 'id' => $id ), 
            array( '%s', '%s', '%s' ), 
            array( '%d' ) 
        );
    }
}
