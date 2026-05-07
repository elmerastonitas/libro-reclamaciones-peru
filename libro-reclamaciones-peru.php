<?php
/**
 * Plugin Name: Libro de Reclamaciones Perú
 * Plugin URI:  https://krontix.com/wordpress/libro-reclamaciones
 * Description: Virtual Complaint Book System in accordance with Peruvian law (Law 29571 and D.S. 011-2011-PCM).
 * Version:     1.0.0
 * Author:      Elmer Astonitas
 * Author URI:  https://elmerastonitas.com
 * License:     GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: libro-reclamaciones-peru
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * Tested up to: 6.7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define Constants
define( 'LRP_VERSION', '1.0.0' );
define( 'LRP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LRP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Autoload (Composer)
if ( file_exists( LRP_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once LRP_PLUGIN_DIR . 'vendor/autoload.php';
}

// Include Classes
require_once LRP_PLUGIN_DIR . 'includes/class-lrp-db.php';
require_once LRP_PLUGIN_DIR . 'includes/class-lrp-form.php';
require_once LRP_PLUGIN_DIR . 'includes/class-lrp-pdf.php';
require_once LRP_PLUGIN_DIR . 'includes/class-lrp-email.php';
require_once LRP_PLUGIN_DIR . 'includes/class-lrp-admin.php';

/**
 * Main Plugin Class
 */
class Libro_Reclamaciones_Peru {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Initialize hooks
		add_action( 'init', array( $this, 'init' ) );
        
        // Initialize Admin
        if ( is_admin() ) {
            new LRP_Admin();
        }
        
        // Initialize Form Shortcode
        new LRP_Form();
	}

	public function init() {
		load_plugin_textdomain( 'libro-reclamaciones-peru', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
        // Ensure DB is up to date (Dev mode auto-update)
        LRP_DB::create_table();
	}
}

// Initialize Plugin
function lrp_init() {
	return Libro_Reclamaciones_Peru::get_instance();
}
add_action( 'plugins_loaded', 'lrp_init' );

// Activation Hook
register_activation_hook( __FILE__, array( 'LRP_DB', 'create_table' ) );
